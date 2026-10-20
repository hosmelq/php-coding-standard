<?php

if ($enabled) {
    $outer->first($inner->one('first argument')->two('second argument')->three())->second()->third('value');
    $outer->first($inner->one('first argument value')->two('second argument value')->three('third argument value'))->second()->third();
    $query->first(fn () => $inner->one()->two()->three())->second('second argument value')->third('third value');
    consume($query->first(['value' => factory(1, 2), 'other' => (1 + 2)])->second('second argument')->third('third'));
    $query->first(function () {
        return $inner->one('first argument value')->two('second argument value')->three('third argument value');
    })->second()->third();
    $outer
        ->first($inner
            ->one()
            ->two()
            ->three())
        ->second()
        ->third();
}

-----
<?php

if ($enabled) {
    $outer
        ->first($inner->one('first argument')->two('second argument')->three())
        ->second()
        ->third('value');
    $outer
        ->first(
            $inner
                ->one('first argument value')
                ->two('second argument value')
                ->three('third argument value'),
        )
        ->second()
        ->third();
    $query
        ->first(fn () => $inner->one()->two()->three())
        ->second('second argument value')
        ->third('third value');
    consume(
        $query
            ->first(['value' => factory(1, 2), 'other' => (1 + 2)])
            ->second('second argument')
            ->third('third'),
    );
    $query->first(function () {
        return $inner
            ->one('first argument value')
            ->two('second argument value')
            ->three('third argument value');
    })->second()->third();
    $outer->first($inner->one()->two()->three())->second()->third();
}
