// --- Lógica de Modo Oscuro ---

// Referencias a los elementos
const htmlElement = document.documentElement;
const darkModeSwitch = document.getElementById('darkModeSwitch');

// 1. Cargar preferencia guardada (localStorage tiene prioridad)
const currentTheme = localStorage.getItem('theme') || 'dark';
htmlElement.setAttribute('data-bs-theme', currentTheme);

// Verificar si el switch existe en el DOM antes de asignar el estado
if (darkModeSwitch) {
    darkModeSwitch.checked = (currentTheme === 'dark');

    // 2. Escuchar el cambio manual del usuario
    darkModeSwitch.addEventListener('change', () => {
        const newTheme = darkModeSwitch.checked ? 'dark' : 'light';
        
        // Aplicar cambio visual inmediato
        htmlElement.setAttribute('data-bs-theme', newTheme);
        
        // Guardar en el navegador
        localStorage.setItem('theme', newTheme);
    });
}

// --- Funciones de Interacción ---

/**
 * Función para confirmar acciones críticas
 * @param {string} task - Descripción de la tarea a realizar
 */

function confirmAction(task, actionType) {
    if(confirm("¿Estás completamente seguro de " + task + "? Esta acción eliminará registros permanentemente y es irreversible.")) {
        
        // Crear un formulario dinámico para enviarlo por POST de forma segura
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'actions/actions_critical.php'; // Crearemos este archivo para tareas peligrosas

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'critical_action';
        input.value = actionType;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}