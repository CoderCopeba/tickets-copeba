/* Áreas y sedes (solo Administrador del sistema) */
var datos = { areas: [], sedes: [], funciones: [] };
var lista;
var ICONO_AREAS = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M17.5 14v7M14 17.5h7"/></svg>';

$(function () {
    lista = Copeba.listado('#lista_areas', {
        id: 'areas',
        titulo: 'Áreas',
        descripcion: 'Las áreas planificadas existen, pero las cubre otra área hasta tener personal propio.',
        icono: ICONO_AREAS,
        boton: { texto: 'Nueva área', accion: function () { abrirArea(null); } },
        pestanas: [
            { texto: 'Todas', filtro: null },
            { texto: 'Activas', filtro: function (a) { return a.area_estado === 'activa'; } },
            { texto: 'Planificadas', filtro: function (a) { return a.area_estado === 'planificada'; } },
            { texto: 'En cada agencia', filtro: function (a) { return Number(a.area_por_sede) === 1; } },
            { texto: 'Atienden tickets', filtro: function (a) { return Number(a.area_atiende) === 1; } }
        ],
        placeholder: 'Buscar área…',
        buscar: function (a) { return [a.area_nom, a.area_descrip, a.padre_nom, a.cargo_nom].join(' '); },
        columnas: [
            { titulo: 'Área', render: function (a) {
                return $('<span>').append(
                    $('<strong>').text((a.padre_nom ? '↳ ' : '') + a.area_nom),
                    $('<small class="cp-suave cp-bloque">').text(a.padre_nom ? 'Subárea de ' + a.padre_nom : (a.area_descrip || '')));
            } },
            { titulo: 'Estado', render: function (a) {
                var planificada = a.area_estado === 'planificada';
                var $e = $('<span>').append(planificada ? $('<span class="cp-estado pendiente">').text('Planificada') : $('<span class="cp-estado abierto">').text('Activa'));
                if (planificada && a.cargo_nom) { $e.append($('<small class="cp-suave cp-bloque">').text('La cubre ' + a.cargo_nom)); }
                return $e;
            } },
            { titulo: 'Alcance', render: function (a) {
                var $c = $('<div class="cp-chips">').append($('<span class="cp-chip">').text(Number(a.area_por_sede) ? 'En cada agencia' : 'Única'));
                if (Number(a.area_atiende)) { $c.append($('<span class="cp-chip">').text('Atiende tickets')); }
                return $c;
            } },
            { titulo: 'Integrantes', clase: 'cp-mono', render: function (a) { return a.miembros; } },
            { titulo: 'Acciones', clase: 'cp-der cp-acciones-fila', render: function (a) {
                return $('<span>').append(
                    boton('miembros', a.area_id, 'Integrantes'),
                    boton('editar', a.area_id, 'Editar'),
                    Number(a.area_atiende) ? null : boton('eliminar', a.area_id, 'Eliminar', true));
            } }
        ],
        vacio: 'No hay áreas.',
        total: 'Total de áreas',
        exportar: { archivo: 'areas', campos: [
            { titulo: 'Área', valor: function (a) { return a.area_nom; } },
            { titulo: 'Pertenece a', valor: function (a) { return a.padre_nom || ''; } },
            { titulo: 'Estado', valor: function (a) { return a.area_estado; } },
            { titulo: 'La cubre', valor: function (a) { return a.cargo_nom || ''; } },
            { titulo: 'Integrantes', valor: function (a) { return a.miembros; } }
        ] }
    });
    cargar();

    $('#area_form').on('submit', guardar);
    $('#area_estado').on('change', mostrarCargo);
    $('#lista_areas').on('click', 'button[data-accion]', function () {
        var id = Number($(this).data('id'));
        ({ editar: abrirArea, miembros: verMiembros, eliminar: eliminar })[$(this).data('accion')](id);
    });
    $('dialog').on('click', '[data-cerrar]', function () { this.closest('dialog').close(); });
});

function cargar() {
    $.post('../../controller/area.php?op=catalogos').done(function (r) {
        datos = Copeba.leer(r) || datos;
        pintarResumen();
        lista.datos(datos.areas);
        pintarSedes();
    });
}

function porId(id) {
    return datos.areas.filter(function (a) { return Number(a.area_id) === Number(id); })[0];
}

function pintarResumen() {
    var activas = datos.areas.filter(function (a) { return a.area_estado === 'activa'; }).length;
    var personas = datos.areas.reduce(function (s, a) { return s + Number(a.miembros); }, 0);
    var kpis = [
        ['Áreas activas', activas], ['Planificadas', datos.areas.length - activas],
        ['Sedes', datos.sedes.length], ['Asignaciones', personas]
    ];
    var $r = $('#resumen').empty();
    $.each(kpis, function (_, k) {
        $r.append($('<article class="cp-tarjeta cp-kpi">').append(
            $('<div class="cp-kpi-cab">').append($('<span>').text(k[0])),
            $('<div class="cp-kpi-pie">').append($('<span class="cp-kpi-num">').text(k[1]))
        ));
    });
}

