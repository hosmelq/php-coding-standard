<?php

declare(strict_types=1);

it('renders registration for guests', function (): void {
    get('/register')->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('auth/Register'));
});

-----
<?php

declare(strict_types=1);

it('renders registration for guests', function (): void {
    get('/register')->assertOk()->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page->component('auth/Register'),
    );
});
