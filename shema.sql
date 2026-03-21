-- 1. Tabla PRINCIPAL: Inquilinos (Tenants)
-- Debe ir primero porque casi todas las demás tablas dependen de ella.
CREATE TABLE tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(100) NOT NULL,
    rif VARCHAR(20),
    license_key VARCHAR(50) UNIQUE, -- Código único de activación
    status ENUM('active', 'suspended', 'expired') DEFAULT 'active',
    expiration_date DATE NOT NULL,
    plan_type ENUM('basic', 'premium', 'unlimited') DEFAULT 'basic',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Configuración Global del Sistema
-- Tabla independiente (Singleton).
CREATE TABLE system_settings (
    id INT PRIMARY KEY,
    bcv_rate DECIMAL(10, 4), -- Tasa del Banco Central (Venezuela context)
    last_update DATETIME
);

-- Insertamos el registro inicial de configuración
INSERT INTO system_settings (id, bcv_rate, last_update) VALUES (1, 36.50, NOW());

-- 3. Usuarios
-- Vinculados al tenant correspondiente.
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL, -- Columna integrada aquí directamente
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Hash de contraseña
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
ALTER TABLE users ADD COLUMN role ENUM('admin', 'seller') DEFAULT 'seller';
-- 4. Categorías
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL, -- Cada categoría pertenece a un negocio específico
    name VARCHAR(100) NOT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- 5. Productos
-- Depende de Categories y Tenants.
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL, -- Columna integrada
    category_id INT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price_base_usd DECIMAL(10, 2) NOT NULL, -- Precio costo
    profit_margin DECIMAL(5, 2) NOT NULL DEFAULT 30.00, -- % de ganancia
    stock INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Índices para búsqueda rápida
    INDEX idx_tenant_product (tenant_id, id),
    -- Llaves foráneas
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
ALTER TABLE products 
ADD COLUMN sku VARCHAR(50) NULL AFTER name,
ADD COLUMN barcode VARCHAR(100) NULL AFTER sku,
ADD COLUMN brand VARCHAR(100) NULL AFTER barcode;
-- 6. Ventas
-- Cabecera de la venta
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    total_amount_usd DECIMAL(10,2),
    total_amount_bs DECIMAL(12,2),
    exchange_rate DECIMAL(10,4), -- Guardamos la tasa del momento exacto
    payment_method ENUM('efectivo_usd', 'efectivo_bs', 'pago_movil', 'zelle', 'punto') DEFAULT 'efectivo_bs',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- 7. Detalles de Venta
-- Depende de Sales y Products.
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price_at_moment DECIMAL(10,2) NOT NULL, -- Precio congelado al momento de la venta
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);