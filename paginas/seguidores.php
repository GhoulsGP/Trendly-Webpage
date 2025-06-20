<?php
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

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener el ID del usuario actual correctamente
$usuario_actual = $_SESSION['usuario'];
$sql_usuario_actual = "SELECT id FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql_usuario_actual);
$stmt->bind_param("s", $usuario_actual);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: login.php");
    exit();
}

$usuario_actual_id = $result->fetch_assoc()['id'];
$stmt->close();

// Verificar parámetros de URL
if (!isset($_GET['tipo']) || !isset($_GET['id'])) {
    header("Location: inicio.php");
    exit();
}

$tipo = $_GET['tipo'];  // 'seguidores' o 'seguidos'
$id = intval($_GET['id']);

// Verificar que el tipo sea válido
if ($tipo != 'seguidores' && $tipo != 'seguidos') {
    header("Location: inicio.php");
    exit();
}

// Obtener información del usuario que estamos viendo
$sql_usuario_perfil = "SELECT nombre, foto_perfil FROM usuarios WHERE id = $id";
$result_usuario_perfil = $conn->query($sql_usuario_perfil);

if ($result_usuario_perfil->num_rows == 0) {
    header("Location: inicio.php");
    exit();
}

$usuario_perfil = $result_usuario_perfil->fetch_assoc();

// Obtener el número de seguidores del perfil actual
$sql_seguidores = "SELECT COUNT(*) AS total FROM seguidores WHERE seguido_id = $id AND (seguidor_id > 0 OR usuario_id > 0)";
$result_seguidores = $conn->query($sql_seguidores);
$total_seguidores = $result_seguidores->fetch_assoc()['total'];

// Obtener el número de seguidos del perfil actual
$sql_seguidos = "SELECT COUNT(*) AS total FROM seguidores WHERE (seguidor_id = $id OR (seguidor_id = 0 AND usuario_id = $id)) AND seguido_id > 0";
$result_seguidos = $conn->query($sql_seguidos);
$total_seguidos = $result_seguidos->fetch_assoc()['total'];

// Marcamos que la conexión a la BD no fue creada por header.php
$header_created_conn = false;

// Incluir el encabezado
include '../includes/header.php';

