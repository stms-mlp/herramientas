<?php /** @var array $eq @var array $componentes */ ?>
<div class="acciones-top">
  <?php if (puede(ROL_TECNICO)): ?>
    <a class="btn" href="?r=equipos.editar&id=<?= (int)$eq['id'] ?>">✎ Editar</a>
  <?php endif; ?>
  <a class="btn" href="?r=reporte.ficha&id=<?= (int)$eq['id'] ?>" target="_blank">📄 Ficha de hardware</a>
  <a class="btn-sec" href="?r=equipos">Volver</a>
  <?php if (puede(ROL_ADMIN)): ?>
    <button type="button" class="btn-del" onclick="if(confirm('¿Eliminar definitivamente el equipo «<?= h($eq['hostname'] ?: ('#'.$eq['id'])) ?>» y todos sus componentes/datos asociados?')) document.getElementById('delEq').submit();">🗑 Eliminar</button>
    <form id="delEq" method="post" action="?r=equipos.borrar" style="display:none">
      <?= csrf_input() ?><input type="hidden" name="id" value="<?= (int)$eq['id'] ?>">
    </form>
  <?php endif; ?>
</div>

<div class="ficha">
  <div class="dato"><span>Nombre de dispositivo</span><b class="mono"><?= h($eq['hostname'] ?: '—') ?></b></div>
  <div class="dato"><span>ID patrimonial</span><b><?= h($eq['id_patrimonial'] ?: '—') ?></b></div>
  <div class="dato"><span>Tipo</span><b><?= h($eq['tnom']) ?></b></div>
  <div class="dato"><span>Repartición</span><b><?= h($eq['acod']) ?> — <?= h($eq['adesc']) ?></b></div>
  <div class="dato"><span>Estado</span><b><?= h($eq['enom']) ?></b></div>
  <div class="dato"><span>Marca / Modelo</span><b><?= h(nombre_equipo($eq)) ?></b></div>
  <div class="dato"><span>N° de serie</span><b><?= h($eq['n_serie'] ?: '—') ?></b></div>
  <div class="dato"><span>IP</span><b class="mono"><?= h($eq['ip'] ?: '—') ?></b></div>
  <div class="dato"><span>Titularidad</span><b><?= h($eq['titularidad']) ?></b></div>
  <div class="dato"><span>Tenencia</span><b><?= h($eq['tenencia']) ?><?= $eq['tenedor'] ? ' ('.h($eq['tenedor']).')' : '' ?></b></div>
  <div class="dato"><span>Responsable</span><b><?= h($eq['responsable'] ?: '—') ?></b></div>
  <?php if (!empty($eq['anydesk_id'])): ?>
    <div class="dato"><span>AnyDesk</span>
      <b><a href="anydesk:<?= h($eq['anydesk_id']) ?>" class="mono" title="Abrir AnyDesk y conectar">🔗 <?= h($eq['anydesk_id']) ?></a></b>
    </div>
  <?php endif; ?>
  <?php if (puede(ROL_ADMIN) && !empty($eq['anydesk_clave'])): ?>
    <div class="dato"><span>Clave remota <small>(admin)</small></span>
      <b class="mono"><span id="adk" data-clave="<?= h($eq['anydesk_clave']) ?>">••••••</span>
        <a href="#" onclick="var s=document.getElementById('adk');s.textContent=s.dataset.clave;return false;">mostrar</a></b>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($atributos)): ?>
  <h2>Ficha del tipo (<?= h($eq['tnom']) ?>)</h2>
  <div class="ficha">
    <?php foreach ($atributos as $a): ?>
      <div class="dato"><span><?= h($a['nombre']) ?></span><b><?= h($a['valor']) ?></b></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2>Componentes</h2>
<?php $resHw = resumen_hardware((int)$eq['id']); ?>
<?php if ($resHw): ?>
  <p class="resumen-hw">🧩 <?= h($resHw) ?></p>
<?php endif; ?>
<?php if (!$componentes): ?>
  <p class="vacio">Sin componentes cargados. Cargalos a mano o importá un reporte CPU-Z/HWMonitor desde «Editar».</p>
<?php else: ?>
  <table class="tabla">
    <thead><tr><th>Tipo</th><th>Marca</th><th>Modelo</th><th>Velocidad</th><th>Memoria</th><th>Bus</th></tr></thead>
    <tbody>
    <?php foreach ($componentes as $c): ?>
      <tr><td><?= h($c['tipo']) ?></td><td><?= h($c['marca']) ?></td><td><?= h($c['modelo']) ?></td>
          <td><?= h($c['velocidad']) ?></td><td><?= h($c['memoria']) ?></td><td><?= h($c['bus']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if ($eq['notas_tecnicas']): ?>
  <h2>Notas técnicas</h2>
  <div class="nota-box"><?= nl2br(h($eq['notas_tecnicas'])) ?></div>
<?php endif; ?>
