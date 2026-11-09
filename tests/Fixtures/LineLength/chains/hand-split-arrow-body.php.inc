<?php

$factory = [
    'course_cohort_id' => fn (array $attributes): int => Enrollment::query()
        ->whereKey($attributes['enrollment_id'])->firstOrFail()->course_cohort_id,
    'enrollment_id' => Enrollment::query()->whereKey($attributes['enrollment_identifier'])
        ->firstOrFail()->sole()->id,
];
$callback = fn (array $attributes): int => new Enrollment()->query()
    ->whereKey($attributes['enrollment_id'])->firstOrFail()->course_cohort_id;

-----
<?php

$factory = [
    'course_cohort_id' => fn (array $attributes): int => Enrollment::query()
        ->whereKey($attributes['enrollment_id'])->firstOrFail()->course_cohort_id,
    'enrollment_id' => Enrollment::query()
        ->whereKey($attributes['enrollment_identifier'])
        ->firstOrFail()
        ->sole()->id,
];
$callback = fn (array $attributes): int => new Enrollment()->query()
    ->whereKey($attributes['enrollment_id'])->firstOrFail()->course_cohort_id;
