let deferredPrompt;
const installBtn = document.createElement('button');
installBtn.className = 'btn btn-warning btn-sm fw-bold ms-2 d-none';
installBtn.innerHTML = '<i class="fas fa-download me-1"></i> Instalar App';

// Insertar botón en el Navbar (ajusta el selector según tu HTML)
document.querySelector('.app-header .navbar-nav.ms-auto').prepend(installBtn);

window.addEventListener('beforeinstallprompt', (e) => {
    // Previene que Chrome muestre el mini-infobar automáticamente
    e.preventDefault();
    deferredPrompt = e;
    
    // Muestra nuestro botón personalizado
    installBtn.classList.remove('d-none');

    installBtn.addEventListener('click', async () => {
        // Oculta el botón
        installBtn.classList.add('d-none');
        // Muestra el prompt nativo de instalación
        deferredPrompt.prompt();
        // Espera la decisión del usuario
        const { outcome } = await deferredPrompt.userChoice;
        if (outcome === 'accepted') {
            console.log('El usuario aceptó la instalación de MultiPOS');
        }
        deferredPrompt = null;
    });
});

// Detectar si ya se está ejecutando como App
window.addEventListener('appinstalled', () => {
    installBtn.classList.add('d-none');
    console.log('MultiPOS instalado correctamente');
});