<?php
declare(strict_types=1);

// Referencia: estos valores van dentro de crm/config.php.
// No subas este archivo como config real; solo copia el bloque que necesites.
//
// En la integracion actual el CRM y el panel IoT usan la MISMA base
// configurada en `database`. Para tu instalacion, deja aqui la base del CRM
// e importa ahi las tablas/datos IoT que venian de ID Activos.
// No pegues los db_host/db_name/db_user/db_pass viejos dentro del bloque `iot`.
return [
  'driver' => 'mysql',
  'host' => 'localhost',
  'database' => 'idindust_crm_idindustrial',
  'username' => 'TU_USUARIO_MYSQL_DEL_CRM',
  'password' => 'TU_PASSWORD_MYSQL_DEL_CRM',
  'charset' => 'utf8mb4',
  'app_url' => 'https://idindustrial.com.mx/crm',
  'quote_request_admin_email' => 'tecnologia@idindustrial.com.mx',
  'quote_request_secondary_email' => '',
  'smtp' => [
    'enabled' => false,
    'host' => 'mail.idindustrial.com.mx',
    'port' => 465,
    'secure' => 'ssl',
    'username' => '',
    'password' => '',
    'from_email' => 'no-reply@idindustrial.com.mx',
    'from_name' => 'ID Industrial',
  ],
  'iot' => [
    'api_token' => 'PEGA_AQUI_EL_API_TOKEN_DE_TU_CONFIG_LOCAL_ANTERIOR',
    'setup_token' => 'PEGA_AQUI_EL_SETUP_TOKEN',
    'crm_sso_iot_email' => 'CORREO_DEL_ADMIN_IOT_SI_USAS_ADMIN_SSO',
    'expo_access_token' => '',
    'shelly_cloud_server' => 'https://TU_SERVER_SHELLY_CLOUD',
    'shelly_cloud_auth_key' => 'TU_AUTH_KEY_SHELLY_CLOUD',
    'shelly_webhook_token' => 'TOKEN_LARGO_INVENTADO_DE_32_CARACTERES_O_MAS',
    'hikvision_bridge_token' => 'TOKEN_LARGO_PARA_CONECTOR_HIKVISION',
    'zkteco_bridge_token' => 'TOKEN_LARGO_PARA_CONECTOR_ZKTECO',
    'alexa_public_base_url' => 'https://idindustrial.com.mx/iot/api',
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
  ],
];
