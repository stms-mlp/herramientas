<?php /** @var array $eq @var array $componentes */ ?>
<section class="rep-cuerpo">
  <h2 class="rep-sub">Ficha de hardware</h2>

  <table class="rep-tabla">
    <tr><th>Hostname</th><td class="mono"><?= h($eq['hostname'] ?: '—') ?></td>
        <th>ID patrimonial</th><td><?= h($eq['id_patrimonial'] ?: '—') ?></td></tr>
    <tr><th>Tipo</th><td><?= h($eq['tnom']) ?></td>
        <th>Estado</th><td><?= h($eq['enom']) ?></td></tr>
    <tr><th>Repartición</th><td colspan="3"><?= h($eq['acod']) ?> — <?= h($eq['adesc']) ?></td></tr>
    <tr><th>Marca / Modelo</th><td><?= h(trim(($eq['marca'] ?? '').' '.($eq['modelo'] ?? ''))) ?></td>
        <th>N° de serie</th><td><?= h($eq['n_serie'] ?: '—') ?></td></tr>
    <tr><th>IP</th><td class="mono"><?= h($eq['ip'] ?: '—') ?></td>
        <th>Titularidad</th><td><?= h($eq['titularidad']) ?></td></tr>
    <tr><th>Tenencia</th><td><?= h($eq['tenencia']) ?></td>
        <th>Responsable</th><td><?= h($eq['responsable'] ?: '—') ?></td></tr>
  </table>

  <h3 class="rep-sub">Componentes</h3>
  <?php if (!$componentes): ?>
    <p>Sin componentes registrados.</p>
  <?php else: ?>
    <table class="rep-tabla full">
      <thead><tr><th>Tipo</th><th>Marca</th><th>Modelo</th><th>Velocidad</th><th>Memoria</th><th>Bus</th></tr></thead>
      <tbody>
      <?php foreach ($componentes as $c): ?>
        <tr><td><?= h($c['tipo']) ?></td><td><?= h($c['marca']) ?></td><td><?= h($c['modelo']) ?></td>
            <td><?= h($c['velocidad']) ?></td><td><?= h($c['memoria']) ?></td><td><?= h($c['bus']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($eq['observaciones']): ?>
    <h3 class="rep-sub">Observaciones</h3>
    <p><?= nl2br(h($eq['observaciones'])) ?></p>
  <?php endif; ?>
</section>
