<?php
return [
  'driver' => 'mysql',
  'host' => 'localhost',
  'database' => 'idindust_crm_idindustrial',
  'username' => 'idindust_tu_usuario',
  'password' => 'CAMBIA_ESTA_CONTRASENA',
  'charset' => 'utf8mb4',
  'app_url' => 'https://idindustrial.com.mx/sistema/crm',
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
];
