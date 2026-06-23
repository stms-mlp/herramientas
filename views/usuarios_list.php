<?php /** @var array $usuarios @var array|null $edit */
$yo = usuario_actual();
$roles = [ROL_ADMIN => 'Administrador', ROL_TECNICO => 'Técnico', ROL_LECTURA => 'Lectura'];
?>
<p class="bread"><a href="?r=auxiliares">← Tablas Auxiliares</a> / <strong>Usuarios</strong></p>

<table class="tabla">
  <thead><tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Activo</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($usuarios as $u): ?>
    <tr class="<?= $u['activo'] ? '' : 'inactiva' ?>">
      <td class="mono"><?= h($u['usuario']) ?><?= (int)$u['id'] === (int)$yo['id'] ? ' <span class="tenue">(vos)</span>' : '' ?></td>
      <td><?= h($u['nombre'] ?: '—') ?></td>
      <td><?= h($roles[$u['rol']] ?? $u['rol']) ?></td>
      <td><?= $u['activo'] ? 'Sí' : '—' ?></td>
      <td class="acc">
        <a href="?r=usuarios&id=<?= (int)$u['id'] ?>">editar</a>
        <?php if ((int)$u['id'] !== (int)$yo['id']): ?>
          · <a href="#" onclick="document.getElementById('delu<?= (int)$u['id'] ?>').submit();return false;" class="del">borrar</a>
          <form id="delu<?= (int)$u['id'] ?>" method="post" action="?r=usuarios.borrar" style="display:none">
            <?= csrf_input() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<form method="post" action="?r=usuarios.guardar" class="form aux-form">
  <?= csrf_input() ?>
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <fieldset>
    <legend><?= $edit ? 'Editar usuario #' . (int)$edit['id'] : 'Nuevo usuario' ?></legend>
    <div class="cols">
      <label>Usuario <input name="usuario" value="<?= h($edit['usuario'] ?? '') ?>" autocomplete="off" required></label>
      <label>Nombre <input name="nombre" value="<?= h($edit['nombre'] ?? '') ?>"></label>
      <label>Rol
        <select name="rol">
          <?php foreach ($roles as $k => $lbl): ?>
            <option value="<?= h($k) ?>" <?= ($edit['rol'] ?? ROL_LECTURA) === $k ? 'selected' : '' ?>><?= h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Clave <span class="hint"><?= $edit ? '(vacío = no cambiar)' : '(mín. 4)' ?></span>
        <input type="password" name="clave" autocomplete="new-password" <?= $edit ? '' : 'required' ?>>
      </label>
      <label class="check"><input type="checkbox" name="activo" value="1" <?= (!$edit || $edit['activo']) ? 'checked' : '' ?>> Activo</label>
    </div>
    <div class="acciones">
      <button type="submit"><?= $edit ? 'Guardar' : 'Crear usuario' ?></button>
      <?php if ($edit): ?><a href="?r=usuarios" class="btn-sec">Nuevo</a><?php endif; ?>
    </div>
  </fieldset>
</form>
