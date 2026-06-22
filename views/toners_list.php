<?php /** @var array $toners @var array|null $edit @var array $compat @var array $marcas @var string $q */ ?>
<p class="bread"><a href="?r=auxiliares">← Tablas Auxiliares</a> / <strong>Tóners</strong></p>

<form method="get" class="filtros">
  <input type="hidden" name="r" value="toners">
  <label>Modelo de tóner <input name="q" value="<?= h($q) ?>" placeholder="ej. CF258A"></label>
  <button type="submit">Buscar</button>
</form>

<table class="tabla">
  <thead><tr><th>Modelo</th><th>Color</th><th>Rendimiento</th><th>Stock</th><th>Compat.</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($toners as $t): ?>
    <tr class="<?= $t['activo'] ? '' : 'inactiva' ?>">
      <td><a href="?r=toners&id=<?= (int)$t['id'] ?>"><?= h($t['modelo']) ?></a></td>
      <td><?= h($t['color'] ?: '—') ?></td>
      <td><?= h($t['rendimiento'] ?: '—') ?></td>
      <td class="<?= (int)$t['stock'] <= 0 ? 'tenue' : '' ?>"><?= (int)$t['stock'] ?></td>
      <td><?= (int)$t['ncompat'] ?></td>
      <td class="acc">
        <a href="?r=toners&id=<?= (int)$t['id'] ?>">editar</a>
        · <a href="#" onclick="document.getElementById('delt<?= (int)$t['id'] ?>').submit();return false;" class="del">borrar</a>
        <form id="delt<?= (int)$t['id'] ?>" method="post" action="?r=toners.borrar" style="display:none">
          <?= csrf_input() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$toners): ?><tr><td colspan="6" class="vacio">Sin tóners.</td></tr><?php endif; ?>
  </tbody>
</table>

<form method="post" action="?r=toners.guardar" class="form aux-form">
  <?= csrf_input() ?>
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <fieldset>
    <legend><?= $edit ? 'Editar tóner #' . (int)$edit['id'] : 'Nuevo tóner' ?></legend>
    <div class="cols">
      <label>Modelo <input name="modelo" value="<?= h($edit['modelo'] ?? '') ?>" required></label>
      <label>Color <input name="color" value="<?= h($edit['color'] ?? '') ?>" placeholder="Negro / Cyan…"></label>
      <label>Rendimiento <input name="rendimiento" value="<?= h($edit['rendimiento'] ?? '') ?>" placeholder="páginas"></label>
      <label>Stock <input type="number" name="stock" value="<?= (int)($edit['stock'] ?? 0) ?>"></label>
    </div>
    <label>Nota <input name="nota" value="<?= h($edit['nota'] ?? '') ?>"></label>
    <div class="acciones">
      <button type="submit"><?= $edit ? 'Guardar' : 'Agregar' ?></button>
      <?php if ($edit): ?><a href="?r=toners" class="btn-sec">Nuevo</a><?php endif; ?>
    </div>
  </fieldset>
</form>

<?php if ($edit): ?>
  <fieldset class="form aux-form">
    <legend>Compatibilidad de «<?= h($edit['modelo']) ?>»</legend>
    <table class="tabla">
      <thead><tr><th>Marca</th><th>Modelo de impresora</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($compat as $c): ?>
        <tr>
          <td><?= h($c['marca']) ?></td>
          <td><?= h($c['modelo'] ?: '(toda la marca)') ?></td>
          <td class="acc">
            <a href="#" onclick="document.getElementById('dc<?= (int)$c['id'] ?>').submit();return false;" class="del">quitar</a>
            <form id="dc<?= (int)$c['id'] ?>" method="post" action="?r=toners.compat_del" style="display:none">
              <?= csrf_input() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="toner_id" value="<?= (int)$edit['id'] ?>">
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$compat): ?><tr><td colspan="3" class="vacio">Sin compatibilidades. Agregá marca (y modelo si querés acotar).</td></tr><?php endif; ?>
      </tbody>
    </table>

    <form method="post" action="?r=toners.compat_add" class="form">
      <?= csrf_input() ?>
      <input type="hidden" name="toner_id" value="<?= (int)$edit['id'] ?>">
      <div class="cols">
        <label>Marca
          <select name="marca_id" id="cmpMarca" required>
            <option value="">—</option>
            <?php foreach ($marcas as $ma): ?><option value="<?= (int)$ma['id'] ?>"><?= h($ma['nombre']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Modelo de impresora <span class="hint">(opcional = toda la marca)</span>
          <select name="modelo_id" id="cmpModelo"><option value="">— toda la marca —</option></select>
        </label>
      </div>
      <div class="acciones"><button type="submit">+ Agregar compatibilidad</button></div>
    </form>
  </fieldset>

  <script>
  const cmpMarca = document.getElementById('cmpMarca'), cmpModelo = document.getElementById('cmpModelo');
  cmpMarca.addEventListener('change', async () => {
    cmpModelo.innerHTML = '<option value="">— toda la marca —</option>';
    if (!cmpMarca.value) return;
    try {
      const ms = await (await fetch('?r=modelos.por_marca&marca=' + cmpMarca.value)).json();
      ms.forEach(m => cmpModelo.appendChild(new Option(m.nombre, m.id)));
    } catch (e) {}
  });
  </script>
<?php endif; ?>
