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
    $seguido_id = intval($_POST['seguido_id']);
    $usuario_actual = $_SESSION['usuario'];

    // Obtener el ID del usuario actual
    $sql_usuario_actual = "SELECT id FROM usuarios WHERE nombre = '$usuario_actual'";
    $result_usuario_actual = $conn->query($sql_usuario_actual);
    $usuario_actual_id = $result_usuario_actual->fetch_assoc()['id'];

    // Eliminar la relación de seguimiento
    $sql_eliminar_seguimiento = "DELETE FROM seguidores WHERE usuario_id = $usuario_actual_id AND seguido_id = $seguido_id";
    if ($conn->query($sql_eliminar_seguimiento) === TRUE) {
        header("Location: perfil_usuario.php?id=$seguido_id");
        exit();
    } else {
        echo "Error al dejar de seguir al usuario: " . $conn->error;
    }
}

$conn->close();
?>