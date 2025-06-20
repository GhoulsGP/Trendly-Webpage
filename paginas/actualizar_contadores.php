<?php
// Este archivo devuelve los contadores actualizados para un perfil específico
header('Content-Type: application/json');

// Evitar caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trendly";

$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit();
}

// Obtener el ID del perfil
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID no válido']);
    exit();
}

// Obtener contadores actualizados
// Contadores para seguidores (personas que siguen al perfil)
$sql_seguidores = "SELECT COUNT(*) AS total FROM seguidores WHERE seguido_id = $id AND (seguidor_id > 0 OR usuario_id > 0)";
$result_seguidores = $conn->query($sql_seguidores);
$total_seguidores = $result_seguidores->fetch_assoc()['total'];

// Contadores para seguidos (personas a las que el perfil sigue)
$sql_seguidos = "SELECT COUNT(*) AS total FROM seguidores WHERE (seguidor_id = $id OR (seguidor_id = 0 AND usuario_id = $id)) AND seguido_id > 0";
$result_seguidos = $conn->query($sql_seguidos);
$total_seguidos = $result_seguidos->fetch_assoc()['total'];

// Devolver los datos actualizados
echo json_encode([
    'success' => true,
    'seguidores' => $total_seguidores,
    'seguidos' => $total_seguidos
]);

$conn->close();
?>