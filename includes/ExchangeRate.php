<?php
// include/ExchangeRate.php

class ExchangeRate {
    private $conn;
    private $table = "system_settings"; // Usamos tu tabla original

    public function __construct($db) {
        $this->conn = $db;
    }

    // --- Lógica Principal ---

    public function getRate() {
        // 1. Consultar la tasa y la última actualización en la misma consulta
        // Asumimos que la configuración principal está siempre en id = 1
        $query = "SELECT bcv_rate, last_update FROM " . $this->table . " WHERE id = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Valores por defecto si la tabla está vacía
        $currentRate = ($row) ? (float)$row['bcv_rate'] : 0.00;
        $lastUpdate  = ($row) ? $row['last_update'] : null;

        // 2. Calcular cuánto tiempo ha pasado (en horas)
        // Si no hay fecha (null), ponemos 999 para forzar la actualización
        $hoursDiff = $lastUpdate ? (time() - strtotime($lastUpdate)) / 3600 : 999;

        // 3. Decidir si actualizamos:
        // Si la tasa es 0 (error previo o inicio) O si pasó más de 1 hora
        if ($currentRate <= 0 || $hoursDiff > 1) {
            
            $newRate = $this->fetchFromBCV();

            // Solo actualizamos la BD si el BCV nos dio un número válido
            if ($newRate > 0) {
                $this->updateRate($newRate);
                return $newRate;
            }
        }

        // Si no se actualizó (BCV caído o caché aún válida), devolvemos lo que hay en BD
        // Si es 0, devolvemos 1.00 para evitar división por cero en tu sistema
        return ($currentRate > 0) ? $currentRate : 1.00;
    }

    // Actualiza la tasa y la hora en tu tabla existente
    public function updateRate($rate) {
        // Usamos NOW() o date de PHP. Usaré date de PHP para consistencia.
        $now = date('Y-m-d H:i:s');

        // Primero intentamos actualizar el registro existente
        $query = "UPDATE " . $this->table . " SET bcv_rate = :rate, last_update = :last_update WHERE id = 1";
        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([':rate' => $rate, ':last_update' => $now]);

        // Si rowCount es 0, podría ser que no existe el ID 1. Lo insertamos por seguridad.
        if ($stmt->rowCount() == 0) {
            // Verificamos si realmente no existe (rowCount puede ser 0 si el valor es idéntico)
            $check = $this->conn->query("SELECT count(*) FROM " . $this->table . " WHERE id = 1")->fetchColumn();
            if ($check == 0) {
                 $insertQuery = "INSERT INTO " . $this->table . " (id, bcv_rate, last_update) VALUES (1, :rate, :last_update)";
                 $insertStmt = $this->conn->prepare($insertQuery);
                 return $insertStmt->execute([':rate' => $rate, ':last_update' => $now]);
            }
        }
        
        return $result;
    }

    // --- Scraping del BCV (Sin cambios, funciona igual) ---
    private function fetchFromBCV() {
        $url = 'https://www.bcv.org.ve/';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Vital para BCV
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // Vital para BCV
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); 

        $html = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$html || !empty($error)) {
            return 0;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//div[@id="dolar"]//strong');

        if ($nodes->length > 0) {
            $rateText = trim($nodes->item(0)->nodeValue);
            $rateText = str_replace(',', '.', $rateText);
            $rateText = preg_replace('/[^0-9.]/', '', $rateText);
            return (float) $rateText;
        }

        return 0;
    }

    // --- Compatibilidad con código legado ---
    public function getSystemRate() {
        return $this->getRate();
    }
}
?>