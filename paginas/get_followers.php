<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/get_followers.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$usuario_id = $input['usuario_id'];
$tipo = $input['tipo'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trendly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit();
}

if ($tipo === 'seguidores') {
    $sql = "SELECT u.id, u.nombre, u.foto_perfil 
            FROM usuarios u 
            INNER JOIN seguidores s ON u.id = s.seguidor_id 
            WHERE s.seguido_id = ?";
} else {
    $sql = "SELECT u.id, u.nombre, u.foto_perfil 
            FROM usuarios u 
            INNER JOIN seguidores s ON u.id = s.seguido_id 
            WHERE s.seguidor_id = ?";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$usuarios = [];
while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

echo json_encode(['success' => true, 'usuarios' => $usuarios]);
$conn->close();
?>