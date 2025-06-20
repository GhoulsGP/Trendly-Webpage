<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trendly";

$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Obtener datos del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtener el ID del seguidor a eliminar
    $seguidor_id = isset($_POST['seguidor_id']) ? intval($_POST['seguidor_id']) : 0;
    
    // Verificar que se proporcionó un ID válido
    if ($seguidor_id <= 0) {
        header("Location: inicio.php");
        exit();
    }
    
    // Obtener el usuario actual
    $usuario_actual = $_SESSION['usuario'];
    
    // Obtener el ID del usuario actual
    $sql_usuario_actual = "SELECT id FROM usuarios WHERE nombre = ?";
    $stmt = $conn->prepare($sql_usuario_actual);
    $stmt->bind_param("s", $usuario_actual);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario_actual_id = $result->fetch_assoc()['id'];
    $stmt->close();

    // Eliminar la relación de seguimiento donde el usuario actual es seguido por el seguidor_id
    $sql_eliminar = "DELETE FROM seguidores WHERE seguido_id = ? AND (seguidor_id = ? OR (seguidor_id = 0 AND usuario_id = ?))";
    $stmt = $conn->prepare($sql_eliminar);
    $stmt->bind_param("iii", $usuario_actual_id, $seguidor_id, $seguidor_id);
    $stmt->execute();
    $stmt->close();
    
    // Forzar actualización completa de la página (no caché)
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Añadir timestamp para evitar caché
    $timestamp = time();
    
    // Redirigir de vuelta a la página de seguidores
    header("Location: seguidores.php?tipo=seguidores&id=$usuario_actual_id&t=$timestamp&updated=1&from_action=1");
    exit();
}

// Si no se envió el formulario, redirigir a la página principal
header("Location: inicio.php");
exit();
?>