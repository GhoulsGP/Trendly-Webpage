<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/add_comment.php
session_start();
header('Content-Type: application/json');

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trendly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit();
}

// ✅ INCLUIR LA FUNCIÓN DE NOTIFICACIONES
include 'crear_notificacion.php';

// Obtener datos del JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['publicacion_id']) || !isset($input['contenido'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

$publicacion_id = (int)$input['publicacion_id'];
$contenido = trim($input['contenido']);
$usuario_id = (int)$input['usuario_id'];

if (empty($contenido)) {
    echo json_encode(['success' => false, 'error' => 'El comentario no puede estar vacío']);
    exit();
}

// Obtener el autor del post para crear la notificación
$sql_post = "SELECT usuario_id FROM publicaciones WHERE id = ?";
$stmt_post = $conn->prepare($sql_post);
$stmt_post->bind_param("i", $publicacion_id);
$stmt_post->execute();
$result_post = $stmt_post->get_result();

if ($result_post->num_rows == 0) {
    echo json_encode(['success' => false, 'error' => 'Publicación no encontrada']);
    exit();
}

$post_data = $result_post->fetch_assoc();
$post_author_id = $post_data['usuario_id'];
$stmt_post->close();

// Insertar el comentario
$sql_insert = "INSERT INTO comentarios (publicacion_id, usuario_id, contenido) VALUES (?, ?, ?)";
$stmt_insert = $conn->prepare($sql_insert);
$stmt_insert->bind_param("iis", $publicacion_id, $usuario_id, $contenido);

if ($stmt_insert->execute()) {
    // 🚨 CREAR NOTIFICACIÓN DE COMENTARIO
    crearNotificacion($conn, $post_author_id, 'comentario', $usuario_id, $publicacion_id);
    
    echo json_encode(['success' => true, 'message' => 'Comentario agregado']);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al agregar comentario']);
}

$stmt_insert->close();
$conn->close();
?>