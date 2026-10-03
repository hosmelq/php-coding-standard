<?php
class Example {
    public function __construct(
        public string $firstPropertyWithALongName,
        protected readonly int $secondPropertyWithALongName,
    ) {}
}
