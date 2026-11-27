/* Tareas por área (como las tareas de un curso en Canvas).
   - "Mis tareas": lo que me asignaron, con mi propio avance.
   - "Tareas que asigné": solo si dirijo un área; avance de cada persona.
   Todo texto de la base se inserta con .text(); las instrucciones llegan ya
   limpias del servidor (html_limpio). */
var URL_TAREA = '../../controller/tarea.php?op=';
var listaMias, listaAsignadas, grupos = [];

var ICONO = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l2 2 4-4"/><rect x="4" y="4" width="16" height="17" rx="2"/><path d="M9 2v4M15 2v4"/></svg>';

/* Avance de cada persona: texto y color (reutiliza las etiquetas .cp-estado) */
var AVANCE = {
    pendiente: { texto: 'Pendiente',   clase: 'pendiente' },
    proceso:   { texto: 'En proceso',  clase: 'proceso' },
    entregada: { texto: 'Entregada',   clase: 'abierto' },
    revisada:  { texto: 'Revisada',    clase: 'revisada' },
    devuelta:  { texto: 'Devuelta',    clase: 'cerrado' }
};
var OJO = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
function botonVer(t) {
    return $('<button type="button" class="cp-ver">').attr({ 'data-ver': t.tarea_id, title: 'Ver tarea', 'aria-label': 'Ver tarea ' + t.tarea_titulo }).html(OJO);
}

/* Orden de los pasos, para ordenar la columna "Mi avance" (lo urgente primero) */
var ORDEN_AVANCE = ['devuelta', 'pendiente', 'proceso', 'entregada', 'revisada'];
function chipAvance(estado) {
    var a = AVANCE[estado] || AVANCE.pendiente;
    return $('<span class="cp-estado">').addClass(a.clase).text(a.texto);
}

function hoyIso() {
    var d = new Date();
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
}
/* Vencida: tiene fecha límite pasada y la persona aún no entregó */
function vencida(t) {
    return !!t.fech_limite && t.fech_limite < hoyIso() && ['pendiente', 'proceso', 'devuelta'].indexOf(t.tu_estado) !== -1;
}
function chipLimite(fechaLimite, atrasada) {
    if (!fechaLimite) { return $('<span class="cp-suave">').text('Sin fecha'); }
    return $('<span>').toggleClass('cp-prioridad vencido', !!atrasada).text(Copeba.fecha(fechaLimite));
}
function urgencia(t) {
    return $('<span class="cp-prioridad">').addClass('p' + t.prio_id).text(t.prio_nom);
}

$(function () {
    listaMias = Copeba.listado('#lista_mias', {
        id: 'tareas_mias',
        titulo: 'Mis tareas',
        descripcion: 'Lo que te asignó la jefatura de tu área.',
        icono: ICONO,
        pestanas: [
            { texto: 'Por hacer', filtro: function (t) { return ['pendiente', 'proceso', 'devuelta'].indexOf(t.tu_estado) !== -1; } },
            { texto: 'Vencidas', filtro: vencida },
            { texto: 'Entregadas', filtro: function (t) { return t.tu_estado === 'entregada' || t.tu_estado === 'revisada'; } },
            { texto: 'Todas', filtro: null }
        ],
        placeholder: 'Buscar por título, área o quién la asignó…',
        buscar: function (t) { return [t.tarea_titulo, t.area_nom, t.asignada_por, t.prio_nom].join(' '); },
        columnas: [
            { titulo: 'Tarea', render: function (t) { return $('<strong class="cp-tarea-nombre">').text(t.tarea_titulo); } },
            { titulo: 'Área', clase: 'cp-suave', render: function (t) { return t.area_nom; } },
            { titulo: 'Asignada por', render: function (t) { return t.asignada_por; } },
            { titulo: 'Urgencia', orden: function (t) { return Number(t.prio_id); }, render: urgencia },
            { titulo: 'Fecha límite', clase: 'cp-mono', orden: function (t) { return t.fech_limite; },
              render: function (t) { return chipLimite(t.fech_limite, vencida(t)); } },
            { titulo: 'Mi avance', orden: function (t) { return ORDEN_AVANCE.indexOf(t.tu_estado); },
              render: function (t) { return chipAvance(t.tu_estado); } },
            { titulo: '', clase: 'cp-der', render: botonVer }
        ],
        vacio: 'No tienes tareas en esta vista.',
        total: 'Total de tareas'
    });

    $('#lista_mias, #lista_asignadas').on('click', '[data-ver]', function () { abrirDetalle(Number($(this).attr('data-ver'))); });
    $('dialog').on('click', '[data-cerrar]', function () { this.closest('dialog').close(); });
    $('#grupo').on('change', cargarPersonas);
    $('#nueva_form').on('submit', crear);
    $('#personas').on('change', '#todas', function () { $('#personas input[name="personas[]"]:not(:disabled)').prop('checked', this.checked); });
    $('#personas').on('change', 'input[name="personas[]"]', function () {
        var $c = $('#personas input[name="personas[]"]');
        $('#todas').prop('checked', $c.length > 0 && $c.filter(':checked').length === $c.length);
    });
    Copeba.editor('#descrip', 160, { barra: 'simple', ayuda: 'Explica qué hay que hacer y cómo entregarlo…' });

    cargar();
});

