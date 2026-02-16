#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Gera Base_de_Dados_Instalacao.sql a partir de Banco_de_Dados.sql:
- Mantém todas as estruturas (CREATE TABLE, índices, FKs, trigger).
- Mantém apenas dados seed: communities, banner_badges, configuracoes,
  configuracoes_sistema (sanitizado), saas_planos, saas_config_admin (1 linha),
  plugins, usuarios (1 admin).
- Tabelas operacionais ficam vazias (sem INSERT).
"""

import re
import os

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DUMP_PATH = os.path.join(BASE_DIR, "Banco_de_Dados.sql")
OUT_PATH = os.path.join(BASE_DIR, "Base_de_Dados_Instalacao.sql")

# Tabelas que NÃO recebem INSERT (dados operacionais/teste)
TABLES_NO_INSERT = {
    "alunos_acessos", "aluno_progresso", "aulas", "aula_arquivos", "banners",
    "cloned_sites", "cloned_site_settings", "cursos", "evolution_messages",
    "gatewaypro_tracking_events", "gatewaypro_tracking_products", "licencas_geradas",
    "login_attempts", "modulos", "notificacoes", "order_bumps", "products_feed_items",
    "product_exclusive_offers", "produtos", "produto_ofertas", "saas_assinaturas",
    "saas_limites_uso", "security_events", "security_logs", "utmfy_integrations",
    "vendas", "webhooks",
}

# configuracoes_sistema: chaves com valor sanitizado para instalação (vazios/placeholders)
CONFIG_SISTEMA_SANITIZED = """INSERT INTO `configuracoes_sistema` (`id`, `community_id`, `chave`, `valor`, `tipo`, `descricao`, `created_at`, `updated_at`) VALUES
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
(27, NULL, 'theme_json', '{\\"primary\\":\\"#32e768\\",\\"primaryHover\\":\\"#2dd05e\\",\\"bg\\":\\"#080e16\\",\\"text\\":\\"rgba(255,255,255,0.9)\\",\\"textMuted\\":\\"rgba(255,255,255,0.5)\\",\\"card\\":\\"#1f3147\\",\\"cardElevated\\":\\"#0f1419\\",\\"border\\":\\"rgba(255,255,255,0.1)\\",\\"radius\\":\\"1.5rem\\",\\"shadow\\":\\"0 4px 6px -1px rgba(0,0,0,0.3)\\",\\"fontSans\\":\\"Montserrat,sans-serif\\"}', 'json', 'Configurações visuais white-label', NOW(), NOW()),
(28, NULL, 'is_master_panel', '0', 'text', NULL, NOW(), NOW()),
(29, NULL, 'master_secret_key', '', 'text', NULL, NOW(), NOW()),
(30, NULL, 'license_api_token', '', 'text', NULL, NOW(), NOW()),
(31, NULL, 'notification_image_url', '', 'text', NULL, NOW(), NOW()),
(32, NULL, 'PROTECT_MEMBER_AREA', 'true', 'boolean', 'Proteção área de membros', NOW(), NOW()),
(33, NULL, 'PROTECT_MEMBER_AREA_BY_COMMUNITY', '{}', 'json', 'Override por community_id', NOW(), NOW());
"""

# Admin único: id=1. Senha padrão: 'password' (TROCAR NO 1º ACESSO)
ADMIN_INSERT = """INSERT INTO `usuarios` (`id`, `usuario`, `nome`, `telefone`, `senha`, `tipo`, `data_cadastro`, `mp_public_key`, `mp_access_token`, `foto_perfil`, `ultima_visualizacao_notificacoes`, `pushinpay_token`, `evolution_name`, `evolution_server_url`, `evolution_api_key`, `evolution_instance`, `efi_client_id`, `efi_client_secret`, `efi_certificate_path`, `efi_pix_key`, `efi_payee_code`, `beehive_secret_key`, `beehive_public_key`, `hypercash_secret_key`, `hypercash_public_key`, `pagarme_api_key`, `pagarme_api_secret`, `pagarme_webhook_secret`, `paypal_client_id`, `paypal_client_secret`, `paypal_webhook_secret`, `stripe_publishable_key`, `stripe_secret_key`, `stripe_webhook_secret`) VALUES
(1, 'admin@example.com', 'Administrador', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW(), NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
"""


def extract_table_name(line):
    m = re.match(r"CREATE TABLE `(\w+)`", line)
    return m.group(1) if m else None


def main():
    with open(DUMP_PATH, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()

    # 2) Coletar blocos: CREATE TABLE + eventual INSERT
    blocks = []
    # Dividir pelo início de cada "CREATE TABLE `nome`"
    parts = re.split(r"\n(?=CREATE TABLE `)", content)
    for part in parts:
        if "CREATE TABLE `" not in part:
            continue
        table_name = extract_table_name(part)
        if not table_name:
            continue
        # CREATE TABLE ... ; (primeira instrução completa)
        # Encontrar fim do CREATE: ); seguido de ENGINE=InnoDB ... ;
        end_cre = part.rfind("ENGINE=InnoDB")
        if end_cre == -1:
            continue
        semi = part.find(";", end_cre)
        if semi == -1:
            continue
        create_sql = part[: semi + 1].strip()
        if not create_sql.startswith("CREATE TABLE"):
            continue
        blocks.append(("create", table_name, create_sql))

    # Ordenar creates na ordem do dump (alunos_acessos, aluno_progresso, ...)
    order_tables = [
        "alunos_acessos", "aluno_progresso", "aulas", "aula_arquivos", "banners", "banner_badges",
        "cloned_sites", "cloned_site_settings", "communities", "configuracoes", "configuracoes_sistema",
        "cursos", "evolution_messages", "gatewaypro_tracking_events", "gatewaypro_tracking_products",
        "licencas_geradas", "login_attempts", "modulos", "notificacoes", "order_bumps",
        "products_feed_items", "product_exclusive_offers", "produtos", "produto_ofertas",
        "saas_assinaturas", "saas_config_admin", "saas_limites_uso", "saas_planos",
        "security_events", "security_logs", "plugins", "usuarios", "utmfy_integrations",
        "vendas", "webhooks",
    ]
    seen = {b[1] for b in blocks if b[0] == "create"}
    ordered_creates = []
    for t in order_tables:
        if t in seen:
            ordered_creates.append(t)
    for b in blocks:
        if b[0] == "create" and b[1] not in ordered_creates:
            ordered_creates.append(b[1])
    create_by_name = {b[1]: b[2] for b in blocks if b[0] == "create"}
    blocks = [("create", t, create_by_name[t]) for t in ordered_creates]

    # Inserções: buscar INSERT para cada tabela de seed no dump
    def find_insert(content, table_name):
        start = content.find("INSERT INTO `" + table_name + "`")
        if start == -1:
            return None
        # Fim: próxima linha que seja ");" ou próximo "-- -----" / "INSERT INTO" / "CREATE TABLE"
        after = content[start:]
        # Para tabelas com valores longos (ex: configuracoes com HTML), ir até o último ");\n" antes do próximo INSERT/CREATE
        next_insert = after.find("\nINSERT INTO `", 20)
        next_create = after.find("\nCREATE TABLE `", 20)
        next_comment = after.find("\n-- -----", 20)
        end_mark = len(after)
        for pos in [next_insert, next_create, next_comment]:
            if pos != -1 and pos < end_mark:
                end_mark = pos
        chunk = after[:end_mark]
        last_paren = chunk.rfind(");")
        if last_paren != -1:
            return content[start : start + last_paren + 2]  # ");"
        return None

    for table_name in ["communities", "banner_badges", "configuracoes", "configuracoes_sistema",
                       "saas_planos", "saas_config_admin", "plugins", "usuarios"]:
        if table_name in TABLES_NO_INSERT:
            continue
        if table_name == "configuracoes_sistema":
            blocks.append(("insert", table_name, CONFIG_SISTEMA_SANITIZED))
        elif table_name == "usuarios":
            blocks.append(("insert", table_name, ADMIN_INSERT))
        else:
            insert_sql = find_insert(content, table_name)
            if insert_sql:
                blocks.append(("insert", table_name, insert_sql))

    # 3) Coletar trigger
    trigger_block = ""
    if "CREATE TRIGGER `after_produto_insert`" in content:
        start = content.find("DELIMITER $$\nCREATE TRIGGER `after_produto_insert`")
        if start != -1:
            end = content.find("DELIMITER ;", start) + len("DELIMITER ;")
            trigger_block = "\n" + content[start:end]

    # 4) Coletar ALTER TABLE (índices, AUTO_INCREMENT, FK)
    idx_alter = content.find("-- Índices para tabelas despejadas")
    if idx_alter == -1:
        idx_alter = content.find("ALTER TABLE `alunos_acessos`")
    end_alter = content.find("/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */")
    if end_alter == -1:
        end_alter = content.find("COMMIT;")
    alter_section = content[idx_alter:end_alter].strip() if idx_alter != -1 and end_alter != -1 else ""

    # 5) Ordem de DROP (tabelas com FK: filhas primeiro)
    all_tables = []
    for kind, name, _ in blocks:
        if kind == "create" and name not in all_tables:
            all_tables.append(name)
    drop_order = [
        "alunos_acessos", "aluno_progresso", "aula_arquivos", "aulas", "notificacoes", "vendas",
        "gatewaypro_tracking_events", "gatewaypro_tracking_products", "modulos", "cursos",
        "order_bumps", "product_exclusive_offers", "produto_ofertas", "products_feed_items",
        "cloned_site_settings", "cloned_sites", "saas_assinaturas", "saas_limites_uso",
        "evolution_messages", "utmfy_integrations", "webhooks", "produtos", "banners",
        "licencas_geradas", "security_events", "security_logs", "login_attempts",
        "configuracoes", "configuracoes_sistema", "communities", "banner_badges",
        "saas_config_admin", "saas_planos", "plugins", "usuarios",
    ]
    for t in all_tables:
        if t not in drop_order:
            drop_order.append(t)
    drop_sql = "DROP TABLE IF EXISTS `" + "`, `".join(drop_order) + "`;\n"

    # 6) Montar arquivo final
    out_lines = [
        "-- =============================================================================",
        "-- Base_de_Dados_Instalacao.sql - Carga inicial (seed) para 1ª instalação",
        "-- Gerado a partir de Banco_de_Dados.sql - Sem dados de teste/operacionais",
        "-- =============================================================================",
        "-- O que foi MANTIDO:",
        "--   - Todas as estruturas (CREATE TABLE, índices, FKs, trigger)",
        "--   - communities (4 slugs: club, mkd, flow, kids)",
        "--   - banner_badges (catálogo do dropdown)",
        "--   - configuracoes (SMTP, template e-mail, etc.)",
        "--   - configuracoes_sistema (chaves padrão com valores vazios/seguros)",
        "--   - saas_planos (Free + Premium)",
        "--   - saas_config_admin (1 linha placeholder)",
        "--   - plugins (Modo SaaS)",
        "--   - 1 usuário admin (admin@example.com / senha: 'password' - TROCAR NO 1º ACESSO)",
        "-- O que NÃO foi incluído: produtos, cursos, módulos, aulas, vendas, acessos,",
        "--   progresso, banners, feed, licenças, logs, webhooks, etc.",
        "-- =============================================================================",
        "",
        "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";",
        "START TRANSACTION;",
        "SET time_zone = \"+00:00\";",
        "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;",
        "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;",
        "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;",
        "/*!40101 SET NAMES utf8mb4 */;",
        "",
        "-- Desabilitar FKs para DROP",
        "SET FOREIGN_KEY_CHECKS = 0;",
        "DROP TRIGGER IF EXISTS `after_produto_insert`;",
        drop_sql,
        "SET FOREIGN_KEY_CHECKS = 1;",
        "",
    ]

    # Ordem: todos CREATE TABLE, depois todos INSERT, depois trigger (trigger depende de produtos)
    for kind, name, sql in blocks:
        if kind == "create":
            out_lines.append("-- --------------------------------------------------------")
            out_lines.append("-- Estrutura: " + name)
            out_lines.append("-- --------------------------------------------------------")
            out_lines.append(sql)
            out_lines.append("")

    for kind, name, sql in blocks:
        if kind == "insert":
            out_lines.append("-- Dados seed: " + name)
            out_lines.append(sql)
            out_lines.append("")

    if trigger_block:
        out_lines.append("-- Trigger: after_produto_insert (insere produto no feed)")
        out_lines.append(trigger_block)
        out_lines.append("")

    out_lines.append("-- Índices, AUTO_INCREMENT e FKs")
    out_lines.append(alter_section)
    out_lines.append("")
    out_lines.append("COMMIT;")
    out_lines.append("/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;")
    out_lines.append("/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;")
    out_lines.append("/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;")

    with open(OUT_PATH, "w", encoding="utf-8", newline="\n") as f:
        f.write("\n".join(out_lines))

    print("Gerado:", OUT_PATH)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
