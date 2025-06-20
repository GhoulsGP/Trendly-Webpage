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
    $comentario_id = intval($_POST['comentario_id']);
    $publicacion_id = intval($_POST['publicacion_id']);
    $usuario = $_SESSION['usuario'];

    // Obtener el ID del usuario actual
    $sql_usuario = "SELECT id FROM usuarios WHERE nombre = '$usuario'";
    $result_usuario = $conn->query($sql_usuario);
    $usuario_id = $result_usuario->fetch_assoc()['id'];

    // Verificar si el usuario tiene permiso para eliminar el comentario
    $sql_verificar = "SELECT c.usuario_id AS autor_comentario, p.usuario_id AS autor_publicacion 
                      FROM comentarios c 
                      JOIN publicaciones p ON c.publicacion_id = p.id 
                      WHERE c.id = $comentario_id";
    $result_verificar = $conn->query($sql_verificar);

    if ($result_verificar->num_rows > 0) {
        $datos = $result_verificar->fetch_assoc();
        $autor_comentario = $datos['autor_comentario'];
        $autor_publicacion = $datos['autor_publicacion'];

        if ($usuario_id == $autor_comentario || $usuario_id == $autor_publicacion) {
            // Eliminar el comentario
            $sql_eliminar = "DELETE FROM comentarios WHERE id = $comentario_id";
            if ($conn->query($sql_eliminar) === TRUE) {
                header("Location: inicio.php#comentarios$publicacion_id");
                exit();
            } else {
                echo "Error al eliminar el comentario: " . $conn->error;
            }
        } else {
            echo "No tienes permiso para eliminar este comentario.";
        }
    } else {
        echo "Comentario no encontrado.";
    }
}

$conn->close();
?>