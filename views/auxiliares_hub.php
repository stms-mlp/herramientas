<?php
/** @var array $cfg */
// Agrupar catálogos por su "grupo"
$grupos = [];
foreach ($cfg as $key => $d) {
    $grupos[$d['grupo']][$key] = $d;
}
// Catálogos con pantalla propia (no genéricos) o pendientes
$grupos['Equipos']['__tipos']   = ['label' => 'Tipos de equipo', 'href' => '?r=tipos', 'soon' => true];
$grupos['Catálogo']['__modelos'] = ['label' => 'Modelos', 'href' => '?r=modelos'];
$grupos['Catálogo']['__params']  = ['label' => 'Parámetros', 'href' => '?r=parametros', 'soon' => true];
$grupos['Organización']['__areas'] = ['label' => 'Reparticiones', 'href' => '?r=areas'];
$grupos['Organización']['__users'] = ['label' => 'Usuarios', 'href' => '?r=usuarios', 'soon' => true];
ksort($grupos);
?>
<p class="ayuda">Catálogos editables del sistema. Lo que cambia con el tiempo se gestiona acá, sin tocar código.</p>

<div class="aux-hub">
  <?php foreach ($grupos as $grupo => $items): ?>
    <div class="aux-card">
      <h3><?= h($grupo) ?></h3>
      <ul>
        <?php foreach ($items as $key => $d): ?>
          <li>
            <?php if (!empty($d['soon'])): ?>
              <span class="soon"><?= h($d['label']) ?> <small>(próximamente)</small></span>
            <?php else: ?>
              <a href="<?= isset($d['href']) ? h($d['href']) : '?r=aux.tabla&t=' . h($key) ?>"><?= h($d['label']) ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</div>
