
<?php
// Iniciar sesión
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

// Obtener datos del usuario
$usuario = $_SESSION['usuario'];
$sql_usuario = "SELECT id FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql_usuario);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: login.php");
    exit();
}

$usuario_id = $result->fetch_assoc()['id'];
$stmt->close();

// Procesar la publicación
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $contenido = trim($_POST['contenido']);
    
    // Verificar que el contenido no esté vacío
    if (empty($contenido)) {
        header("Location: perfil.php?error=empty_content");
        exit();
    }
    
    // Variable para almacenar la ruta de la imagen
    $ruta_imagen = "";
    
    // Procesar la imagen si se ha subido
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $nombre_archivo = $_FILES['imagen']['name'];
        $tipo_archivo = $_FILES['imagen']['type'];
        $tamano_archivo = $_FILES['imagen']['size'];
        $temp_archivo = $_FILES['imagen']['tmp_name'];
        
        // Verificar el tipo de archivo
        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), $extensiones_permitidas)) {
            if ($tamano_archivo <= 10000000) { // 10MB máximo
                // Crear directorio si no existe
                $directorio_destino = '../uploads/';
                if (!file_exists($directorio_destino)) {
                    mkdir($directorio_destino, 0777, true);
                }
                
                // Crear un nombre único para la imagen
                $nombre_unico = 'uploads/post_' . $usuario_id . '_' . time() . '.' . $ext;
                $ruta_destino = '../' . $nombre_unico;
                
                if (move_uploaded_file($temp_archivo, $ruta_destino)) {
                    $ruta_imagen = $nombre_unico;
                } else {
                    header("Location: perfil.php?error=upload_failed");
                    exit();
                }
            } else {
                header("Location: perfil.php?error=file_too_large");
                exit();
            }
        } else {
            header("Location: perfil.php?error=invalid_file_type");
            exit();
        }
    }
    
    // Insertar la publicación en la base de datos
    $sql = "INSERT INTO publicaciones (usuario_id, contenido, imagen, fecha) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $usuario_id, $contenido, $ruta_imagen);
    
    if ($stmt->execute()) {
        // Determinar de dónde vino la solicitud para redireccionar correctamente
        $redirect = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'inicio.php') !== false ? 'inicio.php' : 'perfil.php';
        header("Location: $redirect?success=published");
    } else {
        header("Location: perfil.php?error=database");
    }
    
    $stmt->close();
} else {
    // Si no es una solicitud POST, redirigir a la página de perfil
    header("Location: perfil.php");
}

$conn->close();
?>