<?php

return [
    'provider' => env('AI_PROVIDER', 'gemini'),
    'api_key' => env('GEMINI_API_KEY'),
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-3.6-flash'),
    'extraction_model' => env('GEMINI_EXTRACTION_MODEL', env('GEMINI_DEFAULT_MODEL', 'gemini-3.6-flash')),
    'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
    'timeout' => (int) env('GEMINI_REQUEST_TIMEOUT', 90),
    'max_retries' => (int) env('GEMINI_MAX_RETRIES', 2),
    'max_tool_iterations' => (int) env('AI_MAX_TOOL_ITERATIONS', 8),
    'approval_expiration_minutes' => (int) env('AI_APPROVAL_EXPIRATION_MINUTES', 60),
    'daily_user_limit' => (int) env('AI_DAILY_USER_LIMIT', 100),
    'max_text_characters' => (int) env('AI_MAX_TEXT_CHARACTERS', 250000),
    'file_upload_limit_kb' => (int) env('AI_FILE_UPLOAD_LIMIT_KB', 10240),
    'allowed_file_types' => ['pdf', 'docx', 'txt', 'csv', 'jpg', 'jpeg', 'png'],
    'property_intake_expiration_hours' => (int) env('PROPERTY_INTAKE_EXPIRATION_HOURS', 24),
    'contact_intake_expiration_hours' => (int) env('CONTACT_INTAKE_EXPIRATION_HOURS', 24),
    'surplus_intake_expiration_hours' => (int) env('SURPLUS_INTAKE_EXPIRATION_HOURS', 24),
    'surplus_csv_max_rows' => (int) env('SURPLUS_CSV_MAX_ROWS', 500),
    'pre_auction_csv_max_rows' => (int) env('PRE_AUCTION_CSV_MAX_ROWS', 500),
    'pre_auction_csv_expiration_hours' => (int) env('PRE_AUCTION_CSV_EXPIRATION_HOURS', 24),
];
