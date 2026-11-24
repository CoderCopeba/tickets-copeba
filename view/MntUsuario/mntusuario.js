/* Mantenimiento de usuarios y de sus áreas (solo Administrador del sistema) */
var catalogos = { areas: [], sedes: [], funciones: [] };
var lista;
var usuarioAreas = null;   /* id de la persona cuyas áreas se están editando */
var ICONO_USUARIOS = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14.5c1.9.8 3 2.7 3 5.5"/></svg>';

$(function () {
    $.post('../../controller/area.php?op=catalogos').done(function (r) {
        catalogos = Copeba.leer(r) || catalogos;
        llenarSelect('#sede_id', catalogos.sedes, 'sede_id', 'sede_nom', 'Sin sede');
        llenarSelect('#m_sede', catalogos.sedes, 'sede_id', 'sede_nom');
        llenarSelect('#m_funcion', catalogos.funciones, 'fun_id', 'fun_nom');
        llenarSelect('#m_area', catalogos.areas.map(function (a) {
            return { area_id: a.area_id, nombre: (a.padre_nom ? a.padre_nom + ' › ' : '') + a.area_nom + (a.area_estado === 'planificada' ? ' (planificada)' : '') };
        }), 'area_id', 'nombre');
        $('#m_funcion').val('3');
    });

    lista = Copeba.listado('#lista_usuarios', {
        id: 'usuarios',
        titulo: 'Personal con acceso',
        descripcion: 'Cuentas, sede principal y áreas de cada persona.',
        icono: ICONO_USUARIOS,
        boton: { texto: 'Nuevo usuario', accion: function () { abrirUsuario(null); } },
        pestanas: [
            { texto: 'Todos', filtro: null },
            { texto: 'Con área', filtro: function (u) { return u.areas.length > 0; } },
            { texto: 'Sin área', filtro: function (u) { return !u.areas.length; } },
            { texto: 'Administradores', filtro: function (u) { return u.admin; } }
        ],
        placeholder: 'Buscar por nombre, usuario, correo, área o sede…',
        buscar: function (u) { return [u.nombre, u.usuario, u.correo, u.sede, u.areas.join(' ')].join(' '); },
        columnas: [
            { titulo: 'Nombre', render: function (u) { return $('<span>').append($('<strong>').text(u.nombre), $('<small class="cp-suave cp-bloque">').text(u.correo)); } },
            { titulo: 'Usuario', clase: 'cp-mono', render: function (u) { return u.usuario; } },
            { titulo: 'Sede', render: function (u) { return u.sede || '—'; } },
            { titulo: 'Áreas', render: function (u) {
                var $a = $('<div class="cp-chips">');
                if (!u.areas.length) { $a.append($('<span class="cp-suave">').text('Sin área')); }
                $.each(u.areas, function (_, x) { $a.append($('<span class="cp-chip">').text(x)); });
                return $a;
            } },
            { titulo: 'Cuenta', render: function (u) { return u.admin ? $('<span class="cp-estado cerrado">').text('Administrador') : $('<span class="cp-suave">').text('Colaborador'); } },
            { titulo: 'Acciones', clase: 'cp-der cp-acciones-fila', render: function (u) {
                return $('<span>').append(boton('areas', u.id, 'Áreas'), boton('editar', u.id, 'Editar'), boton('eliminar', u.id, 'Eliminar', true));
            } }
        ],
        vacio: 'No hay usuarios.',
        total: 'Total de usuarios',
        exportar: { archivo: 'usuarios', campos: [
            { titulo: 'Nombre', valor: function (u) { return u.nombre; } },
            { titulo: 'Usuario', valor: function (u) { return u.usuario; } },
            { titulo: 'Correo', valor: function (u) { return u.correo; } },
            { titulo: 'Sede', valor: function (u) { return u.sede; } },
            { titulo: 'Áreas', valor: function (u) { return u.areas.join(', '); } },
            { titulo: 'Cuenta', valor: function (u) { return u.admin ? 'Administrador del sistema' : 'Colaborador'; } }
        ] }
    });
    cargar();

    $('#usuario_form').on('submit', guardar);
    $('#membresia_form').on('submit', agregarMembresia);
    $('#membresias').on('click', 'button', function () { quitarMembresia($(this).data('id')); });
    $('#lista_usuarios').on('click', 'button[data-accion]', function () {
        var id = $(this).data('id');
        ({ editar: abrirUsuario, areas: abrirAreas, eliminar: eliminar })[$(this).data('accion')](id);
    });
    $('dialog').on('click', '[data-cerrar]', function () { this.closest('dialog').close(); });
    $('#dlg_areas').on('close', cargar);   /* refresca la tabla al terminar */
});

function llenarSelect(sel, datos, id, texto, vacio) {
    var $s = $(sel).empty();
    if (vacio) { $s.append($('<option value="">').text(vacio)); }
    $.each(datos, function (_, x) { $s.append($('<option>').val(x[id]).text(x[texto])); });
}

