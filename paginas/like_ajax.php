<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/like_ajax.php
session_start();

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
$post_id = (int)$input['post_id'];
$user_id = (int)$input['user_id'];

// Verificar que el post existe y obtener el autor
$sql_post = "SELECT usuario_id FROM publicaciones WHERE id = ?";
$stmt_post = $conn->prepare($sql_post);
$stmt_post->bind_param("i", $post_id);
$stmt_post->execute();
$result_post = $stmt_post->get_result();

if ($result_post->num_rows == 0) {
    echo json_encode(['success' => false, 'error' => 'Post no encontrado']);
    exit();
}

$post_data = $result_post->fetch_assoc();
$post_author_id = $post_data['usuario_id'];
$stmt_post->close();

// Verificar si ya existe el like
$sql_check = "SELECT id FROM likes WHERE publicacion_id = ? AND usuario_id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("ii", $post_id, $user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    // Ya existe el like, eliminarlo
    $sql_delete = "DELETE FROM likes WHERE publicacion_id = ? AND usuario_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("ii", $post_id, $user_id);
    $stmt_delete->execute();
    $stmt_delete->close();
    
    $liked = false;
} else {
    // No existe el like, crearlo
    $sql_insert = "INSERT INTO likes (publicacion_id, usuario_id) VALUES (?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("ii", $post_id, $user_id);
    $stmt_insert->execute();
    $stmt_insert->close();
    
    $liked = true;
    
    // 🚨 CREAR NOTIFICACIÓN DE LIKE
    crearNotificacion($conn, $post_author_id, 'like', $user_id, $post_id);
}

$stmt_check->close();

// Contar total de likes
$sql_count = "SELECT COUNT(*) as total FROM likes WHERE publicacion_id = ?";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $post_id);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$count_data = $result_count->fetch_assoc();
$total_likes = $count_data['total'];
$stmt_count->close();

$conn->close();

echo json_encode([
    'success' => true,
    'liked' => $liked,
    'total_likes' => $total_likes
]);
?>