<?php
// Iniciar sesión
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

// Obtener el ID del usuario actual
$usuario = $_SESSION['usuario'];
$sql_usuario = "SELECT id FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql_usuario);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();
$usuario_id = $result->fetch_assoc()['id'];
$stmt->close();

// Verificar si se proporcionó un ID de publicación válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: perfil.php?error=invalid_id");
    exit();
}

$publicacion_id = intval($_GET['id']);

// Verificar que la publicación pertenece al usuario actual
$sql_verificar = "SELECT usuario_id FROM publicaciones WHERE id = ?";
$stmt = $conn->prepare($sql_verificar);
$stmt->bind_param("i", $publicacion_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // La publicación no existe
    header("Location: perfil.php?error=not_found");
    exit();
}

$publicacion = $result->fetch_assoc();
$stmt->close();

if ($publicacion['usuario_id'] != $usuario_id) {
    // La publicación no pertenece al usuario actual
    header("Location: perfil.php?error=unauthorized");
    exit();
}

// Obtener la ruta de la imagen antes de eliminar la publicación
$sql_imagen = "SELECT imagen FROM publicaciones WHERE id = ?";
$stmt = $conn->prepare($sql_imagen);
$stmt->bind_param("i", $publicacion_id);
$stmt->execute();
$result = $stmt->get_result();
$ruta_imagen = $result->fetch_assoc()['imagen'];
$stmt->close();

// Eliminar comentarios asociados a la publicación
$sql_eliminar_comentarios = "DELETE FROM comentarios WHERE publicacion_id = ?";
$stmt = $conn->prepare($sql_eliminar_comentarios);
$stmt->bind_param("i", $publicacion_id);
$stmt->execute();
$stmt->close();

// Eliminar likes asociados a la publicación
$sql_eliminar_likes = "DELETE FROM likes WHERE publicacion_id = ?";
$stmt = $conn->prepare($sql_eliminar_likes);
$stmt->bind_param("i", $publicacion_id);
$stmt->execute();
$stmt->close();

// Eliminar la publicación
$sql_eliminar = "DELETE FROM publicaciones WHERE id = ? AND usuario_id = ?";
$stmt = $conn->prepare($sql_eliminar);
$stmt->bind_param("ii", $publicacion_id, $usuario_id);
$resultado = $stmt->execute();
$stmt->close();

if ($resultado) {
    // Eliminar la imagen del servidor si existe
    if (!empty($ruta_imagen)) {
        $ruta_completa = "../" . $ruta_imagen;
        if (file_exists($ruta_completa)) {
            unlink($ruta_completa);
        }
    }
    
    header("Location: perfil.php?success=deleted");
} else {
    header("Location: perfil.php?error=delete_failed");
}

$conn->close();
?>