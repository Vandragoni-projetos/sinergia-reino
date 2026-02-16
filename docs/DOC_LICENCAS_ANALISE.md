# Análise do Sistema de Licenças Atual

## 1. Fluxo Atual

### 1.1 Arquitetura
- **Master Panel**: Painel administrativo que é o "servidor de licenças" (GATEWAYPRO_MASTER_SECRET + is_master_panel)
- **Client Panels**: Instalações GatewayPro que precisam de licença para funcionar
- **Validação**: Painéis clientes validam via webhook externo (n8n) ou API local

### 1.2 Armazenamento

#### configuracoes_sistema (painel cliente)
| Chave | Descrição |
|-------|-----------|
| license_key | Chave ativada nesta instalação |
| license_status | active, invalid, expired |
| license_expiration | Data ou 'lifetime' |
| license_activated_at | Data da ativação |
| license_last_check | Última verificação |
| license_type | VITALICIO, ANUAL, etc. |
| license_days | Dias de validade (se aplicável) |

#### licencas_geradas (painel master - inferido do código)
| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | int | PK |
| chave_licenca | varchar | Chave única (ex: GATEWAYPRO-VITALICIO-XXXX-YYYY) |
| tipo_licenca | varchar | VITALICIO, ANUAL, SEMESTRAL, MENSAL |
| dias_validade | int | null=vitalício, 365=anual, etc. |
| aluno_email | varchar | Quem gerou (cliente com acesso ao produto) |
| aluno_nome | varchar | Nome do gerador |
| produto_id | int | Produto que concede o direito de gerar |
| status | enum | disponivel, ativada, expirada, revogada |
| data_geracao | timestamp | Quando foi gerada |
| data_ativacao | timestamp | Quando foi ativada (primeira vez) |
| data_expiracao | date | null=vitalício |
| instalacao_id | varchar | system_id da instalação que ativou |
| ip_ativacao | varchar | IP na ativação |

### 1.3 Fluxo de Geração (Master)
1. Aluno (tipo 'usuario') compra produto com `gera_licenca=1`
2. Acessa member_licenses.php (Minhas Licenças)
3. get_license_info: Busca alunos_acessos + produto_ofertas para produtos com gera_licenca
4. generate_license: Insere em licencas_geradas com status 'disponivel'

### 1.4 Fluxo de Ativação (Cliente)
1. Usuário acessa ativacao.php
2. Cola chave e submete
3. activateLicense() → validateLicenseKey() → curl ao webhook n8n
4. Webhook provavelmente chama license_api.php no master
5. license_api: Busca licencas_geradas, valida, atualiza status 'ativada'
6. Cliente salva em configuracoes_sistema (license_key, license_status, etc.)

### 1.5 Validação no Login
1. login.php / member_login.php: checkLicenseOnLogin()
2. Valida chave via webhook (ou checkLicenseLocal se offline)
3. Se inválida: redireciona para /ativacao

### 1.6 Limitações Atuais
- Sem conceito de escopo (SYSTEM, PRODUCT, etc.)
- Sem owner_user_id / assigned_user_id explícitos
- Sem status 'bloqueada'
- Infoprodutor não pode gerar licenças (apenas aluno no master)
- Uma licença = uma ativação (instalacao_id fixo)
