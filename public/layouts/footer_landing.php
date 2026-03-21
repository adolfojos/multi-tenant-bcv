<footer class="bg-dark text-white pt-5 pb-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-box-seam me-2"></i>MultiPOS</h5>
                    <p class="text-secondary small">Transformando la gestión comercial con tecnología intuitiva y potente. Control total de tu negocio en la palma de tu mano.</p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white fs-5"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="text-white fs-5"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="text-white fs-5"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                
                <div class="col-md-2 mb-4">
                    <h6 class="fw-bold">Producto</h6>
                    <ul class="list-unstyled small">
                        <li><a href="#" class="text-secondary text-decoration-none">Funciones</a></li>
                        <li><a href="#" class="text-secondary text-decoration-none">Actualizaciones</a></li>
                        <li><a href="#" class="text-secondary text-decoration-none">Seguridad</a></li>
                    </ul>
                </div>

                <div class="col-md-3 mb-4">
                    <h6 class="fw-bold">Soporte</h6>
                    <ul class="list-unstyled small">
                        <li><a href="#" class="text-secondary text-decoration-none">Centro de Ayuda</a></li>
                        <li><a href="#" class="text-secondary text-decoration-none">Documentación API</a></li>
                        <li><a href="https://wa.me/573148900155" class="text-secondary text-decoration-none">Contacto WhatsApp</a></li>
                    </ul>
                </div>

                <div class="col-md-3 mb-4 text-md-end">
                    <h6 class="fw-bold">Legal</h6>
                    <ul class="list-unstyled small">
                        <li><a href="#" class="text-secondary text-decoration-none">Términos de Servicio</a></li>
                        <li><a href="#" class="text-secondary text-decoration-none">Política de Privacidad</a></li>
                    </ul>
                </div>
            </div>
            
            <hr class="border-secondary mt-4">
            
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="text-secondary mb-0 small">&copy; <?php echo date("Y"); ?> MultiPOS. Todos los derechos reservados.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="text-secondary mb-0 small">Hecho con <i class="bi bi-heart-fill text-danger"></i> para emprendedores.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts de Bootstrap y AdminLTE 4 -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>
    
    <!-- Script para efectos de scroll en el Navbar -->
    <script>
        window.addEventListener('scroll', function() {
            const nav = document.querySelector('.navbar-landing');
            if (window.scrollY > 50) {
                nav.classList.add('shadow-sm');
            } else {
                nav.classList.remove('shadow-sm');
            }
        });
    </script>
</body>
</html>