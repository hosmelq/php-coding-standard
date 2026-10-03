<?php
$object = new class {
    public function __construct(
        public string &$firstPropertyWithALongName,
        /* parameter */ string ...$otherArgumentsWithALongName,
    ) {}
};
