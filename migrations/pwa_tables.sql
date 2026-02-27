-- Migração: tabelas do módulo PWA
-- Execute este arquivo no banco de dados do projeto (ex.: mysql < migrations/pwa_tables.sql)

-- Configuração do PWA (uma linha por ambiente)
CREATE TABLE IF NOT EXISTS `pwa_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `app_name` varchar(255) NOT NULL DEFAULT 'Plataforma',
  `short_name` varchar(50) NOT NULL DEFAULT 'App',
  `description` text,
  `icon_path` varchar(500) DEFAULT NULL,
  `theme_color` varchar(20) NOT NULL DEFAULT '#32e768',
  `background_color` varchar(20) NOT NULL DEFAULT '#ffffff',
  `display_mode` varchar(30) NOT NULL DEFAULT 'standalone',
  `start_url` varchar(255) NOT NULL DEFAULT '/',
  `scope` varchar(255) NOT NULL DEFAULT '/',
  `push_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `vapid_public_key` text,
  `vapid_private_key` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inscrições para notificações push
CREATE TABLE IF NOT EXISTS `pwa_push_subscriptions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) unsigned NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_endpoint` (`endpoint`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico de notificações enviadas (opcional)
CREATE TABLE IF NOT EXISTS `pwa_push_notifications` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text,
  `url` varchar(500) DEFAULT NULL,
  `icon` varchar(500) DEFAULT NULL,
  `sent_count` int(11) NOT NULL DEFAULT 0,
  `failed_count` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
