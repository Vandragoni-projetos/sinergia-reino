-- Aparência da página de oferta do funil (cores, logo, títulos) – por produto/funil
-- Rode uma vez; se a coluna já existir, ignore o erro.

ALTER TABLE `product_funnels`
  ADD COLUMN `offer_theme` TEXT NULL COMMENT 'JSON: primary_color, secondary_color, logo_url, page_bg, header_label_upsell, header_headline_upsell, header_label_downsell, header_headline_downsell' AFTER `downsell_custom_config`;
