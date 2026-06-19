<?php
/** @var array|null $eq @var array|null $old @var array $areas @var array $tipos @var array $estados
 *  @var array $componentes @var array $valores @var array $atrMapa @var array $tiposComp
 *  @var array $catMarca @var array $catModelo */
$edit = $eq !== null;
$val = function (string $k, string $def = '') use ($eq, $old) {
    if ($old !== null && array_key_exists($k, $old)) return (string)$old[$k];
    if ($eq !== null && array_key_exists($k, $eq)) return (string)($eq[$k] ?? '');
    return $def;
};
$sel = fn(string $k, $v) => (string)$val($k) === (string)$v ? 'selected' : '';
?>
<form method="post" action="?r=<?= $edit ? 'equipos.actualizar' : 'equipos.crear' ?>" class="form">
  <?= csrf_input() ?>
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$eq['id'] ?>"><?php endif; ?>

  <fieldset>
    <legend>Identificación</legend>
    <div class="cols">
      <label>Tipo de equipo
        <select name="tipo_id" id="tipo" required>
          <option value="">—</option>
          <?php foreach ($tipos as $t): ?>
            <option value="<?= (int)$t['id'] ?>" data-host="<?= (int)$t['lleva_hostname'] ?>" <?= $sel('tipo_id', $t['id']) ?>>
              <?= h($t['nombre_es']) ?> (<?= h($t['codigo']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Repartición
        <select name="area_id" id="area" required>
          <option value="">—</option>
          <?php foreach ($areas as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= $sel('area_id', $a['id']) ?>>
              <?= h($a['codigo']) ?> — <?= h($a['descripcion']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>ID patrimonial
        <input name="id_patrimonial" value="<?= h($val('id_patrimonial')) ?>" placeholder="ej. MLP-0042">
      </label>
    </div>
    <label>Hostname <span class="hint">(recomendación editable — dejalo vacío para autogenerar)</span>
      <div class="host-row">
        <input name="hostname" id="hostname" class="mono host-input"
               value="<?= h($val('hostname')) ?>" maxlength="15" autocomplete="off"
               placeholder="se sugiere al elegir tipo y repartición">
        <button type="button" id="btn-sugerir" class="btn-sec">Sugerir</button>
      </div>
    </label>
    <div class="avisos" id="avisos"></div>
  </fieldset>

  <fieldset>
    <legend>Ficha del tipo</legend>
    <div id="attr-cont"><p class="ayuda">Elegí un tipo de equipo para ver sus campos.</p></div>
  </fieldset>

  <fieldset>
    <legend>Datos técnicos</legend>
    <p class="ayuda">Marca y modelo son opcionales: para equipos <strong>armados/genéricos</strong>
       dejalos vacíos (se mostrará «Genérico (armado)») y cargá el detalle en Componentes.</p>
    <div class="cols">
      <label>Marca <input name="marca" list="dl-marca" value="<?= h($val('marca')) ?>" placeholder="(opcional)"></label>
      <label>Modelo <input name="modelo" list="dl-modelo" value="<?= h($val('modelo')) ?>" placeholder="(opcional)"></label>
      <label>N° de serie <input name="n_serie" value="<?= h($val('n_serie')) ?>"></label>
      <label>N° de parte <input name="n_parte" value="<?= h($val('n_parte')) ?>"></label>
      <label>IP <input name="ip" value="<?= h($val('ip')) ?>" placeholder="192.168.0.x"></label>
      <label>Estado
        <select name="estado_id">
          <?php foreach ($estados as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= $sel('estado_id', $s['id']) ?>><?= h($s['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </fieldset>

  <fieldset>
    <legend>Componentes</legend>
    <div class="import-row">
      <input type="file" id="reporte" accept=".txt,.html,.htm,.csv,text/plain">
      <button type="button" id="btn-analizar" class="btn-sec">Analizar CPU-Z / HWMonitor</button>
      <span id="imp-msg" class="ayuda"></span>
    </div>
    <table class="tabla comp-tabla">
      <thead><tr><th>Tipo</th><th>Marca</th><th>Modelo</th><th>N° serie</th><th>Velocidad</th><th>Memoria</th><th>Bus</th><th></th></tr></thead>
      <tbody id="comp-body"></tbody>
    </table>
    <button type="button" id="btn-add-comp" class="btn-sec">+ Componente</button>
  </fieldset>

  <fieldset>
    <legend>Titularidad y tenencia</legend>
    <div class="cols">
      <label>Titularidad
        <select name="titularidad">
          <option <?= $sel('titularidad', 'Municipal') ?>>Municipal</option>
          <option <?= $sel('titularidad', 'Personal') ?>>Personal</option>
        </select>
      </label>
      <label>Tenencia
        <select name="tenencia">
          <option <?= $sel('tenencia', 'En sede') ?>>En sede</option>
          <option <?= $sel('tenencia', 'Domicilio de empleado') ?>>Domicilio de empleado</option>
        </select>
      </label>
      <label>Responsable <input name="responsable" value="<?= h($val('responsable')) ?>"></label>
      <label>Tenedor (si difiere) <input name="tenedor" value="<?= h($val('tenedor')) ?>"></label>
    </div>
  </fieldset>

  <fieldset>
    <legend>Notas</legend>
    <label>Observaciones <textarea name="observaciones" rows="2"><?= h($val('observaciones')) ?></textarea></label>
    <label>Notas técnicas / operativas (rol, software, VPN, etc.)
      <textarea name="notas_tecnicas" rows="2"><?= h($val('notas_tecnicas')) ?></textarea></label>
  </fieldset>

  <div class="acciones">
    <button type="submit"><?= $edit ? 'Guardar cambios' : 'Crear equipo' ?></button>
    <a href="?r=<?= $edit ? 'equipos.ver&id=' . (int)$eq['id'] : 'equipos' ?>" class="btn-sec">Cancelar</a>
  </div>
</form>

<?php // datalists compartidos ?>
<datalist id="dl-marca"><?php foreach ($catMarca as $m): ?><option value="<?= h($m) ?>"><?php endforeach; ?></datalist>
<datalist id="dl-modelo"><?php foreach ($catModelo as $m): ?><option value="<?= h($m) ?>"><?php endforeach; ?></datalist>
<datalist id="dl-comp-tipo"><?php foreach ($tiposComp as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist>
<datalist id="dl-comp-marca"><?php foreach ($catMarca as $m): ?><option value="<?= h($m) ?>"><?php endforeach; ?></datalist>
<datalist id="dl-comp-modelo"><?php foreach ($catModelo as $m): ?><option value="<?= h($m) ?>"><?php endforeach; ?></datalist>

<script src="assets/equipos.js"></script>
<script>
window.ATTR_MAP = <?= json_encode($atrMapa, JSON_UNESCAPED_UNICODE) ?>;
const VALORES = <?= json_encode((object)$valores, JSON_UNESCAPED_UNICODE) ?>;
const COMPS   = <?= json_encode(array_values($componentes), JSON_UNESCAPED_UNICODE) ?>;

const tipo = document.getElementById('tipo'),
      area = document.getElementById('area'),
      hostInput = document.getElementById('hostname'),
      avisos = document.getElementById('avisos'),
      attrCont = document.getElementById('attr-cont'),
      compBody = document.getElementById('comp-body');
let editado = <?= $val('hostname') !== '' ? 'true' : 'false' ?>;

hostInput.addEventListener('input', () => { editado = true; });
function llevaHost() { const o = tipo.options[tipo.selectedIndex]; return !o || o.dataset.host !== '0'; }

async function sugerir(forzar) {
  if (!llevaHost()) {
    hostInput.value = ''; hostInput.disabled = true;
    avisos.innerHTML = '<em>Este tipo se asocia a un equipo padre y no genera nombre de red.</em>';
    return;
  }
  hostInput.disabled = false;
  if (!forzar && editado) return;
  if (!tipo.value || !area.value) { avisos.innerHTML = ''; return; }
  try {
    const j = await (await fetch('?r=equipos.preview&area=' + area.value + '&tipo=' + tipo.value)).json();
    if (j.hostname) { hostInput.value = j.hostname; editado = false; }
    avisos.innerHTML = (j.avisos || []).map(x => '<div class="aviso">⚠ ' + x + '</div>').join('')
                     + (j.error ? '<div class="aviso">' + j.error + '</div>' : '');
  } catch (e) {}
}

function refrescarTipo() {
  nmRenderAtributos(attrCont, tipo.value, '', VALORES);
  sugerir(false);
}
tipo.addEventListener('change', refrescarTipo);
area.addEventListener('change', () => sugerir(false));
document.getElementById('btn-sugerir').addEventListener('click', () => sugerir(true));
document.getElementById('btn-add-comp').addEventListener('click', () => compBody.appendChild(nmCompRow('', {})));
document.getElementById('btn-analizar').addEventListener('click', () =>
  nmAnalizar(document.getElementById('reporte'), compBody, '', document.getElementById('imp-msg')));

// Estado inicial (alta o edición)
if (tipo.value) nmRenderAtributos(attrCont, tipo.value, '', VALORES);
COMPS.forEach(c => compBody.appendChild(nmCompRow('', c)));
if (!llevaHost()) sugerir(false);
</script>
