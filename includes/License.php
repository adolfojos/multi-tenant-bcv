<?php
class License {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Registrar una nueva tienda (Tenant)
    public function registerTenant($name, $rif, $months) {
        $expiration = date('Y-m-d', strtotime("+$months months"));
        $license_key = bin2hex(random_bytes(8)); // Genera un código aleatorio

        $query = "INSERT INTO tenants (business_name, rif, license_key, expiration_date) 
                  VALUES (:name, :rif, :key, :exp)";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':name' => $name,
            ':rif' => $rif,
            ':key' => strtoupper($license_key),
            ':exp' => $expiration
        ]);
    }

    // Renovar licencia
    public function renew($tenant_id, $months) {
        $query = "UPDATE tenants 
                  SET expiration_date = DATE_ADD(expiration_date, INTERVAL :months MONTH),
                      status = 'active'
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':months' => $months, ':id' => $tenant_id]);
    }
}