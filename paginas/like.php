<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$usuario = $_SESSION['usuario'];

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trendly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Obtener el ID del usuario que dio like
$sql_usuario = "SELECT id FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql_usuario);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result_usuario = $stmt->get_result();

if (!$result_usuario || $result_usuario->num_rows === 0) {
    die("Usuario no encontrado");
}
$usuario_id = $result_usuario->fetch_assoc()['id'];

// Obtener el ID de la publicación que recibió el like desde $_GET
if (!isset($_GET['post_id']) || empty($_GET['post_id'])) {
    die("ID de publicación no proporcionado");
}
$publicacion_id = intval($_GET['post_id']);

// Verificar si ya existe un like para esta publicación por este usuario
$sql_verificar_like = "SELECT * FROM likes WHERE usuario_id = ? AND publicacion_id = ?";
$stmt_verificar = $conn->prepare($sql_verificar_like);
$stmt_verificar->bind_param("ii", $usuario_id, $publicacion_id);
$stmt_verificar->execute();
$result_verificar = $stmt_verificar->get_result();

if ($result_verificar->num_rows > 0) {
    // Si ya existe un like, eliminarlo (toggle de "Me gusta")
    $sql_eliminar_like = "DELETE FROM likes WHERE usuario_id = ? AND publicacion_id = ?";
    $stmt_eliminar = $conn->prepare($sql_eliminar_like);
    $stmt_eliminar->bind_param("ii", $usuario_id, $publicacion_id);
    $stmt_eliminar->execute();
} else {
    // Si no existe un like, agregarlo
    $sql_insert_like = "INSERT INTO likes (usuario_id, publicacion_id) VALUES (?, ?)";
    $stmt_like = $conn->prepare($sql_insert_like);
    $stmt_like->bind_param("ii", $usuario_id, $publicacion_id);
    if (!$stmt_like->execute()) {
        die("Error al insertar like: " . $stmt_like->error);
    }
}

$conn->close();

// Redirigir de vuelta a la página anterior
header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
?>