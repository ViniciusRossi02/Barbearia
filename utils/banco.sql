-- ============================================================
--  BARBEARIA — Script de instalação do banco de dados
--  Cole este conteúdo no phpMyAdmin > SQL e clique em Executar
-- ============================================================

-- Tabela de agendamentos dos clientes
CREATE TABLE IF NOT EXISTS agendamentos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(100)  NOT NULL,
    whatsapp   VARCHAR(20)   DEFAULT '',
    servico    VARCHAR(80)   NOT NULL,
    data       DATE          NOT NULL,
    horario    TIME          NOT NULL,
    status     ENUM('pendente','confirmado','recusado') DEFAULT 'pendente',
    observacao VARCHAR(300)  DEFAULT '',
    criado_em  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de usuários (acesso ao painel do barbeiro)
CREATE TABLE IF NOT EXISTS usuarios (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    usuario   VARCHAR(60)   NOT NULL UNIQUE,
    senha     VARCHAR(255)  NOT NULL,
    criado_em TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
