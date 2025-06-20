<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/logout.php
session_start();

// 🔴 MARCAR COMO DESCONECTADO ANTES DE CERRAR SESIÓN
if (isset($_SESSION['usuario'])) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "trendly";

    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if (!$conn->connect_error) {
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
        
        // Marcar como desconectado con hora exacta
        $sql = "UPDATE usuarios SET en_linea = 0, ultimo_acceso = NOW() WHERE nombre = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    }
}

// Destruir la sesión
session_destroy();

// Redirigir al login
header("Location: login.php");
exit();
?>