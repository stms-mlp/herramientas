<?php /** @var string $key @var array $def @var array $items @var array|null $edit */ ?>
<p class="bread"><a href="?r=auxiliares">← Tablas Auxiliares</a> / <strong><?= h($def['label']) ?></strong></p>

<table class="tabla">
  <thead><tr><th>#</th><th>Nombre</th><th>Activo</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($items as $it): ?>
    <tr class="<?= $it['activo'] ? '' : 'inactiva' ?>">
      <td class="mono"><?= (int)$it['id'] ?></td>
      <td><?= h($it['nombre']) ?></td>
      <td><?= $it['activo'] ? 'Sí' : '—' ?></td>
      <td class="acc">
        <a href="?r=aux.tabla&t=<?= h($key) ?>&id=<?= (int)$it['id'] ?>">editar</a>
        · <a href="#" onclick="document.getElementById('del<?= (int)$it['id'] ?>').submit();return false;" class="del">borrar</a>
        <form id="del<?= (int)$it['id'] ?>" method="post" action="?r=aux.borrar" style="display:none">
          <?= csrf_input() ?><input type="hidden" name="t" value="<?= h($key) ?>"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$items): ?><tr><td colspan="4" class="vacio">Sin registros.</td></tr><?php endif; ?>
  </tbody>
</table>

<form method="post" action="?r=aux.guardar" class="form aux-form">
  <?= csrf_input() ?>
  <input type="hidden" name="t" value="<?= h($key) ?>">
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <fieldset>
    <legend><?= $edit ? 'Editar #' . (int)$edit['id'] : 'Nuevo' ?></legend>
    <div class="cols">
      <label>Nombre <input name="nombre" value="<?= h($edit['nombre'] ?? '') ?>" autofocus required></label>
      <label class="check"><input type="checkbox" name="activo" value="1" <?= (!$edit || $edit['activo']) ? 'checked' : '' ?>> Activo</label>
    </div>
    <div class="acciones">
      <button type="submit"><?= $edit ? 'Guardar' : 'Agregar' ?></button>
      <?php if ($edit): ?><a href="?r=aux.tabla&t=<?= h($key) ?>" class="btn-sec">Cancelar</a><?php endif; ?>
    </div>
  </fieldset>
</form>
