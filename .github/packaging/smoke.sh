#!/usr/bin/env bash

set -euo pipefail

consumer="${1:?Usage: smoke.sh <php|laravel-package|laravel-app> [archive.zip]}"

case "$consumer" in
    php|laravel-package|laravel-app) ;;
    *) printf 'Unknown consumer: %s\n' "$consumer" >&2; exit 1 ;;
esac

fixture_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repository_root="$(git -C "$fixture_root" rev-parse --show-toplevel)"
smoke_dir="$(mktemp -d "${TMPDIR:-/tmp}/coding-standard-smoke.XXXXXX")"
archive="${2:-$smoke_dir/package.zip}"

printf 'Smoke workspace: %s\n' "$smoke_dir"
php -r 'printf("PHP %s\n", PHP_VERSION);'

if [[ $# -lt 2 ]]; then
    git -C "$repository_root" archive --format=zip --output="$archive" HEAD
fi

archive="$(cd "$(dirname "$archive")" && pwd)/$(basename "$archive")"
unzip -p "$archive" composer.json > "$smoke_dir/package.json"
cp -R "$fixture_root/$consumer" "$smoke_dir/consumer"
cd "$smoke_dir/consumer"

mkdir -p bootstrap/cache storage/framework/cache storage/framework/views storage/logs
export COMPOSER_HOME="$smoke_dir/composer"
export COMPOSER_NO_INTERACTION=1

php -r '
$package = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$package["version"] = "1.0.0";
$package["dist"] = ["type" => "zip", "url" => "file://".$argv[2], "shasum" => sha1_file($argv[2])];
$consumer = json_decode(file_get_contents("composer.json"), true, flags: JSON_THROW_ON_ERROR);
$consumer["repositories"] = [["type" => "package", "package" => $package]];
file_put_contents("composer.json", json_encode($consumer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
' "$smoke_dir/package.json" "$archive"

composer update --no-interaction --no-progress --prefer-dist --prefer-stable

if [[ "$consumer" == php ]]; then
    php -r '
    require "vendor/autoload.php";
    foreach (\Composer\InstalledVersions::getInstalledPackages() as $package) {
        if (preg_match("~^(?:illuminate/|laravel/|larastan/|pestphp/|nesbot/carbon$|driftingly/rector-laravel$)~", $package)) {
            throw new \RuntimeException("Unexpected framework dependency: ".$package);
        }
    }
    '
fi

run_clean() {
    local label="$1"
    shift

    "$@" 2>&1 | tee "$smoke_dir/$label.log"

    if grep -Ei '(^|[[:space:][:punct:]])(deprecated|deprecations?|warnings?)([[:space:][:punct:]]|$)' "$smoke_dir/$label.log"; then
        printf 'Unexpected diagnostic in %s\n' "$label" >&2
        exit 1
    fi
}

run_clean rector-first php -d error_reporting=-1 -d display_errors=1 vendor/bin/rector process --no-ansi --no-progress-bar
run_clean ecs-first php -d error_reporting=-1 -d display_errors=1 vendor/bin/ecs check --fix --no-ansi --no-progress-bar
run_clean phpstan-first php -d error_reporting=-1 -d display_errors=1 vendor/bin/phpstan analyse --configuration=phpstan.neon --debug --no-ansi --no-progress --memory-limit=1G

run_clean rector-second php -d error_reporting=-1 -d display_errors=1 vendor/bin/rector process --dry-run --clear-cache --no-ansi --no-progress-bar
run_clean ecs-second php -d error_reporting=-1 -d display_errors=1 vendor/bin/ecs check --clear-cache --no-ansi --no-progress-bar
run_clean phpstan-second php -d error_reporting=-1 -d display_errors=1 vendor/bin/phpstan analyse --configuration=phpstan.neon --debug --no-ansi --no-progress --memory-limit=1G

php -r '
require "vendor/autoload.php";
$sample = new \Example\Sample();
$result = $argv[1] === "php" ? $sample->nextDay("2026-01-01") : $sample->normalize(" Example ");
$expected = $argv[1] === "php" ? "2026-01-02" : "example";
if ($result !== $expected) {
    throw new \RuntimeException("Consumer behavior changed: ".$result);
}
' "$consumer"

if [[ "$consumer" == php ]]; then
    composer config allow-plugins.pestphp/pest-plugin true
    composer require --dev --no-interaction --no-progress --prefer-dist 'pestphp/pest:^5.0' 'pestphp/pest-plugin-rector:^5.0'

    mkdir -p tests
    cp "$fixture_root/pest/SampleTest.php" tests/SampleTest.php
    cp "$fixture_root/pest/rector.php" rector-pest.php

    run_clean pest-before php -d error_reporting=-1 -d display_errors=1 vendor/bin/pest tests/SampleTest.php --colors=never --fail-on-all-issues
    run_clean rector-pest-first php -d error_reporting=-1 -d display_errors=1 vendor/bin/rector process --config=rector-pest.php --no-ansi --no-progress-bar

    php -r '
    $source = file_get_contents("tests/SampleTest.php");
    if (! str_contains($source, "->toBe(") || str_contains($source, "->toBeTrue(")) {
        throw new \RuntimeException("The optional Pest preset did not simplify the equality expectation.");
    }
    '

    run_clean rector-pest-second php -d error_reporting=-1 -d display_errors=1 vendor/bin/rector process --config=rector-pest.php --dry-run --clear-cache --no-ansi --no-progress-bar
    run_clean pest-after php -d error_reporting=-1 -d display_errors=1 vendor/bin/pest tests/SampleTest.php --colors=never --fail-on-all-issues
fi

printf 'Packaging smoke passed: %s\n' "$consumer"
