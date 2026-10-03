<?php
class Example {
    public function __construct(public string $first = <<<TEXT
text remains unchanged
TEXT, public string $secondPropertyWithALongName = 'second') {}
}
class Other {
    public function __construct(
        public string $firstPropertyWithALongName,
        public string $secondPropertyWithALongName
    ) {}
}
