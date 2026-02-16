-- =============================================================================
-- Base_de_Dados_Instalacao.sql - Carga inicial (seed) para 1ª instalação
-- Gerado a partir de Banco_de_Dados.sql - Sem dados de teste/operacionais
-- =============================================================================
-- O que foi MANTIDO:
--   - Todas as estruturas (CREATE TABLE, índices, FKs, trigger)
--   - communities (4 slugs: club, mkd, flow, kids)
--   - banner_badges (catálogo do dropdown)
--   - configuracoes (SMTP, template e-mail, etc.)
--   - configuracoes_sistema (chaves padrão com valores vazios/seguros)
--   - saas_planos (Free + Premium)
--   - saas_config_admin (1 linha placeholder)
--   - plugins (Modo SaaS)
--   - 1 usuário admin (admin@example.com / senha: 'password' - TROCAR NO 1º ACESSO)
-- O que NÃO foi incluído: produtos, cursos, módulos, aulas, vendas, acessos,
--   progresso, banners, feed, licenças, logs, webhooks, etc.
-- =============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Usar o banco de dados (selecione o correto ou crie antes: CREATE DATABASE checkout;)
USE `checkout`;

-- Desabilitar FKs para DROP
SET FOREIGN_KEY_CHECKS = 0;
DROP TRIGGER IF EXISTS `after_produto_insert`;
DROP TABLE IF EXISTS `alunos_acessos`, `aluno_progresso`, `aula_arquivos`, `aulas`, `notificacoes`, `vendas`, `gatewaypro_tracking_events`, `gatewaypro_tracking_products`, `modulos`, `cursos`, `order_bumps`, `product_exclusive_offers`, `produto_ofertas`, `products_feed_items`, `cloned_site_settings`, `cloned_sites`, `saas_assinaturas`, `saas_limites_uso`, `evolution_messages`, `utmfy_integrations`, `webhooks`, `produtos`, `banners`, `licencas_geradas`, `security_events`, `security_logs`, `login_attempts`, `configuracoes`, `configuracoes_sistema`, `communities`, `banner_badges`, `saas_config_admin`, `saas_planos`, `plugins`, `usuarios`;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Estrutura: alunos_acessos
-- --------------------------------------------------------
CREATE TABLE `alunos_acessos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aluno_email` varchar(255) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `oferta_id` int(11) DEFAULT NULL COMMENT 'ID da oferta que gerou este acesso (NULL = preço padrão do produto)',
  `data_concessao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_expiracao` timestamp NULL DEFAULT NULL COMMENT 'Data de expiração do acesso. NULL = acesso vitalício',
  `criado_manualmente` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Acesso criado manualmente pelo infoprodutor, 0 = Acesso via compra',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: aluno_progresso
-- --------------------------------------------------------
CREATE TABLE `aluno_progresso` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aluno_email` varchar(255) NOT NULL,
  `aula_id` int(11) NOT NULL,
  `data_conclusao` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: aulas
-- --------------------------------------------------------
CREATE TABLE `aulas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modulo_id` int(11) NOT NULL,
  `community_id` int(11) DEFAULT 1 COMMENT 'Comunidade (subdomínio)',
  `titulo` varchar(255) NOT NULL,
  `url_video` text DEFAULT NULL COMMENT 'URL do vídeo ou código embed',
  `origem_video` varchar(32) NOT NULL DEFAULT 'youtube' COMMENT 'youtube, vimeo, url_externa, codigo_incorporado',
  `descricao` text DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `release_days` int(11) NOT NULL DEFAULT 0 COMMENT 'Número de dias após a compra para a aula ser liberada',
  `tipo_conteudo` enum('video','files','mixed') NOT NULL DEFAULT 'video' COMMENT 'Tipo de conteúdo da aula: video, files ou mixed',
  `lesson_cover_type` enum('upload','url') DEFAULT NULL COMMENT 'upload ou url',
  `lesson_cover_url` varchar(512) DEFAULT NULL COMMENT 'URL externa da imagem',
  `lesson_cover_path` varchar(512) DEFAULT NULL COMMENT 'Caminho relativo do upload',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: aula_arquivos
-- --------------------------------------------------------
CREATE TABLE `aula_arquivos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aula_id` int(11) NOT NULL,
  `nome_original` varchar(255) NOT NULL COMMENT 'Nome original do arquivo',
  `nome_salvo` varchar(255) NOT NULL COMMENT 'Nome do arquivo salvo no servidor',
  `caminho_arquivo` varchar(255) NOT NULL COMMENT 'Caminho completo do arquivo no servidor (ex: uploads/aula_files/arquivo.pdf)',
  `tipo_mime` varchar(100) DEFAULT NULL COMMENT 'Tipo MIME do arquivo (ex: application/pdf, image/png)',
  `tamanho_bytes` int(11) DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição do arquivo dentro da aula',
  `data_upload` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: banners
-- --------------------------------------------------------
CREATE TABLE `banners` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `community_id` int(11) UNSIGNED DEFAULT NULL COMMENT 'Para multi-tenant (FK lógico)',
  `usuario_id` int(11) UNSIGNED NOT NULL COMMENT 'Infoprodutor owner',
  `titulo` varchar(255) DEFAULT NULL COMMENT 'Título do banner (opcional)',
  `badge_id` int(11) UNSIGNED DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL COMMENT 'Caminho local do upload (ex: uploads/banner_xxx.png)',
  `image_url` varchar(1000) DEFAULT NULL COMMENT 'URL externa da imagem',
  `click_url` varchar(1000) DEFAULT NULL COMMENT 'Link de destino ao clicar',
  `open_new_tab` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Abrir em nova aba',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Banner ativo/inativo',
  `show_in_products_grid` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Mostrar no grid de produtos (infoprodutor)',
  `show_in_member_dashboard` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Mostrar no dashboard do cliente',
  `show_in_offers_section` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Mostrar na seção Ofertas Exclusivas',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Banners publicitários do infoprodutor';

-- --------------------------------------------------------
-- Estrutura: banner_badges
-- --------------------------------------------------------
CREATE TABLE `banner_badges` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `icon` varchar(8) NOT NULL COMMENT 'Emoji ou caractere (ex: ?)',
  `label` varchar(40) NOT NULL COMMENT 'Label curta exibida no card',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Opções de badge para banners publicitários';

-- --------------------------------------------------------
-- Estrutura: cloned_sites
-- --------------------------------------------------------
CREATE TABLE `cloned_sites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do infoprodutor dono do site clonado',
  `original_url` varchar(2048) NOT NULL COMMENT 'URL do site original que foi clonado',
  `title` varchar(255) DEFAULT NULL COMMENT 'Título da página clonada',
  `original_html` longtext NOT NULL COMMENT 'Conteúdo HTML original da página clonada',
  `edited_html` longtext DEFAULT NULL COMMENT 'Conteúdo HTML da página após edição do usuário',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `slug` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: cloned_site_settings
