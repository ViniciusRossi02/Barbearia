<?php
require_once __DIR__ . '/php/agendar_sql.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['barbeiro_logado'])) {
    header('Location: admin.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = trim($_POST['senha']   ?? '');

    if (!$usuario || !$senha) {
        $erro = 'Preencha usuário e senha.';
    } elseif (verificar_login($usuario, $senha)) {
        $_SESSION['barbeiro_logado']  = true;
        $_SESSION['barbeiro_usuario'] = $usuario;
        header('Location: admin.php');
        exit;
    } else {
        $erro = 'Usuário ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barbearia — Login</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --preto:   #0a0a0a;
    --cinza-m: #1e1e1e;
    --cinza-e: #111111;
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
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem;
  }
  .box {
    width: 100%;
    max-width: 380px;
    background: var(--cinza-m);
    border: 1px solid var(--borda);
    border-radius: 12px;
    padding: 2.5rem 2rem;
  }
  .topo { text-align: center; margin-bottom: 2rem; }
  .icone { font-size: 2.2rem; margin-bottom: .75rem; }
  h1 { font-size: 1.3rem; font-weight: 600; margin-bottom: .3rem; }
  .sub { font-size: .82rem; color: var(--muted); }
  .erro {
    background: #2a0a0a; border: 1px solid #7a2020; color: #f08080;
    padding: .75rem 1rem; border-radius: 6px; font-size: .85rem;
    margin-bottom: 1.25rem; text-align: center;
  }
  .campo { margin-bottom: 1rem; }
  .campo label {
    display: block; font-size: .75rem; color: var(--muted);
    margin-bottom: .4rem; letter-spacing: .04em; text-transform: uppercase;
  }
  .campo input {
    width: 100%; background: var(--cinza-e); border: 1px solid var(--borda);
    border-radius: 6px; padding: .75rem .9rem; color: var(--texto);
    font-size: .95rem; outline: none; transition: border-color .15s;
  }
  .campo input:focus { border-color: #555; }
  .btn-entrar {
    width: 100%; padding: .9rem; background: var(--verde); color: var(--preto);
    border: none; border-radius: 6px; font-size: .95rem; font-weight: 700;
    letter-spacing: .05em; cursor: pointer; text-transform: uppercase;
    margin-top: .5rem; transition: opacity .15s;
  }
  .btn-entrar:hover { opacity: .85; }
  .rodape { margin-top: 1.5rem; text-align: center; font-size: .8rem; color: var(--muted); }
  .rodape a { color: var(--muted); text-decoration: none; border-bottom: 1px solid var(--borda); padding-bottom: 1px; }
  .rodape a:hover { color: var(--texto); border-color: #555; }
</style>
</head>
<body>
<div class="box">
  <div class="topo">
    <div class="icone">✂️</div>
    <h1>Área do Barbeiro</h1>
    <p class="sub">Acesso restrito ao painel</p>
  </div>
  <?php if ($erro): ?>
    <div class="erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="campo">
      <label>Usuário</label>
      <input type="text" name="usuario" placeholder="Digite seu usuário" required autofocus>
    </div>
    <div class="campo">
      <label>Senha</label>
      <input type="password" name="senha" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn-entrar">Entrar</button>
  </form>
  <div class="rodape">
    <a href="index.php">← Voltar para agendamentos</a>
  </div>
</div>
</body>
</html>
