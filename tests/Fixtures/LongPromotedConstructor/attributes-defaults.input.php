<?php
class Example {
    public function __construct(#[Map(['first' => 1, 'second' => 2])] public array $values = ['nested' => [1, 2]], public object $service = new Service(1, 2)) {}
}
