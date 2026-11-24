/* Mantenimiento de usuarios y de sus áreas (solo Administrador del sistema) */
var catalogos = { areas: [], sedes: [], funciones: [] };
var usuarios = [];
var usuarioAreas = null;   /* id de la persona cuyas áreas se están editando */

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

    cargar();

    $('#buscar').on('input', pintar);
    $('#btn_nuevo').on('click', function () { abrirUsuario(null); });
    $('#usuario_form').on('submit', guardar);
    $('#membresia_form').on('submit', agregarMembresia);
    $('#membresias').on('click', 'button', function () { quitarMembresia($(this).data('id')); });
    $('#usuarios').on('click', 'button[data-accion]', function () {
        var id = $(this).data('id');
        ({ editar: abrirUsuario, areas: abrirAreas, eliminar: eliminar })[$(this).data('accion')](id);
    });
    $('dialog').on('click', '[data-cerrar]', function () { this.closest('dialog').close(); });
    $('#dlg_areas').on('close', cargar);   /* refresca la tabla al terminar */
});

function llenarSelect(sel, lista, id, texto, vacio) {
    var $s = $(sel).empty();
    if (vacio) { $s.append($('<option value="">').text(vacio)); }
    $.each(lista, function (_, x) { $s.append($('<option>').val(x[id]).text(x[texto])); });
}

/* ---------- Tabla ---------- */
function cargar() {
    $.post('../../controller/usuario.php?op=listar').done(function (r) {
        usuarios = (Copeba.leer(r) || {}).data || [];
        pintar();
    });
}

function pintar() {
    var q = $.trim($('#buscar').val()).toLowerCase();
    var $t = $('#usuarios').empty();
    var filas = usuarios.filter(function (u) {
        return !q || [u.nombre, u.usuario, u.correo, u.sede, u.areas.join(' ')].join(' ').toLowerCase().indexOf(q) !== -1;
    });
    if (!filas.length) {
        $t.append($('<tr>').append($('<td colspan="6" class="cp-vacio">').text(q ? 'Sin resultados.' : 'No hay usuarios.')));
        return;
    }
    $.each(filas, function (_, u) {
        var $areas = $('<div class="cp-chips">');
        if (!u.areas.length) { $areas.append($('<span class="cp-suave">').text('Sin área')); }
        $.each(u.areas, function (_, a) { $areas.append($('<span class="cp-chip">').text(a)); });
        $t.append($('<tr>').append(
            $('<td>').append($('<strong>').text(u.nombre), $('<small class="cp-suave cp-bloque">').text(u.correo)),
            $('<td class="cp-mono">').text(u.usuario),
            $('<td>').text(u.sede || '—'),
            $('<td>').append($areas),
            $('<td>').append(u.admin ? $('<span class="cp-estado cerrado">').text('Administrador') : $('<span class="cp-suave">').text('Colaborador')),
            $('<td class="cp-der cp-acciones-fila">').append(
                boton('areas', u.id, 'Áreas'),
                boton('editar', u.id, 'Editar'),
                boton('eliminar', u.id, 'Eliminar', true)
            )
        ));
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
    var u = usuarios.filter(function (x) { return x.id === id; })[0];
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
