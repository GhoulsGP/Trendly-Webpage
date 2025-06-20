<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/check_like.php
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

$sql = "SELECT id FROM likes WHERE publicacion_id = ? AND usuario_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $publicacion_id, $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

echo json_encode(['liked' => $result->num_rows > 0]);
$conn->close();
?>