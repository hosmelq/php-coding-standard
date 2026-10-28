<?php

Route::middleware('auth')->group(function (): void {
    Route::middleware('verified')->group(function (): void {
        Route::get('organizations/{organization}/settings/general', [OrganizationController::class, 'show'])
            ->name('organizations.settings.general');
    });
});

-----
<?php

Route::middleware('auth')->group(function (): void {
    Route::middleware('verified')->group(function (): void {
        Route::get(
            'organizations/{organization}/settings/general',
            [OrganizationController::class, 'show'],
        )->name('organizations.settings.general');
    });
});
