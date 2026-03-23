<?php
class Sale {
    private $conn;
    private $tenant_id;
    private $user_id;

    public function __construct($db, $tenant_id, $user_id) {
        $this->conn = $db;
        $this->tenant_id = $tenant_id;
        $this->user_id = $user_id;
    }

    /**
     * Procesa la venta: Valida stock, calcula totales y registra en BD
     */
    public function createSale($cartItems, $payment_method, $current_exchange_rate) {
        try {
            $this->conn->beginTransaction();

            $total_usd = 0;
            
            foreach ($cartItems as $item) {
                $sqlProd = "SELECT price_base_usd, profit_margin, stock 
                            FROM products 
                            WHERE id = :id AND tenant_id = :tenant_id 
                            FOR UPDATE"; 
                
                $stmt = $this->conn->prepare($sqlProd);
                $stmt->execute([':id' => $item['id'], ':tenant_id' => $this->tenant_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new Exception("Producto ID {$item['id']} no encontrado o no pertenece a su tienda.");
                }
                if ($product['stock'] < $item['qty']) {
                    throw new Exception("Stock insuficiente para el producto ID {$item['id']}.");
                }

                $unit_price = $product['price_base_usd'] * (1 + ($product['profit_margin'] / 100));
                $total_usd += ($unit_price * $item['qty']);
            }

            // Redondeamos el total USD a 2 decimales para limpiar micro-decimales de los márgenes
            $total_usd = round($total_usd, 2); 

            // Calculamos el total en Bs y lo redondeamos también a 2 decimales
            $total_bs = round($total_usd * $current_exchange_rate, 2);

            $sqlHead = "INSERT INTO sales 
                        (tenant_id, user_id, total_amount_usd, total_amount_bs, exchange_rate, payment_method, created_at) 
                        VALUES (:tid, :uid, :tusd, :tbs, :rate, :method, NOW())";
            
            $stmtHead = $this->conn->prepare($sqlHead);
            $stmtHead->execute([
                ':tid' => $this->tenant_id,
                ':uid' => $this->user_id,
                ':tusd' => $total_usd,
                ':tbs' => $total_bs,
                ':rate' => $current_exchange_rate,
                ':method' => $payment_method
            ]);
            
            $sale_id = $this->conn->lastInsertId();

            $sqlDetail = "INSERT INTO sale_items (sale_id, product_id, quantity, price_at_moment_usd) VALUES (?, ?, ?, ?)";
            $sqlStock  = "UPDATE products SET stock = stock - ? WHERE id = ? AND tenant_id = ?";

            foreach ($cartItems as $item) {
                $stmtP = $this->conn->prepare("SELECT price_base_usd, profit_margin FROM products WHERE id = ?");
                $stmtP->execute([$item['id']]);
                $p = $stmtP->fetch(PDO::FETCH_ASSOC);
                $finalPrice = $p['price_base_usd'] * (1 + ($p['profit_margin'] / 100));

                $stmtD = $this->conn->prepare($sqlDetail);
                $stmtD->execute([$sale_id, $item['id'], $item['qty'], $finalPrice]);

                $stmtS = $this->conn->prepare($sqlStock);
                $stmtS->execute([$item['qty'], $item['id'], $this->tenant_id]);
            }

            $this->conn->commit();

            return [
                "status" => "success", 
                "message" => "Venta registrada exitosamente", 
                "sale_id" => $sale_id,
                "total_usd" => $total_usd
            ];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    // --- MÉTODOS DE LECTURA (REPORTES) ---

    public function getHistory($filter = 'today') {
        $sql = "SELECT s.*, u.username 
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.tenant_id = :tid";

        // Filtros actualizados según la nueva interfaz
        if ($filter == 'today') {
            $sql .= " AND DATE(s.created_at) = CURDATE()";
        } elseif ($filter == '7days') {
            $sql .= " AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        } elseif ($filter == '30days') {
            $sql .= " AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($filter == 'month') {
            $sql .= " AND MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())";
        }
        // Si es 'all', no se aplica ningún filtro de fecha.

        $sql .= " ORDER BY s.id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSaleHeader($sale_id) {
        $sql = "SELECT s.*, t.business_name, t.rif,t.ticket_footer, u.username 
                FROM sales s
                JOIN tenants t ON s.tenant_id = t.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.id = :id AND s.tenant_id = :tid"; 
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $sale_id, ':tid' => $this->tenant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSaleItems($sale_id) {
        $sql = "SELECT si.*, p.name as product_name 
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                LEFT JOIN products p ON si.product_id = p.id
                WHERE si.sale_id = :id AND s.tenant_id = :tid";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $sale_id, ':tid' => $this->tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCashFlowStats($startDate, $endDate) {
        $sql = "SELECT 
                    payment_method, 
                    SUM(total_amount_usd) as total_usd, 
                    SUM(total_amount_bs) as total_bs,
                    COUNT(id) as total_transactions
                FROM sales 
                WHERE tenant_id = :tid 
                AND created_at BETWEEN :start AND :end 
                GROUP BY payment_method";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':tid' => $this->tenant_id,
            ':start' => $startDate . " 00:00:00",
            ':end' => $endDate . " 23:59:59"
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSalesChartData($startDate, $endDate) {
        $sql = "SELECT DATE(created_at) as sale_date, SUM(total_amount_usd) as total 
                FROM sales 
                WHERE tenant_id = :tid 
                AND created_at BETWEEN :start AND :end 
                GROUP BY DATE(created_at) 
                ORDER BY sale_date ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':tid' => $this->tenant_id,
            ':start' => $startDate . " 00:00:00",
            ':end' => $endDate . " 23:59:59"
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Anula una venta, devuelve el stock a los productos y actualiza el estado.
     */
    public function cancelSale($sale_id) {
        try {
            $this->conn->beginTransaction();

            // 1. Verificar existencia y estado actual de la venta
            $stmt = $this->conn->prepare("SELECT status FROM sales WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $stmt->execute([$sale_id, $this->tenant_id]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) throw new Exception("Venta no encontrada.");
            if ($sale['status'] === 'anulada') throw new Exception("Esta venta ya fue anulada anteriormente.");

            // 2. Obtener los productos y cantidades de la venta usando tu método existente
            $items = $this->getSaleItems($sale_id);

            // 3. Devolver el stock a la tabla products
            $sqlStock = "UPDATE products SET stock = stock + ? WHERE id = ? AND tenant_id = ?";
            $stmtStock = $this->conn->prepare($sqlStock);

            foreach ($items as $item) {
                $stmtStock->execute([$item['quantity'], $item['product_id'], $this->tenant_id]);
            }

            // 4. Cambiar el estado de la venta
            $stmtUpdate = $this->conn->prepare("UPDATE sales SET status = 'anulada' WHERE id = ? AND tenant_id = ?");
            $stmtUpdate->execute([$sale_id, $this->tenant_id]);

            $this->conn->commit();
            return ["status" => "success", "message" => "Venta anulada y stock restaurado correctamente."];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}
?>