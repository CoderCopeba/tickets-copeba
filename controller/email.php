<?php
    /* llamada a las clases necesarias */
    require_once("../config/conexion.php");

    /* Permisos ANTES de instanciar nada */
    requiere_sesion("ajax");

    require_once("../models/Email.php");   /* carga también models/Ticket.php */
    $ticket = new Ticket();
    $email = new Email();

    /* opciones del controlador */
    switch ($_GET["op"] ?? "") {
        /* Aviso de ticket creado: propietario o Soporte/Admin */
        case "ticket_abierto":
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $email->ticket_abierto($tick_id);
            break;

        /* Aviso de ticket cerrado: propietario o Soporte/Admin (los mismos que pueden cerrarlo) */
        case "ticket_cerrado":
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $email->ticket_cerrado($tick_id);
            break;

        /* Aviso de asignación: solo Soporte/Admin */
        case "ticket_asignado":
            requiere_rol(ROLES_SOPORTE);
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $email->ticket_asignado($tick_id);
            break;

        default:
            auth_denegar(404, "ajax", "Operación no válida.");
    }
?>
