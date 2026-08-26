<?php

return [
    'max_recipients' => (int) env('VVR_EMAIL_MAX_RECIPIENTS', 10),
    'hourly_user_limit' => (int) env('VVR_EMAIL_HOURLY_USER_LIMIT', 30),
    'max_attachments' => (int) env('VVR_EMAIL_MAX_ATTACHMENTS', 5),
    'attachment_max_kb' => (int) env('VVR_EMAIL_ATTACHMENT_MAX_KB', 10240),
    'deleted_draft_retention_days' => (int) env('VVR_EMAIL_DELETED_DRAFT_RETENTION_DAYS', 30),
    'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'jpg', 'jpeg', 'png'],
    'merge_fields' => [
        '{{first_name}}' => 'Contact first name',
        '{{last_name}}' => 'Contact last name',
        '{{contact_name}}' => 'Contact full name',
        '{{property_address}}' => 'Property address',
        '{{parcel_id}}' => 'Property parcel ID or Surplus Case Identifier parcel ID',
        '{{county}}' => 'Property county or Surplus Case Identifier county',
        '{{surplus_amount}}' => 'Surplus amount',
        '{{case_number}}' => 'Surplus or deal case number',
        '{{sender_name}}' => 'VVR sender name',
    ],
];
