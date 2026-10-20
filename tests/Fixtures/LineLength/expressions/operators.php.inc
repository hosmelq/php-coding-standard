<?php
$isEditable = $membership->status === CustomerSubscriptionPlanStatus::Pending && $user->can('update', $membership);
$this->props['aria-pressed'] = $value instanceof NumberFieldInputProps ? $value->ariaPressed : $value;
$name = $customer->preferredDisplayNameForInvoices ?: $customer->legalNameForInvoicesAndReceipts;
function check() {
    if ($customerSubscriptionPlan->status === CustomerSubscriptionPlanStatus::Approved && $user->isAdmin()) {
        return $user->belongsToOrganization($rate->pricingRule->billingProfile->organization) && $rate->pricingRule->billingProfile->deactivated_at === null;
    }
    return 'can:create,'.BillingProfilePricingRuleRate::class.',organization,billing_profile,pricing_rule'.$suffix;
}
AuditLog::assertRecorded(
    fn (AuditLogData $log): bool => $log->event === AuditLogEvent::CustomerContactCreated->value
        && $log->tenantId === (string) $address->organization_id
        && $log->targets[0]->id === (string) $address->id,
);
$filter = collect($items)->filter(fn (Organization $organizationArgument, CreateBillingProfileInput $input): bool => $organizationArgument->is($organization));
$a = $b ? fn ($x) => $x : 'fallback value that is long enough to overflow the line width of one hundred';
$x = [
    'id_token' => "You can't sign in with Apple on this account. Use your email to continue, please now.",
];

-----
<?php
$isEditable = $membership->status === CustomerSubscriptionPlanStatus::Pending
    && $user->can('update', $membership);
$this->props['aria-pressed'] = $value instanceof NumberFieldInputProps
    ? $value->ariaPressed
    : $value;
$name = $customer->preferredDisplayNameForInvoices ?: $customer->legalNameForInvoicesAndReceipts;
function check() {
    if (
        $customerSubscriptionPlan->status === CustomerSubscriptionPlanStatus::Approved
        && $user->isAdmin()
    ) {
        return $user->belongsToOrganization($rate->pricingRule->billingProfile->organization)
            && $rate->pricingRule->billingProfile->deactivated_at === null;
    }
    return 'can:create,'
        .BillingProfilePricingRuleRate::class
        .',organization,billing_profile,pricing_rule'
        .$suffix;
}
AuditLog::assertRecorded(
    fn (AuditLogData $log): bool => $log->event === AuditLogEvent::CustomerContactCreated->value
        && $log->tenantId === (string) $address->organization_id
        && $log->targets[0]->id === (string) $address->id,
);
$filter = collect($items)->filter(
    fn (Organization $organizationArgument, CreateBillingProfileInput $input): bool
        => $organizationArgument->is($organization),
);
$a = $b
    ? fn ($x) => $x
    : 'fallback value that is long enough to overflow the line width of one hundred';
$x = [
    'id_token' => "You can't sign in with Apple on this account. Use your email to continue, please now.",
];
