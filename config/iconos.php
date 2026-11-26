<?php
/* Iconos SVG del sistema (trazo, heredan el color del texto).
   Uso: echo icono("ticket");  echo icono("salir", 16, 2); */
const ICONOS = array(
    "inicio"   => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    "nuevo"    => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
    "mas"      => '<path d="M12 5v14M5 12h14"/>',
    "ticket"   => '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/>',
    "usuarios" => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14.5c1.9.8 3 2.7 3 5.5"/>',
    "perfil"   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
    "salir"    => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 16l-4-4 4-4M6 12h10"/>',
    "abierto"  => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 2v4M16 2v4M4 10h16"/><circle cx="15" cy="15" r="2"/>',
    "cerrado"  => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    "cola"     => '<path d="M4 6h16M4 12h10M4 18h6"/>',
    "menu"     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    "adjunto"  => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M12 18v-6M9 15l3-3 3 3"/>',
    "guardar"  => '<path d="M5 3h11l5 5v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M7 3v5h8V3M7 21v-7h10v7"/>',
    "areas"    => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M17.5 14v7M14 17.5h7"/>',
    "tareas"   => '<path d="M9 11l2 2 4-4"/><rect x="4" y="4" width="16" height="17" rx="2"/><path d="M9 2v4M15 2v4"/>',
    "calendario" => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h2M14 14h2M8 18h2"/>',
    "reportes" => '<path d="M3 3v18h18"/><path d="M7 15v3M11.5 10v8M16 12v6M20.5 6v12"/>',
    "llave"    => '<circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 9.2-9.2M17 6l3 3M14.5 8.5l2 2"/>',
    "volver"   => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
    "candado"  => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    "clip"     => '<path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 7"/>',
    "enviar"   => '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
    "quitar"   => '<path d="M6 6l12 12M18 6 6 18"/>',
);

function icono($nombre, $tam = 18, $grosor = 1.8){
    $trazo = ICONOS[$nombre] ?? "";
    return '<svg width="'.(int)$tam.'" height="'.(int)$tam.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'
        .(float)$grosor.'" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$trazo.'</svg>';
}
