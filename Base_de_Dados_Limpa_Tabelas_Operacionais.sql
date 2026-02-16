-- =============================================================================
-- Base_de_Dados_Limpa_Tabelas_Operacionais.sql
-- Limpa um banco JÁ EXISTENTE de dados operacionais/teste (opcional).
-- Use quando quiser zerar vendas, produtos, acessos, etc., mantendo estrutura
-- e dados essenciais (communities, configuracoes, admin, etc.).
-- =============================================================================
-- ATENÇÃO: Faça BACKUP do banco antes de executar.
-- O que será LIMPO: alunos_acessos, aluno_progresso, aula_arquivos, aulas,
--   modulos, cursos, vendas, notificacoes, products_feed_items, order_bumps,
--   product_exclusive_offers, produto_ofertas, produtos, banners,
--   gatewaypro_tracking_events, gatewaypro_tracking_products, licencas_geradas,
--   evolution_messages, utmfy_integrations, webhooks, security_events,
--   security_logs, login_attempts, saas_assinaturas, saas_limites_uso,
--   cloned_site_settings, cloned_sites.
-- Opcional: remove usuários que não são admin (id=1).
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Tabelas operacionais (ordem: filhas antes de pais, por causa de FKs ao reativar)
TRUNCATE TABLE `aluno_progresso`;
TRUNCATE TABLE `alunos_acessos`;
TRUNCATE TABLE `aula_arquivos`;
TRUNCATE TABLE `aulas`;
TRUNCATE TABLE `modulos`;
TRUNCATE TABLE `cursos`;
TRUNCATE TABLE `notificacoes`;
TRUNCATE TABLE `vendas`;
TRUNCATE TABLE `products_feed_items`;
TRUNCATE TABLE `order_bumps`;
TRUNCATE TABLE `product_exclusive_offers`;
TRUNCATE TABLE `produto_ofertas`;
TRUNCATE TABLE `gatewaypro_tracking_events`;
TRUNCATE TABLE `gatewaypro_tracking_products`;
TRUNCATE TABLE `produtos`;
TRUNCATE TABLE `banners`;
TRUNCATE TABLE `licencas_geradas`;
TRUNCATE TABLE `evolution_messages`;
TRUNCATE TABLE `utmfy_integrations`;
TRUNCATE TABLE `webhooks`;
TRUNCATE TABLE `security_events`;
TRUNCATE TABLE `security_logs`;
TRUNCATE TABLE `login_attempts`;
TRUNCATE TABLE `saas_assinaturas`;
TRUNCATE TABLE `saas_limites_uso`;
TRUNCATE TABLE `cloned_site_settings`;
TRUNCATE TABLE `cloned_sites`;

SET FOREIGN_KEY_CHECKS = 1;

-- Opcional: manter apenas o usuário admin (id=1). Descomente se quiser remover infoprodutores e clientes.
-- DELETE FROM `usuarios` WHERE `id` != 1;
-- ALTER TABLE `usuarios` AUTO_INCREMENT = 2;