function boton(accion, id, texto, peligro) {
    return $('<button type="button" class="cp-btn cp-btn-borde cp-btn-chico">')
        .toggleClass('cp-btn-peligro', !!peligro).attr({ 'data-accion': accion, 'data-id': id }).text(texto);
}

function pintarSedes() {
    var tipos = { central: 'Central · Administración y agencia', administrativa: 'Administrativa', agencia: 'Agencia', supermercado: 'Supermercado' };
    var $s = $('#sedes').empty();
    $.each(datos.sedes, function (_, s) {
        var lugar = [s.sede_municipio, s.sede_departamento].filter(Boolean).join(', ');
        $s.append($('<li>').append(
            $('<div>').append($('<strong>').text(s.sede_nom), $('<small class="cp-suave cp-bloque">').text(lugar || 'Ubicación por completar')),
            $('<span class="cp-chip">').text(tipos[s.sede_tipo] || s.sede_tipo)
        ));
    });
}

/* ---------- Alta / edición ---------- */
function opcionesAreas(sel, vacio, excluir) {
    var $s = $(sel).empty().append($('<option value="">').text(vacio));
    $.each(datos.areas, function (_, a) {
        if (Number(a.area_id) === Number(excluir) || a.padre_nom) { return; }
        $s.append($('<option>').val(a.area_id).text(a.area_nom));
    });
}

function mostrarCargo() {
    $('#campo_cargo').prop('hidden', $('#area_estado').val() !== 'planificada');
}

function abrirArea(id) {
    var a = id ? porId(id) : null;
    $('#area_form')[0].reset();
    opcionesAreas('#area_padre_id', 'Ninguna (área principal)', id);
    opcionesAreas('#area_cargo_id', 'Nadie', id);
    $('#dlg_area_titulo').text(a ? 'Editar área' : 'Nueva área');
    $('#area_id').val(a ? a.area_id : '');
    if (a) {
        $('#area_nom').val(a.area_nom);
        $('#area_descrip').val(a.area_descrip || '');
        $('#area_padre_id').val(a.area_padre_id || '');
        $('#area_estado').val(a.area_estado);
        $('#area_cargo_id').val(a.area_cargo_id || '');
        $('#area_por_sede').prop('checked', Number(a.area_por_sede) === 1);
        $('#area_atiende').prop('checked', Number(a.area_atiende) === 1);
    } else {
        var admin = datos.areas.filter(function (x) { return x.area_nom === 'Gerencia y Administración'; })[0];
        if (admin) { $('#area_cargo_id').val(admin.area_id); }
    }
    mostrarCargo();
    Copeba.dialogo('dlg_area');
}

function guardar(e) {
    e.preventDefault();
    if ($.trim($('#area_nom').val()) === '') { Copeba.aviso('Escribe el nombre del área.'); return; }
    Copeba.enviar('../../controller/area.php?op=guardar_area', new FormData($('#area_form')[0]), 'No se pudo guardar el área.')
        .done(function () {
            Copeba.dialogo('dlg_area', false);
            cargar();
            Copeba.exito('Área guardada.');
        });
}

function eliminar(id) {
    var a = porId(id);
    Copeba.confirmar('¿Eliminar el área "' + (a ? a.area_nom : '') + '"?', function () {
        Copeba.enviar('../../controller/area.php?op=desactivar_area', { area_id: id }, 'No se pudo eliminar el área.')
            .done(function () { cargar(); Copeba.exito('Área eliminada.'); });
    }, { peligro: true });
}

function verMiembros(id) {
    var a = porId(id);
    $('#dlg_miembros_titulo').text('Integrantes de ' + (a ? a.area_nom : 'el área'));
    $.post('../../controller/area.php?op=miembros', { area_id: id }).done(function (r) {
        var lista = Copeba.leer(r) || [];
        var $l = $('#miembros').empty();
        if (!lista.length) { $l.append($('<li class="cp-vacio">').text('Todavía no tiene integrantes.')); }
        $.each(lista, function (_, m) {
            $l.append($('<li>').append($('<div>').append(
                $('<strong>').text(m.usu_nom + ' ' + m.usu_ape),
                $('<small class="cp-suave cp-bloque">').text([m.fun_nom, m.au_puesto, m.sede_nom].filter(Boolean).join(' · '))
            )));
        });
        Copeba.dialogo('dlg_miembros');
    });
}