function crearListaAsignadas() {
    $('#lista_asignadas').prop('hidden', false);
    listaAsignadas = Copeba.listado('#lista_asignadas', {
        id: 'tareas_asignadas',
        titulo: 'Tareas que asigné',
        descripcion: 'Tareas de las áreas que diriges y el avance de cada persona.',
        icono: ICONO,
        boton: { texto: 'Nueva tarea', accion: abrirNueva },
        pestanas: [
            { texto: 'En curso', filtro: function (t) { return Number(t.revisadas) < Number(t.total); } },
            { texto: 'Por revisar', filtro: function (t) { return Number(t.por_revisar) > 0; } },
            { texto: 'Terminadas', filtro: function (t) { return Number(t.revisadas) === Number(t.total); } },
            { texto: 'Todas', filtro: null }
        ],
        placeholder: 'Buscar por título, área o sede…',
        buscar: function (t) { return [t.tarea_titulo, t.area_nom, t.sede_nom, t.asignada_por].join(' '); },
        columnas: [
            { titulo: 'Tarea', render: function (t) { return $('<strong class="cp-tarea-nombre">').text(t.tarea_titulo); } },
            { titulo: 'Área', clase: 'cp-suave', render: function (t) { return t.area_nom + (t.sede_nom ? ' · ' + t.sede_nom : ''); } },
            { titulo: 'Urgencia', orden: function (t) { return Number(t.prio_id); }, render: urgencia },
            { titulo: 'Fecha límite', clase: 'cp-mono', orden: function (t) { return t.fech_limite; }, render: function (t) {
                return chipLimite(t.fech_limite, t.fech_limite && t.fech_limite < hoyIso() && Number(t.revisadas) < Number(t.total));
            } },
            /* Avance del equipo: cuántas personas terminaron (entrega revisada) de cuántas
               se asignaron, y qué falta: entregas por revisar y personas que no han entregado */
            { titulo: 'Avance', orden: function (t) { return Number(t.total) ? Number(t.revisadas) / Number(t.total) : 0; }, render: function (t) {
                var total = Number(t.total), hechas = Number(t.revisadas), porRevisar = Number(t.por_revisar);
                var sinEntregar = Math.max(0, total - hechas - porRevisar);
                var $b = $('<span class="cp-tarea-avance">');
                $b.append($('<span class="cp-tarea-avance-fila">').append(
                    $('<span class="cp-tarea-barra">').append($('<i>').css('width', (total ? hechas / total * 100 : 0) + '%')),
                    $('<span>').attr('title', hechas + ' de ' + total + ' personas terminaron').text(hechas + '/' + total)));
                var falta = [];
                if (porRevisar) { falta.push(porRevisar + ' por revisar'); }
                if (sinEntregar) { falta.push(sinEntregar + ' sin entregar'); }
                if (falta.length) { $b.append($('<small>').toggleClass('cp-tarea-revisar', porRevisar > 0).text(falta.join(' · '))); }
                return $b;
            } },
            { titulo: '', clase: 'cp-der', render: botonVer }
        ],
        vacio: 'No hay tareas en esta vista.',
        total: 'Total de tareas'
    });
}

