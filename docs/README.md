# Documentação do Projeto — Índice

Documentação do sistema GatewayPro/SinergIA (checkout próprio, área de membros, infoprodutos). Leia na ordem abaixo para instalação e manutenção.

---

## 1. Instalação e implantação

| Ordem | Documento | Descrição |
|-------|-----------|-----------|
| 1 | **[../INSTALL.md](../INSTALL.md)** | Guia de instalação: requisitos, passo a passo Hostinger, .env, checklist. Ativação e bypass opcional (primeiro acesso sem chave; aula explicativa). |
| 2 | **[DEPLOY_VPS.md](DEPLOY_VPS.md)** | Implantação em VPS: Ubuntu/Debian, Nginx ou Apache, PHP-FPM, MariaDB, SSL, permissões. |
| 3 | **[DEPLOY_HOSTINGER.md](DEPLOY_HOSTINGER.md)** | Deploy na Hostinger com código vindo de VPS: diferenças de ambiente, permissões, PHP, .env, checklist. |
| 4 | **[DOC_INSTALACAO_E_ESTRUTURA.md](DOC_INSTALACAO_E_ESTRUTURA.md)** | Estrutura do projeto, funcionalidades resumidas, pontos de atenção, referência .env. |

---

## 2. Funcionalidades e fluxos

| Ordem | Documento | Descrição |
|-------|-----------|-----------|
| 5 | **[README_FUNCIONALIDADES.md](README_FUNCIONALIDADES.md)** | Módulos (Admin, Infoprodutor, Cliente), fluxo produto→curso→módulos→aulas, ofertas/checkout/gateway, banners/feed drag-drop, licenças, duplicidades. |

---

## 3. Funcionalidades específicas

| Ordem | Documento | Descrição |
|-------|-----------|-----------|
| 6 | **[README_LICENCAS.md](README_LICENCAS.md)** | Sistema de licenças: tipos, escopos, migração. |
| 7 | **[README_MINHAS_LICENCAS.md](README_MINHAS_LICENCAS.md)** | Fluxo "Minhas Licenças" (revendedor) e requisitos (painel master, .env). |
| 8 | **[DOC_LICENCAS_ANALISE.md](DOC_LICENCAS_ANALISE.md)** | Análise do sistema de licenças atual. |
| 9 | **[DOC_LICENCAS_PROPOSTA.md](DOC_LICENCAS_PROPOSTA.md)** | Proposta de evolução (escopos, owner/assigned). |
| 10 | **[MEMBER_AREA_PROTECTION.md](MEMBER_AREA_PROTECTION.md)** | Proteção da área de membros: watermark, anti-print, anti-devtools, logs. |
| 11 | **[README_FONTES_VIDEO_FASE1.md](README_FONTES_VIDEO_FASE1.md)** | Fontes de vídeo (YouTube, Vimeo, self-hosted): campos, validações, player. |
| 12 | **[CHECKLIST_TESTES_FONTES_VIDEO.md](CHECKLIST_TESTES_FONTES_VIDEO.md)** | Checklist de testes manuais — fontes de vídeo. |
| 13 | **[README_visual.md](README_visual.md)** | Configurações visuais (white-label): theme_json, CSS variables. |

---

## 4. Relatórios, planos e diagnósticos

| Ordem | Documento | Descrição |
|-------|-----------|-----------|
| 14 | **[RELATORIO_ANALISE_NOVAS_FONTES_VIDEO.md](RELATORIO_ANALISE_NOVAS_FONTES_VIDEO.md)** | Relatório técnico: análise de impacto — novas fontes de vídeo. |
| 15 | **[RELATORIO_REBRANDING_ETAPA1_LEVANTAMENTO.md](RELATORIO_REBRANDING_ETAPA1_LEVANTAMENTO.md)** | Levantamento rebranding etapa 1. |
| 16 | **[DIAGNOSTICO_AMBIENTE_NAO_DOCKER.md](DIAGNOSTICO_AMBIENTE_NAO_DOCKER.md)** | Diagnóstico: ambiente fora do Docker (config, funções globais). |
| 17 | **[PLANO_MELHORIAS_AREA_MEMBROS.md](PLANO_MELHORIAS_AREA_MEMBROS.md)** | Plano de melhorias na área de membros. |
| 18 | **[IMPLEMENTACAO_BANNERS.md](IMPLEMENTACAO_BANNERS.md)** | Implementação do sistema de banners (banco, APIs, drag-drop). |

---

## Arquivos SQL de referência

- **Base_de_Dados_Instalacao.sql** (raiz): carga inicial para 1ª instalação (schema + seed mínimo).
- **Base_de_Dados_Limpa_Tabelas_Operacionais.sql** (raiz): opcional — limpar tabelas operacionais.
- **migrations/**: scripts incrementais para evolução do banco; ver [../migrations/README.md](../migrations/README.md).