-- --------------------------------------------------------
CREATE TABLE `cloned_site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cloned_site_id` int(11) NOT NULL COMMENT 'ID do site clonado associado',
  `facebook_pixel_id` varchar(255) DEFAULT NULL COMMENT 'ID do Facebook Pixel',
  `google_analytics_id` varchar(255) DEFAULT NULL COMMENT 'ID do Google Analytics',
  `custom_head_scripts` longtext DEFAULT NULL COMMENT 'Scripts personalizados a serem injetados no <head>',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: communities
-- --------------------------------------------------------
CREATE TABLE `communities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL COMMENT 'Subdomínio: mktd, club, flow, kids',
  `name` varchar(255) NOT NULL,
  `theme_json` text DEFAULT NULL,
  `primary_color` varchar(20) DEFAULT '#32e768',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura: configuracoes
-- --------------------------------------------------------
CREATE TABLE `configuracoes` (
  `chave` varchar(255) NOT NULL,
  `valor` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: configuracoes_sistema
-- --------------------------------------------------------
CREATE TABLE `configuracoes_sistema` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `community_id` int(11) DEFAULT NULL COMMENT 'NULL=global, 1+=por comunidade',
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `tipo` varchar(50) DEFAULT 'text',
  `descricao` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura: cursos
-- --------------------------------------------------------
CREATE TABLE `cursos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produto_id` int(11) NOT NULL,
  `community_id` int(11) DEFAULT 1 COMMENT 'Comunidade (subdomínio)',
  `titulo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `imagem_url` varchar(255) DEFAULT NULL,
  `banner_url` varchar(255) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: evolution_messages
-- --------------------------------------------------------
CREATE TABLE `evolution_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do infoprodutor dono da mensagem',
  `produto_id` int(11) DEFAULT NULL COMMENT 'ID do produto específico (NULL para todos)',
  `name` varchar(255) NOT NULL COMMENT 'Nome identificador da mensagem',
  `event_type` enum('approved','pending','rejected','refunded','charged_back') NOT NULL COMMENT 'Evento que dispara a mensagem',
  `message_template` text NOT NULL COMMENT 'Template da mensagem com variáveis',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=inativo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: gatewaypro_tracking_events
-- --------------------------------------------------------
CREATE TABLE `gatewaypro_tracking_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tracking_product_id` int(11) NOT NULL COMMENT 'ID do produto rastreado em gatewaypro_tracking_products',
  `session_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'ID único da sessão do usuário',
  `event_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Tipo do evento (page_view, initiate_checkout, purchase)',
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Dados adicionais do evento (ex: url, referrer)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura: gatewaypro_tracking_products
-- --------------------------------------------------------
CREATE TABLE `gatewaypro_tracking_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do infoprodutor dono do produto',
  `produto_id` int(11) NOT NULL COMMENT 'ID do produto real sendo rastreado',
  `tracking_id` varchar(64) NOT NULL COMMENT 'ID único para o script de rastreamento',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: licencas_geradas
-- --------------------------------------------------------
CREATE TABLE `licencas_geradas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave_licenca` varchar(64) NOT NULL COMMENT 'Chave única de ativação',
  `tipo_licenca` varchar(32) NOT NULL DEFAULT 'VITALICIO' COMMENT 'VITALICIO, MENSAL, ANUAL, SEMESTRAL',
  `dias_validade` int(11) DEFAULT NULL COMMENT 'NULL=vitalício, 30=mensal, 365=anual',
  `escopo` enum('SYSTEM','COMMUNITY','PRODUCT','USER_LIMIT') NOT NULL DEFAULT 'SYSTEM' COMMENT 'SYSTEM=ativa sistema, PRODUCT=produto específico, etc.',
  `escopo_ref_id` int(11) DEFAULT NULL COMMENT 'ID do produto/comunidade quando escopo=PRODUCT ou COMMUNITY',
  `community_id` int(11) DEFAULT NULL COMMENT 'Para escopo COMMUNITY',
  `status` varchar(32) NOT NULL DEFAULT 'disponivel' COMMENT 'disponivel, ativa, ativada, expirada, bloqueada, revogada',
  `owner_user_id` int(11) DEFAULT NULL COMMENT 'Quem gerou a licença (usuario_id)',
  `assigned_user_id` int(11) DEFAULT NULL COMMENT 'Quem está usando (usuario_id), NULL=ainda não ativada',
  `aluno_email` varchar(255) DEFAULT NULL COMMENT 'Retrocompat: email do gerador',
  `aluno_nome` varchar(255) DEFAULT NULL COMMENT 'Retrocompat: nome do gerador',
  `produto_id` int(11) DEFAULT NULL COMMENT 'Produto que concede direito de gerar',
  `observacoes` text DEFAULT NULL,
  `data_geracao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_ativacao` timestamp NULL DEFAULT NULL,
  `data_expiracao` date DEFAULT NULL COMMENT 'NULL=vitalício',
  `instalacao_id` varchar(128) DEFAULT NULL COMMENT 'system_id da instalação que ativou',
  `ip_ativacao` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: login_attempts
-- --------------------------------------------------------
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL COMMENT 'Endereço IP do cliente',
  `email` varchar(255) DEFAULT NULL COMMENT 'Email/usuário tentado (opcional)',
  `attempts` int(11) NOT NULL DEFAULT 1 COMMENT 'Número de tentativas falhas',
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data/hora da última tentativa',
  `blocked_until` timestamp NULL DEFAULT NULL COMMENT 'Data/hora até quando está bloqueado',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Rastreamento de tentativas de login para proteção contra força bruta';

-- --------------------------------------------------------
-- Estrutura: modulos
-- --------------------------------------------------------
CREATE TABLE `modulos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curso_id` int(11) NOT NULL,
  `community_id` int(11) DEFAULT 1 COMMENT 'Comunidade (subdomínio)',
  `titulo` varchar(255) NOT NULL,
  `imagem_capa_url` varchar(255) DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `release_days` int(11) NOT NULL DEFAULT 0 COMMENT 'Número de dias após a compra para o módulo ser liberado',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: notificacoes
-- --------------------------------------------------------
CREATE TABLE `notificacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do infoprodutor que deve receber a notificação',
  `tipo` varchar(50) NOT NULL COMMENT 'Tipo de evento (ex: Compra Aprovada, Pix Gerado, Boleto Pago)',
  `mensagem` text NOT NULL COMMENT 'Mensagem completa da notificação',
  `valor` decimal(10,2) DEFAULT NULL COMMENT 'Valor associado à notificação (ex: valor da venda)',
  `data_notificacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `lida` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 para não lida, 1 para lida',
  `link_acao` varchar(255) DEFAULT NULL COMMENT 'Link opcional para detalhes da venda',
  `displayed_live` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 para não exibida ao vivo, 1 para já exibida ao vivo',
  `venda_id_fk` int(11) DEFAULT NULL COMMENT 'Chave estrangeira para a tabela de vendas',
  `metodo_pagamento` varchar(50) DEFAULT NULL COMMENT 'Método de pagamento da venda associada, para notificação live',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: order_bumps
