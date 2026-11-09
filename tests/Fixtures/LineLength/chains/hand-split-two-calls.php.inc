<?php

livewire(EditCourseCohort::class, ['parentRecord' => $cohort->course, 'record' => $cohort->id])
    ->call('save')->assertHasNoFormErrors();
livewire(EditCourseCohort::class, [
    'parentRecord' => $cohort->course,
])->mountAction(TestAction::make('attendance')->table($session))
    ->callMountedAction()->assertHasNoFormErrors();

-----
<?php

livewire(EditCourseCohort::class, ['parentRecord' => $cohort->course, 'record' => $cohort->id])
    ->call('save')->assertHasNoFormErrors();
livewire(EditCourseCohort::class, [
    'parentRecord' => $cohort->course,
])
    ->mountAction(TestAction::make('attendance')->table($session))
    ->callMountedAction()
    ->assertHasNoFormErrors();
