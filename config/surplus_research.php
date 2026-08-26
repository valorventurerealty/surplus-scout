<?php

return [
    'osceola' => [
        'source_url' => env('OSCEOLA_SURPLUS_REPORT_URL', 'https://courts.osceolaclerk.com/reports/TaxDeedsSurplusFundsAvailableWeb.pdf'),
        'download_url' => env('OSCEOLA_SURPLUS_DOWNLOAD_URL'),
        'relay_token' => env('OSCEOLA_SURPLUS_RELAY_TOKEN'),
        'timeout' => (int) env('OSCEOLA_SURPLUS_REQUEST_TIMEOUT', 30),
        'retries' => (int) env('OSCEOLA_SURPLUS_REQUEST_RETRIES', 2),
        'max_file_bytes' => (int) env('OSCEOLA_SURPLUS_MAX_FILE_BYTES', 15728640),
        'minimum_records' => (int) env('OSCEOLA_SURPLUS_MINIMUM_RECORDS', 1),
        'pdf_to_text_binary' => env('PDF_TO_TEXT_BINARY'),
    ],
    'owner_research' => [
        'osceola_base_url' => env('OSCEOLA_PROPERTY_APPRAISER_URL', 'https://search.property-appraiser.org'),
        'primary_trim_year' => (int) env('OSCEOLA_PRIMARY_TRIM_YEAR', 2025),
        'fallback_trim_year' => (int) env('OSCEOLA_FALLBACK_TRIM_YEAR', 2024),
        'request_timeout' => (int) env('OSCEOLA_OWNER_RESEARCH_TIMEOUT', 15),
        'request_retries' => (int) env('OSCEOLA_OWNER_RESEARCH_RETRIES', 2),
        'minimum_request_interval_ms' => (int) env('OSCEOLA_OWNER_RESEARCH_INTERVAL_MS', 1500),
        'max_trim_file_bytes' => (int) env('OSCEOLA_TRIM_MAX_FILE_BYTES', 10485760),
    ],
];
