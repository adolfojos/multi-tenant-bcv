<?php
class Credit {
    private $conn;
    private $tenant_id;

    public function __construct($db, $tenant_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
    }

    // Obtener todos los créditos pendientes o con saldo
    public function getPending() {
        $sql = "SELECT c.*, cust.name as customer_name, cust.document, s.created_at as sale_date 
                FROM credits c
                JOIN customers cust ON c.customer_id = cust.id
                JOIN sales s ON c.sale_id = s.id
                WHERE c.tenant_id = :tid AND c.status != 'cancelled'
                ORDER BY c.status ASC, c.due_date ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener historial de pagos de un crédito específico
    public function getPayments($credit_id) {
        $sql = "SELECT cp.*, u.username 
                FROM credit_payments cp
                LEFT JOIN users u ON cp.user_id = u.id
                WHERE cp.credit_id = :cid AND cp.tenant_id = :tid
                ORDER BY cp.created_at DESC";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':cid' => $credit_id, ':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Registrar un nuevo abono
    public function addPayment($credit_id, $user_id, $amount_usd, $exchange_rate, $method) {
        try {
            $this->conn->beginTransaction();

            // 1. Verificar el saldo actual y bloquear la fila para evitar cobros dobles simultáneos
            $stmtCheck = $this->conn->prepare("SELECT balance_usd FROM credits WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $stmtCheck->execute([$credit_id, $this->tenant_id]);
            $credit = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$credit) {
                throw new Exception("Crédito no encontrado.");
            }

            if ($amount_usd <= 0) {
                throw new Exception("El monto debe ser mayor a cero.");
            }

            if ($amount_usd > $credit['balance_usd']) {
                throw new Exception("El abono ($" . number_format($amount_usd, 2) . ") supera el saldo pendiente ($" . number_format($credit['balance_usd'], 2) . ").");
            }

            $amount_bs = $amount_usd * $exchange_rate;

            // 2. Insertar el pago
            $sqlPay = "INSERT INTO credit_payments (tenant_id, credit_id, user_id, amount_usd, amount_bs, exchange_rate, payment_method) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmtPay = $this->conn->prepare($sqlPay);
            $stmtPay->execute([$this->tenant_id, $credit_id, $user_id, $amount_usd, $amount_bs, $exchange_rate, $method]);

            // 3. Actualizar el saldo del crédito
            $new_balance = $credit['balance_usd'] - $amount_usd;
            $new_status = ($new_balance <= 0.00) ? 'paid' : 'pending';

            $sqlUpdate = "UPDATE credits SET balance_usd = ?, status = ? WHERE id = ? AND tenant_id = ?";
            $stmtUpdate = $this->conn->prepare($sqlUpdate);
            $stmtUpdate->execute([$new_balance, $new_status, $credit_id, $this->tenant_id]);

            $this->conn->commit();
            return ["status" => true, "message" => "Abono registrado exitosamente."];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ["status" => false, "message" => $e->getMessage()];
        }
    }
}
?>