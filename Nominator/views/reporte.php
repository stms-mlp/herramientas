<?php /** @var string $titulo @var string $contenido */ ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titulo) ?> · <?= h(ORG_NOMBRE) ?></title>
<link rel="stylesheet" href="assets/pcb.css">
</head>
<body class="reporte">
<header class="rep-cab">
  <img src="<?= h(ORG_ESCUDO) ?>" alt="Escudo" class="rep-escudo" onerror="this.style.visibility='hidden'">
  <div class="rep-org">
    <strong><?= h(ORG_NOMBRE) ?></strong>
    <span><?= h(ORG_DEPTO) ?></span>
  </div>
  <div class="rep-tit"><?= h($titulo) ?></div>
</header>

<?= $contenido ?>

<footer class="rep-pie">
  <span><?= h(ORG_DEPTO) ?> — <?= h(ORG_NOMBRE) ?></span>
  <span>Emitido: <?= h(date('d/m/Y H:i')) ?></span>
</footer>

<div class="rep-acciones no-print">
  <button onclick="window.print()">Imprimir / PDF</button>
  <a href="javascript:history.back()">Volver</a>
</div>
</body>
</html>
