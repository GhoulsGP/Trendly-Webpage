<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trendly";

$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// ✅ INCLUIR LA FUNCIÓN DE NOTIFICACIONES
include 'crear_notificacion.php';

// Obtener datos del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtener el ID del usuario a seguir o dejar de seguir
    $seguido_id = isset($_POST['seguido_id']) ? intval($_POST['seguido_id']) : 0;
    
    // Verificar que se proporcionó un ID válido
    if ($seguido_id <= 0) {
        header("Location: inicio.php");
        exit();
    }
    
    // Obtener la acción (seguir o dejar)
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    
    // Verificar que la acción es válida
    if ($accion != 'seguir' && $accion != 'dejar') {
        header("Location: inicio.php");
        exit();
    }
    
    // Obtener el usuario actual
    $usuario_actual = $_SESSION['usuario'];
    
    // Verificar si viene de la página de seguidores
    $referrer = isset($_POST['referrer']) ? $_POST['referrer'] : '';
    $perfil_id = isset($_POST['perfil_id']) ? intval($_POST['perfil_id']) : 0;
    $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';

    // Obtener el ID del usuario actual
    $sql_usuario_actual = "SELECT id FROM usuarios WHERE nombre = ?";
    $stmt = $conn->prepare($sql_usuario_actual);
    $stmt->bind_param("s", $usuario_actual);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario_actual_id = $result->fetch_assoc()['id'];
    $stmt->close();

    if ($accion === "seguir") {
        // Verificar si ya existe la relación
        $sql_verificar = "SELECT id FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?";
        $stmt = $conn->prepare($sql_verificar);
        $stmt->bind_param("ii", $usuario_actual_id, $seguido_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Verificar si existe una relación con seguidor_id = 0
            $sql_verificar_cero = "SELECT id FROM seguidores WHERE usuario_id = ? AND seguido_id = ? AND (seguidor_id IS NULL OR seguidor_id = 0)";
            $stmt = $conn->prepare($sql_verificar_cero);
            $stmt->bind_param("ii", $usuario_actual_id, $seguido_id);
            $stmt->execute();
            $result_cero = $stmt->get_result();
            
            if ($result_cero->num_rows > 0) {
                // Si existe, actualizar el registro existente
                $sql_actualizar = "UPDATE seguidores SET seguidor_id = ? WHERE usuario_id = ? AND seguido_id = ? AND (seguidor_id IS NULL OR seguidor_id = 0)";
                $stmt = $conn->prepare($sql_actualizar);
                $stmt->bind_param("iii", $usuario_actual_id, $usuario_actual_id, $seguido_id);
                $stmt->execute();
            } else {
                // Insertar en la tabla de seguidores con seguidor_id
                $sql_seguir = "INSERT INTO seguidores (seguidor_id, usuario_id, seguido_id) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql_seguir);
                $stmt->bind_param("iii", $usuario_actual_id, $usuario_actual_id, $seguido_id);
                $stmt->execute();
                
                // 🚨 CREAR NOTIFICACIÓN DE SEGUIDOR
                crearNotificacion($conn, $seguido_id, 'seguidor', $usuario_actual_id, null);
            }
            $stmt->close();
        } 
        
    } elseif ($accion === "dejar") {
        // Intentar eliminar primero registros con seguidor_id correcto
        $sql_dejar = "DELETE FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?";
        $stmt = $conn->prepare($sql_dejar);
        $stmt->bind_param("ii", $usuario_actual_id, $seguido_id);
        $stmt->execute();
        $rows_affected = $stmt->affected_rows;
        $stmt->close();
        
        // Si no se eliminó ningún registro, intentar con la columna usuario_id
        if ($rows_affected == 0) {
            $sql_dejar_alt = "DELETE FROM seguidores WHERE usuario_id = ? AND seguido_id = ?";
            $stmt = $conn->prepare($sql_dejar_alt);
            $stmt->bind_param("ii", $usuario_actual_id, $seguido_id);
            $stmt->execute();
            $rows_affected = $stmt->affected_rows;
            $stmt->close();
        }
    }
    
    // Forzar actualización completa de la página (no caché)
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Añadir timestamp para evitar caché
    $timestamp = time();
    
    // Redirigir según de dónde vino la solicitud
    if ($referrer === 'seguidores' && $perfil_id > 0 && !empty($tipo)) {
        header("Location: seguidores.php?tipo=$tipo&id=$perfil_id&t=$timestamp&updated=1&from_action=1");
    } else {
        header("Location: perfil_usuario.php?id=$seguido_id&t=$timestamp&updated=1&from_action=1");
    }
    exit();
}

$conn->close();
?>