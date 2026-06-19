<?php /** @var array $areas */ ?>
<p class="ayuda">
  Reparticiones del organigrama. El <strong>token</strong> es la base del hostname
  (código invertido, <code>#</code> → <code>-</code>).
</p>
<table class="tabla">
  <thead>
    <tr><th>Código</th><th>Token</th><th>Descripción</th><th>Estructura</th><th>Padre</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($areas as $a): ?>
    <tr class="<?= $a['duplicado'] ? 'fila-alerta' : '' ?>">
      <td class="mono"><?= h($a['codigo']) ?><?= $a['duplicado'] ? ' ⚠' : '' ?></td>
      <td class="mono token"><?= h(nm_token_area($a['codigo'])) ?></td>
      <td><?= h($a['descripcion']) ?></td>
      <td><?= h($a['estructura']) ?></td>
      <td class="mono"><?= h($a['codigo_padre']) ?></td>
      <td><a href="?r=equipos&area=<?= (int)$a['id'] ?>">equipos</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
