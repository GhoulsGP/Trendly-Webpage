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

// ✅ INCLUIR LA FUNCIÓN DE NOTIFICACIONES
include 'crear_notificacion.php';

// Obtener datos del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['seguido_id'])) {
    // Obtener el ID del usuario a seguir
    $seguido_id = (int)$_POST['seguido_id'];
    
    // Validar que sea un ID válido
    if ($seguido_id <= 0) {
        header("Location: inicio.php?error=invalid_id");
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

    // Verificar si ya existe la relación
    $sql_verificar = "SELECT id FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?";
    $stmt = $conn->prepare($sql_verificar);
    $stmt->bind_param("ii", $usuario_actual_id, $seguido_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Ya lo sigue, dejar de seguir
        $sql_dejar = "DELETE FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?";
        $stmt_dejar = $conn->prepare($sql_dejar);
        $stmt_dejar->bind_param("ii", $usuario_actual_id, $seguido_id);
        $stmt_dejar->execute();
        $stmt_dejar->close();
        
        $mensaje = "dejaste_de_seguir";
    } else {
        // No lo sigue, empezar a seguir
        $sql_seguir = "INSERT INTO seguidores (seguidor_id, usuario_id, seguido_id) VALUES (?, ?, ?)";
        $stmt_seguir = $conn->prepare($sql_seguir);
        $stmt_seguir->bind_param("iii", $usuario_actual_id, $usuario_actual_id, $seguido_id);
        $stmt_seguir->execute();
        $stmt_seguir->close();
        
        // 🚨 CREAR NOTIFICACIÓN DE SEGUIDOR
        crearNotificacion($conn, $seguido_id, 'seguidor', $usuario_actual_id, null);
        
        $mensaje = "ahora_sigues";
    }
    
    $stmt->close();
}

$conn->close();

// Redirigir de vuelta
$redirect_url = $_POST['redirect_url'] ?? $_SERVER['HTTP_REFERER'] ?? 'inicio.php';
header("Location: " . $redirect_url . "?action=" . $mensaje);
exit();
?>