<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/update_online_status.php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trendly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    exit('Database connection failed');
}

$usuario = $_SESSION['usuario'];

// 🆕 VERIFICAR Y AGREGAR COLUMNAS SI NO EXISTEN (SIN IF NOT EXISTS)

// Verificar ultimo_acceso
$result = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'ultimo_acceso'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE usuarios ADD COLUMN ultimo_acceso TIMESTAMP NULL DEFAULT NULL");
}

// Verificar en_linea
$result = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'en_linea'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE usuarios ADD COLUMN en_linea TINYINT(1) DEFAULT 0");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['offline'])) {
        // 🔴 MARCAR COMO DESCONECTADO
        $sql = "UPDATE usuarios SET en_linea = 0, ultimo_acceso = NOW() WHERE nombre = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $stmt->close();
        }
        
        http_response_code(200);
        echo 'User marked offline';
    } elseif (isset($_POST['keep_alive'])) {
        // 🟢 MANTENER EN LÍNEA
        $sql = "UPDATE usuarios SET en_linea = 1, ultimo_acceso = NOW() WHERE nombre = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $stmt->close();
        }
        
        http_response_code(200);
        echo 'Status updated';
    }
} else {
    // 🔄 LIMPIAR USUARIOS INACTIVOS (más de 2 minutos)
    $sql_cleanup = "UPDATE usuarios SET en_linea = 0 WHERE ultimo_acceso < DATE_SUB(NOW(), INTERVAL 2 MINUTE) AND en_linea = 1";
    $conn->query($sql_cleanup);
    
    http_response_code(200);
    echo 'Cleanup performed';
}

$conn->close();
?>