<?php

return [
    'db_host' => 'localhost',
    'db_name' => 'idactivo_idindustrialiot',
    'db_user' => 'idactivo_idactivo_idiot_admin',
    'db_pass' => 'xetZ8tIikUpx',
    'api_token' => 'WJuIUBSvjb46uUL4IBg4DulwZvbZ74Nn',
    'setup_token' => 'CAMBIA_ESTE_TOKEN_DE_INSTALACION_DE_32_CARACTERES',
    // Opcional: correo del usuario IoT que recibira el acceso desde el CRM.
    'crm_sso_iot_email' => '',
    // Se completan al habilitar el control remoto mediante Shelly Cloud.
    'shelly_cloud_server' => '',
    'shelly_cloud_auth_key' => '',
    'shelly_webhook_token' => 'GENERA_UN_TOKEN_ALEATORIO_DE_32_CARACTERES_O_MAS',
    // Alexa Smart Home. Estos valores permanecen solamente en cPanel.
    'alexa_public_base_url' => 'https://TU_DOMINIO/ID-Industrial/api',
    'alexa_oauth_client_id' => 'idindustrial-alexa',
    'alexa_oauth_client_secret' => 'GENERA_UN_SECRETO_ALEATORIO_DE_48_CARACTERES_O_MAS',
    'alexa_lambda_shared_secret' => 'GENERA_OTRO_SECRETO_ALEATORIO_DE_48_CARACTERES_O_MAS',
    // Credenciales mostradas en Permissions > Alexa Skill Messaging.
    'alexa_event_client_id' => 'CLIENT_ID_DE_ALEXA_SKILL_MESSAGING',
    'alexa_event_client_secret' => 'CLIENT_SECRET_DE_ALEXA_SKILL_MESSAGING',
    // Mexico utiliza el Event Gateway de Norteamerica.
    'alexa_event_region' => 'NA',
    'alexa_oauth_redirect_uris' => [
        'https://pitangui.amazon.com/api/skill/link/TU_REDIRECT_ID',
        'https://layla.amazon.com/api/skill/link/TU_REDIRECT_ID',
        'https://alexa.amazon.co.jp/api/skill/link/TU_REDIRECT_ID',
    ],
    'retention_raw_days' => 90,
    'retention_hourly_months' => 24,
    'retention_hours_per_run' => 48,
    'retention_max_runtime_seconds' => 45,
];
