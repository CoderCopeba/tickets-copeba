<?php
/* Envío de correos de tickets con PHPMailer 7 (instalado con Composer).
   La configuración SMTP sale de .env (MAIL_*); si falta, no se envía nada. */
require_once(__DIR__."/../vendor/autoload.php");
require_once(__DIR__."/../config/conexion.php");
require_once(__DIR__."/Ticket.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class Email {

    public function ticket_abierto($tick_id){
        return $this->enviar($tick_id, "Ticket Abierto", "NuevoTicket.html");
    }

    public function ticket_cerrado($tick_id){
        return $this->enviar($tick_id, "Ticket Cerrado", "CerradoTicket.html");
    }

    public function ticket_asignado($tick_id){
        return $this->enviar($tick_id, "Ticket Asignado", "AsignarTicket.html");
    }

    private function enviar($tick_id, $asunto, $plantilla){
        $host    = env_get("MAIL_HOST", false);
        $usuario = env_get("MAIL_USER", false);
        $clave   = env_get("MAIL_PASSWORD", false);
        if (!$host || !$usuario || !$clave) {
            return false;               /* correo aún no configurado */
        }

        $ticket = new Ticket();
        $datos = $ticket->listar_ticket_x_id($tick_id);
        if (empty($datos)) {
            return false;
        }
        $row = $datos[0];

        $cuerpo = file_get_contents(__DIR__."/../public/".$plantilla);
        $cuerpo = strtr($cuerpo, array(
            "xnroticket" => e($row["tick_id"]),
            "lblNomUsu"  => e($row["usu_nom"]),
            "lblTitu"    => e($row["tick_titulo"]),
            "lblCate"    => e($row["cat_nom"]),
        ));

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->Port       = (int) (env_get("MAIL_PORT", false) ?: 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = $usuario;
            $mail->Password   = $clave;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = PHPMailer::CHARSET_UTF8;
            $mail->setFrom(env_get("MAIL_FROM", false) ?: $usuario,
                           (env_get("MAIL_FROM_NAME", false) ?: "Copeba Tickets")." · ".$asunto." ".$row["tick_id"]);
            $mail->addAddress($row["usu_correo"]);
            $mail->isHTML(true);
            $mail->Subject = $asunto." #".$row["tick_id"];
            $mail->Body    = $cuerpo;
            $mail->AltBody = $asunto." #".$row["tick_id"].": ".$row["tick_titulo"];
            return $mail->send();
        } catch (MailException $ex) {
            error_log("Correo no enviado (ticket ".$row["tick_id"]."): ".$mail->ErrorInfo);
            return false;
        }
    }
}
