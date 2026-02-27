-- Sessão única por usuário: apenas um navegador/dispositivo logado por vez.
-- Ao fazer login em outro lugar, as demais sessões são invalidadas.
-- Execute uma vez no banco (ex.: mysql ... < migrations/single_session.sql).

ALTER TABLE `usuarios`
  ADD COLUMN `session_token` VARCHAR(64) NULL DEFAULT NULL
  COMMENT 'Token da sessão ativa; novo login invalida sessões anteriores';
