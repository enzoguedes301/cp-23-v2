<?php
/**
 * =====================================================================
 *  MODELO DE CREDENCIAIS
 * =====================================================================
 *  Copie este arquivo para "credenciais.php" e preencha com seus
 *  tokens reais. O credenciais.php NÃO vai para o git (.gitignore).
 * ---------------------------------------------------------------------
 */

/* --- API DE CPF (Gibson Corp) --- */
define('CPF_API_URL',   'https://SEU-ENDPOINT/all/cpf/');
define('CPF_API_TOKEN', 'SEU_TOKEN_CPF');

/* --- API DE PIX --- */
define('PIX_MODO',    'mock');          // 'mock' ou 'producao'
define('PIX_GATEWAY', 'mercadopago');   // mercadopago | pushinpay | custom

define('MP_ACCESS_TOKEN', '');          // Mercado Pago (APP_USR-...)
define('PUSHINPAY_TOKEN', '');          // PushinPay
define('CUSTOM_API_URL',   '');         // Gateway custom
define('CUSTOM_API_TOKEN', '');
