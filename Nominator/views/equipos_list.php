<?php /** @var array $equipos @var array $areas @var int $filtroArea */ ?>
<form method="get" class="filtros">
  <input type="hidden" name="r" value="equipos">
  <label>Repartición
    <select name="area" onchange="this.form.submit()">
      <option value="0">— Todas —</option>
      <?php foreach ($areas as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= $filtroArea === (int)$a['id'] ? 'selected' : '' ?>>
          <?= h($a['codigo']) ?> — <?= h($a['descripcion']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php if ($filtroArea): ?>
    <a class="btn" href="?r=reporte.area&area=<?= $filtroArea ?>" target="_blank">📄 Extracto del área</a>
  <?php endif; ?>
</form>

<?php if (!$equipos): ?>
  <p class="vacio">No hay equipos<?= $filtroArea ? ' en esta repartición' : '' ?>.</p>
<?php else: ?>
<table class="tabla">
  <thead>
    <tr><th>Hostname</th><th>Tipo</th><th>Marca/Modelo</th><th>Repartición</th>
        <th>IP</th><th>Estado</th><th>Titular.</th></tr>
  </thead>
  <tbody>
  <?php foreach ($equipos as $e): ?>
    <tr>
      <td class="mono"><a href="?r=equipos.ver&id=<?= (int)$e['id'] ?>">
        <?= h($e['hostname'] ?: '—') ?></a></td>
      <td><?= h($e['tnom']) ?></td>
      <td class="<?= nombre_equipo($e) === 'Genérico (armado)' ? 'tenue' : '' ?>"><?= h(nombre_equipo($e)) ?></td>
      <td><?= h($e['acod']) ?></td>
      <td class="mono"><?= h($e['ip']) ?></td>
      <td><?= h($e['enom']) ?></td>
      <td><?= $e['titularidad'] === 'Personal' ? '<span class="tag pers">Personal</span>' : 'Municipal' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
