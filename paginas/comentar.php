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
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comentario'], $_POST['publicacion_id'])) {
    $comentario = trim($_POST['comentario']);
    $publicacion_id = (int)$_POST['publicacion_id'];
    
    // Obtener ID del usuario actual
    $usuario = $_SESSION['usuario'];
    $sql_usuario = "SELECT id FROM usuarios WHERE nombre = ?";
    $stmt_usuario = $conn->prepare($sql_usuario);
    $stmt_usuario->bind_param("s", $usuario);
    $stmt_usuario->execute();
    $result_usuario = $stmt_usuario->get_result();
    $usuario_data = $result_usuario->fetch_assoc();
    $usuario_id = $usuario_data['id'];
    $stmt_usuario->close();
    
    if (!empty($comentario)) {
        // Obtener el autor del post
        $sql_post = "SELECT usuario_id FROM publicaciones WHERE id = ?";
        $stmt_post = $conn->prepare($sql_post);
        $stmt_post->bind_param("i", $publicacion_id);
        $stmt_post->execute();
        $result_post = $stmt_post->get_result();
        $post_data = $result_post->fetch_assoc();
        $post_author_id = $post_data['usuario_id'];
        $stmt_post->close();
        
        // Insertar comentario
        $sql_comentario = "INSERT INTO comentarios (publicacion_id, usuario_id, contenido) VALUES (?, ?, ?)";
        $stmt_comentario = $conn->prepare($sql_comentario);
        $stmt_comentario->bind_param("iis", $publicacion_id, $usuario_id, $comentario);
        
        if ($stmt_comentario->execute()) {
            // 🚨 CREAR NOTIFICACIÓN DE COMENTARIO
            crearNotificacion($conn, $post_author_id, 'comentario', $usuario_id, $publicacion_id);
            
            header("Location: inicio.php?success=comentario_agregado");
        } else {
            header("Location: inicio.php?error=error_comentario");
        }
        $stmt_comentario->close();
    }
}

$conn->close();
?>