-- --------------------------------------------------------
CREATE TABLE `order_bumps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `main_product_id` int(11) NOT NULL COMMENT 'ID do produto principal (o do checkout)',
  `offer_product_id` int(11) NOT NULL COMMENT 'ID do produto que está sendo ofertado',
  `headline` varchar(255) DEFAULT 'Sim, eu quero aproveitar essa oferta!',
  `description` text DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição no checkout',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: products_feed_items
-- --------------------------------------------------------
CREATE TABLE `products_feed_items` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `community_id` int(11) UNSIGNED DEFAULT NULL COMMENT 'Para multi-tenant',
  `usuario_id` int(11) UNSIGNED NOT NULL COMMENT 'Infoprodutor owner',
  `item_type` enum('product','banner') NOT NULL COMMENT 'Tipo do item',
  `item_id` int(11) UNSIGNED NOT NULL COMMENT 'ID do produto ou banner',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição (menor = primeiro)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordem dos itens no feed (produtos + banners)';

-- --------------------------------------------------------
-- Estrutura: product_exclusive_offers
-- --------------------------------------------------------
CREATE TABLE `product_exclusive_offers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_product_id` int(11) NOT NULL COMMENT 'ID do produto que o cliente já possui e que gera a oferta',
  `offer_product_id` int(11) NOT NULL COMMENT 'ID do produto (tipo area_membros) ofertado',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status da oferta: 1=ativo, 0=inativo',
  `custom_link` varchar(500) DEFAULT NULL COMMENT 'Link personalizado para a oferta. Se NULL, usa o checkout padrão do produto.',
  `custom_button_text` varchar(100) DEFAULT NULL COMMENT 'Texto personalizado do botão. Se NULL, usa "Comprar por R$ X,XX".',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: produtos
-- --------------------------------------------------------
CREATE TABLE `produtos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `is_free` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se o produto é gratuito (1=grátis, 0=pago)',
  `is_showcase` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se o produto é vitrine para registro gratuito (1=vitrine, 0=normal)',
  `foto` varchar(255) DEFAULT NULL,
  `checkout_hash` varchar(255) NOT NULL,
  `checkout_config` text DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `community_id` int(11) DEFAULT 1 COMMENT 'Comunidade (subdomínio)',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `preco_anterior` decimal(10,2) DEFAULT NULL,
  `tipo_entrega` varchar(50) NOT NULL DEFAULT 'link',
  `conteudo_entrega` varchar(255) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT 'mercadopago',
  `gera_licenca` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se este produto permite gerar licenças (apenas no painel master)',
  `ordem` int(11) NOT NULL DEFAULT 0 COMMENT 'Ordem manual para exibição (drag & drop)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: produto_ofertas
-- --------------------------------------------------------
CREATE TABLE `produto_ofertas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produto_id` int(11) NOT NULL COMMENT 'ID do produto principal',
  `nome` varchar(255) NOT NULL COMMENT 'Nome da oferta (ex: Black Friday, Lançamento)',
  `preco` decimal(10,2) NOT NULL COMMENT 'Preço específico desta oferta',
  `tipo_acesso` enum('mensal','semestral','anual','vitalicio') NOT NULL DEFAULT 'vitalicio' COMMENT 'Tipo de acesso: mensal (30 dias), semestral (180 dias), anual (365 dias), vitalicio (sem expiração)',
  `hash` varchar(64) NOT NULL COMMENT 'Hash único para o link da oferta',
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=inativo',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: saas_assinaturas
-- --------------------------------------------------------
CREATE TABLE `saas_assinaturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `status` enum('ativo','expirado','cancelado','pendente') DEFAULT 'pendente',
  `data_inicio` datetime DEFAULT current_timestamp(),
  `data_vencimento` datetime NOT NULL,
  `transacao_id` varchar(255) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `renovacao_automatica` tinyint(1) DEFAULT 1,
  `notificado_vencimento` tinyint(1) DEFAULT 0,
  `notificado_expirado` tinyint(1) DEFAULT 0,
  `criado_em` datetime DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura: saas_config_admin
-- --------------------------------------------------------
CREATE TABLE `saas_config_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mp_access_token` text DEFAULT NULL,
  `mp_public_key` varchar(255) DEFAULT NULL,
  `pushinpay_token` text DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_methods` text DEFAULT NULL COMMENT 'JSON com métodos de pagamento habilitados',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura: saas_limites_uso
-- --------------------------------------------------------
CREATE TABLE `saas_limites_uso` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `mes_ano` varchar(7) NOT NULL COMMENT 'Formato: YYYY-MM',
  `produtos_criados` int(11) DEFAULT 0,
  `pedidos_realizados` int(11) DEFAULT 0,
  `resetado_em` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura: saas_planos
-- --------------------------------------------------------
CREATE TABLE `saas_planos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) DEFAULT 0.00,
  `periodo` enum('mensal','anual') DEFAULT 'mensal',
  `max_produtos` int(11) DEFAULT NULL COMMENT 'NULL = ilimitado',
  `max_pedidos_mes` int(11) DEFAULT NULL COMMENT 'NULL = ilimitado',
  `tracking_enabled` tinyint(1) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` datetime DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura: security_events
-- --------------------------------------------------------
CREATE TABLE `security_events` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `community_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `event_type` varchar(64) NOT NULL,
  `page` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura: security_logs
-- --------------------------------------------------------
CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL COMMENT 'Tipo do evento (failed_login_attempt, blocked_login_attempt, unauthorized_access, etc)',
  `user_id` int(11) DEFAULT NULL COMMENT 'ID do usuário (se aplicável)',
  `ip_address` varchar(45) NOT NULL COMMENT 'Endereço IP do cliente',
  `details` text DEFAULT NULL COMMENT 'Detalhes do evento em JSON',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data/hora do evento',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Logs de eventos de segurança para auditoria';

-- --------------------------------------------------------
-- Estrutura: plugins
-- --------------------------------------------------------
CREATE TABLE `plugins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `pasta` varchar(100) NOT NULL,
  `versao` varchar(20) DEFAULT '1.0.0',
  `ativo` tinyint(1) DEFAULT 0,
  `instalado_em` datetime DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura: usuarios
