<?php
require_once __DIR__ . '/php/agendar_sql.php';

exigir_login();

// ── Paginação e busca ────────────────────────────────────────
$por_pagina = 20;
$pagina     = max(1, (int) ($_GET['pagina'] ?? 1));
$busca      = trim($_GET['busca'] ?? '');

$registros  = listar_historico($pagina, $por_pagina, $busca);
$total      = contar_historico($busca);
$total_pag  = (int) ceil($total / $por_pagina);
$faturado   = faturamento_historico($busca);

// ── Helpers ──────────────────────────────────────────────────
function dia_semana_hist(string $data): string {
    $nomes = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    return $nomes[(int)(new DateTime($data))->format('w')];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barbearia — Histórico</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --preto:   #0a0a0a;
    --cinza-e: #111111;
    --cinza-m: #1e1e1e;
    --borda:   #333333;
    --texto:   #f0f0f0;
    --muted:   #888888;
    --verde:   #c8f542;
  }
  body {
    background: var(--preto); color: var(--texto);
    font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh;
  }

  /* ── header ── */
  header {
    border-bottom: 1px solid var(--borda); padding: 1.1rem 2rem;
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
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
  .header-links a.ativo { border-color: var(--verde); color: var(--verde); }

  /* ── layout ── */
  main { max-width: 1000px; margin: 0 auto; padding: 2.5rem 1.5rem; }
  h1 { font-size: 1.6rem; font-weight: 600; margin-bottom: .4rem; }
  .subtitulo { font-size: .9rem; color: var(--muted); margin-bottom: 2rem; }

  /* ── cards de resumo ── */
  .resumo { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 2rem; }
  .resumo-card { background: var(--cinza-m); border: 1px solid var(--borda); border-radius: 8px; padding: 1.1rem 1.5rem; }
  .resumo-card .rv { font-size: 1.8rem; font-weight: 700; color: var(--verde); line-height: 1; margin-bottom: .3rem; }
  .resumo-card .rl { font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }

  /* ── busca ── */
  .busca-form { display: flex; gap: 8px; margin-bottom: 1.5rem; }
  .busca-input {
    flex: 1; background: var(--cinza-m); border: 1px solid var(--borda); border-radius: 6px;
    padding: .65rem 1rem; color: var(--texto); font-size: .9rem; outline: none;
    transition: border-color .15s;
  }
  .busca-input:focus { border-color: #555; }
  .busca-btn {
    padding: .65rem 1.2rem; background: var(--cinza-m); border: 1px solid var(--borda);
    border-radius: 6px; color: var(--muted); font-size: .85rem; cursor: pointer;
    transition: color .15s, border-color .15s;
  }
  .busca-btn:hover { color: var(--texto); border-color: #555; }
  .busca-limpar {
    padding: .65rem 1rem; background: transparent; border: 1px solid var(--borda);
    border-radius: 6px; color: var(--muted); font-size: .82rem; cursor: pointer;
    text-decoration: none; display: flex; align-items: center;
  }
  .busca-limpar:hover { color: var(--texto); }

  /* ── tabela ── */
  .tabela-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  th {
    font-size: .7rem; color: var(--muted); letter-spacing: .08em; text-transform: uppercase;
    padding: .5rem .75rem; text-align: left; font-weight: 500;
  }
  td {
    padding: .85rem .75rem; font-size: .875rem;
    border-top: 1px solid var(--borda); vertical-align: middle;
  }
  tr:hover td { background: var(--cinza-e); }
  .horario { font-weight: 600; color: var(--verde); font-size: .9rem; }
  .valor-cell { font-weight: 600; color: var(--verde); }
  .sem-valor { color: var(--muted); }

  /* ── vazio ── */
  .vazio {
    padding: 3rem; text-align: center; color: var(--muted); font-size: .9rem;
    border: 1px dashed var(--borda); border-radius: 8px;
  }
  .vazio .emoji { font-size: 2rem; margin-bottom: .75rem; }

  /* ── paginação ── */
  .paginacao { display: flex; gap: 6px; justify-content: center; margin-top: 2rem; flex-wrap: wrap; }
  .pag-btn {
    padding: .4rem .85rem; background: var(--cinza-m); border: 1px solid var(--borda);
    border-radius: 5px; color: var(--muted); text-decoration: none; font-size: .82rem;
    transition: color .15s, border-color .15s;
  }
  .pag-btn:hover { color: var(--texto); border-color: #555; }
  .pag-btn.ativo { border-color: var(--verde); color: var(--verde); }
  .pag-btn.desabilitado { opacity: .3; pointer-events: none; }

  /* ── badge readonly ── */
  .badge-passado {
    display: inline-block; font-size: .68rem; font-weight: 600; letter-spacing: .05em;
    text-transform: uppercase; padding: .2rem .55rem; border-radius: 4px;
    background: #1a2a00; color: #b8f040; border: 1px solid #4a7a00;
  }
</style>
</head>
<body>

<header>
  <div class="logo">Barbearia <span>/ Histórico</span></div>
  <div class="header-links">
    <span style="font-size:.8rem;color:var(--muted)">
      Olá, <strong style="color:var(--texto)"><?= htmlspecialchars($_SESSION['barbeiro_usuario'] ?? '') ?></strong>
    </span>
    <a href="admin.php">← Painel</a>
    <a href="index.php" class="ativo">Ver agendamentos</a>
    <a href="admin.php?logout=1">Sair</a>
  </div>
</header>

<main>
  <h1>Histórico de cortes</h1>
  <p class="subtitulo">Apenas cortes realizados (confirmados em datas passadas) — somente leitura.</p>

  <!-- Resumo -->
  <div class="resumo">
    <div class="resumo-card">
      <div class="rv"><?= $total ?></div>
      <div class="rl"><?= $busca ? 'Cortes encontrados' : 'Total de cortes' ?></div>
    </div>
    <div class="resumo-card">
      <div class="rv">R$ <?= number_format($faturado, 2, ',', '.') ?></div>
      <div class="rl"><?= $busca ? 'Faturado (filtrado)' : 'Total faturado' ?></div>
    </div>
    <div class="resumo-card">
      <div class="rv">
        <?= $total > 0 && $faturado > 0
            ? 'R$ ' . number_format($faturado / $total, 2, ',', '.')
            : '—' ?>
      </div>
      <div class="rl">Ticket médio</div>
    </div>
  </div>

  <!-- Busca -->
  <form method="GET" class="busca-form">
    <input type="text" name="busca" class="busca-input"
           placeholder="Buscar por nome ou serviço…"
           value="<?= htmlspecialchars($busca) ?>">
    <button type="submit" class="busca-btn">Buscar</button>
    <?php if ($busca): ?>
      <a href="historico.php" class="busca-limpar">✕ Limpar</a>
    <?php endif; ?>
  </form>

  <!-- Tabela -->
  <?php if (empty($registros)): ?>
    <div class="vazio">
      <div class="emoji">✂️</div>
      <?= $busca ? "Nenhum corte encontrado para \"" . htmlspecialchars($busca) . "\"." : 'Nenhum corte realizado ainda.' ?>
    </div>
  <?php else: ?>
    <div class="tabela-wrap">
      <table>
        <thead>
          <tr>
            <th>Data</th>
            <th>Horário</th>
            <th>Cliente</th>
            <th>WhatsApp</th>
            <th>Serviço</th>
            <th>Observação</th>
            <th>Valor</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($registros as $r): ?>
            <tr>
              <td>
                <?= dia_semana_hist($r['data']) ?>,
                <?= (new DateTime($r['data']))->format('d/m/Y') ?>
              </td>
              <td><span class="horario"><?= substr($r['horario'], 0, 5) ?></span></td>
              <td><?= htmlspecialchars($r['nome']) ?></td>
              <td><?= htmlspecialchars($r['whatsapp'] ?: '—') ?></td>
              <td><?= htmlspecialchars($r['servico']) ?></td>
              <td style="color:var(--muted);font-size:.82rem">
                <?= htmlspecialchars($r['observacao'] ?: '—') ?>
              </td>
              <td>
                <?php if ($r['valor'] !== null): ?>
                  <span class="valor-cell">R$ <?= number_format((float)$r['valor'], 2, ',', '.') ?></span>
                <?php else: ?>
                  <span class="sem-valor">—</span>
                <?php endif; ?>
              </td>
              <td><span class="badge-passado">realizado</span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginação -->
    <?php if ($total_pag > 1): ?>
      <div class="paginacao">
        <a href="?pagina=<?= $pagina - 1 ?>&busca=<?= urlencode($busca) ?>"
           class="pag-btn <?= $pagina <= 1 ? 'desabilitado' : '' ?>">← Anterior</a>

        <?php for ($i = max(1, $pagina - 2); $i <= min($total_pag, $pagina + 2); $i++): ?>
          <a href="?pagina=<?= $i ?>&busca=<?= urlencode($busca) ?>"
             class="pag-btn <?= $i === $pagina ? 'ativo' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <a href="?pagina=<?= $pagina + 1 ?>&busca=<?= urlencode($busca) ?>"
           class="pag-btn <?= $pagina >= $total_pag ? 'desabilitado' : '' ?>">Próxima →</a>
      </div>
      <p style="text-align:center;font-size:.78rem;color:var(--muted);margin-top:.75rem">
        Página <?= $pagina ?> de <?= $total_pag ?> — <?= $total ?> registros
      </p>
    <?php endif; ?>
  <?php endif; ?>
</main>

</body>
</html>
