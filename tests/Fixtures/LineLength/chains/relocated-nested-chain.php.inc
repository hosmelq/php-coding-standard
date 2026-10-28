<?php

Builder::first('first argument value that makes the initial call too long to keep its arguments on one line', $secondArgument)
    ->last($query
        ->first('first')->second('xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'));

-----
<?php

Builder::first(
    'first argument value that makes the initial call too long to keep its arguments on one line',
    $secondArgument,
)->last($query->first('first')->second('xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'));
