<?php
declare(strict_types=1);

// En este repo la fuente preferida es crm/config.php, seccion 'iot'.
// Usa este archivo solo si despliegas IoT separado del CRM.
return [
    'db_host' => 'localhost',
    'db_name' => 'TU_BASE_DE_DATOS',
    'db_user' => 'TU_USUARIO',
    'db_pass' => 'TU_PASSWORD',
    'api_token' => 'GENERA_UN_TOKEN_ALEATORIO_DE_32_CARACTERES_O_MAS',
    'setup_token' => 'GENERA_OTRO_TOKEN_ALEATORIO_DE_32_CARACTERES_O_MAS',
    'expo_access_token' => '',
    'shelly_cloud_server' => '',
    'shelly_cloud_auth_key' => '',
    'shelly_webhook_token' => 'GENERA_UN_TOKEN_ALEATORIO_DE_32_CARACTERES_O_MAS',
    'hikvision_bridge_token' => 'GENERA_UN_TOKEN_ALEATORIO_DE_48_CARACTERES_O_MAS',
    'zkteco_bridge_token' => 'GENERA_OTRO_TOKEN_ALEATORIO_DE_48_CARACTERES_O_MAS',
    'alexa_public_base_url' => 'https://TU_DOMINIO/IoT/iot/api',
    'alexa_oauth_client_id' => 'idindustrial-alexa',
    'alexa_oauth_client_secret' => 'GENERA_UN_SECRETO_ALEATORIO_DE_48_CARACTERES_O_MAS',
    'alexa_lambda_shared_secret' => 'GENERA_OTRO_SECRETO_ALEATORIO_DE_48_CARACTERES_O_MAS',
    'alexa_event_client_id' => '',
    'alexa_event_client_secret' => '',
    'alexa_event_region' => 'NA',
    'alexa_oauth_redirect_uris' => [
        'https://pitangui.amazon.com/api/skill/link/TU_REDIRECT_ID',
        'https://layla.amazon.com/api/skill/link/TU_REDIRECT_ID',
        'https://alexa.amazon.co.jp/api/skill/link/TU_REDIRECT_ID',
    ],
    'retention_raw_days' => 90,
    'retention_hourly_months' => 24,
    'retention_shelly_event_days' => 365,
    'retention_push_days' => 90,
    'retention_hours_per_run' => 48,
    'retention_max_runtime_seconds' => 45,
];
