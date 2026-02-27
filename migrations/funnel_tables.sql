-- Migração: tabelas para Funil de Vendas (Upsell / Downsell)
-- No phpMyAdmin: selecione o banco no painel esquerdo e use a aba SQL (evita erro 1046).
-- Pela linha de comando: mysql -u usuario -p nome_do_banco < migrations/funnel_tables.sql

CREATE TABLE IF NOT EXISTS `product_funnels` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `main_product_id` int(11) NOT NULL COMMENT 'FK produtos.id (signed no schema original)',
  `community_id` int(11) unsigned DEFAULT NULL,
  `upsell_product_id` int(11) DEFAULT NULL COMMENT 'FK produtos.id',
  `downsell_product_id` int(11) DEFAULT NULL COMMENT 'FK produtos.id',
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_main_product` (`main_product_id`),
  KEY `idx_community` (`community_id`),
  CONSTRAINT `fk_product_funnels_main_product` FOREIGN KEY (`main_product_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

