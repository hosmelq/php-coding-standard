<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('records', function (Blueprint $table): void {
    $table->json('options')->default([
        'first_very_long_option_name' => 'first_value',
        'second_very_long_option_name' => 'second_value',
    ]);
});
Schema::table('records', function (Blueprint $table): void {
    $table->json('options')->default([
        'first_very_long_option_name' => 'first_value',
        'second_very_long_option_name' => 'second_value',
    ]);
});