function cargar() {
    $.post('../../controller/usuario.php?op=listar').done(function (r) {
        lista.datos((Copeba.leer(r) || {}).data || []);
    });
}

function boton(accion, id, texto, peligro) {
    return $('<button type="button" class="cp-btn cp-btn-borde cp-btn-chico">')
        .toggleClass('cp-btn-peligro', !!peligro).attr({ 'data-accion': accion, 'data-id': id }).text(texto);
}

/* ---------- Alta / edición ---------- */
function abrirUsuario(id) {
    $('#usuario_form')[0].reset();
    $('#usu_id').val('');
    $('#dlg_usuario_titulo').text(id ? 'Editar usuario' : 'Nuevo usuario');
    $('#usu_pass').attr('placeholder', id ? 'Dejar vacío para no cambiar' : '');
    $('#usu_pass_ayuda').text(id ? 'Déjala vacía para conservar la actual.' : 'Obligatoria para un usuario nuevo.');
    if (!id) { Copeba.dialogo('dlg_usuario'); return; }
    $.post('../../controller/usuario.php?op=mostrar', { usu_id: id }).done(function (r) {
        var u = Copeba.leer(r);
        if (!u) { Copeba.error('No se pudo cargar el usuario.'); return; }
        $('#usu_id').val(u.usu_id);
        $('#usu_nom').val(u.usu_nom);
        $('#usu_ape').val(u.usu_ape);
        $('#usu_usuario').val(u.usu_usuario);
        $('#usu_correo').val(u.usu_correo);
        $('#sede_id').val(u.sede_id || '');
        $('#rol_id').val(u.rol_id);
        Copeba.dialogo('dlg_usuario');
    });
}

function guardar(e) {
    e.preventDefault();
    var nuevo = $('#usu_id').val() === '';
    var usuario = $.trim($('#usu_usuario').val()).toLowerCase();
    if (!/^[a-z0-9._-]{3,30}$/.test(usuario)) {
        Copeba.aviso('Usuario no válido: de 3 a 30 caracteres, letras sin acentos, números, punto, guion o guion bajo.');
        return;
    }
    $('#usu_usuario').val(usuario);
    if (nuevo && $('#usu_pass').val() === '') { Copeba.aviso('La contraseña es obligatoria para un usuario nuevo.'); return; }

    Copeba.enviar('../../controller/usuario.php?op=guardaryeditar', new FormData($('#usuario_form')[0]), 'No se pudo guardar el usuario.')
        .done(function (r) {
            Copeba.dialogo('dlg_usuario', false);
            cargar();
            if (nuevo) {
                Copeba.exito('Ahora asígnale sus áreas.', 'Usuario creado');
                setTimeout(function () { abrirAreas(r.usu_id); }, 1100);
            } else {
                Copeba.exito('Usuario guardado.');
            }
        });
}

function eliminar(id) {
    Copeba.confirmar('¿Desactivar este usuario? Ya no podrá iniciar sesión.', function () {
        Copeba.enviar('../../controller/usuario.php?op=eliminar', { usu_id: id }, 'No se pudo eliminar.')
            .done(function () { cargar(); Copeba.exito('Usuario desactivado.'); });
    }, { peligro: true });
}

/* ---------- Áreas de la persona ---------- */
function abrirAreas(id) {
    usuarioAreas = id;
    var u = lista.filas().filter(function (x) { return x.id === id; })[0];
    $('#dlg_areas_titulo').text('Áreas de ' + (u ? u.nombre : 'la persona'));
    cargarMembresias();
    Copeba.dialogo('dlg_areas');
}

function cargarMembresias() {
    $.post('../../controller/area.php?op=membresias', { usu_id: usuarioAreas }).done(function (r) {
        var lista = Copeba.leer(r) || [];
        var $l = $('#membresias').empty();
        if (!lista.length) { $l.append($('<li class="cp-vacio">').text('Todavía no pertenece a ningún área.')); return; }
        $.each(lista, function (_, m) {
            $l.append($('<li>').append(
                $('<div>').append(
                    $('<strong>').text(m.area_nom),
                    $('<small class="cp-suave cp-bloque">').text([m.fun_nom, m.au_puesto, m.sede_nom].filter(Boolean).join(' · '))
                ),
                $('<button type="button" class="cp-btn cp-btn-borde cp-btn-chico cp-btn-peligro">').attr('data-id', m.au_id).text('Quitar')
            ));
        });
    });
}

function agregarMembresia(e) {
    e.preventDefault();
    Copeba.enviar('../../controller/area.php?op=guardar_membresia', {
        usu_id: usuarioAreas, area_id: $('#m_area').val(), sede_id: $('#m_sede').val(),
        fun_id: $('#m_funcion').val(), au_puesto: $('#m_puesto').val()
    }, 'No se pudo agregar el área.').done(function () {
        $('#m_puesto').val('');
        cargarMembresias();
    });
}

function quitarMembresia(au_id) {
    Copeba.enviar('../../controller/area.php?op=quitar_membresia', { au_id: au_id }, 'No se pudo quitar.')
        .done(cargarMembresias);
}
