<?php

$result = $query /* root */ ->first(/* argument */ true) /* between */ ->second()->third();
$query->first() // Preserve an existing line comment.
    ->second()->third();
// $query->first()->second()->third();
/* $query->first()->second()->third(); */
