# Changelog

All notable changes to `hosmelq/php-coding-standard` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and releases follow
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Added

- Native project configuration overrides and a shared 100-column default.
- Optional Pest Rector rules.
- PHP, Laravel package and Laravel app presets for ECS, Rector and PHPStan.
- Token-based fixers for fluent chains, comment-aware line length, associative arrays and promoted
  constructor parameters.

### Changed

- Keep PHP-CS-Fixer as a direct dependency for custom fixer interfaces and the latest stable
  patch version. ECS also bundles its own copy; the project autoloader supplies the direct version.
- Load PHPStan extensions through its Composer extension installer.
- Reserve Carbon, DateTimeImmutable and multibyte string transformations for Laravel presets.

### Fixed

- Include all preset files in distribution archives.
- Preserve short nested arrays and recognize promoted constructor modifiers without visibility.
- Verify native preset overrides, token preservation and formatter convergence in consumers.
