<?php
/** @var array $grupos  @var string $sec */
$actual = ($sec !== '' && isset($grupos[$sec])) ? $grupos[$sec] : null;
?>
<div class="acciones-top">
  <?php if (puede(ROL_ADMIN)): ?>
    <a class="btn" href="?r=areas.nueva">+ Nueva repartición</a>
  <?php endif; ?>
</div>

<?php if (!$actual): ?>
  <!-- MAESTRO: secretarías / jurisdicciones -->
  <p class="ayuda">Elegí una secretaría para ver sus reparticiones (subsecretarías,
     direcciones y departamentos están al mismo nivel).</p>
  <div class="grid-stats">
    <?php foreach ($grupos as $g): ?>
      <a class="chip <?= $g['duplic'] ? 'alerta' : '' ?>" href="?r=areas&sec=<?= h(urlencode($g['codigo'])) ?>">
        <span class="n"><?= count($g['areas']) ?></span>
        <strong><?= h($g['titulo']) ?></strong>
        <small class="mono"><?= h($g['codigo']) ?></small>
        <?php if ($g['duplic']): ?><small class="warn">⚠ <?= $g['duplic'] ?> duplicado(s)</small><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

<?php else: ?>
  <!-- DETALLE: reparticiones de la secretaría elegida -->
  <p class="bread"><a href="?r=areas">← Secretarías</a> /
     <strong><?= h($actual['titulo']) ?></strong>
     <span class="mono tenue">(<?= h($actual['codigo']) ?>)</span></p>
  <p class="ayuda">El <strong>token</strong> es la base del hostname
     (código invertido, <code>#</code> → <code>-</code>).</p>
  <table class="tabla">
    <thead>
      <tr><th>Código</th><th>Token</th><th>Descripción</th><th>Estructura</th><th>Padre</th><th>Activa</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($actual['areas'] as $a): ?>
      <tr class="<?= $a['duplicado'] ? 'fila-alerta' : '' ?> <?= $a['activa'] ? '' : 'inactiva' ?>">
        <td class="mono"><?= h($a['codigo']) ?><?= $a['duplicado'] ? ' ⚠' : '' ?></td>
        <td class="mono token"><?= h(nm_token_area($a['codigo'])) ?></td>
        <td><?= h($a['descripcion']) ?></td>
        <td><?= h($a['estructura']) ?></td>
        <td class="mono"><?= h($a['codigo_padre']) ?></td>
        <td><?= $a['activa'] ? 'Sí' : '—' ?></td>
        <td class="acc">
          <a href="?r=equipos&area=<?= (int)$a['id'] ?>">equipos</a>
          <?php if (puede(ROL_ADMIN)): ?>
            · <a href="?r=areas.editar&id=<?= (int)$a['id'] ?>">editar</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