// Si acabamos de realizar una acción de seguir/dejar
if (isset($_GET['updated']) && isset($_GET['from_action'])) {
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            let url = new URL(window.location.href);
            url.searchParams.delete("updated");
            url.searchParams.delete("from_action");
            url.searchParams.delete("t");
            url.searchParams.set("t", Date.now());
            window.location.href = url.toString();
        }, 500);
    });
    </script>';
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-sm rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <img src="../<?php echo $usuario_perfil['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                             alt="Foto de perfil" 
                             class="rounded-circle me-3" 
                             style="width: 50px; height: 50px; object-fit: cover;">
                        <div>
                            <h4 class="mb-0"><?php echo htmlspecialchars($usuario_perfil['nombre']); ?></h4>
                            <div class="text-muted small">
                                <span><span id="contador-seguidores"><?php echo $total_seguidores; ?></span> seguidores</span> • 
                                <span><span id="contador-seguidos"><?php echo $total_seguidos; ?></span> seguidos</span>
                            </div>
                        </div>
                    </div>
                    <div class="btn-group">
                        <a href="seguidores.php?tipo=seguidores&id=<?php echo $id; ?>&t=<?php echo time(); ?>" 
                           class="btn <?php echo $tipo == 'seguidores' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            Seguidores
                        </a>
                        <a href="seguidores.php?tipo=seguidos&id=<?php echo $id; ?>&t=<?php echo time(); ?>" 
                           class="btn <?php echo $tipo == 'seguidos' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            Seguidos
                        </a>
                    </div>
                </div>

                <div class="list-group">
                    <?php
                    // Consulta para obtener seguidores o seguidos según el tipo
                    if ($tipo == 'seguidores') {
                        // Mostrar usuarios que siguen al perfil actual
                        $sql = "SELECT 
                                u.id, 
                                u.nombre, 
                                u.foto_perfil,
                                (SELECT COUNT(*) FROM seguidores 
                                    WHERE (seguidor_id = $usuario_actual_id OR usuario_id = $usuario_actual_id) 
                                    AND seguido_id = u.id) as ya_sigue
                                FROM seguidores s
                                JOIN usuarios u ON (s.seguidor_id = u.id OR (s.seguidor_id = 0 AND s.usuario_id = u.id))
                                WHERE s.seguido_id = $id
                                GROUP BY u.id
                                ORDER BY u.nombre ASC";
                    } else { // seguidos
                        // Mostrar usuarios a los que sigue el perfil actual
                        $sql = "SELECT 
                                u.id, 
                                u.nombre, 
                                u.foto_perfil,
                                (SELECT COUNT(*) FROM seguidores 
                                    WHERE (seguidor_id = $usuario_actual_id OR usuario_id = $usuario_actual_id) 
                                    AND seguido_id = u.id) as ya_sigue
                                FROM seguidores s
                                JOIN usuarios u ON s.seguido_id = u.id
                                WHERE (s.seguidor_id = $id OR (s.seguidor_id = 0 AND s.usuario_id = $id))
                                GROUP BY u.id
                                ORDER BY u.nombre ASC";
                    }
                    
                    $result = $conn->query($sql);
                    
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            // No mostrar el propio usuario en la lista
                            if ($row['id'] == $usuario_actual_id) {
                                continue;
                            }
                    ?>
                            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <a href="perfil_usuario.php?id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                        <img src="../<?php echo $row['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                                             alt="Foto de perfil" 
                                             class="rounded-circle me-3" 
                                             style="width: 40px; height: 40px; object-fit: cover;">
                                    </a>
                                    <a href="perfil_usuario.php?id=<?php echo $row['id']; ?>" class="text-decoration-none text-dark">
                                        <strong><?php echo htmlspecialchars($row['nombre']); ?></strong>
                                    </a>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <?php if ($row['id'] != $usuario_actual_id): ?>
                                        <?php if ($tipo == 'seguidores' && $id == $usuario_actual_id): ?>
                                            <!-- Solo mostrar botón de Eliminar en mis seguidores -->
                                            <form method="POST" action="eliminar_seguidor.php" class="d-inline">
                                                <input type="hidden" name="seguidor_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm rounded-pill px-3" 
                                                        onclick="return confirm('¿Estás seguro de que quieres eliminar a este seguidor?');">
                                                    <i class="bi bi-person-x-fill me-1"></i> Eliminar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <!-- Mostrar los botones de seguir/siguiendo en el resto de casos -->
                                            <?php if ($row['ya_sigue'] > 0): ?>
                                                <form method="POST" action="seguir_o_dejar.php" class="d-inline">
                                                    <input type="hidden" name="seguido_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="accion" value="dejar">
                                                    <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                                        <i class="bi bi-person-check-fill me-1"></i> Siguiendo
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="seguir_o_dejar.php" class="d-inline">
                                                    <input type="hidden" name="seguido_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="accion" value="seguir">
                                                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">
                                                        <i class="bi bi-person-plus-fill me-1"></i> Seguir
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                    <?php
                        }
                    } else {
                        echo '<div class="alert alert-info">No hay '.($tipo == 'seguidores' ? 'seguidores' : 'usuarios seguidos').' para mostrar.</div>';
                    }
                    ?>
                </div>
                
                <div class="mt-4">
                    <a href="<?php echo $id == $usuario_actual_id ? 'perfil.php' : 'perfil_usuario.php?id='.$id; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Volver al perfil
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Agregar parámetros a los formularios mediante JavaScript
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    // Agregar parámetros a los formularios
    const forms = document.querySelectorAll("form[action=\'seguir_o_dejar.php\']");
    forms.forEach(form => {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "referrer";
        input.value = "seguidores";
        form.appendChild(input);
        
        const idInput = document.createElement("input");
        idInput.type = "hidden";
        idInput.name = "perfil_id";
        idInput.value = "'.$id.'";
        form.appendChild(idInput);
        
        const tipoInput = document.createElement("input");
        tipoInput.type = "hidden";
        tipoInput.name = "tipo";
        tipoInput.value = "'.$tipo.'";
        form.appendChild(tipoInput);
        
        // Desactivar temporalmente el botón después de hacer clic
        form.addEventListener("submit", function() {
            const button = this.querySelector("button");
            button.disabled = true;
            button.innerHTML = "<span class=\'spinner-border spinner-border-sm\' role=\'status\' aria-hidden=\'true\'></span> Procesando...";
        });
    });
});

// Función para actualizar los contadores sin recargar toda la página
function actualizarContadores() {
    fetch("actualizar_contadores.php?id='.$id.'&t=" + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById("contador-seguidores").textContent = data.seguidores;
                document.getElementById("contador-seguidos").textContent = data.seguidos;
            }
        })
        .catch(error => console.error("Error al actualizar contadores:", error));
}

document.addEventListener("DOMContentLoaded", function() {
    setTimeout(actualizarContadores, 1000);
});
</script>';

// Manejo de la conexión a la base de datos
include '../includes/footer.php';
?>