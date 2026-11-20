
<?php

require_once(__DIR__ . "/config/conexion.php");

$isCli = PHP_SAPI === "cli";
$remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "";
$isLoopback = $remoteAddress === "127.0.0.1" || $remoteAddress === "::1";

if (!$isCli && !$isLoopback) {
    http_response_code(403);
    exit;
}

class PruebaConectar extends Conectar
{
    public function probarConexion()
    {
        return $this->Conexion();
    }
}

try {
    $prueba = new PruebaConectar();
    $conexion = $prueba->probarConexion();
    $consulta = $conexion->query("SELECT COUNT(*) FROM tm_ticket");
    $total = $consulta->fetchColumn();

    echo "<h1>Conexión exitosa</h1>";
    echo "<p>PHP se conectó correctamente con MySQL.</p>";
    echo "<p>Total de tickets: " . (int) $total . "</p>";
} catch (Exception $e) {
    error_log("Fallo la prueba de conexión a la base de datos");
    http_response_code(500);
    echo "<h1>Error de conexión</h1>";
}
