<?php
    /* Plan listo para imprimir o guardar como PDF (con espacio para firmas y sello).
       Lo puede abrir la persona dueña del plan o su jefatura; nadie más. */
    require_once("../../config/conexion.php");
    require_once("../../models/Plan.php");
    require_once("../../models/Area.php");
    requiere_sesion("vista");

    $modelo = new Plan();
    $plan = $modelo->plan((int)($_GET["plan"] ?? 0));
    if ($plan === null){
        auth_denegar(404, "vista", "El plan no existe.");
    }
    $yo = usuario_actual_id();
    if ((int)$plan["usu_id"] !== $yo && !$modelo->puede_supervisar($yo, es_admin(), $plan["usu_id"])){
        auth_denegar(403, "vista", "Solo la persona o su jefatura pueden descargar este plan.");
    }

    $periodo = Plan::periodo($plan["plan_tipo"], $plan["fech_inicio"]);
    $actividades = $modelo->actividades($plan["plan_id"]);
    $area = (new Area())->resumen_usuario($plan["usu_id"]);

    $meses = array(1 => "enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre");
    $dias = array(1 => "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom");
    function fecha_corta($v){ return $v ? date("d/m/Y", strtotime($v)) : ""; }
    $inicio = new DateTime($periodo["inicio"]);
    $titulo_periodo = $plan["plan_tipo"] === Plan::MES
        ? "Plan mensual · ".ucfirst($meses[(int)$inicio->format("n")])." ".$inicio->format("Y")
        : "Plan semanal · del ".fecha_corta($periodo["inicio"])." al ".fecha_corta($periodo["fin"]);

    $estados = array("pendiente" => "Pendiente", "hecho" => "Hecho", "no_cumplido" => "No cumplido", "pospuesto" => "Pospuesto");
    $estados_plan = array("borrador" => "Borrador (sin entregar)", "entregado" => "Entregado", "revisado" => "Revisado");
    $cuenta = array("hecho" => 0, "no_cumplido" => 0, "pospuesto" => 0, "pendiente" => 0);
    foreach ($actividades as $a){ $cuenta[$a["act_estado"]] = ($cuenta[$a["act_estado"]] ?? 0) + 1; }
    $tarde = Plan::tarde($plan["fech_entrega"], $periodo["plazo"]);
    $archivo = "plan_".$plan["plan_tipo"]."_".$periodo["inicio"]."_".preg_replace('/[^a-z0-9]+/i', "_", $plan["persona"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- El navegador usa el título como nombre del PDF -->
    <title><?php echo e($archivo); ?></title>
    <style>
        @page { size: letter; margin: 16mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", Arial, sans-serif; font-size: 11.5pt; color: #1d2433; background: #f3efe6; }
        .hoja { max-width: 820px; margin: 24px auto; padding: 28px 32px; background: #fff; box-shadow: 0 6px 24px rgba(0,0,0,.1); }
        .barra { max-width: 820px; margin: 16px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        .barra button { padding: 9px 16px; border-radius: 8px; border: 1px solid #e6e1d6; background: #fff; font: inherit; font-size: 10.5pt; font-weight: 600; cursor: pointer; }
        .barra .primario { background: #0b4a2b; border-color: #0b4a2b; color: #fff; }
        .cab { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding-bottom: 12px; border-bottom: 3px solid #0b4a2b; }
        .cab img { height: 54px; }
        .cab .org { text-align: right; font-size: 9.5pt; color: #4b5563; }
        h1 { margin: 16px 0 4px; font-family: Arial, sans-serif; font-size: 16pt; }
        .datos { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; margin: 10px 0 16px; font-size: 10.5pt; }
        .datos b { color: #4b5563; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: 10.5pt; }
        th, td { padding: 7px 8px; border: 1px solid #d6d1c6; text-align: left; vertical-align: top; }
        th { background: #f3efe6; font-size: 9.5pt; text-transform: uppercase; letter-spacing: .02em; }
        td.n { width: 28px; text-align: center; color: #6b7280; }
        td.dia { width: 78px; white-space: nowrap; }
        td.estado { width: 96px; white-space: nowrap; font-weight: 600; }
        .nota { display: block; margin-top: 3px; font-size: 9.5pt; color: #4b5563; }
        .resumen { margin: 12px 0 0; font-size: 10.5pt; }
        .comentario { margin: 12px 0 0; padding: 8px 10px; border-left: 3px solid #0b4a2b; background: #f6f3ec; font-size: 10.5pt; }
        .firmas { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 28px; margin-top: 64px; text-align: center; font-size: 10pt; }
        .firmas div { padding-top: 6px; border-top: 1px solid #1d2433; }
        .sello { height: 90px; border: 1px dashed #b8b2a6; border-top: 1px dashed #b8b2a6 !important; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 6px; color: #6b7280; }
        .pie { margin-top: 24px; font-size: 8.5pt; color: #6b7280; text-align: right; }
        @media print {
            body { background: #fff; }
            .barra { display: none; }
            .hoja { margin: 0; padding: 0; max-width: none; box-shadow: none; }
            tr { page-break-inside: avoid; }
            .firmas { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="barra">
        <button type="button" onclick="window.close()">Cerrar</button>
        <button type="button" class="primario" onclick="window.print()">Descargar PDF / Imprimir</button>
    </div>

    <main class="hoja">
        <header class="cab">
            <img src="../../public/img/copeba/logo-copeba.png" alt="Cooperativa Copeba, R.L.">
            <div class="org">Cooperativa Copeba, R.L.<br>Planificación de actividades</div>
        </header>

        <h1><?php echo e($titulo_periodo); ?></h1>
        <div class="datos">
            <span><b>Nombre:</b> <?php echo e($plan["persona"]); ?></span>
            <span><b>Estado:</b> <?php echo e($estados_plan[$plan["plan_estado"]] ?? $plan["plan_estado"]); ?></span>
            <span><b>Puesto · Área:</b> <?php echo e($area); ?></span>
            <span><b>Entregado:</b> <?php echo $plan["fech_entrega"] ? e(fecha_corta($plan["fech_entrega"])).($tarde ? " (fuera de plazo)" : "") : "—"; ?></span>
        </div>

        <table>
            <thead><tr><th>#</th><th>Día</th><th>Actividad</th><th>Estado</th></tr></thead>
            <tbody>
            <?php if (!$actividades) { ?>
                <tr><td colspan="4" style="text-align:center;color:#6b7280">Sin actividades.</td></tr>
            <?php } foreach ($actividades as $i => $a) {
                $d = $a["act_dia"] ? new DateTime($a["act_dia"]) : null; ?>
                <tr>
                    <td class="n"><?php echo $i + 1; ?></td>
                    <td class="dia"><?php echo $d ? e($dias[(int)$d->format("N")]." ".$d->format("d/m")) : "—"; ?></td>
                    <td>
                        <?php echo e($a["act_texto"]); ?>
                        <?php if ($a["act_tick_id"]) { ?><span class="nota">Ticket #<?php echo e(str_pad($a["act_tick_id"], 3, "0", STR_PAD_LEFT)); ?> · <?php echo e($a["tick_titulo"]); ?></span><?php } ?>
                        <?php if ($a["act_origen_id"]) { ?><span class="nota">Viene pospuesta<?php echo $a["origen_justificacion"] ? ": ".e($a["origen_justificacion"]) : ""; ?></span><?php } ?>
                        <?php if ((int)$a["act_extra"]) { ?><span class="nota">Agregada después de entregar el plan</span><?php } ?>
                        <?php if ($a["act_justificacion"]) { ?><span class="nota">Justificación: <?php echo e($a["act_justificacion"]); ?></span><?php } ?>
                    </td>
                    <td class="estado"><?php echo e($estados[$a["act_estado"]] ?? $a["act_estado"]); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <p class="resumen"><b>Resumen:</b>
            <?php echo (int)$cuenta["hecho"]; ?> de <?php echo count($actividades); ?> hechas ·
            <?php echo (int)$cuenta["no_cumplido"]; ?> no cumplidas ·
            <?php echo (int)$cuenta["pospuesto"]; ?> pospuestas ·
            <?php echo (int)$cuenta["pendiente"]; ?> pendientes</p>

        <?php if ($plan["plan_comentario"]) { ?>
        <p class="comentario"><b>Comentario de <?php echo e($plan["revisado_por"] ?: "la jefatura"); ?>:</b> <?php echo e($plan["plan_comentario"]); ?></p>
        <?php } ?>

        <section class="firmas">
            <div>Firma de <?php echo e($plan["persona"]); ?></div>
            <div>Firma de la jefatura<?php echo $plan["revisado_por"] ? "<br>".e($plan["revisado_por"]) : ""; ?></div>
            <div class="sello">Sello</div>
        </section>

        <p class="pie">Generado el <?php echo e(date("d/m/Y H:i")); ?> · Copeba Tickets</p>
    </main>

    <script>
        /* Abre el cuadro de impresión (elegir "Guardar como PDF") */
        window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
    </script>
</body>
</html>