-- --------------------------------------------------------
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(255) NOT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'infoprodutor' COMMENT 'Define o tipo de usuário (admin, infoprodutor, usuario[cliente])',
  `data_cadastro` timestamp NULL DEFAULT current_timestamp(),
  `mp_public_key` varchar(255) DEFAULT NULL,
  `mp_access_token` varchar(255) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `ultima_visualizacao_notificacoes` timestamp NULL DEFAULT NULL COMMENT 'Timestamp da última vez que o usuário visualizou o painel de notificações',
  `pushinpay_token` varchar(255) DEFAULT NULL,
  `evolution_name` varchar(255) DEFAULT NULL COMMENT 'Nome da integração Evolution API',
  `evolution_server_url` varchar(500) DEFAULT NULL COMMENT 'URL do servidor Evolution API',
  `evolution_api_key` varchar(255) DEFAULT NULL COMMENT 'API Key global da Evolution API',
  `evolution_instance` varchar(255) DEFAULT NULL COMMENT 'Nome da instância na Evolution API',
  `efi_client_id` varchar(255) DEFAULT NULL COMMENT 'Client ID da aplicação Efí',
  `efi_client_secret` varchar(255) DEFAULT NULL COMMENT 'Client Secret da aplicação Efí',
  `efi_certificate_path` varchar(500) DEFAULT NULL COMMENT 'Caminho do certificado P12 da Efí',
  `efi_pix_key` varchar(255) DEFAULT NULL COMMENT 'Chave Pix cadastrada na Efí',
  `efi_payee_code` varchar(255) DEFAULT NULL COMMENT 'Código do recebedor Efí para cartão de crédito',
  `beehive_secret_key` varchar(255) DEFAULT NULL COMMENT 'Secret Key da Beehive',
  `beehive_public_key` varchar(255) DEFAULT NULL COMMENT 'Public Key da Beehive',
  `hypercash_secret_key` varchar(255) DEFAULT NULL COMMENT 'Secret Key da Hypercash',
  `hypercash_public_key` varchar(255) DEFAULT NULL COMMENT 'Public Key da Hypercash',
  `pagarme_api_key` varchar(255) DEFAULT NULL COMMENT 'API Key (Pública) Pagar.me',
  `pagarme_api_secret` varchar(255) DEFAULT NULL COMMENT 'API Secret (Privada) Pagar.me',
  `pagarme_webhook_secret` varchar(255) DEFAULT NULL COMMENT 'Webhook Secret Pagar.me',
  `paypal_client_id` varchar(255) DEFAULT NULL COMMENT 'Client ID (API Key) PayPal',
  `paypal_client_secret` varchar(255) DEFAULT NULL COMMENT 'Client Secret PayPal',
  `paypal_webhook_secret` varchar(255) DEFAULT NULL COMMENT 'Webhook Secret PayPal',
  `stripe_publishable_key` varchar(255) DEFAULT NULL COMMENT 'Publishable Key Stripe',
  `stripe_secret_key` varchar(255) DEFAULT NULL COMMENT 'Secret Key Stripe',
  `stripe_webhook_secret` varchar(255) DEFAULT NULL COMMENT 'Webhook Secret Stripe',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: utmfy_integrations
-- --------------------------------------------------------
CREATE TABLE `utmfy_integrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do infoprodutor dono da integração',
  `name` varchar(255) NOT NULL COMMENT 'Nome amigável da integração (ex: Campanha de Lançamento X)',
  `api_token` varchar(255) NOT NULL COMMENT 'API Token fornecido pela UTMfy',
  `product_id` int(11) DEFAULT NULL COMMENT 'ID do produto específico que dispara a notificação (NULL para todos os produtos do infoprodutor)',
  `event_approved` tinyint(1) NOT NULL DEFAULT 0,
  `event_pending` tinyint(1) NOT NULL DEFAULT 0,
  `event_rejected` tinyint(1) NOT NULL DEFAULT 0,
  `event_refunded` tinyint(1) NOT NULL DEFAULT 0,
  `event_charged_back` tinyint(1) NOT NULL DEFAULT 0,
  `event_initiate_checkout` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Disparar evento ao iniciar checkout',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: vendas
-- --------------------------------------------------------
CREATE TABLE `vendas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produto_id` int(11) NOT NULL,
  `community_id` int(11) DEFAULT 1 COMMENT 'Comunidade (subdomínio)',
  `oferta_id` int(11) DEFAULT NULL COMMENT 'ID da oferta usada (NULL se preço padrão)',
  `valor` decimal(10,2) NOT NULL,
  `status_pagamento` varchar(50) NOT NULL,
  `data_venda` timestamp NOT NULL DEFAULT current_timestamp(),
  `comprador_email` varchar(255) DEFAULT NULL,
  `comprador_nome` varchar(255) DEFAULT NULL,
  `comprador_cpf` varchar(20) DEFAULT NULL,
  `comprador_telefone` varchar(20) DEFAULT NULL,
  `transacao_id` varchar(255) DEFAULT NULL,
  `metodo_pagamento` varchar(50) DEFAULT NULL,
  `checkout_session_uuid` varchar(255) DEFAULT NULL COMMENT 'UUID para agrupar vendas de um mesmo checkout (principal + order bumps)',
  `email_entrega_enviado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = Não enviado, 1 = Enviado',
  `utm_source` varchar(255) DEFAULT NULL,
  `utm_campaign` varchar(255) DEFAULT NULL,
  `utm_medium` varchar(255) DEFAULT NULL,
  `utm_content` varchar(255) DEFAULT NULL,
  `utm_term` varchar(255) DEFAULT NULL,
  `src` varchar(255) DEFAULT NULL,
  `sck` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Estrutura: webhooks
-- --------------------------------------------------------
CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do infoprodutor dono do webhook',
  `produto_id` int(11) DEFAULT NULL COMMENT 'ID do produto específico que dispara o webhook (NULL para todos os produtos do infoprodutor)',
  `url` varchar(2048) NOT NULL COMMENT 'URL para onde o webhook será enviado',
  `event_approved` tinyint(1) NOT NULL DEFAULT 0,
  `event_pending` tinyint(1) NOT NULL DEFAULT 0,
  `event_rejected` tinyint(1) NOT NULL DEFAULT 0,
  `event_refunded` tinyint(1) NOT NULL DEFAULT 0,
  `event_charged_back` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dados seed: communities
INSERT INTO `communities` (`id`, `slug`, `name`, `theme_json`, `primary_color`, `created_at`) VALUES
(1, 'club', 'SinergIA Club', NULL, '#32e768', '2026-02-01 22:44:40'),
(2, 'mkd', 'SinergIA Mkd', NULL, '#32e768', '2026-02-01 22:44:40'),
(3, 'flow', 'SinergIA Flow', NULL, '#32e768', '2026-02-01 22:44:40'),
(4, 'kids', 'SinergIA Kids', NULL, '#32e768', '2026-02-01 22:44:40');


-- Dados seed: banner_badges (expansão)

INSERT INTO `banner_badges`
(`id`, `slug`, `icon`, `label`, `is_active`, `sort_order`, `created_at`)
VALUES
(1,  'premium',        '🥇', 'Destaque premium',        1,  10, '2026-02-03 01:38:46'),
(2,  'warning',        '🔔', 'Aviso importante',        1,  20, '2026-02-03 01:38:46'),
(3,  'bonus',          '🎁', 'Bônus / Promoção',        1,  30, '2026-02-03 01:38:46'),
(4,  'partners',       '🤝', 'Parceiros',               1,  40, '2026-02-03 01:38:46'),
(5,  'news',           '🚀', 'Novidade',                1,  50, '2026-02-03 01:38:46'),
(6,  'exclusive',      '💎', 'Exclusivo',               1,  60, '2026-02-03 01:38:46'),

(7,  'bestseller',     '🏆', 'Mais vendido',            1,  70, '2026-02-03 01:38:46'),
(8,  'tips',           '💡', 'Dica rápida',             1,  80, '2026-02-03 01:38:46'),
(9,  'review',         '⭐', 'Avaliação',               1,  90, '2026-02-03 01:38:46'),
(10, 'recommend',      '✅', 'Recomendado',             1, 100, '2026-02-03 01:38:46'),
(11, 'digital',        '💳', 'Já vende no digital?',    1, 110, '2026-02-03 01:38:46'),

