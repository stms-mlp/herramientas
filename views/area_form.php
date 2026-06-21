<?php
/** @var array|null $area @var array $secretarias */
$edit = $area !== null;
// Derivar nivel/abreviatura/padre del código existente
$cod = (string)($area['codigo'] ?? '');
$esDep = str_contains($cod, '#');
$nivel = $edit ? ($esDep ? 'dependencia' : 'secretaria') : 'dependencia';
$abrev = $edit ? ($esDep ? explode('#', $cod)[0] : $cod) : '';
$padre = $edit && $esDep ? explode('#', $cod)[1] : '';
$estructura = (string)($area['estructura'] ?? '');
$estrDep = ['Subsecretaría', 'Dirección', 'Departamento', 'División'];
?>
<form method="post" action="?r=<?= $edit ? 'areas.actualizar' : 'areas.crear' ?>" class="form" id="areaForm">
  <?= csrf_input() ?>
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$area['id'] ?>"><?php endif; ?>

  <fieldset>
    <legend>Nivel</legend>
    <div class="cols">
      <label class="radio"><input type="radio" name="nivel" value="secretaria" id="nivSec"
        <?= $nivel === 'secretaria' ? 'checked' : '' ?>> Secretaría</label>
      <label class="radio"><input type="radio" name="nivel" value="dependencia" id="nivDep"
        <?= $nivel === 'dependencia' ? 'checked' : '' ?>> Dependencia (subsecretaría / dirección / departamento)</label>
    </div>
  </fieldset>

  <fieldset>
    <legend>Datos</legend>
    <label>Nombre / Descripción
      <input name="descripcion" value="<?= h($area['descripcion'] ?? '') ?>" required>
    </label>
    <div class="cols">
      <label>Abreviatura <span class="hint">(hasta 4 letras)</span>
        <input name="abreviatura" id="abrev" class="mono" maxlength="4" value="<?= h($abrev) ?>"
               pattern="[A-Za-z]{1,4}" style="text-transform:uppercase" required>
      </label>

      <div id="boxDep">
        <label>Secretaría a la que pertenece
          <select name="secretaria" id="secretaria">
            <option value="">—</option>
            <?php foreach ($secretarias as $s): ?>
              <option value="<?= h($s['codigo']) ?>" <?= $padre === $s['codigo'] ? 'selected' : '' ?>>
                <?= h($s['codigo']) ?> — <?= h($s['descripcion']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Tipo de dependencia
          <select name="estructura" id="estrDep">
            <?php foreach ($estrDep as $e): ?>
              <option <?= $estructura === $e ? 'selected' : '' ?>><?= h($e) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    </div>

    <label>Ubicación <input name="ubicacion" value="<?= h($area['ubicacion'] ?? '') ?>" placeholder="dirección o link de mapa"></label>
    <label class="check">
      <input type="checkbox" name="activa" value="1" <?= (!$edit || $area['activa']) ? 'checked' : '' ?>> Activa
    </label>

    <p class="preview">
      <span class="lbl">Código:</span> <span class="host mono" id="pvCod">—</span>
      &nbsp;·&nbsp; <span class="lbl">Hostname base:</span> <span class="host mono" id="pvTok">—</span>
    </p>
  </fieldset>

  <div class="acciones">
    <button type="submit"><?= $edit ? 'Guardar cambios' : 'Crear repartición' ?></button>
    <a href="?r=areas" class="btn-sec">Cancelar</a>
  </div>
</form>

<script>
const nivSec = document.getElementById('nivSec'), boxDep = document.getElementById('boxDep'),
      abrev = document.getElementById('abrev'), secretaria = document.getElementById('secretaria'),
      pvCod = document.getElementById('pvCod'), pvTok = document.getElementById('pvTok');

function refrescar() {
  const sec = nivSec.checked;
  boxDep.style.display = sec ? 'none' : '';
  secretaria.required = !sec;
  const a = (abrev.value || '').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 4);
  abrev.value = a;
  let cod = '', tok = '';
  if (a) {
    if (sec) { cod = a; tok = a; }
    else {
      const p = (secretaria.value || '').toUpperCase();
      cod = p ? a + '#' + p : a + '#?';
      tok = p ? p + '-' + a : '?-' + a;
    }
  }
  pvCod.textContent = cod || '—';
  pvTok.textContent = tok || '—';
}
document.querySelectorAll('input[name="nivel"]').forEach(e => e.addEventListener('change', refrescar));
abrev.addEventListener('input', refrescar);
secretaria.addEventListener('change', refrescar);
refrescar();
</script>
