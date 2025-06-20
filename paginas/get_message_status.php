<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/get_message_status.php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    exit();
}

// Verificar parámetros
if (!isset($_GET['chat_with'])) {
    http_response_code(400);
    exit();
}

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trendly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    exit();
}

$usuario = $_SESSION['usuario'];
$chat_with = intval($_GET['chat_with']);

// Obtener ID del usuario actual
$sql_usuario = "SELECT id FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql_usuario);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();
$usuario_data = $result->fetch_assoc();
$usuario_id = $usuario_data['id'];
$stmt->close();

// Obtener estado de mensajes enviados por el usuario actual
$sql_messages = "SELECT id, leido FROM mensajes 
                 WHERE sender_id = ? AND receiver_id = ? 
                 ORDER BY fecha DESC LIMIT 20";
$stmt = $conn->prepare($sql_messages);
$stmt->bind_param("ii", $usuario_id, $chat_with);
$stmt->execute();
$result = $stmt->get_result();

$messages = array();
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($messages);
?>