(12, 'limited',        '⏳', 'Tempo limitado',          1, 120, '2026-02-03 01:38:46'),
(13, 'hot',            '🔥', 'Em alta',                 1, 130, '2026-02-03 01:38:46'),
(14, 'free',           '🆓', 'Grátis',                  1, 140, '2026-02-03 01:38:46'),
(15, 'community',      '📣', 'Comunidade',              1, 150, '2026-02-03 01:38:46'),
(16, 'support',        '🛟', 'Precisa de ajuda?',       1, 160, '2026-02-03 01:38:46');



-- Dados seed: configuracoes
INSERT INTO `configuracoes` (`chave`, `valor`) VALUES
('email_template_delivery_html', '<!DOCTYPE html>\n<html lang=\"pt-br\">\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <meta http-equiv=\"X-UA-Compatible\" content=\"ie=edge\">\n    <title>Bem-vindo(a)!</title>\n    <style>\n        @import url(\'https://www.google.com/url?sa=E&source=gmail&q=https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700%26display=swap\');\n        /* Estilos para responsividade */\n        @media screen and (max-width: 600px) {\n            .container {\n                width: 100% !important;\n                padding: 10px !important;\n            }\n            .content {\n                padding: 25px 20px !important;\n            }\n            .header-img {\n                width: 150px !important;\n            }\n            h1 {\n                font-size: 24px !important;\n            }\n        }\n    </style>\n</head>\n<body style=\"margin: 0; padding: 0; background-color: #f1f5f9; font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;\">\n    <!-- Preheader (texto de visualização no cliente de e-mail) -->\n    <div style=\"display: none; max-height: 0; overflow: hidden;\">Tudo pronto! Seu acesso aos produtos já está disponível.</div>\n    <table align=\"center\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"border-collapse: collapse;\">\n        <tr>\n            <td align=\"center\" style=\"padding: 20px 0;\">\n                <table class=\"container\" align=\"center\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"border-collapse: collapse; background-color: #ffffff; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #e2e8f0;\">\n                    <!-- Cabeçalho com a Nova Logo -->\n                    <tr>\n                        <td align=\"center\" bgcolor=\"#1e1e2f\" style=\"padding: 30px 20px; background-color: #1e1e2f;\">\n                            <div>\n                                <img class=\"header-img\" src=\"https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/logo-gatewaypro.png\" alt=\"Logo GarewayPro\" width=\"200\" style=\"display: block; border: 0;\" />\n                            </div>\n                        </td>\n                    </tr>\n                    <!-- Corpo Principal -->\n                    <tr>\n                        <td class=\"content\" style=\"padding: 40px 35px;\">\n                            <h1 style=\"font-size: 28px; font-weight: 700; color: #0f172a; margin: 0 0 15px 0;\">Parabéns, {CLIENT_NAME}!</h1>\n                            <p style=\"margin: 0 0 25px 0; font-size: 16px; line-height: 1.6; color: #475569;\">\n                                Seus produtos adquiridos foram liberados com sucesso! Abaixo estão os detalhes de acesso para cada um deles:\n                            </p>\n                            <!-- Início do Loop de Produtos -->\n                            <!-- LOOP_PRODUCTS_START -->\n                            <div style=\"background-color: #ffffff; border: 1px solid #2DD05E; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.03);\">\n                                <h2 style=\"font-size: 20px; font-weight: 600; color: #1e293b; margin: 0 0 15px 0;\">{PRODUCT_NAME}</h2>\n                                \n                                <!-- Bloco para Área de Membros -->\n                                <!-- IF_PRODUCT_TYPE_MEMBER_AREA -->\n                                <p style=\"margin: 0 0 10px 0; font-size: 15px; color: #475569;\">Este produto está disponível em sua área de membros.</p>\n                                <p style=\"margin: 0 0 5px 0; font-size: 15px; color: #475569;\"><strong>Seu login:</strong> {CLIENT_EMAIL}</p>\n                                <p style=\"margin: 0 0 20px 0; font-size: 15px; color: #475569;\"><strong>Sua senha:</strong> {MEMBER_AREA_PASSWORD}</p>\n                                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"border-collapse: collapse;\">\n                                    <tr>\n                                        <td align=\"center\" style=\"background-color: #2DD05E; border-radius: 8px;\">\n                                            <a href=\"{MEMBER_AREA_LOGIN_URL}\" target=\"_blank\" style=\"color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 28px; border: 19px solid #2DD05E; display: inline-block; border-radius: 8px;\">Acessar sua Área de Membros</a>\n                                        </td>\n                                    </tr>\n                                </table>\n                                <!-- END_IF_PRODUCT_TYPE_MEMBER_AREA -->\n\n                                <!-- Bloco para Link -->\n                                <!-- IF_PRODUCT_TYPE_LINK -->\n                                <p style=\"margin: 0 0 15px 0; font-size: 15px; color: #475569;\"><strong>Link de Acesso:</strong></p>\n                                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"border-collapse: collapse; margin-bottom: 10px;\">\n                                    <tr>\n                                        <td align=\"center\" style=\"background-color: #2DD05E; border-radius: 8px;\">\n                                            <!-- ### CORREÇÃO AQUI ### -->\n                                            <!-- Eu mudei o \'border: 1px\' para \'border: 19px\' para bater com o botão da área de membros. -->\n                                            <!-- Isso força o Outlook e outros clientes de e-mail a tornar toda a área do botão clicável. -->\n                                            <a href=\"{PRODUCT_LINK}\" target=\"_blank\" style=\"color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 28px; border: 19px solid #2DD05E; display: inline-block; border-radius: 8px;\">Acessar {PRODUCT_NAME}</a>\n                                        </td>\n                                    </tr>\n                                </table>\n                                <p style=\"word-break: break-all; font-size: 12px; color: #64748b;\">Se o botão não funcionar, copie e cole o link: <a href=\"{PRODUCT_LINK}\" style=\"color: #2DD05E;\">{PRODUCT_LINK}</a></p>\n                                <!-- END_IF_PRODUCT_TYPE_LINK -->\n\n                                <!-- Bloco para PDF -->\n                                <!-- IF_PRODUCT_TYPE_PDF -->\n                                <p style=\"margin: 0 0 10px 0; font-size: 15px; color: #475569;\">Seu PDF está anexado a este e-mail. Faça o download para começar a aproveitar!</p>\n                                <!-- END_IF_PRODUCT_TYPE_PDF -->\n                            </div>\n                            <!-- Fim do Loop de Produtos -->\n                            <!-- LOOP_PRODUCTS_END -->\n\n                            <p style=\"margin: 30px 0 0 0; font-size: 16px; line-height: 1.6; color: #475569;\">\n                                Caso tenha alguma dúvida ou precise de suporte, entre em contato conosco.\n                            </p>\n                            <p style=\"margin: 15px 0 0 0; font-size: 16px; line-height: 1.6; color: #475569;\">\n                                Obrigado e aproveite seus novos produtos!\n                            </p>\n                        </td>\n                    </tr>\n                    <!-- Rodapé -->\n                    <tr>\n                        <td align=\"center\" style=\"padding: 25px 30px; background-color: #f8fafc; border-top: 1px solid #e2e8f0;\">\n                            <p style=\"margin: 0; font-size: 13px; color: #64748b;\">\n                                Este é um e-mail automático, por favor, não responda.\n                            </p>\n                            <p style=\"margin: 10px 0 0 0; font-size: 13px; color: #94a3b8;\">\n                                GatewayPro &copy; 2025. Todos os direitos reservados.\n                            </p>\n                        </td>\n                    </tr>\n                </table>\n            </td>\n        </tr>\n    </table>\n</body>\n</html>'),
('email_template_delivery_subject', 'Acesso ao seu Produto GatewayPro!'),
('member_area_login_url', ''),
('mercado_pago_enable_credit_card', '1'),
('mercado_pago_enable_pix', '1'),
('mercado_pago_max_installments', '24'),
('smtp_encryption', 'ssl'),
('smtp_from_email', ''),
('smtp_from_name', 'GatewayPro'),
('smtp_host', 'smtp.gmail.com'),
('smtp_password', 'senha_app'),
('smtp_port', '465'),
('smtp_username', '');

