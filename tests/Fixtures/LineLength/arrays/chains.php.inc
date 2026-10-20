<?php
$values = ['first' => $query
    ->one()
    ->two() /* retain this comment */
    ->three(), 'second' => 2];

-----
<?php
$values = [
    'first' => $query
        ->one()
        ->two() /* retain this comment */
        ->three(),
    'second' => 2,
];
