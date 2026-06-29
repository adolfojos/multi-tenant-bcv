const bcvRate = window.APP_BCVRATE;

$(document).ready(function() {
    $('#creditsTable').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
        order: [[4, 'desc']]
    });

    // Calculadora en vivo en el modal
    $('#pay_amount').on('input', function() {
        let usd = parseFloat($(this).val()) || 0;
        $('#pay_bs_conversion').text(`Equivale a: Bs ${(usd * bcvRate).toFixed(2)}`);
    });

    // Enviar pago por AJAX
    $('#formPayment').on('submit', function(e) {
        e.preventDefault();
        $('#btnSubmitPayment').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');

        $.ajax({
            url: 'actions/actions_credit.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.status) {
                    Swal.fire('¡Éxito!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                    $('#btnSubmitPayment').prop('disabled', false).text('Confirmar Pago');
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
                $('#btnSubmitPayment').prop('disabled', false).text('Confirmar Pago');
            }
        });
    });
});

function openPaymentModal(id, maxAmount, customer) {
    $('#pay_credit_id').val(id);
    $('#pay_customer_name').text(customer);
    $('#pay_balance_display').text('$' + parseFloat(maxAmount).toFixed(2));
    $('#pay_amount').attr('max', maxAmount).val('');
    $('#pay_bs_conversion').text('Equivale a: Bs 0.00');
    
    let modal = new bootstrap.Modal(document.getElementById('modalPayment'));
    modal.show();
}

function viewHistory(credit_id) {
    $('#historyTableBody').html('<tr><td colspan="5"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>');
    let modal = new bootstrap.Modal(document.getElementById('modalHistory'));
    modal.show();

    $.ajax({
        url: 'actions/actions_credit.php',
        type: 'POST',
        data: { action: 'get_history', credit_id: credit_id },
        dataType: 'json',
        success: function(res) {
            if(res.status && res.data.length > 0) {
                let html = '';
                res.data.forEach(p => {
                    html += `<tr>
                        <td>${new Date(p.created_at).toLocaleString('es-VE')}</td>
                        <td class="text-success fw-bold">$${parseFloat(p.amount_usd).toFixed(2)}</td>
                        <td>Bs ${parseFloat(p.amount_bs).toFixed(2)}</td>
                        <td class="text-capitalize">${p.payment_method.replace('_', ' ')}</td>
                        <td><span class="badge bg-secondary">${p.username || 'N/A'}</span></td>
                    </tr>`;
                });
                $('#historyTableBody').html(html);
            } else {
                $('#historyTableBody').html('<tr><td colspan="5" class="text-muted">No hay pagos registrados.</td></tr>');
            }
        }
    });
}
window.enviarRecordatorioWhatsApp = function(phone, customerName, balanceUsd, currentBcv) {
    let cleanPhone = phone.replace(/\D/g, '');
    if (cleanPhone.startsWith('0')) {
        cleanPhone = '58' + cleanPhone.substring(1);
    } else if (cleanPhone.length === 10 && ['414', '424', '412', '416', '426'].some(prefix => cleanPhone.startsWith(prefix))) {
        cleanPhone = '58' + cleanPhone;
    }

    let usd = parseFloat(balanceUsd);
    let bcv = parseFloat(currentBcv);
    let bs = usd * bcv;
    let tenant = window.APP_TENANT_NAME || 'nuestro negocio';

    let mensaje = `Hola *${customerName}*, un gusto saludarte. 🌟\n`;
    mensaje += `Te escribimos de *${tenant}* para recordarte amablemente que mantienes un saldo pendiente en tu cuenta de:\n`;
    mensaje += `💵 *$${usd.toFixed(2)} USD*\n`;
    mensaje += `🇻🇪 *Bs. ${bs.toFixed(2)}* _(Calculado a la tasa BCV del día: Bs. ${bcv.toFixed(2)})_\n`;
    mensaje += `Puedes realizar tu pago móvil a los siguientes datos:\n`;
    mensaje += `📱 04161607891 | C.I. 19551521 | Banco: 0102\n`;
    mensaje += `Quedamos atentos a tu confirmación de pago. ¡Feliz día! 👍`;

    let mensajeCodificado = encodeURIComponent(mensaje);
    let webUrl = `https://wa.me/${cleanPhone}?text=${mensajeCodificado}`;
    let appUrl = `whatsapp://send?phone=${cleanPhone}&text=${mensajeCodificado}`;

    // 1. Intentamos abrir la App de escritorio
    window.location.href = appUrl;

    // 2. Usamos un temporizador para detectar si la App no se abrió
    // Si después de 2 segundos (2000ms) el navegador sigue "vivo", abrimos la versión web
    setTimeout(function() {
        // Verificamos si el foco sigue en el navegador
        if (!document.hidden) {
            window.open(webUrl, '_blank');
        }
    }, 2000);
};