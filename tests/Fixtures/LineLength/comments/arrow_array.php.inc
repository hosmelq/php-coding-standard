<?php

$callbacks = [fn ($customer) => $customer->billingAddress, fn ($customer) => $customer->preferredLanguage];

-----
<?php

$callbacks = [
    fn ($customer) => $customer->billingAddress,
    fn ($customer) => $customer->preferredLanguage,
];
