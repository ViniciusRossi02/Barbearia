<?php
require_once __DIR__ . '/php/agendar_sql.php';

// Horários fixos disponíveis no dia
$todos_horarios = ['09:00','09:30','10:00','10:30','11:00','11:30','14:00','14:30','15:00','15:30','16:00','16:30'];

// Data selecionada (default: hoje)
$data_selecionada = $_GET['data'] ?? date('Y-m-d');

// Busca horários ocupados no banco
$ocupados = listar_horarios_ocupados($data_selecionada);

// Processar formulário
$mensagem    = '';
$tipo_msg    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome       = trim($_POST['nome']       ?? '');
    $whatsapp   = trim($_POST['whatsapp']   ?? '');
    $servico    = trim($_POST['servico']    ?? '');
    $horario    = trim($_POST['horario']    ?? '');
    $data       = trim($_POST['data']       ?? '');
    $observacao = trim($_POST['observacao'] ?? '');

    if (!$nome || !$servico || !$horario || !$data) {
        $mensagem = 'Preencha todos os campos obrigatórios.';
        $tipo_msg = 'erro';
    } elseif (horario_esta_ocupado($data, $horario)) {
        $mensagem = 'Esse horário já foi reservado. Escolha outro.';
        $tipo_msg = 'erro';
    } else {
        $ok = marcar_horario($nome, $whatsapp, $servico, $data, $horario, $observacao);
        if ($ok) {
            $mensagem = "Pedido enviado com sucesso, $nome! Aguarde a confirmação do barbeiro.";
            $tipo_msg = 'ok';
        } else {
            $mensagem = 'Erro ao salvar. Tente novamente.';
            $tipo_msg = 'erro';
        }
    }
}

