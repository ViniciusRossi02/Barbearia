<?php
require_once __DIR__ . '/php/agendar_sql.php';

// Garante que a tabela existe
criar_tabela_usuarios();

$sucesso = '';
$erro    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = trim($_POST['senha']   ?? '');
    $confirma = trim($_POST['confirma'] ?? '');

    // Validações
    if (!$usuario || !$senha || !$confirma) {
        $erro = 'Preencha todos os campos.';
    } elseif (strlen($usuario) < 3) {
        $erro = 'O usuário precisa ter ao menos 3 caracteres.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha precisa ter ao menos 6 caracteres.';
    } elseif ($senha !== $confirma) {
        $erro = 'As senhas não coincidem.';
    } else {
        // Tenta cadastrar
        $pdo  = conectar_banco();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = :usuario");
        $stmt->execute([':usuario' => $usuario]);
        $existe = (int) $stmt->fetchColumn();

        if ($existe) {
            $erro = "O usuário \"$usuario\" já está cadastrado.";
        } else {
            $hash = password_hash($senha, PASSWORD_BCRYPT);
            $ins  = $pdo->prepare("INSERT INTO usuarios (usuario, senha) VALUES (:usuario, :senha)");
            $ins->execute([':usuario' => $usuario, ':senha' => $hash]);
            $sucesso = "Conta criada com sucesso! Usuário: <strong>$usuario</strong>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barbearia — Cadastro</title>
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
    max-width: 420px;
    background: var(--cinza-m);
    border: 1px solid var(--borda);
    border-radius: 12px;
    padding: 2.5rem 2rem;
  }

  .topo { text-align: center; margin-bottom: 2rem; }
  .icone { font-size: 2.2rem; margin-bottom: .75rem; }
  h1 { font-size: 1.3rem; font-weight: 600; margin-bottom: .3rem; }
  .sub { font-size: .82rem; color: var(--muted); }

  /* Mensagens */
  .msg {
    padding: .8rem 1rem;
    border-radius: 6px;
    font-size: .875rem;
    margin-bottom: 1.25rem;
    text-align: center;
  }
  .msg.erro    { background: #2a0a0a; border: 1px solid #7a2020; color: #f08080; }
  .msg.sucesso { background: #1a2a00; border: 1px solid #4a7a00; color: #b8f040; }

  /* Campos */
  .campo { margin-bottom: 1rem; }
  .campo label {
    display: block;
    font-size: .75rem;
    color: var(--muted);
    margin-bottom: .4rem;
    letter-spacing: .04em;
    text-transform: uppercase;
  }
  .campo input {
    width: 100%;
    background: var(--cinza-e);
    border: 1px solid var(--borda);
    border-radius: 6px;
    padding: .75rem .9rem;
    color: var(--texto);
    font-size: .95rem;
    outline: none;
    transition: border-color .15s;
  }
  .campo input:focus { border-color: #555; }

  .dica {
    font-size: .72rem;
    color: var(--muted);
    margin-top: .3rem;
  }

  /* Botão */
  .btn-cadastrar {
    width: 100%;
    padding: .9rem;
    background: var(--verde);
    color: var(--preto);
    border: none;
    border-radius: 6px;
    font-size: .95rem;
    font-weight: 700;
    letter-spacing: .05em;
    cursor: pointer;
    text-transform: uppercase;
    margin-top: .5rem;
    transition: opacity .15s;
  }
  .btn-cadastrar:hover { opacity: .85; }
  .btn-cadastrar:disabled { opacity: .4; cursor: not-allowed; }

  /* Rodapé */
  .rodape {
    margin-top: 1.5rem;
    text-align: center;
    font-size: .8rem;
    color: var(--muted);
    display: flex;
    justify-content: center;
    gap: 1rem;
  }
  .rodape a {
    color: var(--muted);
    text-decoration: none;
    border-bottom: 1px solid var(--borda);
    padding-bottom: 1px;
  }
  .rodape a:hover { color: var(--texto); border-color: #555; }

  /* Indicador de força da senha */
  .forca-barra {
    height: 3px;
    border-radius: 2px;
    margin-top: .5rem;
    background: var(--borda);
    overflow: hidden;
  }
  .forca-fill {
    height: 100%;
    width: 0%;
    border-radius: 2px;
    transition: width .3s, background .3s;
  }
</style>
</head>
<body>

<div class="box">
  <div class="topo">
    <div class="icone">🔑</div>
    <h1>Criar Conta</h1>
    <p class="sub">Cadastro de acesso ao painel do barbeiro</p>
  </div>

  <?php if ($erro): ?>
    <div class="msg erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <?php if ($sucesso): ?>
    <div class="msg sucesso"><?= $sucesso ?> — <a href="login.php" style="color:inherit;font-weight:600">Fazer login →</a></div>
  <?php endif; ?>

  <?php if (!$sucesso): ?>
  <form method="POST" id="formCadastro">
    <div class="campo">
      <label>Usuário</label>
      <input type="text" name="usuario" id="usuario"
             placeholder="Ex: joao_barbeiro" required minlength="3"
             value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
      <p class="dica">Mínimo 3 caracteres, sem espaços.</p>
    </div>

    <div class="campo">
      <label>Senha</label>
      <input type="password" name="senha" id="senha"
             placeholder="Mínimo 6 caracteres" required minlength="6">
      <div class="forca-barra"><div class="forca-fill" id="forcaFill"></div></div>
      <p class="dica" id="forcaTexto"></p>
    </div>

    <div class="campo">
      <label>Confirmar senha</label>
      <input type="password" name="confirma" id="confirma"
             placeholder="Repita a senha" required>
      <p class="dica" id="dicaConfirma"></p>
    </div>

    <button type="submit" class="btn-cadastrar" id="btnCadastrar">Criar conta</button>
  </form>
  <?php endif; ?>

  <div class="rodape">
    <a href="login.php">Já tenho conta → Login</a>
    <a href="index.php">← Agendamentos</a>
  </div>
</div>

<script>
  const senhaInput   = document.getElementById('senha');
  const confirmaInput = document.getElementById('confirma');
  const forcaFill    = document.getElementById('forcaFill');
  const forcaTexto   = document.getElementById('forcaTexto');
  const dicaConfirma = document.getElementById('dicaConfirma');
  const btn          = document.getElementById('btnCadastrar');
  const usuarioInput = document.getElementById('usuario');

  // Remove espaços do usuário em tempo real
  usuarioInput?.addEventListener('input', () => {
    usuarioInput.value = usuarioInput.value.replace(/\s/g, '');
  });

  // Força da senha
  senhaInput?.addEventListener('input', () => {
    const v = senhaInput.value;
    let forca = 0;
    if (v.length >= 6)  forca++;
    if (v.length >= 10) forca++;
    if (/[A-Z]/.test(v)) forca++;
    if (/[0-9]/.test(v)) forca++;
    if (/[^A-Za-z0-9]/.test(v)) forca++;

    const cores  = ['#f08080','#f0a040','#f0c040','#8fd44a','#c8f542'];
    const labels = ['Muito fraca','Fraca','Razoável','Boa','Forte'];
    const pct    = [20, 40, 60, 80, 100];

    const idx = Math.min(forca, 4);
    forcaFill.style.width      = pct[idx] + '%';
    forcaFill.style.background = cores[idx];
    forcaTexto.textContent     = v.length ? 'Força: ' + labels[idx] : '';
    checarConfirma();
  });

  // Checagem de confirmação
  function checarConfirma() {
    if (!confirmaInput.value) { dicaConfirma.textContent = ''; return; }
    if (senhaInput.value === confirmaInput.value) {
      dicaConfirma.style.color = '#8fd44a';
      dicaConfirma.textContent = '✓ Senhas coincidem';
    } else {
      dicaConfirma.style.color = '#f08080';
      dicaConfirma.textContent = '✗ Senhas não coincidem';
    }
  }
  confirmaInput?.addEventListener('input', checarConfirma);
</script>

</body>
</html>
