<?php /** @var array $reportes */ ?>
<p class="ayuda">Reportes de hardware enviados por el <strong>agente</strong> de los
   usuarios (script de doble clic). Revisá cada uno y creá el equipo eligiendo
   área y tipo; los componentes se cargan solos.</p>

<?php if (!$reportes): ?>
  <p class="vacio">No hay reportes pendientes.</p>
<?php else: ?>
<table class="tabla">
  <thead>
    <tr><th>Recibido</th><th>Equipo (host)</th><th>Usuario</th><th>Comp.</th><th>Resumen</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($reportes as $r): ?>
    <tr>
      <td class="mono"><?= h($r['recibido']) ?></td>
      <td class="mono"><?= h($r['origen_host'] ?: '—') ?></td>
      <td><?= h($r['origen_usuario'] ?: '—') ?></td>
      <td class="num"><?= (int)$r['n_componentes'] ?></td>
      <td><small class="hw"><?= h($r['resumen']) ?></small></td>
      <td><a class="btn" href="?r=reportes.ver&id=<?= (int)$r['id'] ?>">Procesar</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
