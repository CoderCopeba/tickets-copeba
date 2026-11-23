<?php
/*
 * Filtro de HTML por lista blanca para el contenido de Summernote
 * (descripción de tickets e historial). Se aplica al guardar y al mostrar.
 *
 * - Etiquetas no permitidas: se eliminan pero se conserva su texto.
 * - Etiquetas peligrosas (script, style, iframe...): se eliminan con su contenido.
 * - Atributos: solo los de la lista; se eliminan on*, class, id, etc.
 * - URLs: <a href> solo http/https/mailto o relativas; <img src> solo http/https o relativas.
 * - style: solo propiedades de formato y valores simples (sin url(), expression(), etc.).
 */

const HTML_ETIQUETAS = array(
    /* pedidas */
    "p" => array("style"), "br" => array(), "b" => array(), "strong" => array(),
    "i" => array(), "em" => array(), "u" => array(), "ul" => array("style"), "ol" => array("style"),
    "li" => array("style"), "span" => array("style"),
    "a" => array("href", "title"), "img" => array("src", "alt", "width", "height"),
    /* que genera la barra de Summernote configurada (tachado, super/subíndice, color, párrafo) */
    "div" => array("style"), "sub" => array(), "sup" => array(), "strike" => array(), "s" => array(),
    "font" => array("color"),
);

const HTML_ELIMINAR_CON_CONTENIDO = array(
    "script", "style", "iframe", "frame", "frameset", "object", "embed", "applet",
    "noscript", "template", "svg", "math", "form", "input", "button", "select",
    "textarea", "link", "meta", "base", "title", "head",
);

const HTML_CSS_PERMITIDO = array(
    "color", "background-color", "font-size", "font-weight", "font-style", "font-family",
    "text-decoration", "text-align", "line-height", "margin-left", "padding-left",
);

function html_url_segura($url, array $esquemas){
    /* quitar espacios y caracteres de control que los navegadores ignoran ("java\tscript:") */
    $limpia = preg_replace('/[\x00-\x20\x7F]+/', '', html_entity_decode((string)$url, ENT_QUOTES | ENT_HTML5, "UTF-8"));
    if ($limpia === "" || $limpia === null){
        return false;
    }
    /* ¿Tiene esquema? (algo: antes de / ? #) */
    if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $limpia, $m)){
        return in_array(strtolower($m[1]), $esquemas, true);
    }
    return true; /* relativa o //host */
}

function html_estilo_seguro($estilo){
    $salida = array();
    foreach (explode(";", (string)$estilo) as $decl){
        $partes = explode(":", $decl, 2);
        if (count($partes) !== 2){
            continue;
        }
        $prop = strtolower(trim($partes[0]));
        $valor = trim($partes[1]);
        if (!in_array($prop, HTML_CSS_PERMITIDO, true)){
            continue;
        }
        /* valores simples: colores, números, unidades, nombres de fuente, rgb() */
        if (!preg_match('/^[#a-z0-9.,%\s\-"\'()]+$/i', $valor)){
            continue;
        }
        if (preg_match('/(expression|url|javascript|vbscript|behavior|@import|\\\\|\/\*)/i', $valor)){
            continue;
        }
        if (preg_match('/\(/', $valor) && !preg_match('/^rgba?\([\d\s.,%]+\)$/i', $valor)){
            continue;
        }
        $salida[] = $prop.": ".$valor;
    }
    return implode("; ", $salida);
}

function html_limpiar_nodo(DOMNode $nodo){
    /* recorrer una copia: la lista cambia mientras se modifica */
    $hijos = array();
    foreach ($nodo->childNodes as $h){
        $hijos[] = $h;
    }
    foreach ($hijos as $hijo){
        if ($hijo->nodeType === XML_TEXT_NODE){
            continue;
        }
        if ($hijo->nodeType !== XML_ELEMENT_NODE){
            /* comentarios, CDATA, instrucciones de proceso */
            $nodo->removeChild($hijo);
            continue;
        }
        $etiqueta = strtolower($hijo->nodeName);

        if (in_array($etiqueta, HTML_ELIMINAR_CON_CONTENIDO, true)){
            $nodo->removeChild($hijo);
            continue;
        }

        /* primero limpiar los descendientes */
        html_limpiar_nodo($hijo);

        if (!array_key_exists($etiqueta, HTML_ETIQUETAS)){
            /* desenvolver: conservar el contenido, quitar la etiqueta */
            while ($hijo->firstChild){
                $nodo->insertBefore($hijo->firstChild, $hijo);
            }
            $nodo->removeChild($hijo);
            continue;
        }

        /* atributos */
        $permitidos = HTML_ETIQUETAS[$etiqueta];
        $atributos = array();
        foreach ($hijo->attributes as $a){
            $atributos[] = $a->nodeName;
        }
        foreach ($atributos as $nombre){
            $n = strtolower($nombre);
            $valor = $hijo->getAttribute($nombre);
            $conservar = in_array($n, $permitidos, true);

            if ($conservar && $n === "href"){
                $conservar = html_url_segura($valor, array("http", "https", "mailto"));
            } elseif ($conservar && $n === "src"){
                $conservar = html_url_segura($valor, array("http", "https"));
            } elseif ($conservar && $n === "style"){
                $valor = html_estilo_seguro($valor);
                $conservar = ($valor !== "");
                if ($conservar){
                    $hijo->setAttribute($nombre, $valor);
                }
            } elseif ($conservar && ($n === "width" || $n === "height")){
                $conservar = (bool)preg_match('/^\d{1,4}(px|%)?$/', $valor);
            } elseif ($conservar && $n === "color"){
                $conservar = (bool)preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,20})$/i', $valor);
            }

            if (!$conservar){
                $hijo->removeAttribute($nombre);
            }
        }

        if ($etiqueta === "a" && $hijo->hasAttribute("href")){
            $hijo->setAttribute("target", "_blank");
            $hijo->setAttribute("rel", "noopener noreferrer");
        }
        if ($etiqueta === "img" && !$hijo->hasAttribute("src")){
            $nodo->removeChild($hijo);
        }
    }
}

function html_limpio($html){
    $html = (string)$html;
    if (trim($html) === ""){
        return "";
    }
    if (!class_exists("DOMDocument")){
        /* sin extensión DOM: degradar a texto seguro */
        return nl2br(htmlspecialchars(strip_tags($html), ENT_QUOTES, "UTF-8"));
    }

    $previo = libxml_use_internal_errors(true);
    $doc = new DOMDocument("1.0", "UTF-8");
    $doc->loadHTML(
        '<?xml encoding="UTF-8"?><div id="__raiz__">'.$html.'</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previo);

    $raiz = $doc->getElementById("__raiz__");
    if ($raiz === null){
        return nl2br(htmlspecialchars(strip_tags($html), ENT_QUOTES, "UTF-8"));
    }

    html_limpiar_nodo($raiz);

    $salida = "";
    foreach ($raiz->childNodes as $hijo){
        $salida .= $doc->saveHTML($hijo);
    }
    return $salida;
}
