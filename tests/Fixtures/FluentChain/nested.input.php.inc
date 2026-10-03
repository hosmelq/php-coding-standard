<?php

if ($enabled) {
    $outer->first($inner->one()->two()->three())->second()->third();
    $query->first(fn () => $inner->one()->two()->three())->second()->third();
    $query->first(function () { return $inner->one()->two()->three(); })->second()->third();
    consume($query->first(['value' => factory(1, 2), 'other' => (1 + 2)])->second()->third());
    $query->first(function () {
        return $inner->one()->two()->three();
    })->second()->third();
}
