<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('records', function (Blueprint $table): void {
    $table->string('an_extremely_long_column_identifier_used_for_testing_the_line_length_limit', 255);
});
Schema::table('records', function (Blueprint $table): void {
    $table->string('an_extremely_long_column_identifier_used_for_testing_the_line_length_limit', 255);
});

-----
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('records', function (Blueprint $table): void {
    $table->string(
        'an_extremely_long_column_identifier_used_for_testing_the_line_length_limit',
        255
    );
});
Schema::table('records', function (Blueprint $table): void {
    $table->string(
        'an_extremely_long_column_identifier_used_for_testing_the_line_length_limit',
        255
    );
});
