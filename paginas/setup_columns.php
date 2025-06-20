<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/setup_columns.php
// Ejecutar este archivo UNA VEZ para agregar las columnas necesarias

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trendly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Verificar si la columna ultimo_acceso existe
$result = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'ultimo_acceso'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE usuarios ADD COLUMN ultimo_acceso TIMESTAMP NULL DEFAULT NULL";
    if ($conn->query($sql)) {
        echo "✅ Columna 'ultimo_acceso' agregada exitosamente.<br>";
    } else {
        echo "❌ Error agregando 'ultimo_acceso': " . $conn->error . "<br>";
    }
} else {
    echo "✅ Columna 'ultimo_acceso' ya existe.<br>";
}

// Verificar si la columna en_linea existe
$result = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'en_linea'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE usuarios ADD COLUMN en_linea TINYINT(1) DEFAULT 0";
    if ($conn->query($sql)) {
        echo "✅ Columna 'en_linea' agregada exitosamente.<br>";
    } else {
        echo "❌ Error agregando 'en_linea': " . $conn->error . "<br>";
    }
} else {
    echo "✅ Columna 'en_linea' ya existe.<br>";
}

$conn->close();
echo "<br>🎉 Configuración completada. Ya puedes eliminar este archivo.";
?>