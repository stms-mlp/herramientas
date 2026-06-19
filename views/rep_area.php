<?php /** @var array $area @var array $equipos */ ?>
<section class="rep-cuerpo">
  <h2 class="rep-sub">Declaración de inventario — <?= h($area['descripcion']) ?></h2>
  <p class="rep-meta">
    Código de repartición: <strong class="mono"><?= h($area['codigo']) ?></strong> ·
    Equipos municipales activos: <strong><?= count($equipos) ?></strong>
  </p>

  <?php if (!$equipos): ?>
    <p>La repartición no tiene equipos municipales activos registrados.</p>
  <?php else: ?>
    <table class="rep-tabla full">
      <thead>
        <tr><th>#</th><th>Hostname</th><th>Tipo</th><th>Marca / Modelo</th>
            <th>N° de serie</th><th>ID patrimonial</th><th>IP</th></tr>
      </thead>
      <tbody>
      <?php foreach ($equipos as $i => $e): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td class="mono"><?= h($e['hostname'] ?: '—') ?></td>
          <td><?= h($e['tnom']) ?></td>
          <td><?= h(nombre_equipo($e)) ?></td>
          <td><?= h($e['n_serie'] ?: '—') ?></td>
          <td><?= h($e['id_patrimonial'] ?: '—') ?></td>
          <td class="mono"><?= h($e['ip'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="rep-nota">
      Este extracto incluye únicamente equipos de titularidad <strong>Municipal</strong>
      activos. Los equipos personales no se declaran como propios del municipio.
    </p>
  <?php endif; ?>
</section>