function cargar() {
    $.post(URL_TAREA + 'lista').done(function (r) {
        var d = Copeba.leer(r) || {};
        listaMias.datos(d.mias || []);
        if (d.dirige) {
            if (!listaAsignadas) { crearListaAsignadas(); }
            listaAsignadas.datos(d.asignadas || []);
            if (!grupos.length) { prepararFormulario(d.grupos || [], d.prioridades || []); }
        }
    });
}

/* ---------- Nueva tarea ---------- */
function prepararFormulario(gs, prioridades) {
    grupos = gs;
    var $g = $('#grupo').empty();
    if (gs.length > 1) { $g.append($('<option value="">').text('Elige el área…')); }
    gs.forEach(function (g, i) {
        $g.append($('<option>').val(i).text(g.area_nom + (g.sede_nom ? ' · ' + g.sede_nom : '')));
    });
    var $p = $('#prio_id').empty();
    prioridades.forEach(function (p) { $p.append($('<option>').val(p.prio_id).text(p.prio_nom)); });
    $p.val('2');
}

/* null = creando; al editar guarda { tarea, avances } */
var editando = null;

function modoFormulario(edicion) {
    $('#dlg_nueva_titulo').text(edicion ? 'Editar tarea' : 'Nueva tarea');
    $('#dlg_nueva_nota').text(edicion
        ? 'Corrige los datos o a quién está asignada. Quien ya entregó no se puede quitar.'
        : 'Se asigna de una vez a todas las personas que marques; cada una hace su propia entrega.');
    $('#nueva_enviar').text(edicion ? 'Guardar cambios' : 'Asignar tarea');
    $('#campo_grupo').prop('hidden', !!edicion);
    $('#campo_grupo_fijo').prop('hidden', !edicion);
}

function abrirNueva() {
    editando = null;
    modoFormulario(false);
    $('#nueva_form')[0].reset();
    $('#descrip').summernote('code', '');
    $('#fech_limite').val('').trigger('cp-fecha');
    $('#prio_id').val('2');
    if (grupos.length === 1) { $('#grupo').val('0'); }
    cargarPersonas();
    Copeba.dialogo('dlg_nueva');
}

/* Abre el mismo formulario con los datos de la tarea (desde el detalle) */
function abrirEditar(t, avances) {
    editando = { tarea: t, avances: avances };
    modoFormulario(true);
    $('#nueva_form')[0].reset();
    $('#grupo_fijo').text(t.area_nom + (t.sede_nom ? ' · ' + t.sede_nom : ''));
    $('#titulo').val(t.tarea_titulo);
    $('#descrip').summernote('code', t.tarea_descrip || '');
    $('#prio_id').val(String(t.prio_id));
    $('#fech_limite').val(t.fech_limite || '').trigger('cp-fecha');
    cargarPersonas();
    Copeba.dialogo('dlg_detalle', false);
    Copeba.dialogo('dlg_nueva');
}

function grupoElegido() {
    if (editando) { return { area_id: editando.tarea.area_id, sede_id: editando.tarea.sede_id, area_nom: editando.tarea.area_nom }; }
    var i = $('#grupo').val();
    return i === '' || i === null ? null : grupos[Number(i)];
}

