-- Checkout BR + Internacional — feature flags, multi-moeda, payment_decline_logs
-- Execute: mysql ... < migrations/checkout_internacional.sql

-- Feature flags e moeda (configuracoes_sistema)
INSERT INTO configuracoes_sistema (chave, valor, tipo, descricao) VALUES
('payment_routing_enabled', '0', 'bool', 'Ativa roteamento BR=Efí / Internacional=Stripe'),
('stripe_enabled', '0', 'bool', 'Habilita Stripe para checkout internacional'),
('paypal_enabled', '0', 'bool', 'Habilita PayPal como fallback internacional'),
('default_currency', 'BRL', 'text', 'Moeda padrão'),
('allowed_currencies', 'BRL,USD,EUR', 'text', 'Moedas permitidas'),
('usd_rate', '5.00', 'text', 'Taxa BRL->USD quando price_usd não definido')
ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo), descricao = VALUES(descricao);

-- Preços multi-moeda em produtos (execute uma vez; reexecutar dará erro Duplicate column)
ALTER TABLE produtos ADD COLUMN price_usd DECIMAL(10,2) NULL DEFAULT NULL AFTER preco;
ALTER TABLE produtos ADD COLUMN price_eur DECIMAL(10,2) NULL DEFAULT NULL AFTER price_usd;

-- Tabela de logs de recusa (opcional)
CREATE TABLE IF NOT EXISTS payment_decline_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  gateway VARCHAR(50) NOT NULL,
  payment_id VARCHAR(255) DEFAULT NULL,
  status VARCHAR(50) DEFAULT NULL,
  status_detail VARCHAR(100) DEFAULT NULL,
  decline_code VARCHAR(100) DEFAULT NULL,
  product_id INT DEFAULT NULL,
  country VARCHAR(2) DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  raw JSON DEFAULT NULL,
  INDEX idx_gateway_ts (gateway, ts),
  INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
