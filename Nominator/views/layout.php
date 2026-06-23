<?php /** @var string $titulo @var string $contenido */ ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titulo) ?> · Nominator</title>
<link rel="stylesheet" href="assets/pcb.css">
</head>
<body class="pcb">
<?php $u = usuario_actual(); ?>
<header class="barra">
  <div class="marca">
    <img src="<?= h(ORG_ESCUDO) ?>" alt="Escudo" class="escudo" onerror="this.style.display='none'">
    <div>
      <span class="logo">NOMINATOR</span>
      <small><?= h(ORG_DEPTO) ?> · <?= h(ORG_NOMBRE) ?></small>
    </div>
  </div>
  <?php if ($u): ?>
  <nav class="menu">
    <a href="?r=dashboard">Tablero</a>
    <a href="?r=equipos">Equipos</a>
    <a href="?r=areas">Reparticiones</a>
    <?php if (puede(ROL_TECNICO)): ?>
      <a href="?r=reportes">Reportes</a>
    <?php endif; ?>
    <?php if (puede(ROL_ADMIN)): ?>
      <a href="?r=auxiliares">Auxiliares</a>
    <?php endif; ?>
    <?php if (puede(ROL_TECNICO)): ?>
      <a href="?r=equipos.nuevo" class="cta">+ Equipo</a>
      <a href="?r=equipos.lote" class="cta">+ Lote</a>
    <?php endif; ?>
    <span class="usr"><?= h($u['nombre'] ?: $u['usuario']) ?> · <?= h($u['rol']) ?></span>
    <a href="?r=logout" class="salir">Salir</a>
  </nav>
  <?php endif; ?>
</header>

<main class="placa">
  <?php $f = $flash ?? null; if ($f): ?>
    <div class="flash <?= h($f['tipo']) ?>"><?= h($f['msg']) ?></div>
  <?php endif; ?>
  <h1 class="titulo"><?= h($titulo) ?></h1>
  <?= $contenido ?>
</main>

<footer class="pie">
  <span><?= h(ORG_DEPTO) ?> — <?= h(ORG_NOMBRE) ?></span>
</footer>
</body>
</html>