function cargarPersonas() {
    var g = grupoElegido(), $p = $('#personas');
    if (!g) { $p.html('<p class="cp-suave">Primero elige el área.</p>'); return; }
    $p.html('<p class="cp-suave">Cargando personas…</p>');
    var asignadas = {};
    (editando ? editando.avances : []).forEach(function (a) { asignadas[Number(a.usu_id)] = a; });
    $.post(URL_TAREA + 'personas', { area_id: g.area_id, sede_id: g.sede_id || '' }).always(function (r, estado) {
        var personas = estado === 'success' ? (Copeba.leer(r) || []) : [];
        if (!$.isArray(personas)) { personas = []; }
        /* Al editar: quienes ya tienen la tarea aparecen aunque ya no estén en el área */
        var vistos = {};
        personas.forEach(function (x) { vistos[Number(x.usu_id)] = true; });
        $.each(asignadas, function (id, a) {
            if (!vistos[id]) {
                var partes = String(a.nombre || '').split(' ');
                personas.push({ usu_id: id, usu_nom: partes.shift(), usu_ape: partes.join(' '), area_nom: g.area_nom });
            }
        });
        $p.empty();
        if (!personas.length) { $p.html('<p class="cp-suave">No hay otras personas en esta área.</p>'); return; }
        $p.append($('<label class="cp-tarea-persona cp-tarea-todas cp-fijo">').append(
            $('<input type="checkbox" id="todas">'), $('<span>').text('Toda el área (' + personas.length + ')')));
        personas.forEach(function (x) {
            /* Puesto (o función) · área si es de otra (Jefatura de Agencia) · sede si el área abarca varias */
            var detalle = [x.puesto || x.fun_nom, x.area_nom !== g.area_nom ? x.area_nom : '', g.sede_id ? '' : x.sede_nom]
                .filter(Boolean).join(' · ');
            var a = asignadas[Number(x.usu_id)];
            /* Quien ya entregó o fue revisado queda fijo: su trabajo no se pierde */
            var fija = !!a && (a.tu_estado === 'entregada' || a.tu_estado === 'revisada');
            if (fija) { detalle = [detalle, 'ya entregó'].filter(Boolean).join(' · '); }
            $p.append($('<label class="cp-tarea-persona">').append(
                $('<input type="checkbox" name="personas[]">').val(x.usu_id).prop({ checked: !!a, disabled: fija }),
                $('<span>').text(x.usu_nom + ' ' + x.usu_ape),
                $('<small>').text(detalle)));
        });
        var $c = $p.find('input[name="personas[]"]');
        $('#todas').prop('checked', $c.length > 0 && $c.filter(':checked').length === $c.length);
        /* "Toda el área" queda fija arriba (cp-fijo); el resto, 12 por página */
        $p.removeData('cp-pagina');
        Copeba.paginar($p, 12);
    });
}

function crear(e) {
    e.preventDefault();
    var g = grupoElegido();
    var $enviar = $('#nueva_enviar');
    if ($enviar.prop('disabled')) { return; }   /* evita crear la tarea dos veces por doble clic */
    var personas = $('#personas input[name="personas[]"]:checked').map(function () { return this.value; }).get();
    if (!g) { Copeba.aviso('Elige el área.'); return; }
    if (!personas.length) { Copeba.aviso('Marca al menos una persona.'); return; }
    if (!$.trim($('#titulo').val())) { Copeba.aviso('Escribe el título de la tarea.'); return; }
    if ($('#descrip').summernote('isEmpty')) { Copeba.aviso('Escribe las instrucciones.'); return; }
    var datos = {
        personas: personas,
        titulo: $('#titulo').val(), descrip: $('#descrip').summernote('code'),
        prio_id: $('#prio_id').val(), fech_limite: $('#fech_limite').val()
    };
    var edicion = editando;
    if (edicion) { datos.tarea_id = edicion.tarea.tarea_id; }
    else { datos.area_id = g.area_id; datos.sede_id = g.sede_id || ''; }

    $enviar.prop('disabled', true);
    Copeba.enviar(URL_TAREA + (edicion ? 'editar' : 'crear'), datos,
                  edicion ? 'No se pudo guardar la tarea.' : 'No se pudo asignar la tarea.')
        .always(function () { $enviar.prop('disabled', false); })
        .done(function (r) {
            Copeba.dialogo('dlg_nueva', false);
            cargar();
            if (!edicion) {
                Copeba.exito('Tarea asignada a ' + r.personas + (r.personas === 1 ? ' persona.' : ' personas.'));
                return;
            }
            editando = null;
            abrirDetalle(edicion.tarea.tarea_id);
            if (r.conservadas && r.conservadas.length) {
                Copeba.aviso('Cambios guardados. No se quitó a ' + r.conservadas.join(', ') + ' porque ya entregó.');
            } else {
                Copeba.exito('Tarea actualizada.');
            }
        });
}

