<?php

$literal = '$query->first()->second()->third()';
$double = "$query->first()->second()->third()";
$interpolated = "{$query->first()->second()->third()}";
$heredoc = <<<TEXT
{$query->first()->second()->third()}
TEXT;
$nowdoc = <<<'TEXT'
$query->first()->second()->third();
TEXT;
$query->first('->second()->third()', "->fourth()")->second()->third();
