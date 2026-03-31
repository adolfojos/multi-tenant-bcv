     const chartDates = window.APP_BCVRATE;

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