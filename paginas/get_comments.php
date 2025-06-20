<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/get_comments.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$publicacion_id = $input['publicacion_id'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trendly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit();
}

$sql = "SELECT c.*, u.nombre as nombre_usuario, u.foto_perfil 
        FROM comentarios c 
        INNER JOIN usuarios u ON c.usuario_id = u.id 
        WHERE c.publicacion_id = ? 
        ORDER BY c.fecha ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $publicacion_id);
$stmt->execute();
$result = $stmt->get_result();

$comentarios = [];
while ($row = $result->fetch_assoc()) {
    $comentarios[] = $row;
}

echo json_encode(['success' => true, 'comentarios' => $comentarios]);
$conn->close();
?>