/* ---------- Detalle ---------- */
function abrirDetalle(id) {
    $.post(URL_TAREA + 'detalle', { tarea_id: id }).done(function (r) {
        var d = Copeba.leer(r);
        if (!d || !d.tarea) { Copeba.aviso((d && d.message) || 'No se pudo abrir la tarea.'); return; }
        pintarDetalle(d);
        Copeba.dialogo('dlg_detalle');
    }).fail(function (x) {
        Copeba.aviso((Copeba.leer(x.responseText) || {}).message || 'No se pudo abrir la tarea.');
    });
}

function pintarDetalle(d) {
    var t = d.tarea;
    $('#det_titulo').text(t.tarea_titulo);
    $('#det_meta').text([
        t.area_nom + (t.sede_nom ? ' · ' + t.sede_nom : ''),
        'Asignada por ' + t.asignada_por + ' el ' + Copeba.fecha(t.fech_crea),
        'Urgencia: ' + t.prio_nom,
        t.fech_limite ? 'Fecha límite: ' + Copeba.fecha(t.fech_limite) : 'Sin fecha límite'
    ].join('  ·  '));
    $('#det_instrucciones').html(t.tarea_descrip);   /* ya filtrado por html_limpio() */
    var $c = $('#det_cuerpo').empty();
    var $pie = $('#det_pie').empty();

    if (d.mio) { $c.append(seccionMia(t, d.mio)); }
    if (d.dirige) { $c.append(seccionAvances(t, d.avances)); }

    if (d.dirige) {
        $pie.append($('<button type="button" class="cp-btn cp-btn-borde cp-btn-peligro">').text('Anular tarea').on('click', function () {
            Copeba.confirmar('¿Anular la tarea "' + t.tarea_titulo + '"? Desaparecerá para todas las personas asignadas.', function () {
                Copeba.enviar(URL_TAREA + 'anular', { tarea_id: t.tarea_id }, 'No se pudo anular.').done(function () {
                    Copeba.dialogo('dlg_detalle', false); cargar(); Copeba.exito('Tarea anulada.');
                });
            }, { peligro: true });
        }));
        $pie.append($('<span class="cp-tarea-espacio">'));
        $pie.append($('<button type="button" class="cp-btn cp-btn-borde">').text('Editar tarea').on('click', function () {
            abrirEditar(t, d.avances);
        }));
    }
    $pie.append($('<button type="button" class="cp-btn cp-btn-borde" data-cerrar>').text('Cerrar'));
}

