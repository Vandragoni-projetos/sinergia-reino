# Corrigir aba Geral em produção (conteúdo trocado)

## O que aconteceu

Se ao clicar na aba **Geral** aparece o conteúdo de **Funil de Vendas** (canvas do funil, “Personalizar oferta Upsell/Downsell”), o arquivo da aba Geral foi sobrescrito com o conteúdo da aba Funil.

- **Correto:** Aba **Geral** = Informações Básicas, Nome, Descrição, Produto Grátis, Produto Vitrine, Capa, Entrega (como na imagem 3).
- **Errado:** Aba **Geral** mostrando “Funil de Vendas”, canvas e personalização Upsell/Downsell.

## Como conferir em produção

1. No servidor, abra o arquivo **`views/produto_config/aba_geral.php`**.
2. Veja o **início do conteúdo** (após o `<?php`):
   - **Certo:** primeiro título é **“Informações Básicas”**, depois “Nome do Produto”, “Descrição”, “Produto Grátis”, “Produto Vitrine”.
   - **Errado:** primeiro título é **“Funil de Vendas”** ou há “Personalizar oferta Upsell/Downsell”, canvas do funil, etc.

Se estiver errado, o conteúdo do Funil foi colado no arquivo da Geral.

## Como corrigir

1. **Substituir** o conteúdo de **`views/produto_config/aba_geral.php`** em produção pelo conteúdo do **`views/produto_config/aba_geral.php`** deste repositório (o que começa com “Informações Básicas”, Produto Grátis, Produto Vitrine, Capa, Configuração de Entrega).
2. **Manter** o conteúdo de **`views/produto_config/aba_funil.php`** com o Funil de Vendas e a personalização Upsell/Downsell (não colar isso na aba Geral).

## Resumo dos arquivos

| Aba   | Arquivo              | Conteúdo que deve ter |
|-------|----------------------|------------------------|
| Geral | `aba_geral.php`      | Informações Básicas, Nome, Descrição, Produto Grátis, Produto Vitrine, Capa, Configuração de Entrega |
| Funil | `aba_funil.php`      | Funil de Vendas (canvas), ativar funil, Personalizar oferta Upsell, Personalizar oferta Downsell |

A inclusão é feita em `produto_config.php` assim: `aba_geral.php` quando `?aba=geral`, `aba_funil.php` quando `?aba=funil`. Não é necessário alterar `produto_config.php` se apenas os arquivos das abas estiverem com o conteúdo certo.
