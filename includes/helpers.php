<?php
function render_content_header($config)
{
    // Valores por defecto para evitar errores
    $title       = $config['title'] ?? 'Panel';
    $colorico    = $config['colorico'] ?? 'primary';
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
                        <i class="<?= htmlspecialchars($icon) ?> text-<?= $colorico ?> me-2"></i>
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

function render_modal($config, $bodyContent)
{
    // Valores por defecto
    $id          = $config['id'] ?? 'defaultModal';
    $formId      = $config['form_id'] ?? null; // Si tiene form_id, envuelve en <form>, si no, en <div>
    $title       = $config['title'] ?? 'Modal';
    $icon        = $config['icon'] ?? 'fas fa-info-circle';
    $bg_color    = $config['bg_color'] ?? 'primary';
    $submit_text = $config['submit_text'] ?? 'Guardar';
    $submit_id   = $config['submit_id'] ?? 'btnSubmit';
    $size        = $config['size'] ?? ''; // ej: 'modal-lg', 'modal-sm'
    $custom_btn  = $config['custom_buttons'] ?? ''; // Para botones extra en el footer

    ob_start();
?>
    <div class="modal fade" id="<?= htmlspecialchars($id) ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered <?= htmlspecialchars($size) ?>">
            <<?= $formId ? "form id=\"".htmlspecialchars($formId)."\"" : "div" ?> class="modal-content shadow">
                <div class="modal-header bg-<?= htmlspecialchars($bg_color) ?> text-white">
                    <h5 class="modal-title" id="<?= htmlspecialchars($id) ?>Title">
                        <i class="<?= htmlspecialchars($icon) ?> me-2"></i> <?= htmlspecialchars($title) ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <?= $bodyContent ?>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <?= $custom_btn ?>
                    <?php if ($formId): ?>
                        <button type="submit" class="btn btn-<?= htmlspecialchars($bg_color) ?> px-4 fw-bold" id="<?= htmlspecialchars($submit_id) ?>">
                            <?= htmlspecialchars($submit_text) ?>
                        </button>
                    <?php endif; ?>
                </<?= $formId ? "form" : "div" ?>>
            </div>
        </div>
    </div>
<?php
    return ob_get_clean();
}