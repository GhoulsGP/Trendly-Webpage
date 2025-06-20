<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/toggle_like.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$publicacion_id = $input['publicacion_id'];
$usuario_id = $input['usuario_id'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trendly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit();
}

// Verificar si ya existe el like
$sql = "SELECT id FROM likes WHERE publicacion_id = ? AND usuario_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $publicacion_id, $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Ya existe, eliminarlo
    $sql = "DELETE FROM likes WHERE publicacion_id = ? AND usuario_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $publicacion_id, $usuario_id);
    $stmt->execute();
    $liked = false;
} else {
    // No existe, agregarlo
    $sql = "INSERT INTO likes (publicacion_id, usuario_id, fecha) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $publicacion_id, $usuario_id);
    $stmt->execute();
    $liked = true;
}

// Obtener el total de likes
$sql = "SELECT COUNT(*) as total FROM likes WHERE publicacion_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $publicacion_id);
$stmt->execute();
$result = $stmt->get_result();
$total_likes = $result->fetch_assoc()['total'];

echo json_encode([
    'success' => true, 
    'liked' => $liked, 
    'total_likes' => $total_likes
]);

$conn->close();
?>