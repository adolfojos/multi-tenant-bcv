<?php
function render_content_header($config)
{
    // Valores por defecto para evitar errores
    $title       = $config['title'] ?? 'Panel';
    $icon        = $config['icon'] ?? 'fas fa-home';
    $tenant      = $config['tenant'] ?? 'Sistema POS';
    $bcv         = $config['bcv'] ?? 0;

    // Configuración del botón (opcional)
    $button      = $config['button'] ?? null;

    ob_start(); // Iniciamos buffer para devolver el HTML como string
?>
    <div class="app-content-header py-3 border-bottom mb-4">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="<?= htmlspecialchars($icon) ?> text-primary me-2"></i>
                        <?= htmlspecialchars($title) ?>
                    </h3>
                    <small class="text-secondary"><?= htmlspecialchars($tenant) ?></small>
                </div>

                <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">

                    <span class="btn btn-outline-secondary mb-2 btn-sm text-start">
                        <i class="fas fa-coins me-1"></i> BCV: Bs. <?= number_format($bcv, 2) ?>
                    </span>
                    <?php if ($button): ?>
                        <button
                            class="<?= $button['class'] ?? 'btn btn-primary' ?>"

                            <?php if (isset($button['attributes'])): ?>
                            <?= $button['attributes'] ?>
                            <?php else: ?>
                            onclick="openModal()"
                            data-bs-toggle="modal"
                            data-bs-target="<?= $button['target'] ?? '#modalDefault' ?>"
                            <?php endif; ?>>
                            <i class="<?= $button['icon'] ?? 'fas fa-plus' ?> me-1"></i>
                            <?= $button['text'] ?? 'Agregar' ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php
    return ob_get_clean();
}
