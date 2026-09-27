<?php

return [
    'audit_retention_days' => (int) env('AUDIT_RETENTION_DAYS', 180),
    'error_retention_days' => (int) env('ERROR_RETENTION_DAYS', 90),
    'security_error_retention_days' => (int) env('SECURITY_ERROR_RETENTION_DAYS', 180),
    'personal_data_incident_retention_years' => (int) env('PERSONAL_DATA_INCIDENT_RETENTION_YEARS', 5),
];
