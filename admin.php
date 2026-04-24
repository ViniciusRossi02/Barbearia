<?php
require_once __DIR__ . '/php/agendar_sql.php';

// ── Exige login ──────────────────────────────────────────────
exigir_login();

// ── Logout ───────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    fazer_logout();
}

// ── Processar ações do barbeiro ──────────────────────────────
$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    if ($acao === 'confirmar' && $id > 0) {
        confirmar_agendamento($id);
        $feedback = 'Agendamento confirmado!';
    } elseif ($acao === 'recusar' && $id > 0) {
        recusar_agendamento($id);
        $feedback = 'Agendamento recusado.';
    }
}

// ── Data selecionada para "outros dias" ──────────────────────
$data_selecionada = $_GET['data'] ?? '';
$agenda_outro_dia = [];
if ($data_selecionada && $data_selecionada !== date('Y-m-d')) {
    $agenda_outro_dia = listar_agenda_por_data($data_selecionada);
}

// ── Dados para exibição ──────────────────────────────────────
$pendentes      = listar_agendamentos('pendente');
$agenda_hoje    = listar_agenda_do_dia(date('Y-m-d'));
$proximos_dias  = listar_proximos_dias(14);
$total_hoje     = contar_agendamentos_hoje();
$total_pendente = contar_pendentes();
$total_semana   = contar_agendamentos_semana();

