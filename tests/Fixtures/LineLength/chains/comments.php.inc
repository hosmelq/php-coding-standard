<?php

$result = $query /* root */ ->first(/* argument */ 'first argument value') /* between */ ->second('second')->third();
$query->first() // Preserve an existing line comment.
    ->second()->third();
$query
    /* A block comment keeps the chain split. */
    ->first()
    ->second();
// $query->first()->second()->third();
/* $query->first()->second()->third(); */

-----
<?php

$result = $query /* root */
    ->first(/* argument */ 'first argument value') /* between */
    ->second('second')
    ->third();
$query->first() // Preserve an existing line comment.
    ->second()->third();
$query
    /* A block comment keeps the chain split. */
    ->first()
    ->second();
// $query->first()->second()->third();
/* $query->first()->second()->third(); */
