<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/includes/footer.php
?>
    </div> <!-- Cierre del contenedor principal -->
    <footer class="text-center mt-5">
        <p>&copy; 2025 Trendly. Todos los derechos reservados.</p>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Loading script -->
    <script src="../js/loading.js"></script>

    <!-- Script personalizado para mejorar los modales -->
    <script>
    $(document).ready(function() {
        // Arreglo para el parpadeo de modales
        $('.modal').on('show.bs.modal', function (event) {
            $(this).appendTo('body');
            
            // Evitar que el modal se cierre al hacer clic dentro
            $(this).on('click', function(e) {
                if ($(e.target).closest('.modal-content').length) {
                    e.stopPropagation();
                }
            });
        });
        
        // Enfoque en áreas de texto al abrir modales
        $('.modal').on('shown.bs.modal', function() {
            $(this).find('textarea').first().focus();
        });
        
        // Estilos para los botones dentro de los modales
        $('.modal .btn').hover(
            function() { $(this).css('transform', 'translateY(-1px)'); },
            function() { $(this).css('transform', 'translateY(0)'); }
        );
    });
    </script>

    <?php
    // Cerrar conexión si fue creada por header.php
    if (isset($header_created_conn) && $header_created_conn && isset($conn) && !$conn->connect_error) {
        $conn->close();
    }
    ?>

</body>
</html>