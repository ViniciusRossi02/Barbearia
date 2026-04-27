<?php

// ============================================================
//  CONEXÃO
// ============================================================

function conectar_banco(): PDO
{
    // No Railway as credenciais vêm das variáveis de ambiente
    // Localmente (XAMPP) usa os valores padrão como fallback
    $host    = getenv('MYSQLHOST')     ?: 'localhost';
    $banco   = getenv('MYSQLDATABASE') ?: 'barbearia';
    $usuario = getenv('MYSQLUSER')     ?: 'root';
    $senha   = getenv('MYSQLPASSWORD') ?: '12345';
    $porta   = getenv('MYSQLPORT')     ?: '3306';

    $pdo = new PDO("mysql:host=$host;port=$porta;dbname=$banco;charset=utf8mb4", $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}


// ============================================================
//  CRIAR TABELA (rodar uma vez na instalação)
// ============================================================

function criar_tabela_agendamentos(): void
{
    $pdo = conectar_banco();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agendamentos (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            nome       VARCHAR(100)  NOT NULL,
            whatsapp   VARCHAR(20)   DEFAULT '',
            servico    VARCHAR(80)   NOT NULL,
            data       DATE          NOT NULL,
            horario    TIME          NOT NULL,
            status     ENUM('pendente','confirmado','recusado') DEFAULT 'pendente',
            observacao VARCHAR(300)  DEFAULT '',
            valor      DECIMAL(8,2)  DEFAULT NULL,
            criado_em  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}


// ============================================================
//  CLIENTE — salvar novo pedido
// ============================================================

function marcar_horario(string $nome, string $whatsapp, string $servico, string $data, string $horario, string $observacao = ''): bool
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("
        INSERT INTO agendamentos (nome, whatsapp, servico, data, horario, observacao)
        VALUES (:nome, :whatsapp, :servico, :data, :horario, :observacao)
    ");
    return $stmt->execute([
        ':nome'       => $nome,
        ':whatsapp'   => $whatsapp,
        ':servico'    => $servico,
        ':data'       => $data,
        ':horario'    => $horario,
        ':observacao' => $observacao,
    ]);
}


// ============================================================
//  CLIENTE — checar se horário já está ocupado
// ============================================================

function horario_esta_ocupado(string $data, string $horario): bool
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM agendamentos
        WHERE data = :data AND horario = :horario
          AND status != 'recusado'
    ");
    $stmt->execute([':data' => $data, ':horario' => $horario]);
    return (int) $stmt->fetchColumn() > 0;
}


// ============================================================
//  CLIENTE — listar horários ocupados numa data
// ============================================================

function listar_horarios_ocupados(string $data): array
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("
        SELECT horario FROM agendamentos
        WHERE data = :data AND status != 'recusado'
    ");
    $stmt->execute([':data' => $data]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}


// ============================================================
//  ADMIN — listar agendamentos por status
// ============================================================

function listar_agendamentos(string $status = 'pendente'): array
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("
        SELECT * FROM agendamentos
        WHERE status = :status
        ORDER BY data ASC, horario ASC
    ");
    $stmt->execute([':status' => $status]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
//  ADMIN — listar agenda de qualquer data selecionada
// ============================================================

function listar_agenda_por_data(string $data): array
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("
        SELECT * FROM agendamentos
        WHERE data = :data AND status = 'confirmado'
        ORDER BY horario ASC
    ");
    $stmt->execute([':data' => $data]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
//  ADMIN — listar próximos dias que têm agendamentos
//  (inclui passados para não sumir agendamentos já feitos)
// ============================================================

function listar_proximos_dias(int $quantidade = 30): array
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("
        SELECT data, COUNT(*) as total
        FROM agendamentos
        WHERE data BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                       AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
          AND status = 'confirmado'
        GROUP BY data
        ORDER BY data ASC
        LIMIT :qtd
    ");
    $stmt->bindValue(':qtd', $quantidade, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
//  ADMIN — listar agenda do dia
// ============================================================

function listar_agenda_do_dia(string $data): array
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("
        SELECT * FROM agendamentos
        WHERE data = :data AND status = 'confirmado'
        ORDER BY horario ASC
    ");
    $stmt->execute([':data' => $data]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ============================================================
//  ADMIN — confirmar agendamento (com valor opcional)
// ============================================================

function confirmar_agendamento(int $id, ?float $valor = null): bool
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("
        UPDATE agendamentos
        SET status = 'confirmado', valor = :valor
        WHERE id = :id
    ");
    return $stmt->execute([':id' => $id, ':valor' => $valor]);
}


// ============================================================
//  ADMIN — atualizar valor de um agendamento já confirmado
// ============================================================

function atualizar_valor(int $id, ?float $valor): bool
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("
        UPDATE agendamentos SET valor = :valor WHERE id = :id
    ");
    return $stmt->execute([':id' => $id, ':valor' => $valor]);
}


// ============================================================
//  ADMIN — desconfirmar (volta para pendente)
// ============================================================

function desconfirmar_agendamento(int $id): bool
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("
        UPDATE agendamentos SET status = 'pendente', valor = NULL WHERE id = :id
    ");
    return $stmt->execute([':id' => $id]);
}


// ============================================================
//  ADMIN — recusar agendamento
// ============================================================

function recusar_agendamento(int $id): bool
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("
        UPDATE agendamentos SET status = 'recusado' WHERE id = :id
    ");
    return $stmt->execute([':id' => $id]);
}


// ============================================================
//  ADMIN — contagens para o painel
// ============================================================

function contar_agendamentos_hoje(): int
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM agendamentos
        WHERE data = CURDATE() AND status = 'confirmado'
    ");
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function contar_pendentes(): int
{
    $pdo  = conectar_banco();
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM agendamentos WHERE status = 'pendente'
    ");
    return (int) $stmt->fetchColumn();
}

// Corrigido: usa intervalo fixo de 7 dias, não YEARWEEK
function contar_agendamentos_semana(): int
{
    $pdo  = conectar_banco();
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM agendamentos
        WHERE data BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                       AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)
          AND status = 'confirmado'
    ");
    return (int) $stmt->fetchColumn();
}


// ============================================================
//  ADMIN — faturamento
// ============================================================

function faturamento_do_dia(string $data): float
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(valor), 0)
        FROM agendamentos
        WHERE data = :data AND status = 'confirmado' AND valor IS NOT NULL
    ");
    $stmt->execute([':data' => $data]);
    return (float) $stmt->fetchColumn();
}

function faturamento_da_semana(): float
{
    $pdo  = conectar_banco();
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(valor), 0)
        FROM agendamentos
        WHERE data BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                       AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)
          AND status = 'confirmado' AND valor IS NOT NULL
    ");
    return (float) $stmt->fetchColumn();
}

function faturamento_do_mes(): float
{
    $pdo  = conectar_banco();
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(valor), 0)
        FROM agendamentos
        WHERE MONTH(data) = MONTH(CURDATE())
          AND YEAR(data)  = YEAR(CURDATE())
          AND status = 'confirmado' AND valor IS NOT NULL
    ");
    return (float) $stmt->fetchColumn();
}


// ============================================================
//  HISTÓRICO — listar confirmados de datas passadas
// ============================================================

function listar_historico(int $pagina = 1, int $por_pagina = 20, string $busca = ''): array
{
    $pdo    = conectar_banco();
    $offset = ($pagina - 1) * $por_pagina;

    if ($busca) {
        $stmt = $pdo->prepare("
            SELECT * FROM agendamentos
            WHERE data < CURDATE()
              AND status = 'confirmado'
              AND (nome LIKE :busca OR servico LIKE :busca)
            ORDER BY data DESC, horario DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':busca', "%$busca%");
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM agendamentos
            WHERE data < CURDATE() AND status = 'confirmado'
            ORDER BY data DESC, horario DESC
            LIMIT :limit OFFSET :offset
        ");
    }
    $stmt->bindValue(':limit',  $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function contar_historico(string $busca = ''): int
{
    $pdo = conectar_banco();
    if ($busca) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM agendamentos
            WHERE data < CURDATE() AND status = 'confirmado'
              AND (nome LIKE :busca OR servico LIKE :busca)
        ");
        $stmt->execute([':busca' => "%$busca%"]);
    } else {
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM agendamentos
            WHERE data < CURDATE() AND status = 'confirmado'
        ");
        $stmt->execute();
    }
    return (int) $stmt->fetchColumn();
}

function faturamento_historico(string $busca = ''): float
{
    $pdo = conectar_banco();
    if ($busca) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(valor), 0) FROM agendamentos
            WHERE data < CURDATE() AND status = 'confirmado'
              AND valor IS NOT NULL
              AND (nome LIKE :busca OR servico LIKE :busca)
        ");
        $stmt->execute([':busca' => "%$busca%"]);
    } else {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(valor), 0) FROM agendamentos
            WHERE data < CURDATE() AND status = 'confirmado'
              AND valor IS NOT NULL
        ");
        $stmt->execute();
    }
    return (float) $stmt->fetchColumn();
}


// ============================================================
//  HISTÓRICO — checar se agendamento é de data passada
// ============================================================

function agendamento_e_passado(int $id): bool
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("SELECT data FROM agendamentos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    return $row['data'] < date('Y-m-d');
}

function criar_tabela_usuarios(): void
{
    $pdo = conectar_banco();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            usuario   VARCHAR(60)  NOT NULL UNIQUE,
            senha     VARCHAR(255) NOT NULL,
            criado_em TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}


// ============================================================
//  AUTH — criar barbeiro (rodar uma vez via setup.php)
// ============================================================

function criar_barbeiro(string $usuario, string $senha): bool
{
    $pdo  = conectar_banco();
    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO usuarios (usuario, senha) VALUES (:usuario, :senha)
    ");
    return $stmt->execute([':usuario' => $usuario, ':senha' => $hash]);
}


// ============================================================
//  AUTH — verificar login
// ============================================================

function verificar_login(string $usuario, string $senha): bool
{
    $pdo  = conectar_banco();
    $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE usuario = :usuario");
    $stmt->execute([':usuario' => $usuario]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    return password_verify($senha, $row['senha']);
}


// ============================================================
//  AUTH — checar se sessão está ativa
// ============================================================

function exigir_login(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['barbeiro_logado'])) {
        header('Location: login.php');
        exit;
    }
}


// ============================================================
//  AUTH — fazer logout
// ============================================================

function fazer_logout(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}
