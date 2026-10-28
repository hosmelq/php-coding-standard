<?php

Route::middleware('auth')->group(function (): void {
    Route::post('invitations/{token}/accept', [InvitationController::class, 'store'])->name('invitations.accept');
});

-----
<?php

Route::middleware('auth')->group(function (): void {
    Route::post('invitations/{token}/accept', [InvitationController::class, 'store'])
        ->name('invitations.accept');
});
