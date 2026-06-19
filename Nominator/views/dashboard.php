<?php /** @var array $stats @var array $porArea */ ?>
<div class="grid-stats">
  <a class="chip" href="?r=equipos"><span class="n"><?= $stats['equipos'] ?></span>Equipos</a>
  <a class="chip" href="?r=areas"><span class="n"><?= $stats['areas'] ?></span>Reparticiones</a>
  <div class="chip"><span class="n"><?= $stats['tipos'] ?></span>Tipos de equipo</div>
  <div class="chip <?= $stats['duplic'] ? 'alerta' : '' ?>">
    <span class="n"><?= $stats['duplic'] ?></span>Códigos duplicados
  </div>
</div>

<?php if ($stats['duplic']): ?>
<div class="flash error">
  Hay <?= $stats['duplic'] ?> código(s) de área duplicado(s) en el organigrama
  (ej. <code>DEPC#SGYA</code>). Revisá <a href="?r=areas">Reparticiones</a> antes
  de asignarles equipos: generan hostnames colisionantes.
</div>
<?php endif; ?>

<h2>Equipos por repartición</h2>
<?php if (!$porArea): ?>
  <p class="vacio">Todavía no hay equipos cargados.</p>
<?php else: ?>
  <table class="tabla">
    <thead><tr><th>Repartición</th><th>Equipos</th></tr></thead>
    <tbody>
    <?php foreach ($porArea as $row): ?>
      <tr><td><?= h($row['descripcion']) ?></td><td class="num"><?= (int)$row['n'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