-- Dados seed: configuracoes_sistema
INSERT INTO `configuracoes_sistema` (`id`, `community_id`, `chave`, `valor`, `tipo`, `descricao`, `created_at`, `updated_at`) VALUES
(1, NULL, 'cor_primaria', '#32e768', 'color', 'Cor primária do sistema', NOW(), NOW()),
(2, NULL, 'logo_url', '', 'image', 'URL da logo do sistema', NOW(), NOW()),
(3, NULL, 'login_image_url', '', 'image', 'URL da imagem de fundo da tela de login', NOW(), NOW()),
(13, NULL, 'nome_plataforma', 'GatewayPro', 'text', NULL, NOW(), NOW()),
(14, NULL, 'logo_checkout_url', '', 'text', NULL, NOW(), NOW()),
(15, NULL, 'favicon_url', '', 'text', NULL, NOW(), NOW()),
(16, NULL, 'master_panel_url', '', 'text', 'URL do painel master para validação de licenças', NOW(), NOW()),
(17, NULL, 'master_panel_api_token', '', 'text', 'Token de autenticação da API do painel master', NOW(), NOW()),
(18, NULL, 'license_key', '', 'text', 'Chave de licença ativada', NOW(), NOW()),
(19, NULL, 'license_status', 'active', 'text', 'Status da licença: active, expired, invalid', NOW(), NOW()),
(20, NULL, 'license_expiration', 'lifetime', 'text', 'Data de expiração da licença ou lifetime', NOW(), NOW()),
(21, NULL, 'license_activated_at', NULL, 'text', 'Data/hora da ativação da licença', NOW(), NOW()),
(22, NULL, 'license_last_check', NULL, 'text', 'Última verificação da licença', NOW(), NOW()),
(23, NULL, 'license_type', 'Vitalício', 'text', 'Tipo da licença: VITALICIO, ANUAL, SEMESTRAL, MENSAL', NOW(), NOW()),
(24, NULL, 'license_days', '', 'text', 'Dias de validade da licença', NOW(), NOW()),
(25, NULL, 'system_id', '', 'text', 'ID único desta instalação (gerado na 1ª ativação)', NOW(), NOW()),
(26, NULL, 'security_seal_url', '', 'text', NULL, NOW(), NOW()),
(27, NULL, 'theme_json', '{\"primary\":\"#32e768\",\"primaryHover\":\"#2dd05e\",\"bg\":\"#080e16\",\"text\":\"rgba(255,255,255,0.9)\",\"textMuted\":\"rgba(255,255,255,0.5)\",\"card\":\"#1f3147\",\"cardElevated\":\"#0f1419\",\"border\":\"rgba(255,255,255,0.1)\",\"radius\":\"1.5rem\",\"shadow\":\"0 4px 6px -1px rgba(0,0,0,0.3)\",\"fontSans\":\"Montserrat,sans-serif\"}', 'json', 'Configurações visuais white-label', NOW(), NOW()),
(28, NULL, 'is_master_panel', '0', 'text', NULL, NOW(), NOW()),
(29, NULL, 'master_secret_key', '', 'text', NULL, NOW(), NOW()),
(30, NULL, 'license_api_token', '', 'text', NULL, NOW(), NOW()),
(31, NULL, 'notification_image_url', '', 'text', NULL, NOW(), NOW()),
(32, NULL, 'PROTECT_MEMBER_AREA', 'true', 'boolean', 'Proteção área de membros', NOW(), NOW()),
(33, NULL, 'PROTECT_MEMBER_AREA_BY_COMMUNITY', '{}', 'json', 'Override por community_id', NOW(), NOW()),
(34, NULL, 'blocked_offers_grayscale', '0', 'boolean', 'Ofertas bloqueadas: 1 = preto e branco, 0 = colorido (área do cliente)', NOW(), NOW());


-- Dados seed: saas_planos
INSERT INTO `saas_planos` (`id`, `nome`, `descricao`, `preco`, `periodo`, `max_produtos`, `max_pedidos_mes`, `tracking_enabled`, `ativo`, `criado_em`, `atualizado_em`) VALUES
(1, 'Plano Free', 'Plano gratuito para começar', 0.00, 'mensal', 3, 10, 0, 1, '2025-12-27 19:52:55', '2025-12-27 19:52:55'),
(2, 'Premium', 'Descr', 35.00, 'mensal', NULL, NULL, 1, 1, '2025-12-27 20:02:00', '2025-12-27 20:02:00');

-- Dados seed: saas_config_admin
INSERT INTO `saas_config_admin` (`id`, `mp_access_token`, `mp_public_key`, `pushinpay_token`, `ativo`, `atualizado_em`, `payment_methods`) VALUES
(1, 'APP_USR-000', 'APP_USR-000', '58271|000', 1, '2025-12-27 22:00:11', '{\"pix\":{\"gateway\":\"pushinpay\",\"enabled\":true},\"credit_card\":{\"gateway\":\"mercadopago\",\"enabled\":true},\"ticket\":{\"gateway\":\"mercadopago\",\"enabled\":true}}'),
(2, NULL, NULL, NULL, 1, '2025-12-27 20:14:54', NULL);

-- Dados seed: plugins
INSERT INTO `plugins` (`id`, `nome`, `pasta`, `versao`, `ativo`, `instalado_em`, `atualizado_em`) VALUES
(4, 'Modo SaaS', 'saas', '1.0.0', 0, '2025-12-28 10:05:12', '2025-12-28 10:05:12');

