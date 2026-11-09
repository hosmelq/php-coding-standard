<?php

$outer->first($inner
    ->one())
    ->second('second argument value')->third('third argument value that is sufficiently long');

-----
<?php

$outer
    ->first($inner->one())
    ->second('second argument value')
    ->third('third argument value that is sufficiently long');
