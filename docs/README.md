# Documentação — SinergIA Core

Índice da documentação do sistema (checkout, área de membros, funil de vendas, infoprodutos).

---

## Estrutura

```
docs/
├── README.md              ← Este arquivo
├── funil/                 Funil de vendas (Upsell/Downsell)
├── deploy/                Instalação e implantação
├── licencas/              Sistema de licenças
├── area-membros/          Área de membros e proteção
├── fontes-video/          Fontes de vídeo (YouTube, Vimeo, etc.)
├── relatorios/            Relatórios e diagnósticos
└── geral/                 Funcionalidades, banners, multi-comunidade
```

---

## 1. Instalação e deploy

| Documento | Descrição |
|-----------|-----------|
| [../INSTALL.md](../INSTALL.md) | Guia de instalação: requisitos, passo a passo, .env, checklist. |
| [deploy/DEPLOY_VPS.md](deploy/DEPLOY_VPS.md) | Implantação em VPS: Ubuntu/Debian, Nginx ou Apache, PHP-FPM, MariaDB, SSL. |
| [deploy/DEPLOY_HOSTINGER.md](deploy/DEPLOY_HOSTINGER.md) | Deploy na Hostinger: diferenças de ambiente, permissões, PHP, .env. |
| [deploy/DOC_INSTALACAO_E_ESTRUTURA.md](deploy/DOC_INSTALACAO_E_ESTRUTURA.md) | Estrutura do projeto, funcionalidades resumidas, referência .env. |

---

## 2. Funil de vendas (Upsell/Downsell)

| Documento | Descrição |
|-----------|-----------|
| [funil/FUNIL_UX_DEV_E_SEGURANCA.md](funil/FUNIL_UX_DEV_E_SEGURANCA.md) | UX, modo DEV, simulador, segurança (uso do funil). |
| [funil/FUNIL_ETAPAS_0_1_2_RELATORIO_PRODUCAO.md](funil/FUNIL_ETAPAS_0_1_2_RELATORIO_PRODUCAO.md) | Relatório produção: Etapas 0–6, migration funnel_events, checklist. |
| [funil/FUNIL_VENDAS_REPLICAR_PRODUCAO.md](funil/FUNIL_VENDAS_REPLICAR_PRODUCAO.md) | Lista completa de arquivos e alterações para replicar o funil em produção. |

---

## 3. Licenças

| Documento | Descrição |
|-----------|-----------|
| [licencas/README_LICENCAS.md](licencas/README_LICENCAS.md) | Sistema de licenças: tipos, escopos, migração. |
| [licencas/README_MINHAS_LICENCAS.md](licencas/README_MINHAS_LICENCAS.md) | Fluxo "Minhas Licenças" (revendedor), requisitos (painel master). |
| [licencas/DOC_LICENCAS_ANALISE.md](licencas/DOC_LICENCAS_ANALISE.md) | Análise do sistema de licenças atual. |
| [licencas/DOC_LICENCAS_PROPOSTA.md](licencas/DOC_LICENCAS_PROPOSTA.md) | Proposta de evolução (escopos, owner/assigned). |

---

## 4. Área de membros

| Documento | Descrição |
|-----------|-----------|
| [area-membros/MEMBER_AREA_PROTECTION.md](area-membros/MEMBER_AREA_PROTECTION.md) | Proteção: watermark, anti-print, anti-devtools, logs. |
| [area-membros/PLANO_MELHORIAS_AREA_MEMBROS.md](area-membros/PLANO_MELHORIAS_AREA_MEMBROS.md) | Plano de melhorias na área de membros. |

---

## 5. Fontes de vídeo

| Documento | Descrição |
|-----------|-----------|
| [fontes-video/README_FONTES_VIDEO_FASE1.md](fontes-video/README_FONTES_VIDEO_FASE1.md) | Fontes de vídeo (YouTube, Vimeo, self-hosted): campos, validações, player. |
| [fontes-video/CHECKLIST_TESTES_FONTES_VIDEO.md](fontes-video/CHECKLIST_TESTES_FONTES_VIDEO.md) | Checklist de testes manuais. |
| [fontes-video/RELATORIO_ANALISE_NOVAS_FONTES_VIDEO.md](fontes-video/RELATORIO_ANALISE_NOVAS_FONTES_VIDEO.md) | Relatório técnico: análise de impacto. |

---

## 6. Geral e funcionalidades

| Documento | Descrição |
|-----------|-----------|
| [geral/README_FUNCIONALIDADES.md](geral/README_FUNCIONALIDADES.md) | Módulos (Admin, Infoprodutor, Cliente), fluxos, ofertas, checkout, banners, licenças. |
| [geral/README_visual.md](geral/README_visual.md) | Configurações visuais (white-label): theme_json, CSS variables. |
| [geral/IMPLEMENTACAO_BANNERS.md](geral/IMPLEMENTACAO_BANNERS.md) | Implementação do sistema de banners (banco, APIs, drag-drop). |
| [geral/MULTI_COMUNIDADE.md](geral/MULTI_COMUNIDADE.md) | Multi-tenant (communities), slugs, subdomínios. |
| [geral/SESSION_TIMEOUT_E_SESSAO_UNICA.md](geral/SESSION_TIMEOUT_E_SESSAO_UNICA.md) | Timeout por inatividade, sessão única, ordem de validação, configuração, API 401. |

---

## 7. Relatórios e diagnósticos

| Documento | Descrição |
|-----------|-----------|
| [relatorios/RELATORIO_REBRANDING_ETAPA1_LEVANTAMENTO.md](relatorios/RELATORIO_REBRANDING_ETAPA1_LEVANTAMENTO.md) | Levantamento rebranding etapa 1. |
| [relatorios/DIAGNOSTICO_AMBIENTE_NAO_DOCKER.md](relatorios/DIAGNOSTICO_AMBIENTE_NAO_DOCKER.md) | Diagnóstico: ambiente fora do Docker (config, funções globais). |
| [relatorios/CORRIGIR_ABA_GERAL_PRODUCAO.md](relatorios/CORRIGIR_ABA_GERAL_PRODUCAO.md) | Correções na aba Geral para produção. |

---

## Arquivos SQL e migrations

- **Base_de_Dados_Instalacao.sql** (raiz): carga inicial para 1ª instalação.
- **migrations/**: scripts incrementais; ver [../migrations/README.md](../migrations/README.md).