-- Dados seed: usuarios
INSERT INTO `usuarios` (`id`, `usuario`, `nome`, `telefone`, `senha`, `tipo`, `data_cadastro`, `mp_public_key`, `mp_access_token`, `foto_perfil`, `ultima_visualizacao_notificacoes`, `pushinpay_token`, `evolution_name`, `evolution_server_url`, `evolution_api_key`, `evolution_instance`, `efi_client_id`, `efi_client_secret`, `efi_certificate_path`, `efi_pix_key`, `efi_payee_code`, `beehive_secret_key`, `beehive_public_key`, `hypercash_secret_key`, `hypercash_public_key`, `pagarme_api_key`, `pagarme_api_secret`, `pagarme_webhook_secret`, `paypal_client_id`, `paypal_client_secret`, `paypal_webhook_secret`, `stripe_publishable_key`, `stripe_secret_key`, `stripe_webhook_secret`) VALUES
(1, 'admin@example.com', 'Administrador', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW(), NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);


-- Trigger after_produto_insert: não incluso (evita erro #1419 em ambientes com binary logging).
-- A aplicação insere em products_feed_items ao criar produto e usa fallback na listagem.

-- Índices, AUTO_INCREMENT e FKs
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `alunos_acessos`
--
ALTER TABLE `alunos_acessos`
  ADD UNIQUE KEY `aluno_produto_unico` (`aluno_email`,`produto_id`),
  ADD KEY `idx_produto_id` (`produto_id`),
  ADD KEY `idx_aluno_email` (`aluno_email`),
  ADD KEY `idx_data_expiracao` (`data_expiracao`),
  ADD KEY `idx_criado_manualmente` (`criado_manualmente`);

--
-- Índices de tabela `aluno_progresso`
--
ALTER TABLE `aluno_progresso`
  ADD UNIQUE KEY `aluno_aula_unico` (`aluno_email`,`aula_id`),
  ADD KEY `idx_aula_id` (`aula_id`);

--
-- Índices de tabela `aulas`
--
ALTER TABLE `aulas`
  ADD KEY `idx_modulo_id` (`modulo_id`),
  ADD KEY `idx_community_id` (`community_id`);

--
-- Índices de tabela `aula_arquivos`
--
ALTER TABLE `aula_arquivos`
  ADD KEY `fk_aula_arquivos_aula` (`aula_id`);

--
-- Índices de tabela `banners`
--
ALTER TABLE `banners`
  ADD KEY `idx_usuario_community` (`usuario_id`,`community_id`),
  ADD KEY `idx_active_show` (`is_active`,`show_in_member_dashboard`,`show_in_offers_section`),
  ADD KEY `idx_banners_badge_id` (`badge_id`);

--
-- Índices de tabela `banner_badges`
--
ALTER TABLE `banner_badges`
  ADD UNIQUE KEY `idx_slug` (`slug`);

--
-- Índices de tabela `cloned_sites`
--
ALTER TABLE `cloned_sites`
  ADD KEY `fk_cloned_sites_usuario` (`usuario_id`);

--
-- Índices de tabela `cloned_site_settings`
--
ALTER TABLE `cloned_site_settings`
  ADD UNIQUE KEY `idx_cloned_site_settings_unique` (`cloned_site_id`),
  ADD KEY `fk_cloned_site_settings_site` (`cloned_site_id`);

--
-- Índices de tabela `communities`
--
ALTER TABLE `communities`
  ADD UNIQUE KEY `idx_slug` (`slug`);

--
-- Índices de tabela `configuracoes`
--
ALTER TABLE `configuracoes`
  ADD PRIMARY KEY (`chave`);

--
-- Índices de tabela `configuracoes_sistema`
--
ALTER TABLE `configuracoes_sistema`
  ADD UNIQUE KEY `chave` (`chave`);

--
-- Índices de tabela `cursos`
--
ALTER TABLE `cursos`
  ADD KEY `idx_produto_id_cursos` (`produto_id`),
  ADD KEY `idx_community_id` (`community_id`);

--
-- Índices de tabela `evolution_messages`
--
ALTER TABLE `evolution_messages`
  ADD KEY `idx_usuario_id` (`usuario_id`),
  ADD KEY `idx_produto_id` (`produto_id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Índices de tabela `gatewaypro_tracking_events`
--
ALTER TABLE `gatewaypro_tracking_events`
  ADD KEY `fk_tracking_events_product` (`tracking_product_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `gatewaypro_tracking_products`
--
ALTER TABLE `gatewaypro_tracking_products`
  ADD UNIQUE KEY `idx_unique_tracking_id` (`tracking_id`),
  ADD UNIQUE KEY `idx_unique_usuario_produto_rastreado` (`usuario_id`,`produto_id`),
  ADD KEY `fk_tracking_products_usuario` (`usuario_id`),
  ADD KEY `fk_tracking_products_produto` (`produto_id`);

--
-- Índices de tabela `licencas_geradas`
--
ALTER TABLE `licencas_geradas`
  ADD UNIQUE KEY `chave_licenca` (`chave_licenca`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_owner` (`owner_user_id`),
  ADD KEY `idx_assigned` (`assigned_user_id`),
  ADD KEY `idx_escopo` (`escopo`);

--
-- Índices de tabela `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_blocked_until` (`blocked_until`),
  ADD KEY `idx_last_attempt` (`last_attempt`),
  ADD KEY `idx_ip_email` (`ip_address`,`email`);

--
-- Índices de tabela `modulos`
--
ALTER TABLE `modulos`
  ADD KEY `idx_curso_id` (`curso_id`),
  ADD KEY `idx_community_id` (`community_id`);

--
-- Índices de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD KEY `idx_usuario_id_notificacoes` (`usuario_id`),
  ADD KEY `idx_lida_data_notificacao` (`lida`,`data_notificacao`),
  ADD KEY `fk_notificacoes_venda` (`venda_id_fk`);

--
-- Índices de tabela `order_bumps`
--
ALTER TABLE `order_bumps`
  ADD KEY `idx_main_product_id` (`main_product_id`),
  ADD KEY `fk_order_bumps_offer_product` (`offer_product_id`);

--
-- Índices de tabela `plugins`
--
ALTER TABLE `plugins`
  ADD UNIQUE KEY `nome` (`nome`),
  ADD UNIQUE KEY `pasta` (`pasta`),
  ADD KEY `idx_ativo` (`ativo`),
  ADD KEY `idx_pasta` (`pasta`);

--
-- Índices de tabela `products_feed_items`
--
ALTER TABLE `products_feed_items`
  ADD UNIQUE KEY `unique_item` (`usuario_id`,`community_id`,`item_type`,`item_id`),
  ADD KEY `idx_order` (`usuario_id`,`community_id`,`sort_order`);

--
-- Índices de tabela `product_exclusive_offers`
--
ALTER TABLE `product_exclusive_offers`
  ADD UNIQUE KEY `idx_unique_product_offer` (`source_product_id`,`offer_product_id`),
  ADD KEY `fk_offer_source_product` (`source_product_id`),
  ADD KEY `fk_offer_target_product` (`offer_product_id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD KEY `idx_usuario_id` (`usuario_id`),
  ADD KEY `idx_community_id` (`community_id`);

--
-- Índices de tabela `produto_ofertas`
--
ALTER TABLE `produto_ofertas`
  ADD UNIQUE KEY `idx_hash` (`hash`),
  ADD KEY `idx_produto_id` (`produto_id`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `saas_assinaturas`
--
ALTER TABLE `saas_assinaturas`
  ADD KEY `plano_id` (`plano_id`),
  ADD KEY `idx_usuario_id` (`usuario_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_data_vencimento` (`data_vencimento`);

--
-- Índices de tabela `saas_limites_uso`
--
ALTER TABLE `saas_limites_uso`
  ADD UNIQUE KEY `unique_usuario_mes` (`usuario_id`,`mes_ano`),
  ADD KEY `idx_usuario_mes` (`usuario_id`,`mes_ano`);

--
-- Índices de tabela `saas_planos`
--
ALTER TABLE `saas_planos`
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `security_events`
--
ALTER TABLE `security_events`
  ADD KEY `idx_community` (`community_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Índices de tabela `security_logs`
--
ALTER TABLE `security_logs`
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Índices de tabela `utmfy_integrations`
--
ALTER TABLE `utmfy_integrations`
  ADD KEY `fk_utmfy_integrations_usuario` (`usuario_id`),
  ADD KEY `fk_utmfy_integrations_produto` (`product_id`);

--
-- Índices de tabela `vendas`
--
ALTER TABLE `vendas`
  ADD KEY `idx_produto_id_vendas` (`produto_id`),
  ADD KEY `idx_checkout_session_uuid` (`checkout_session_uuid`),
  ADD KEY `idx_vendas_oferta_id` (`oferta_id`),
  ADD KEY `idx_community_id` (`community_id`);

--
-- Índices de tabela `webhooks`
--
ALTER TABLE `webhooks`
  ADD KEY `fk_webhooks_usuario` (`usuario_id`),
  ADD KEY `fk_webhooks_produto` (`produto_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `alunos_acessos`
--
ALTER TABLE `alunos_acessos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de tabela `aluno_progresso`
--
ALTER TABLE `aluno_progresso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT de tabela `aulas`
--
ALTER TABLE `aulas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT de tabela `aula_arquivos`
--
ALTER TABLE `aula_arquivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de tabela `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `banner_badges`
--
ALTER TABLE `banner_badges`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de tabela `cloned_sites`
--
ALTER TABLE `cloned_sites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT de tabela `cloned_site_settings`
--
ALTER TABLE `cloned_site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT de tabela `communities`
--
ALTER TABLE `communities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `configuracoes_sistema`
--
ALTER TABLE `configuracoes_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT de tabela `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de tabela `evolution_messages`
--
ALTER TABLE `evolution_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `gatewaypro_tracking_events`
--
ALTER TABLE `gatewaypro_tracking_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `gatewaypro_tracking_products`
--
ALTER TABLE `gatewaypro_tracking_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `licencas_geradas`
--
ALTER TABLE `licencas_geradas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `modulos`
--
ALTER TABLE `modulos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=245;

--
-- AUTO_INCREMENT de tabela `order_bumps`
--
ALTER TABLE `order_bumps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT de tabela `plugins`
--
ALTER TABLE `plugins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `products_feed_items`
--
ALTER TABLE `products_feed_items`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `product_exclusive_offers`
--
ALTER TABLE `product_exclusive_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT de tabela `produto_ofertas`
--
ALTER TABLE `produto_ofertas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `saas_assinaturas`
--
ALTER TABLE `saas_assinaturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `saas_config_admin`
--
ALTER TABLE `saas_config_admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `saas_limites_uso`
--
ALTER TABLE `saas_limites_uso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `saas_planos`
--
ALTER TABLE `saas_planos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `security_events`
--
ALTER TABLE `security_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de tabela `utmfy_integrations`
--
ALTER TABLE `utmfy_integrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `vendas`
--
ALTER TABLE `vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=244;

--
-- AUTO_INCREMENT de tabela `webhooks`
--
ALTER TABLE `webhooks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `alunos_acessos`
--
ALTER TABLE `alunos_acessos`
  ADD CONSTRAINT `fk_alunos_acessos_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `aluno_progresso`
--
ALTER TABLE `aluno_progresso`
  ADD CONSTRAINT `fk_aluno_progresso_aula` FOREIGN KEY (`aula_id`) REFERENCES `aulas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `aulas`
--
ALTER TABLE `aulas`
  ADD CONSTRAINT `fk_aulas_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `aula_arquivos`
--
ALTER TABLE `aula_arquivos`
  ADD CONSTRAINT `fk_aula_arquivos_aula` FOREIGN KEY (`aula_id`) REFERENCES `aulas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `cloned_sites`
--
ALTER TABLE `cloned_sites`
  ADD CONSTRAINT `fk_cloned_sites_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `cloned_site_settings`
--
ALTER TABLE `cloned_site_settings`
  ADD CONSTRAINT `fk_cloned_site_settings_site` FOREIGN KEY (`cloned_site_id`) REFERENCES `cloned_sites` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `cursos`
--
ALTER TABLE `cursos`
  ADD CONSTRAINT `fk_cursos_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `gatewaypro_tracking_events`
--
ALTER TABLE `gatewaypro_tracking_events`
  ADD CONSTRAINT `fk_tracking_events_product` FOREIGN KEY (`tracking_product_id`) REFERENCES `gatewaypro_tracking_products` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `gatewaypro_tracking_products`
--
ALTER TABLE `gatewaypro_tracking_products`
  ADD CONSTRAINT `fk_tracking_products_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tracking_products_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `modulos`
--
ALTER TABLE `modulos`
  ADD CONSTRAINT `fk_modulos_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD CONSTRAINT `fk_notificacoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notificacoes_venda` FOREIGN KEY (`venda_id_fk`) REFERENCES `vendas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `order_bumps`
--
ALTER TABLE `order_bumps`
  ADD CONSTRAINT `fk_order_bumps_main_product` FOREIGN KEY (`main_product_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_bumps_offer_product` FOREIGN KEY (`offer_product_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `product_exclusive_offers`
--
ALTER TABLE `product_exclusive_offers`
  ADD CONSTRAINT `fk_offer_source_product` FOREIGN KEY (`source_product_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_offer_target_product` FOREIGN KEY (`offer_product_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `fk_produtos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `produto_ofertas`
--
ALTER TABLE `produto_ofertas`
  ADD CONSTRAINT `fk_produto_ofertas_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `saas_assinaturas`
--
ALTER TABLE `saas_assinaturas`
  ADD CONSTRAINT `saas_assinaturas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `saas_assinaturas_ibfk_2` FOREIGN KEY (`plano_id`) REFERENCES `saas_planos` (`id`);

--
-- Restrições para tabelas `saas_limites_uso`
--
ALTER TABLE `saas_limites_uso`
  ADD CONSTRAINT `saas_limites_uso_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `utmfy_integrations`
--
ALTER TABLE `utmfy_integrations`
  ADD CONSTRAINT `fk_utmfy_integrations_produto` FOREIGN KEY (`product_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_utmfy_integrations_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `vendas`
--
ALTER TABLE `vendas`
  ADD CONSTRAINT `fk_vendas_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `webhooks`
--
ALTER TABLE `webhooks`
  ADD CONSTRAINT `fk_webhooks_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_webhooks_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
