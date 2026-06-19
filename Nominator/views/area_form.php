<?php /** @var array|null $area @var array $padres */
$edit = $area !== null;
$v = fn(string $k) => h((string)($area[$k] ?? ''));
$estructuras = ['Intendencia', 'Secretaría', 'Subsecretaría', 'Dirección', 'Departamento', 'División'];
?>
<form method="post" action="?r=<?= $edit ? 'areas.actualizar' : 'areas.crear' ?>" class="form">
  <?= csrf_input() ?>
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$area['id'] ?>"><?php endif; ?>

  <fieldset>
    <legend>Repartición</legend>
    <div class="cols">
      <label>Código <span class="hint">(ej. DA#SGYA — hijo#padre)</span>
        <input name="codigo" class="mono" value="<?= $v('codigo') ?>" required>
      </label>
      <label>Abreviatura <input name="abreviatura" value="<?= $v('abreviatura') ?>"></label>
      <label>Estructura
        <select name="estructura">
          <option value="">—</option>
          <?php foreach ($estructuras as $e): ?>
            <option <?= ($area['estructura'] ?? '') === $e ? 'selected' : '' ?>><?= h($e) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Código padre
        <input name="codigo_padre" class="mono" list="padres" value="<?= $v('codigo_padre') ?>">
        <datalist id="padres">
          <?php foreach ($padres as $p): ?>
            <option value="<?= h($p['codigo']) ?>"><?= h($p['descripcion']) ?></option>
          <?php endforeach; ?>
        </datalist>
      </label>
    </div>
    <label>Descripción <input name="descripcion" value="<?= $v('descripcion') ?>" required></label>
    <label>Ubicación <input name="ubicacion" value="<?= $v('ubicacion') ?>" placeholder="dirección o link de mapa"></label>
    <label class="check">
      <input type="checkbox" name="activa" value="1" <?= (!$edit || $area['activa']) ? 'checked' : '' ?>>
      Activa
    </label>
    <?php if ($edit): ?>
      <p class="preview"><span class="lbl">Token de hostname:</span>
        <span class="host"><?= h(nm_token_area($area['codigo'])) ?></span></p>
    <?php endif; ?>
  </fieldset>

  <div class="acciones">
    <button type="submit"><?= $edit ? 'Guardar cambios' : 'Crear repartición' ?></button>
    <a href="?r=areas" class="btn-sec">Cancelar</a>
  </div>
</form>
