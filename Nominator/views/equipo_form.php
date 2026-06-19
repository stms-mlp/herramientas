<?php /** @var array $areas @var array $tipos @var array $estados */ ?>
<form method="post" action="?r=equipos.crear" class="form">
  <?= csrf_input() ?>

  <fieldset>
    <legend>Identificación</legend>
    <div class="cols">
      <label>Tipo de equipo
        <select name="tipo_id" id="tipo" required>
          <option value="">—</option>
          <?php foreach ($tipos as $t): ?>
            <option value="<?= (int)$t['id'] ?>" data-host="<?= (int)$t['lleva_hostname'] ?>">
              <?= h($t['nombre_es']) ?> (<?= h($t['codigo']) ?>)
            </option>
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
      <label>ID patrimonial
        <input name="id_patrimonial" placeholder="ej. MLP-0042">
      </label>
    </div>
    <div class="preview" id="preview">
      <span class="lbl">Hostname:</span> <span class="host" id="host">—</span>
      <div class="avisos" id="avisos"></div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Datos técnicos</legend>
    <div class="cols">
      <label>Marca <input name="marca"></label>
      <label>Modelo <input name="modelo"></label>
      <label>N° de serie <input name="n_serie"></label>
      <label>N° de parte <input name="n_parte"></label>
      <label>IP <input name="ip" placeholder="192.168.0.x"></label>
      <label>Estado
        <select name="estado_id">
          <?php foreach ($estados as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= h($s['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </fieldset>

  <fieldset>
    <legend>Titularidad y tenencia</legend>
    <div class="cols">
      <label>Titularidad
        <select name="titularidad">
          <option>Municipal</option>
          <option>Personal</option>
        </select>
      </label>
      <label>Tenencia
        <select name="tenencia">
          <option>En sede</option>
          <option>Domicilio de empleado</option>
        </select>
      </label>
      <label>Tenedor / Responsable <input name="responsable"></label>
      <label>Tenedor (si difiere) <input name="tenedor"></label>
    </div>
  </fieldset>

  <fieldset>
    <legend>Notas</legend>
    <label>Observaciones <textarea name="observaciones" rows="2"></textarea></label>
    <label>Notas técnicas / operativas (rol, software, VPN, etc.)
      <textarea name="notas_tecnicas" rows="2"></textarea></label>
  </fieldset>

  <div class="acciones">
    <button type="submit">Crear equipo</button>
    <a href="?r=equipos" class="btn-sec">Cancelar</a>
  </div>
</form>

<script>
const tipo = document.getElementById('tipo'),
      area = document.getElementById('area'),
      host = document.getElementById('host'),
      avisos = document.getElementById('avisos');

async function preview() {
  const t = tipo.value, a = area.value;
  if (!t || !a) { host.textContent = '—'; avisos.innerHTML = ''; return; }
  const opt = tipo.options[tipo.selectedIndex];
  if (opt.dataset.host === '0') {
    host.textContent = '(sin hostname)';
    avisos.innerHTML = '<em>Este tipo se asocia a un equipo padre y no genera nombre de red.</em>';
    return;
  }
  try {
    const res = await fetch('?r=equipos.preview&area=' + a + '&tipo=' + t);
    const j = await res.json();
    host.textContent = j.hostname || j.error || '—';
    avisos.innerHTML = (j.avisos || []).map(x => '<div class="aviso">⚠ ' + x + '</div>').join('');
  } catch (e) { host.textContent = '—'; }
}
tipo.addEventListener('change', preview);
area.addEventListener('change', preview);
</script>
