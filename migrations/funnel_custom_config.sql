-- Personalização das ofertas de Upsell e Downsell (banner cabeçalho, banner lateral, descrição, capa)
-- Execute com o banco selecionado. Rode uma vez; se as colunas já existirem, ignore o erro.

ALTER TABLE `product_funnels`
  ADD COLUMN `upsell_custom_config` TEXT NULL COMMENT 'JSON: banner_header, banner_side, description, cover_image' AFTER `is_active`;
ALTER TABLE `product_funnels`
  ADD COLUMN `downsell_custom_config` TEXT NULL COMMENT 'JSON: banner_header, banner_side, description, cover_image' AFTER `upsell_custom_config`;
