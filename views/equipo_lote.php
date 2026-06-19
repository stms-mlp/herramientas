<?php
/** @var array $areas @var array $tipos @var array $estados @var array $atrMapa
 *  @var array $tiposComp @var array $catMarca @var array $catModelo */
?>
<form method="post" action="?r=equipos.lote_guardar" class="form">
  <?= csrf_input() ?>

  <fieldset>
    <legend>Área del lote</legend>
    <label>Repartición <span class="hint">(se aplica a todos los equipos cargados)</span>
      <select name="area_id" required>
        <option value="">—</option>
        <?php foreach ($areas as $a): ?>
          <option value="<?= (int)$a['id'] ?>"><?= h($a['codigo']) ?> — <?= h($a['descripcion']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </fieldset>

  <div id="cards"></div>
  <button type="button" id="add-eq" class="btn">+ Agregar equipo</button>

  <div class="acciones">
    <button type="submit">Guardar lote</button>
    <a href="?r=equipos" class="btn-sec">Cancelar</a>
  </div>
</form>

<datalist id="dl-marca"><?php foreach ($catMarca as $m): ?><option value="<?= h($m) ?>"><?php endforeach; ?></datalist>
<datalist id="dl-modelo"><?php foreach ($catModelo as $m): ?><option value="<?= h($m) ?>"><?php endforeach; ?></datalist>
<datalist id="dl-comp-tipo"><?php foreach ($tiposComp as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist>
<datalist id="dl-comp-marca"><?php foreach ($catMarca as $m): ?><option value="<?= h($m) ?>"><?php endforeach; ?></datalist>
<datalist id="dl-comp-modelo"><?php foreach ($catModelo as $m): ?><option value="<?= h($m) ?>"><?php endforeach; ?></datalist>

<script src="assets/equipos.js"></script>
<script>
window.ATTR_MAP = <?= json_encode($atrMapa, JSON_UNESCAPED_UNICODE) ?>;
const TIPOS = <?= json_encode(array_map(fn($t) => ['id' => (int)$t['id'], 'n' => $t['nombre_es'], 'c' => $t['codigo'], 'h' => (int)$t['lleva_hostname']], $tipos), JSON_UNESCAPED_UNICODE) ?>;
const ESTADOS = <?= json_encode(array_map(fn($s) => ['id' => (int)$s['id'], 'n' => $s['nombre']], $estados), JSON_UNESCAPED_UNICODE) ?>;

const cards = document.getElementById('cards');
let contador = 0;

function optTipos() {
  return '<option value="">—</option>' + TIPOS.map(t =>
    `<option value="${t.id}" data-host="${t.h}">${nmEsc(t.n)} (${nmEsc(t.c)})</option>`).join('');
}
function optEstados() {
  return ESTADOS.map(s => `<option value="${s.id}">${nmEsc(s.n)}</option>`).join('');
}

function agregarCard() {
  const i = contador++;
  const p = `eq[${i}]`;
  const card = document.createElement('div');
  card.className = 'lote-card';
  card.innerHTML = `
    <div class="lote-head"><strong>Equipo ${i + 1}</strong>
      <button type="button" class="btn-del quitar" title="Quitar equipo">✕ quitar</button></div>
    <div class="cols">
      <label>Tipo <select name="${p}[tipo_id]" class="c-tipo" required>${optTipos()}</select></label>
      <label>ID patrimonial <input name="${p}[id_patrimonial]" placeholder="ej. MLP-0042"></label>
      <label>Hostname <span class="hint">(vacío = autogenera)</span>
        <input name="${p}[hostname]" class="mono" maxlength="15" placeholder="autogenera al guardar"></label>
      <label>Estado <select name="${p}[estado_id]">${optEstados()}</select></label>
    </div>
    <div class="cols">
      <label>Marca <input name="${p}[marca]" list="dl-marca" placeholder="(opcional)"></label>
      <label>Modelo <input name="${p}[modelo]" list="dl-modelo" placeholder="(opcional)"></label>
      <label>N° serie <input name="${p}[n_serie]"></label>
      <label>IP <input name="${p}[ip]"></label>
    </div>
    <div class="cols">
      <label>Titularidad <select name="${p}[titularidad]"><option>Municipal</option><option>Personal</option></select></label>
      <label>Tenencia <select name="${p}[tenencia]"><option>En sede</option><option>Domicilio de empleado</option></select></label>
      <label>Responsable <input name="${p}[responsable]"></label>
    </div>
    <div class="sub-leg">Ficha del tipo</div>
    <div class="attr-cont"><p class="ayuda">Elegí un tipo para ver sus campos.</p></div>
    <div class="sub-leg">Componentes</div>
    <div class="import-row">
      <input type="file" class="c-file" accept=".txt,.html,.htm,.csv">
      <button type="button" class="btn-sec c-analizar">Analizar CPU-Z</button>
      <span class="ayuda c-msg"></span>
    </div>
    <table class="tabla comp-tabla">
      <thead><tr><th>Tipo</th><th>Marca</th><th>Modelo</th><th>N° serie</th><th>Velocidad</th><th>Memoria</th><th>Bus</th><th></th></tr></thead>
      <tbody class="c-comp"></tbody>
    </table>
    <button type="button" class="btn-sec c-addcomp">+ Componente</button>`;

  cards.appendChild(card);
  const tipoSel = card.querySelector('.c-tipo');
  const attrCont = card.querySelector('.attr-cont');
  const compBody = card.querySelector('.c-comp');
  tipoSel.addEventListener('change', () => nmRenderAtributos(attrCont, tipoSel.value, p, {}));
  card.querySelector('.c-addcomp').addEventListener('click', () => compBody.appendChild(nmCompRow(p, {})));
  card.querySelector('.c-analizar').addEventListener('click', () =>
    nmAnalizar(card.querySelector('.c-file'), compBody, p, card.querySelector('.c-msg')));
  card.querySelector('.quitar').addEventListener('click', () => card.remove());
}

document.getElementById('add-eq').addEventListener('click', agregarCard);
agregarCard(); // arranca con una tarjeta
</script>
