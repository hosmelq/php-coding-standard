<?php

$logs = $processed->map(
    fn (Company $record): AuditLogData => audit_log(AuditLogEvent::CompanyArchived)
        ->bucket(AuditLogChannel::Admin)
        ->description(AuditLogEvent::CompanyArchived->description())
        ->target($record)
        ->toAuditLogData(),
);
$photosBefore ??= $company->media()
    ->where('collection_name', Company::MEDIA_COLLECTION_PHOTO_THAT_IS_LONG)
    ->get();

-----
<?php

$logs = $processed->map(
    fn (Company $record): AuditLogData => audit_log(AuditLogEvent::CompanyArchived)
        ->bucket(AuditLogChannel::Admin)
        ->description(AuditLogEvent::CompanyArchived->description())
        ->target($record)
        ->toAuditLogData(),
);
$photosBefore ??= $company->media()
    ->where('collection_name', Company::MEDIA_COLLECTION_PHOTO_THAT_IS_LONG)
    ->get();