// Gerar próximos 7 dias para o seletor
$dias = [];
for ($i = 0; $i < 7; $i++) {
    $ts      = strtotime("+$i days");
    $dias[]  = [
        'valor'    => date('Y-m-d', $ts),
        'dia_num'  => date('d', $ts),
        'dia_nome' => ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][date('w', $ts)],
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barbearia — Agendar</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --preto:    #0a0a0a;
    --cinza-e:  #111111;
    --cinza-m:  #1e1e1e;
    --cinza-b:  #2c2c2c;
    --borda:    #333333;
    --texto:    #f0f0f0;
    --muted:    #888888;
    --branco:   #ffffff;
    --verde:    #c8f542;   /* destaque único */
  }

  body {
    background: var(--preto);
    color: var(--texto);
    font-family: 'Segoe UI', system-ui, sans-serif;
    min-height: 100vh;
  }

  /* ── header ── */
  header {
    border-bottom: 1px solid var(--borda);
    padding: 1.25rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .logo { font-size: 1.1rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; }
  .logo span { color: var(--muted); font-weight: 400; }
  header a { font-size: .8rem; color: var(--muted); text-decoration: none; border: 1px solid var(--borda); padding: .35rem .9rem; border-radius: 4px; }
  header a:hover { color: var(--texto); border-color: #555; }

  /* ── layout ── */
  main {
    max-width: 680px;
    margin: 0 auto;
    padding: 2.5rem 1.5rem;
  }

  h1 { font-size: 1.6rem; font-weight: 600; margin-bottom: .4rem; }
  .subtitulo { font-size: .9rem; color: var(--muted); margin-bottom: 2rem; }

  /* ── mensagem feedback ── */
  .msg {
    padding: .85rem 1.1rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
    font-size: .9rem;
  }
  .msg.ok   { background: #1a2a00; border: 1px solid #4a7a00; color: #b8f040; }
  .msg.erro { background: #2a0a0a; border: 1px solid #7a2020; color: #f08080; }

  /* ── seção ── */
  .secao { margin-bottom: 1.75rem; }
  .secao-titulo {
    font-size: .7rem;
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: .75rem;
  }

  /* ── seletor de dias ── */
  .dias-grid {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }
  .dia-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: .65rem .9rem;
    background: var(--cinza-m);
    border: 1px solid var(--borda);
    border-radius: 6px;
    color: var(--muted);
    text-decoration: none;
    font-size: .75rem;
    min-width: 52px;
    transition: border-color .15s;
  }
  .dia-btn .num {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--texto);
    line-height: 1;
  }
  .dia-btn:hover { border-color: #555; }
  .dia-btn.ativo { border-color: var(--verde); }
  .dia-btn.ativo .num { color: var(--verde); }

  /* ── horários ── */
  .horarios-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
  }
  .horario-label {
    display: block;
    cursor: pointer;
  }
  .horario-label input { display: none; }
  .horario-box {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: .7rem .4rem;
    background: var(--cinza-m);
    border: 1px solid var(--borda);
    border-radius: 6px;
    font-size: .85rem;
    color: var(--texto);
    transition: border-color .15s, background .15s;
    text-align: center;
  }
  .horario-label input:checked + .horario-box {
    background: var(--verde);
    border-color: var(--verde);
    color: var(--preto);
    font-weight: 600;
  }
  .horario-label.ocupado .horario-box {
    background: var(--cinza-e);
    color: var(--borda);
    text-decoration: line-through;
    cursor: not-allowed;
  }

  /* ── campos de texto ── */
  .campo { margin-bottom: 1rem; }
  .campo label {
    display: block;
    font-size: .75rem;
    color: var(--muted);
    margin-bottom: .4rem;
    letter-spacing: .04em;
  }
  .campo input,
  .campo select {
    width: 100%;
    background: var(--cinza-m);
    border: 1px solid var(--borda);
    border-radius: 6px;
    padding: .7rem .9rem;
    color: var(--texto);
    font-size: .9rem;
    outline: none;
    transition: border-color .15s;
  }
  .campo input:focus,
  .campo select:focus { border-color: #555; }
  .campo select option { background: var(--cinza-m); }

  /* ── botão ── */
  .btn-confirmar {
    width: 100%;
    padding: .9rem;
    background: var(--verde);
    color: var(--preto);
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: .04em;
    cursor: pointer;
    text-transform: uppercase;
    transition: opacity .15s;
  }
  .btn-confirmar:hover { opacity: .85; }
</style>
</head>
<body>

<header>
  <div class="logo">Borges Barbearia <span>/ Agendamento</span></div>
  <a href="login.php">Área do barbeiro</a>
</header>

<main>
  <h1>Marque seu horário</h1>
  <p class="subtitulo">Escolha o dia e horário disponível abaixo.</p>

  <?php if ($mensagem): ?>
    <div class="msg <?= $tipo_msg ?>"><?= htmlspecialchars($mensagem) ?></div>
  <?php endif; ?>

  <!-- Seletor de dias -->
  <div class="secao">
    <p class="secao-titulo">Escolha o dia</p>
    <div class="dias-grid">
      <?php foreach ($dias as $d): ?>
        <a href="?data=<?= $d['valor'] ?>"
           class="dia-btn <?= $d['valor'] === $data_selecionada ? 'ativo' : '' ?>">
          <span class="num"><?= $d['dia_num'] ?></span>
          <?= $d['dia_nome'] ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <form method="POST">
    <input type="hidden" name="data" value="<?= htmlspecialchars($data_selecionada) ?>">

    <!-- Horários -->
    <div class="secao">
      <p class="secao-titulo">Horário disponível —
        <?= (new DateTime($data_selecionada))->format('d/m/Y') ?></p>
      <div class="horarios-grid">
        <?php foreach ($todos_horarios as $h):
          $ocupado = in_array($h . ':00', $ocupados) || in_array($h, $ocupados);
        ?>
          <label class="horario-label <?= $ocupado ? 'ocupado' : '' ?>">
            <input type="radio" name="horario" value="<?= $h ?>"
              <?= $ocupado ? 'disabled' : '' ?> required>
            <span class="horario-box"><?= $h ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Dados do cliente -->
    <div class="secao">
      <p class="secao-titulo">Seus dados</p>
      <div class="campo">
        <label>Nome *</label>
        <input type="text" name="nome" placeholder="Seu nome completo" required>
      </div>
      <div class="campo">
        <label>WhatsApp (opcional)</label>
        <input type="tel" name="whatsapp" placeholder="(11) 99999-9999">
      </div>
      <div class="campo">
        <label>Serviço *</label>
        <select name="servico" required>
          <option value="" disabled selected>Selecione…</option>
          <option>Corte degradê</option>
          <option>Corte navalhado</option>
          <option>Corte social</option>
          <option>Barba completa</option>
          <option>Barba degradê</option>
          <option>Sobrancelha</option>
          <option>Pigmentação</option>
          <option>Platinado</option>
          <option>Corte degradê + Barba completa</option>
          <option>Corte social + Barba completa</option>
        </select>
      </div>
      <div class="campo">
        <label>Observações sobre o corte (opcional)</label>
        <textarea name="observacao" rows="3"
          placeholder="Ex: degradê baixo na lateral, franja longa, barba com contorno…"
          style="width:100%;background:var(--cinza-m);border:1px solid var(--borda);border-radius:6px;padding:.7rem .9rem;color:var(--texto);font-size:.9rem;outline:none;resize:vertical;font-family:inherit;"></textarea>
      </div>
    </div>

    <button type="submit" class="btn-confirmar">Confirmar agendamento</button>
  </form>
</main>

</body>
</html>
