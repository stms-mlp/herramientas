<?php /** @var array|null $flash */ ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingresar · Nominator</title>
<link rel="stylesheet" href="assets/pcb.css">
</head>
<body class="pcb login-body">
<div class="login-card">
  <img src="<?= h(ORG_ESCUDO) ?>" alt="Escudo" class="escudo-login" onerror="this.style.display='none'">
  <h1 class="logo">NOMINATOR</h1>
  <p class="sub"><?= h(ORG_DEPTO) ?><br><?= h(ORG_NOMBRE) ?></p>
  <?php if ($flash): ?><div class="flash <?= h($flash['tipo']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>
  <form method="post" action="?r=login">
    <?= csrf_input() ?>
    <label>Usuario<input name="usuario" autofocus required></label>
    <label>Clave<input type="password" name="clave" required></label>
    <button type="submit">Ingresar</button>
  </form>
  <p class="nota">
    <a href="agente/Reportar-Hardware.bat" download class="agente-dl">⬇ Descargar agente de hardware</a>
  </p>
  <p class="nota tenue">Relevá esta PC y envialo al inventario (doble clic).</p>
</div>
</body>
</html>
