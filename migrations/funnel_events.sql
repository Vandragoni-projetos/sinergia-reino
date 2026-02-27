-- Etapa 2: Controle de duplicidade do funil por transação + oferta
-- Rastreia se a oferta foi mostrada, aceita, recusada ou pulada (evita reexibir)
-- Execute com o banco selecionado. Rode uma vez.

CREATE TABLE IF NOT EXISTS `funnel_events` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `community_id` INT(11) NOT NULL DEFAULT 1,
  `main_payment_id` VARCHAR(255) NOT NULL COMMENT 'transacao_id da compra principal',
  `step` ENUM('upsell','downsell') NOT NULL,
  `offer_product_id` INT(11) NOT NULL,
  `decision` ENUM('shown','accepted','declined','skipped') NOT NULL DEFAULT 'shown',
  `offer_payment_id` VARCHAR(255) NULL DEFAULT NULL COMMENT 'transacao_id do pagamento do upsell/downsell quando existir',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_main_step_offer` (`main_payment_id`,`step`,`offer_product_id`),
  KEY `idx_main_payment` (`main_payment_id`),
  KEY `idx_community` (`community_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Eventos do funil: shown=exibiu oferta, accepted/declined/skipped=decisão final';