// ── Helpers ──────────────────────────────────────────────────
function dia_semana(string $data): string {
    $nomes = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    return $nomes[(int)(new DateTime($data))->format('w')];
}
function dia_semana_ext(string $data): string {
    $nomes = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
    return $nomes[(int)(new DateTime($data))->format('w')];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barbearia — Painel</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --preto:   #0a0a0a;
    --cinza-e: #111111;
    --cinza-m: #1e1e1e;
    --cinza-b: #2c2c2c;
    --borda:   #333333;
    --texto:   #f0f0f0;
    --muted:   #888888;
    --verde:   #c8f542;
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
    padding: 1.1rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
  }
  .logo { font-size: 1.1rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; }
  .logo span { color: var(--muted); font-weight: 400; }
  .header-links { display: flex; align-items: center; gap: 8px; }
  .header-links a {
    font-size: .8rem; color: var(--muted); text-decoration: none;
    border: 1px solid var(--borda); padding: .35rem .9rem; border-radius: 4px;
    transition: color .15s, border-color .15s;
  }
  .header-links a:hover { color: var(--texto); border-color: #555; }
  .header-links a.destaque { border-color: var(--verde); color: var(--verde); }
  .header-links a.destaque:hover { background: var(--verde); color: var(--preto); }
  .header-usuario { font-size: .8rem; color: var(--muted); }
  .header-usuario strong { color: var(--texto); }

  /* ── layout ── */
  main { max-width: 960px; margin: 0 auto; padding: 2.5rem 1.5rem; }

  h1 { font-size: 1.6rem; font-weight: 600; margin-bottom: .4rem; }
  .subtitulo { font-size: .9rem; color: var(--muted); margin-bottom: 2rem; }

  /* ── feedback ── */
  .feedback {
    padding: .75rem 1rem; background: #1a2a00; border: 1px solid #4a7a00;
    color: #b8f040; border-radius: 6px; font-size: .875rem; margin-bottom: 1.5rem;
  }

  /* ── stats ── */
  .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 2.5rem; }
  .stat { background: var(--cinza-m); border: 1px solid var(--borda); border-radius: 8px; padding: 1.25rem 1.5rem; }
  .stat .valor { font-size: 2rem; font-weight: 700; line-height: 1; margin-bottom: .3rem; }
  .stat .rotulo { font-size: .75rem; color: var(--muted); letter-spacing: .06em; text-transform: uppercase; }
  .stat.destaque .valor { color: var(--verde); }
  .stat.alerta .valor { color: #f0c040; }

  /* ── seção ── */
  .secao { margin-bottom: 2.5rem; }
  .secao-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1rem; padding-bottom: .6rem; border-bottom: 1px solid var(--borda);
  }
  .secao-titulo {
    font-size: .7rem; font-weight: 600; letter-spacing: .12em;
    text-transform: uppercase; color: var(--muted);
  }

  /* ── seletor de dias ── */
  .dias-scroll { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 1rem; }
  .dias-scroll::-webkit-scrollbar { height: 3px; }
  .dias-scroll::-webkit-scrollbar-track { background: transparent; }
  .dias-scroll::-webkit-scrollbar-thumb { background: var(--borda); border-radius: 2px; }
  .dia-card {
    flex-shrink: 0;
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    padding: .65rem .9rem; min-width: 60px;
    background: var(--cinza-m); border: 1px solid var(--borda); border-radius: 8px;
    text-decoration: none; color: var(--muted); font-size: .72rem; text-align: center;
    transition: border-color .15s;
  }
  .dia-card .dc-num { font-size: 1.2rem; font-weight: 600; color: var(--texto); line-height: 1; }
  .dia-card .dc-total {
    background: var(--cinza-b); border-radius: 10px;
    padding: 1px 6px; font-size: .68rem; color: var(--muted);
  }
  .dia-card:hover { border-color: #555; }
  .dia-card.ativo { border-color: var(--verde); }
  .dia-card.ativo .dc-num { color: var(--verde); }
  .dia-card.ativo .dc-total { background: #1a2a00; color: #b8f040; }

  /* ── tabela ── */
  table { width: 100%; border-collapse: collapse; }
  th {
    font-size: .7rem; color: var(--muted); letter-spacing: .08em;
    text-transform: uppercase; padding: .5rem .75rem; text-align: left; font-weight: 500;
  }
  td {
    padding: .85rem .75rem; font-size: .875rem;
    border-top: 1px solid var(--borda); vertical-align: middle;
  }
  tr:hover td { background: var(--cinza-e); }

  /* ── badges ── */
  .badge {
    display: inline-block; font-size: .7rem; font-weight: 600;
    letter-spacing: .06em; text-transform: uppercase; padding: .25rem .65rem; border-radius: 4px;
  }
  .badge.pendente   { background: #2a1f00; color: #f0c040; border: 1px solid #5a3f00; }
  .badge.confirmado { background: #1a2a00; color: #b8f040; border: 1px solid #4a7a00; }
  .badge.recusado   { background: #2a0a0a; color: #f08080; border: 1px solid #7a2020; }

  .horario { font-weight: 600; color: var(--verde); font-size: .9rem; }

  /* ── botões de ação ── */
  .acoes { display: flex; gap: 6px; }
  .btn-acao {
    padding: .35rem .85rem; border-radius: 4px; font-size: .78rem;
    font-weight: 600; letter-spacing: .04em; cursor: pointer; border: 1px solid;
    text-transform: uppercase; transition: opacity .15s; background: transparent;
  }
  .btn-acao:hover { opacity: .75; }
  .btn-aceitar { border-color: var(--verde); color: var(--verde); }
  .btn-recusar { border-color: #7a2020; color: #f08080; }

  /* ── vazio ── */
  .vazio {
    padding: 2rem; text-align: center; color: var(--muted); font-size: .875rem;
    border: 1px dashed var(--borda); border-radius: 8px;
  }
</style>
</head>
<body>

<header>
  <div class="logo">Barbearia <span>/ Painel</span></div>
  <div class="header-links">
    <span class="header-usuario">Olá, <strong><?= htmlspecialchars($_SESSION['barbeiro_usuario'] ?? '') ?></strong></span>
    <a href="index.php" class="destaque">Ver agendamentos</a>
    <a href="admin.php?logout=1">Sair</a>
  </div>
</header>

<main>
  <h1>Painel do barbeiro</h1>
  <p class="subtitulo">
    Hoje é <?= dia_semana_ext(date('Y-m-d')) ?>,
    <?= (new DateTime())->format('d/m/Y') ?>
  </p>

  <?php if ($feedback): ?>
    <div class="feedback"><?= htmlspecialchars($feedback) ?></div>
  <?php endif; ?>

  <!-- Estatísticas -->
  <div class="stats">
    <div class="stat destaque">
      <div class="valor"><?= $total_hoje ?></div>
      <div class="rotulo">Confirmados hoje</div>
    </div>
    <div class="stat alerta">
      <div class="valor"><?= $total_pendente ?></div>
      <div class="rotulo">Aguardando resposta</div>
    </div>
    <div class="stat">
      <div class="valor"><?= $total_semana ?></div>
      <div class="rotulo">Esta semana</div>
    </div>
  </div>

  <!-- Agenda de hoje -->
  <div class="secao">
    <div class="secao-header">
      <p class="secao-titulo">Agenda de hoje</p>
    </div>
    <?php if (empty($agenda_hoje)): ?>
      <div class="vazio">Nenhum horário confirmado para hoje.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Horário</th><th>Cliente</th><th>WhatsApp</th>
            <th>Serviço</th><th>Observação</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($agenda_hoje as $a): ?>
            <tr>
              <td><span class="horario"><?= substr($a['horario'], 0, 5) ?></span></td>
              <td><?= htmlspecialchars($a['nome']) ?></td>
              <td><?= htmlspecialchars($a['whatsapp'] ?: '—') ?></td>
              <td><?= htmlspecialchars($a['servico']) ?></td>
              <td style="color:var(--muted);font-size:.82rem"><?= htmlspecialchars($a['observacao'] ?: '—') ?></td>
              <td><span class="badge confirmado">confirmado</span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Próximos dias -->
  <div class="secao">
    <div class="secao-header">
      <p class="secao-titulo">Próximos dias com agendamento</p>
    </div>

    <?php if (empty($proximos_dias)): ?>
      <div class="vazio">Nenhum agendamento nos próximos dias.</div>
    <?php else: ?>
      <!-- Cards de dias -->
      <div class="dias-scroll">
        <?php foreach ($proximos_dias as $d):
          if ($d['data'] === date('Y-m-d')) continue; // hoje já aparece acima
          $ativo = $d['data'] === $data_selecionada;
        ?>
          <a href="admin.php?data=<?= $d['data'] ?>#proximos"
             class="dia-card <?= $ativo ? 'ativo' : '' ?>">
            <span><?= dia_semana($d['data']) ?></span>
            <span class="dc-num"><?= (new DateTime($d['data']))->format('d/m') ?></span>
            <span class="dc-total"><?= $d['total'] ?> <?= $d['total'] == 1 ? 'cliente' : 'clientes' ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Tabela do dia selecionado -->
      <?php if ($data_selecionada && $data_selecionada !== date('Y-m-d')): ?>
        <div id="proximos" style="margin-top:.5rem">
          <p style="font-size:.82rem;color:var(--muted);margin-bottom:.85rem;">
            <?= dia_semana_ext($data_selecionada) ?>,
            <?= (new DateTime($data_selecionada))->format('d/m/Y') ?>
          </p>
          <?php if (empty($agenda_outro_dia)): ?>
            <div class="vazio">Nenhum agendamento para este dia.</div>
          <?php else: ?>
            <table>
              <thead>
                <tr>
                  <th>Horário</th><th>Cliente</th><th>WhatsApp</th>
                  <th>Serviço</th><th>Observação</th><th>Status</th><th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($agenda_outro_dia as $a): ?>
                  <tr>
                    <td><span class="horario"><?= substr($a['horario'], 0, 5) ?></span></td>
                    <td><?= htmlspecialchars($a['nome']) ?></td>
                    <td><?= htmlspecialchars($a['whatsapp'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($a['servico']) ?></td>
                    <td style="color:var(--muted);font-size:.82rem"><?= htmlspecialchars($a['observacao'] ?: '—') ?></td>
                    <td><span class="badge <?= $a['status'] ?>"><?= $a['status'] ?></span></td>
                    <td>
                      <?php if ($a['status'] === 'pendente'): ?>
                        <div class="acoes">
                          <form method="POST" style="margin:0">
                            <input type="hidden" name="acao" value="confirmar">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn-acao btn-aceitar">Aceitar</button>
                          </form>
                          <form method="POST" style="margin:0">
                            <input type="hidden" name="acao" value="recusar">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn-acao btn-recusar">Recusar</button>
                          </form>
                        </div>
                      <?php else: ?>
                        <span style="font-size:.8rem;color:var(--muted)">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      <?php elseif (empty($data_selecionada)): ?>
        <p style="font-size:.82rem;color:var(--muted);margin-top:.5rem">
          Clique em um dia acima para ver os agendamentos.
        </p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Solicitações pendentes -->
  <div class="secao">
    <div class="secao-header">
      <p class="secao-titulo">Solicitações pendentes</p>
    </div>
    <?php if (empty($pendentes)): ?>
      <div class="vazio">Nenhuma solicitação aguardando resposta.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Data</th><th>Horário</th><th>Cliente</th>
            <th>WhatsApp</th><th>Serviço</th><th>Observação</th><th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendentes as $p): ?>
            <tr>
              <td><?= dia_semana($p['data']) ?>, <?= (new DateTime($p['data']))->format('d/m') ?></td>
              <td><span class="horario"><?= substr($p['horario'], 0, 5) ?></span></td>
              <td><?= htmlspecialchars($p['nome']) ?></td>
              <td><?= htmlspecialchars($p['whatsapp'] ?: '—') ?></td>
              <td><?= htmlspecialchars($p['servico']) ?></td>
              <td style="color:var(--muted);font-size:.82rem"><?= htmlspecialchars($p['observacao'] ?: '—') ?></td>
              <td>
                <div class="acoes">
                  <form method="POST" style="margin:0">
                    <input type="hidden" name="acao" value="confirmar">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn-acao btn-aceitar">Aceitar</button>
                  </form>
                  <form method="POST" style="margin:0">
                    <input type="hidden" name="acao" value="recusar">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn-acao btn-recusar">Recusar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</main>

</body>
</html>
