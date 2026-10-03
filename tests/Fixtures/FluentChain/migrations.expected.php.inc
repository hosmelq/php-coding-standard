<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('users', function (Blueprint $table): void {
    $table->string('name')->nullable()->index();
    $table->string('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')->nullable()->index();
    $table
        ->string('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')
        ->nullable()
        ->index();
    $table
        ->foreignId('an_extremely_long_foreign_identifier_used_for_testing')
        ->nullable()
        ->constrained()
        ->cascadeOnDelete();
    $query
        ->first()
        ->second()
        ->third();
    $table->column
        ->first()
        ->second()
        ->third();
    consume(function ($table) {
        $table
            ->first()
            ->second()
            ->third();
    });
});
Schema::table('users', fn (Blueprint $table) => $table->string('name')->nullable()->index());
Schema::create('users', function (Blueprint $table): void {
    consume(fn ($table) => $table
        ->first()
        ->second()
        ->third());
});
Schema::table(tableName('users'), static function (Blueprint $table): void {
    $table->foreignId('team_id')->nullable()->constrained();
});
\Illuminate\Support\Facades\Schema::table('users', function (
    \Illuminate\Database\Schema\Blueprint $table,
): void {
    $table->string('name')->nullable()->index();
});
Schema::table('users', function ($table): void {
    $table
        ->string('name')
        ->nullable()
        ->index();
});
Schema::table('users', function (Blueprint $other): void {
    $table
        ->first()
        ->second()
        ->third();
});
$callback = function (Blueprint $table): void {
    $table
        ->first()
        ->second()
        ->third();
};
Other::table('users', function (Blueprint $table): void {
    $table
        ->first()
        ->second()
        ->third();
});
$table
    ->first()
    ->second()
    ->third();
