<?php

$processed = $records->filter(fn (Company $record): bool => $actor->can('delete', $record))->each->delete()->values();
$processed = $records->filter(fn (Company $record): bool => $actor->can('delete', $record))
    ->each->delete()->values();
$processed = $records
    ->filter(fn (Company $record): bool => $actor->can('delete', $record))
    ->each->delete()
    ->values();

-----
<?php

$processed = $records
    ->filter(fn (Company $record): bool => $actor->can('delete', $record))
    ->each->delete()
    ->values();
$processed = $records
    ->filter(fn (Company $record): bool => $actor->can('delete', $record))
    ->each->delete()
    ->values();
$processed = $records
    ->filter(fn (Company $record): bool => $actor->can('delete', $record))
    ->each->delete()
    ->values();
