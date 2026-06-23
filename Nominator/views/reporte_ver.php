<?php /** @var array $rep @var array $comp @var array $areas @var array $tipos @var array $estados */ ?>
<div class="acciones-top"><a class="btn-sec" href="?r=reportes">← Volver a reportes</a></div>

<div class="ficha">
  <div class="dato"><span>Equipo (host)</span><b class="mono"><?= h($rep['origen_host'] ?: '—') ?></b></div>
  <div class="dato"><span>Usuario</span><b><?= h($rep['origen_usuario'] ?: '—') ?></b></div>
  <div class="dato"><span>Recibido</span><b><?= h($rep['recibido']) ?></b></div>
  <div class="dato"><span>Origen IP</span><b class="mono"><?= h($rep['origen_ip'] ?: '—') ?></b></div>
</div>

<h2>Componentes detectados (<?= count($comp) ?>)</h2>
<?php if (!$comp): ?>
  <p class="vacio">No se reconocieron componentes en el reporte.</p>
<?php else: ?>
<table class="tabla">
  <thead><tr><th>Tipo</th><th>Marca</th><th>Modelo</th><th>N° serie</th><th>Velocidad</th><th>Memoria</th><th>Bus</th></tr></thead>
  <tbody>
  <?php foreach ($comp as $c): ?>
    <tr><td><?= h($c['tipo']) ?></td><td><?= h($c['marca']) ?></td><td><?= h($c['modelo']) ?></td>
        <td class="mono"><?= h($c['n_serie']) ?></td><td><?= h($c['velocidad']) ?></td>
        <td><?= h($c['memoria']) ?></td><td><?= h($c['bus']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2>Crear equipo desde este reporte</h2>
<form method="post" action="?r=reportes.procesar" class="form">
  <?= csrf_input() ?>
  <input type="hidden" name="reporte_id" value="<?= (int)$rep['id'] ?>">
  <div class="cols">
    <label>Tipo de equipo
      <select name="tipo_id" id="tipo" required>
        <option value="">—</option>
        <?php foreach ($tipos as $t): ?>
          <option value="<?= (int)$t['id'] ?>" data-host="<?= (int)$t['lleva_hostname'] ?>"
            <?= $t['codigo'] === 'DK' ? 'selected' : '' ?>><?= h($t['nombre_es']) ?> (<?= h($t['codigo']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Repartición
      <select name="area_id" id="area" required>
        <option value="">—</option>
        <?php foreach ($areas as $a): ?>
          <option value="<?= (int)$a['id'] ?>"><?= h($a['codigo']) ?> — <?= h($a['descripcion']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>ID patrimonial <input name="id_patrimonial" placeholder="ej. MLP-0042"></label>
    <label>Estado
      <select name="estado_id">
        <?php foreach ($estados as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['nombre']) ?></option><?php endforeach; ?>
      </select>
    </label>
  </div>
  <label>Hostname <span class="hint">(recomendación editable — vacío para autogenerar)</span>
    <div class="host-row">
      <input name="hostname" id="hostname" class="mono host-input" maxlength="15" autocomplete="off"
             placeholder="se sugiere al elegir tipo y repartición">
      <button type="button" id="btn-sugerir" class="btn-sec">Sugerir</button>
    </div>
  </label>
  <div class="avisos" id="avisos"></div>
  <div class="acciones"><button type="submit">Crear equipo + componentes</button></div>
</form>

<script>
const tipo = document.getElementById('tipo'), area = document.getElementById('area'),
      host = document.getElementById('hostname'), avisos = document.getElementById('avisos');
let editado = false;
host.addEventListener('input', () => { editado = true; });
function llevaHost() { const o = tipo.options[tipo.selectedIndex]; return !o || o.dataset.host !== '0'; }
async function sugerir(forzar) {
  if (!llevaHost()) { host.value = ''; host.disabled = true; avisos.innerHTML = '<em>Este tipo no genera hostname.</em>'; return; }
  host.disabled = false;
  if (!forzar && editado) return;
  if (!tipo.value || !area.value) return;
  try {
    const j = await (await fetch('?r=equipos.preview&area=' + area.value + '&tipo=' + tipo.value)).json();
    if (j.hostname) { host.value = j.hostname; editado = false; }
    avisos.innerHTML = (j.avisos || []).map(x => '<div class="aviso">⚠ ' + x + '</div>').join('');
  } catch (e) {}
}
tipo.addEventListener('change', () => sugerir(false));
area.addEventListener('change', () => sugerir(false));
document.getElementById('btn-sugerir').addEventListener('click', () => sugerir(true));
</script>