/* Lo que ve la persona asignada: su avance y cómo entregar */
function seccionMia(t, mio) {
    var $s = $('<section class="cp-tarea-mia">').append($('<h3>').text('Mi avance'), chipAvance(mio.tu_estado));
    if (mio.tu_estado === 'devuelta' && mio.tu_observacion) {
        $s.append($('<p class="cp-tarea-nota devuelta">').text('Por corregir: ' + mio.tu_observacion));
    }
    if (mio.tu_entrega && mio.tu_estado !== 'devuelta') {
        $s.append($('<p class="cp-tarea-nota">').text('Tu entrega (' + Copeba.fecha(mio.fech_entrega) + '): ' + mio.tu_entrega));
    }
    if (mio.tu_estado === 'revisada' && mio.tu_observacion) {
        $s.append($('<p class="cp-tarea-nota">').text('Comentario de la revisión: ' + mio.tu_observacion));
    }
    if (['pendiente', 'proceso', 'devuelta'].indexOf(mio.tu_estado) !== -1) {
        var $txt = $('<textarea class="cp-control cp-tarea-texto" maxlength="2000" placeholder="Cuenta qué hiciste o dónde quedó el resultado…">');
        var $acciones = $('<div class="cp-tarea-acciones">');
        if (mio.tu_estado !== 'proceso') {
            $acciones.append($('<button type="button" class="cp-btn cp-btn-borde cp-btn-chico">').text('Marcar en proceso').on('click', function () {
                avanzar(t.tarea_id, 'proceso', '');
            }));
        }
        $acciones.append($('<button type="button" class="cp-btn cp-btn-primario cp-btn-chico">').text('Entregar').on('click', function () {
            if (!$.trim($txt.val())) { Copeba.aviso('Escribe un comentario de lo que hiciste.'); return; }
            avanzar(t.tarea_id, 'entregada', $txt.val());
        }));
        $s.append($('<label class="cp-etiqueta">').text('Comentario de entrega'), $txt, $acciones);
    }
    return $s;
}

function avanzar(tarea_id, estado, comentario) {
    Copeba.enviar(URL_TAREA + 'avanzar', { tarea_id: tarea_id, estado: estado, comentario: comentario }, 'No se pudo actualizar.')
        .done(function () {
            cargar();
            abrirDetalle(tarea_id);
            Copeba.exito(estado === 'entregada' ? 'Tarea entregada.' : 'Marcada en proceso.');
        });
}

/* Lo que ve quien dirige: el avance de cada persona, con revisar / devolver */
function seccionAvances(t, avances) {
    var revisadas = avances.filter(function (a) { return a.tu_estado === 'revisada'; }).length;
    var $s = $('<section class="cp-tarea-equipo">').append(
        $('<h3>').text('Avance del equipo · ' + revisadas + ' de ' + avances.length + ' revisadas'));
    var $tabla = $('<div class="cp-tarea-filas">');
    avances.forEach(function (a) {
        var $f = $('<div class="cp-tarea-fila">');
        var $info = $('<div>').append($('<strong>').text(a.nombre));
        if (a.tu_entrega) { $info.append($('<p>').text('Entrega (' + Copeba.fecha(a.fech_entrega) + '): ' + a.tu_entrega)); }
        if (a.tu_observacion) { $info.append($('<p class="cp-suave">').text('Observación: ' + a.tu_observacion)); }
        var $acc = $('<div class="cp-tarea-acciones">').append(chipAvance(a.tu_estado));
        if (a.tu_estado === 'entregada') {
            $acc.append(
                $('<button type="button" class="cp-btn cp-btn-borde cp-btn-chico">').text('Devolver').on('click', function () {
                    Copeba.pedirTexto('Devolver a ' + a.nombre, '¿Qué debe corregir?', function (obs) { revisar(t.tarea_id, a.usu_id, 'devuelta', obs); });
                }),
                $('<button type="button" class="cp-btn cp-btn-primario cp-btn-chico">').text('Marcar revisada').on('click', function () {
                    revisar(t.tarea_id, a.usu_id, 'revisada', '');
                })
            );
        }
        $tabla.append($f.append($info, $acc));
    });
    return $s.append($tabla);
}

function revisar(tarea_id, usu_id, decision, observacion) {
    Copeba.enviar(URL_TAREA + 'revisar', { tarea_id: tarea_id, usu_id: usu_id, decision: decision, observacion: observacion }, 'No se pudo revisar.')
        .done(function () {
            cargar();
            abrirDetalle(tarea_id);
            Copeba.exito(decision === 'revisada' ? 'Entrega revisada.' : 'Tarea devuelta.');
        });
}
