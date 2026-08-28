<?php
declare(strict_types=1);

// Copia esta estructura a crm/config.php o usala como guia.
// No cambies la base principal a la vieja de ID Activos.
// La base activa debe ser la del CRM con las tablas IoT importadas.

return [
  'driver' => 'mysql',
  'host' => 'localhost',
  'database' => 'idindust_crm_idindustrial',
  'username' => 'USUARIO_MYSQL_DEL_CRM',
  'password' => 'PASSWORD_MYSQL_DEL_CRM',
  'charset' => 'utf8mb4',
  'app_url' => 'https://idindustrial.com.mx/crm',
  'smtp' => [
    'enabled' => false,
    'host' => 'mail.idindustrial.com.mx',
    'port' => 465,
    'secure' => 'ssl',
    'username' => 'no-reply@idindustrial.com.mx',
    'password' => 'PASSWORD_DEL_CORREO_CPANEL',
    'from_email' => 'no-reply@idindustrial.com.mx',
    'from_name' => 'ID Industrial',
  ],
  'iot' => [
    'api_token' => 'API_TOKEN_DEL_CONFIG_LOCAL_VIEJO',
    'setup_token' => 'SETUP_TOKEN_DEL_CONFIG_LOCAL_VIEJO',
    'crm_sso_iot_email' => 'admin@idindustrial.com',

    'shelly_cloud_server' => 'https://TU_SERVER_SHELLY_CLOUD',
    'shelly_cloud_auth_key' => 'TU_AUTH_KEY_SHELLY_CLOUD',
    'shelly_webhook_token' => 'TOKEN_LARGO_WEBHOOK',

    'hikvision_bridge_token' => 'TOKEN_LARGO_INVENTADO',
    'zkteco_bridge_token' => 'TOKEN_LARGO_INVENTADO',

    'alexa_public_base_url' => 'https://idindustrial.com.mx/iot/api',
    'alexa_oauth_client_id' => 'idindustrial-alexa',
    'alexa_oauth_client_secret' => 'SECRETO_LARGO_INVENTADO',
    'alexa_lambda_shared_secret' => 'OTRO_SECRETO_LARGO_INVENTADO',
    'alexa_event_client_id' => '',
    'alexa_event_client_secret' => '',
    'alexa_event_region' => 'NA',

    'retention_raw_days' => 90,
    'retention_hourly_months' => 24,
    'retention_shelly_event_days' => 365,
    'retention_push_days' => 90,
    'retention_hours_per_run' => 48,
    'retention_max_runtime_seconds' => 45,
  ],
];
