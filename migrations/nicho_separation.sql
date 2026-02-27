-- Separação por Nicho — vitrine/catálogos segmentados
-- Execute uma vez no banco (ex.: mysql ... < migrations/nicho_separation.sql)
-- Reversível: ver seção DOWN no final

-- 1. Tabela nichos
CREATE TABLE IF NOT EXISTS `nichos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabela categorias (por nicho)
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nicho_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(64) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_categorias_nicho` (`nicho_id`),
  CONSTRAINT `fk_categorias_nicho` FOREIGN KEY (`nicho_id`) REFERENCES `nichos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Pivot produto_categorias
CREATE TABLE IF NOT EXISTS `produto_categorias` (
  `produto_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  PRIMARY KEY (`produto_id`, `categoria_id`),
  KEY `fk_pc_categoria` (`categoria_id`),
  CONSTRAINT `fk_pc_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pc_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Coluna nicho_id em produtos (execute uma vez; reexecutar causará erro de coluna duplicada)
ALTER TABLE `produtos`
  ADD COLUMN `nicho_id` int(11) NULL DEFAULT NULL
  COMMENT 'Nicho do produto (NULL = oculto em vitrine por padrão)' AFTER `community_id`;

ALTER TABLE `produtos` ADD KEY `idx_produtos_nicho` (`nicho_id`);
ALTER TABLE `produtos` ADD CONSTRAINT `fk_produtos_nicho` FOREIGN KEY (`nicho_id`) REFERENCES `nichos` (`id`) ON DELETE SET NULL;

-- 5. Coluna nicho_id em usuarios
ALTER TABLE `usuarios`
  ADD COLUMN `nicho_id` int(11) NULL DEFAULT NULL
  COMMENT 'Nicho permitido para cliente (NULL = admin/infoprodutor)' AFTER `tipo`;

ALTER TABLE `usuarios` ADD KEY `idx_usuarios_nicho` (`nicho_id`);
ALTER TABLE `usuarios` ADD CONSTRAINT `fk_usuarios_nicho` FOREIGN KEY (`nicho_id`) REFERENCES `nichos` (`id`) ON DELETE SET NULL;

-- Seed: 4 nichos exemplo
INSERT INTO `nichos` (`slug`, `nome`, `descricao`, `sort_order`) VALUES
('marketing', 'Marketing Digital', 'Produtos de marketing e vendas online', 1),
('financeiro', 'Financeiro', 'Educação financeira e investimentos', 2),
('saude', 'Saúde e Bem-estar', 'Produtos de saúde e autocuidado', 3),
('educacao', 'Educação', 'Cursos e formação', 4)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- ============================================================
-- DOWN (reversão manual — execute em ordem inversa)
-- ============================================================
-- ALTER TABLE produtos DROP FOREIGN KEY fk_produtos_nicho;
-- ALTER TABLE produtos DROP KEY idx_produtos_nicho;
-- ALTER TABLE produtos DROP COLUMN nicho_id;
-- ALTER TABLE usuarios DROP FOREIGN KEY fk_usuarios_nicho;
-- ALTER TABLE usuarios DROP KEY idx_usuarios_nicho;
-- ALTER TABLE usuarios DROP COLUMN nicho_id;
-- DROP TABLE IF EXISTS produto_categorias;
-- DROP TABLE IF EXISTS categorias;
-- DROP TABLE IF EXISTS nichos;
