<?php
$values = array_map(static fn (Organization $organizationArgument): string => $organizationArgument->displayName(), $organizations);
$curried = fn (int $first): Closure => fn (int $second): int => $first + $second + $thirdValueFromOuterScope + $fourth;
$grouped = ($customerSubscriptionPlan->status === CustomerSubscriptionPlanStatus::Approved || $forced) && $enabled;
while ($remainingAttemptsForProvisioning > 0 && ! $customerSubscriptionPlan->organization->serversProvisioned()) {
    --$remainingAttemptsForProvisioning;
}
$total = $customerSubscriptionPlan->status === CustomerSubscriptionPlanStatus::Approved ? 1 : 0 + $base;
$mock->withArgs(
    fn (BillingProfilePricingRule $pricingRuleArgument, UpdateBillingProfilePricingRuleInput $input): bool => $pricingRuleArgument->is($pricingRule),
);

-----
<?php
$values = array_map(
    static fn (Organization $organizationArgument): string => $organizationArgument->displayName(),
    $organizations,
);
$curried = fn (int $first): Closure
    => fn (int $second): int => $first + $second + $thirdValueFromOuterScope + $fourth;
$grouped = (
    $customerSubscriptionPlan->status === CustomerSubscriptionPlanStatus::Approved
    || $forced
)
    && $enabled;
while (
    $remainingAttemptsForProvisioning > 0
    && ! $customerSubscriptionPlan->organization->serversProvisioned()
) {
    --$remainingAttemptsForProvisioning;
}
$total = $customerSubscriptionPlan->status === CustomerSubscriptionPlanStatus::Approved
    ? 1
    : 0 + $base;
$mock->withArgs(
    fn (
        BillingProfilePricingRule $pricingRuleArgument,
        UpdateBillingProfilePricingRuleInput $input,
    ): bool => $pricingRuleArgument->is($pricingRule),
);
