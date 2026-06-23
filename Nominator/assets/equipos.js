/* Nominator — Lógica compartida de formularios de equipo:
   campos dinámicos por tipo, tabla de componentes y análisis CPU-Z/HWMonitor.
   Requiere window.ATTR_MAP = { tipo_id: [ {id,nombre,tipo_dato,opciones} ] }. */

function nmEsc(s) {
  return (s == null ? '' : String(s)).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

/* Campo de un atributo según su tipo de dato. */
function nmAttrField(a, name, val) {
  val = val == null ? '' : val;
  if (a.tipo_dato === 'booleano') {
    return `<select name="${name}"><option value=""></option>`
      + `<option ${val === 'Sí' ? 'selected' : ''}>Sí</option>`
      + `<option ${val === 'No' ? 'selected' : ''}>No</option></select>`;
  }
  if (a.tipo_dato === 'lista') {
    const ops = (a.opciones || '').split(',').map(o => o.trim()).filter(Boolean);
    return `<select name="${name}"><option value=""></option>`
      + ops.map(o => `<option ${o === val ? 'selected' : ''}>${nmEsc(o)}</option>`).join('') + '</select>';
  }
  const type = a.tipo_dato === 'numero' ? 'number' : (a.tipo_dato === 'fecha' ? 'date' : 'text');
  return `<input type="${type}" name="${name}" value="${nmEsc(val)}">`;
}

/* Renderiza la ficha de atributos de un tipo en un contenedor. */
function nmRenderAtributos(cont, tipoId, prefix, valores) {
  valores = valores || {};
  const list = (window.ATTR_MAP || {})[tipoId] || [];
  if (!list.length) { cont.innerHTML = '<p class="ayuda">Este tipo no tiene campos específicos.</p>'; return; }
  cont.innerHTML = '<div class="cols">' + list.map(a => {
    const name = prefix ? `${prefix}[attr][${a.id}]` : `attr[${a.id}]`;
    return `<label>${nmEsc(a.nombre)} ${nmAttrField(a, name, valores[a.id])}</label>`;
  }).join('') + '</div>';
}

/* Una fila de componente. */
function nmCompRow(prefix, d) {
  d = d || {};
  const nm = k => prefix ? `${prefix}[${k}][]` : `${k}[]`;
  const tr = document.createElement('tr');
  tr.innerHTML =
    `<td><input name="${nm('comp_tipo')}" list="dl-comp-tipo" value="${nmEsc(d.tipo)}"></td>`
    + `<td><input name="${nm('comp_marca')}" list="dl-comp-marca" value="${nmEsc(d.marca)}"></td>`
    + `<td><input name="${nm('comp_modelo')}" list="dl-comp-modelo" value="${nmEsc(d.modelo)}"></td>`
    + `<td><input name="${nm('comp_serie')}" value="${nmEsc(d.n_serie)}"></td>`
    + `<td><input name="${nm('comp_velocidad')}" value="${nmEsc(d.velocidad)}"></td>`
    + `<td><input name="${nm('comp_memoria')}" value="${nmEsc(d.memoria)}"></td>`
    + `<td><input name="${nm('comp_bus')}" value="${nmEsc(d.bus)}"></td>`
    + `<td><button type="button" class="btn-del" title="Quitar">✕</button></td>`;
  tr.querySelector('.btn-del').onclick = () => tr.remove();
  return tr;
}

/* Sube un reporte CPU-Z/HWMonitor, lo analiza y agrega los componentes. */
async function nmAnalizar(fileInput, tbody, prefix, msgEl) {
  const f = fileInput.files[0];
  if (!f) { msgEl.textContent = 'Elegí un archivo primero.'; return; }
  msgEl.textContent = 'Analizando…';
  const fd = new FormData();
  fd.append('reporte', f);
  try {
    const j = await (await fetch('?r=componentes.analizar', { method: 'POST', body: fd })).json();
    (j.componentes || []).forEach(c => tbody.appendChild(nmCompRow(prefix, c)));
    msgEl.textContent = (j.componentes ? j.componentes.length : 0) + ' componente(s) agregado(s). '
      + ((j.avisos || []).join(' '));
  } catch (e) { msgEl.textContent = 'No se pudo analizar el archivo.'; }
}
