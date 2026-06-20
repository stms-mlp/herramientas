<?php /** @var array $area @var array $tipos */ ?>
<p class="bread"><a href="?r=equipos&area=<?= (int)$area['id'] ?>">← Equipos del área</a> /
   <strong>Extracto de inventario</strong></p>

<?php if (!$tipos): ?>
  <p class="vacio">El área no tiene equipos municipales activos para declarar.</p>
<?php else: ?>
  <p class="ayuda">Elegí qué tipos de equipo incluir en el extracto que se entrega a
     <strong><?= h($area['descripcion']) ?></strong>. Por defecto se incluyen todos.</p>

  <form method="get" action="?" target="_blank" class="form">
    <input type="hidden" name="r" value="reporte.area">
    <input type="hidden" name="area" value="<?= (int)$area['id'] ?>">

    <fieldset>
      <legend>Tipos a incluir</legend>
      <p><a href="#" onclick="marcar(true);return false;">Marcar todos</a> ·
         <a href="#" onclick="marcar(false);return false;">Desmarcar todos</a></p>
      <?php foreach ($tipos as $t): ?>
        <label class="check">
          <input type="checkbox" name="tipos[]" value="<?= (int)$t['id'] ?>" checked>
          <?= h($t['nombre_es']) ?> <span class="tenue">(<?= (int)$t['n'] ?>)</span>
        </label>
      <?php endforeach; ?>
    </fieldset>

    <div class="acciones">
      <button type="submit">📄 Generar extracto</button>
      <a href="?r=equipos&area=<?= (int)$area['id'] ?>" class="btn-sec">Volver</a>
    </div>
  </form>
<?php endif; ?>

<script>
function marcar(v){ document.querySelectorAll('input[name="tipos[]"]').forEach(c=>c.checked=v); }
</script>
