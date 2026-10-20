<?php
$value = $organization->packages()->where('status', 'active')->orderBy('created_at_dates')->first()::class;
$value = Package::query()->where('status', 'active')->orderBy('created_at_dates')->firstOrFail()->name;

-----
<?php
$value = $organization
    ->packages()
    ->where('status', 'active')
    ->orderBy('created_at_dates')
    ->first()::class;
$value = Package::query()
    ->where('status', 'active')
    ->orderBy('created_at_dates')
    ->firstOrFail()->name;
