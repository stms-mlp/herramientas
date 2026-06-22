<?php /** @var array $modelos @var array $marcas @var array $tipos @var array|null $edit @var string $q @var int $m */ ?>
<p class="bread"><a href="?r=auxiliares">← Tablas Auxiliares</a> / <strong>Modelos</strong></p>

<form method="get" class="filtros">
  <input type="hidden" name="r" value="modelos">
  <label>Marca
    <select name="m" onchange="this.form.submit()">
      <option value="0">— Todas —</option>
      <?php foreach ($marcas as $ma): ?>
        <option value="<?= (int)$ma['id'] ?>" <?= $m === (int)$ma['id'] ? 'selected' : '' ?>><?= h($ma['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Modelo <input name="q" value="<?= h($q) ?>" placeholder="buscar…"></label>
  <button type="submit">Buscar</button>
</form>

<table class="tabla">
  <thead><tr><th>#</th><th>Modelo</th><th>Marca</th><th>Categoría</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($modelos as $mo): ?>
    <tr>
      <td class="mono"><?= (int)$mo['id'] ?></td>
      <td><?= h($mo['nombre']) ?></td>
      <td><?= h($mo['marca']) ?></td>
      <td><?= h($mo['tipo'] ?: '—') ?></td>
      <td class="acc">
        <a href="?r=modelos&id=<?= (int)$mo['id'] ?><?= $m ? '&m=' . $m : '' ?>">editar</a>
        · <a href="#" onclick="document.getElementById('delm<?= (int)$mo['id'] ?>').submit();return false;" class="del">borrar</a>
        <form id="delm<?= (int)$mo['id'] ?>" method="post" action="?r=modelos.borrar" style="display:none">
          <?= csrf_input() ?><input type="hidden" name="id" value="<?= (int)$mo['id'] ?>">
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$modelos): ?><tr><td colspan="5" class="vacio">Sin modelos.</td></tr><?php endif; ?>
  </tbody>
</table>

<form method="post" action="?r=modelos.guardar" class="form aux-form">
  <?= csrf_input() ?>
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <fieldset>
    <legend><?= $edit ? 'Editar modelo #' . (int)$edit['id'] : 'Nuevo modelo' ?></legend>
    <div class="cols">
      <label>Modelo <input name="nombre" value="<?= h($edit['nombre'] ?? '') ?>" required></label>
      <label>Marca
        <select name="marca_id" required>
          <option value="">—</option>
          <?php foreach ($marcas as $ma): ?>
            <option value="<?= (int)$ma['id'] ?>" <?= ($edit['marca_id'] ?? $m) == $ma['id'] ? 'selected' : '' ?>><?= h($ma['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Categoría (tipo)
        <select name="tipo_id">
          <option value="">— sin definir —</option>
          <?php foreach ($tipos as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= ($edit['tipo_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>><?= h($t['nombre_es']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="acciones">
      <button type="submit"><?= $edit ? 'Guardar' : 'Agregar' ?></button>
      <?php if ($edit): ?><a href="?r=modelos" class="btn-sec">Cancelar</a><?php endif; ?>
      <span class="ayuda">¿Falta una marca? Cargala en <a href="?r=aux.tabla&t=marcas">Marcas</a>.</span>
    </div>
  </fieldset>
</form>
