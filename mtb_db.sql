-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 31-03-2026 a las 15:24:08
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `mtb_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categories`
--

INSERT INTO `categories` (`id`, `tenant_id`, `name`) VALUES
(1, 1, 'Analgésicos'),
(2, 1, 'Antialérgicos'),
(3, 1, 'Antibióticos'),
(4, 1, 'Anticonceptivos'),
(5, 1, 'Antidiarreicos'),
(6, 1, 'Antiespasmódicos'),
(7, 1, 'Antimicóticos'),
(8, 1, 'Antineurítico'),
(9, 1, 'Antipruriginosos'),
(10, 1, 'Antisépticos'),
(11, 1, 'Antivirales'),
(12, 1, 'Broncodilatador'),
(13, 1, 'Cuidado De La Vista'),
(14, 1, 'Cuidado Oral'),
(15, 1, 'Dermocosmética'),
(16, 1, 'Equipos Médicos'),
(17, 1, 'Expectorante'),
(18, 1, 'Higiene y Cuidado Personal'),
(19, 1, 'Infantil y Maternidad'),
(20, 1, 'Descartable / Curación'),
(21, 1, 'Nutrición y Suplementos'),
(22, 1, 'Protector Gástrico'),
(23, 1, 'Salud Sexual'),
(24, 1, 'Soluciones y Sueros'),
(25, 1, 'Vitaminas'),
(33, 11, 'Impresiones y Fotocopias'),
(34, 11, 'Transcripciones y Digitalización (P/P)'),
(35, 11, 'Diapositivas (P/L)'),
(36, 11, 'Gestión de Trámites'),
(37, 11, 'Servicio Técnico');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `credits`
--

CREATE TABLE `credits` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total_amount_usd` decimal(10,2) NOT NULL,
  `balance_usd` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `credits`
--

INSERT INTO `credits` (`id`, `tenant_id`, `sale_id`, `customer_id`, `total_amount_usd`, `balance_usd`, `status`, `due_date`, `created_at`) VALUES
(6, 11, 70, 1, 2.00, 2.00, 'pending', '2026-03-30', '2026-03-31 03:44:32'),
(7, 11, 71, 1, 1.95, 1.95, 'pending', NULL, '2026-03-31 03:45:32'),
(8, 11, 73, 5, 1.95, 1.95, 'pending', '2026-04-11', '2026-03-31 03:46:15'),
(9, 11, 74, 1, 1.95, 1.95, 'pending', '2026-03-28', '2026-03-31 03:50:16'),
(10, 11, 75, 1, 1.95, 1.95, 'pending', NULL, '2026-03-31 03:51:41'),
(11, 11, 78, 1, 2.00, 2.00, 'pending', NULL, '2026-03-31 04:20:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `credit_payments`
--

CREATE TABLE `credit_payments` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `credit_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount_usd` decimal(10,2) NOT NULL,
  `amount_bs` decimal(10,2) NOT NULL,
  `exchange_rate` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `document` varchar(50) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `customers`
--

INSERT INTO `customers` (`id`, `tenant_id`, `name`, `document`, `phone`, `created_at`) VALUES
(1, 11, 'Elizabeth Álvarez', '18357594', '04245277596', '2026-03-26 23:13:51'),
(2, 11, 'Jose Morillo', '19551522', '0426678951', '2026-03-26 23:51:36'),
(5, 11, 'Luis Suarez', '123456', '0426678955s', '2026-03-27 00:22:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price_base_usd` decimal(10,2) NOT NULL,
  `profit_margin` decimal(5,2) NOT NULL DEFAULT 30.00,
  `stock` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `products`
--

INSERT INTO `products` (`id`, `tenant_id`, `category_id`, `name`, `sku`, `barcode`, `brand`, `image`, `description`, `price_base_usd`, `profit_margin`, `stock`, `created_at`) VALUES
(1, 1, 1, 'Diclofenac Potásico 50mg x 30 Comprimidos', 'GEN-DIP-50MG30', '750123456001', 'Genven', 'Genven.png', 'Antiinflamatorio no esteroideo (AINE).', 0.00, 30.00, 0, '2026-01-03 09:54:12'),
(2, 1, 4, 'Femigyna 50mg-5mg/Ml 1ml. X1 Ampolla', 'OVE-FEM-1ML', '750123456002', 'Overol', 'Overol.png', 'Anticonceptivo inyectable.', 0.00, 30.00, 0, '2026-01-03 10:22:57'),
(3, 1, 1, 'Diclofenac Potásico 50mg 10 Comprimidos', 'GEN-DIP-50MG10', '750123456003', 'Genven', 'Genven.png', 'Alivio del dolor e inflamación.', 0.00, 30.00, 0, '2026-01-03 10:26:21'),
(4, 1, 1, 'Acetaminofén 650mg 30 Comprimidos', 'GEN-ACE-650MG30', '750123456004', 'Genven', 'Genven.png', 'Analgésico y antipirético.', 1.24, 30.00, 3, '2026-01-03 10:29:02'),
(5, 1, 1, 'Acetaminofén 650mg 10 Tabletas', 'SAN-ACE-650MG10', '750123456005', 'La Santé', 'Lasante.png', 'Alivio de fiebre y dolor.', 0.00, 30.00, 0, '2026-01-03 10:32:44'),
(6, 1, 1, 'Diclofenac Potásico 50mg x 10 Tabletas', 'KMP-DIP-50MG10', '750123456006', 'KMPLUS', 'Kmplus.png', 'Analgésico y antiinflamatorio.', 0.00, 30.00, 0, '2026-01-03 10:36:50'),
(7, 1, 19, 'Analper 180mg/5ml Pediátrico Fresa 120ml', 'PHA-ANA-120ML', '750123456007', 'Pharmetique', 'Pharmetique.png', 'Acetaminofén de uso pediátrico.', 2.70, 150.00, 2, '2026-01-03 10:42:40'),
(8, 1, 1, 'Diclofenac Potásico 50mg x 10 Tabletas', 'SAN-DIP-50MG10', '750123456008', 'La Santé', 'Lasante.png', 'Tratamiento de dolores leves.', 0.00, 30.00, 0, '2026-01-03 10:43:54'),
(9, 1, 20, 'Algodón Hidrófilo Algobap 10g/20g', 'ALG-ALGO-20G', '750123456009', 'Algobap', 'Algobap.png', 'Algodón absorbente para curas.', 0.77, 30.00, 4, '2026-01-03 10:48:59'),
(10, 1, 24, 'Hidrolit Suero Oral 21.5Gr X Sobre', 'DRO-HID-21GR', '750123456010', 'Drotafarma', 'Drotafarma.png', 'Reposición de electrolitos.', 1.25, 30.00, 3, '2026-01-03 10:55:57'),
(11, 1, 7, 'Fluconazol 150 mg 100 Tabletas', 'DRO-FLU-150MG', '750123456011', 'Drotafarma', 'Drotafarma.png', 'Antifúngico para candidiasis.', 0.00, 30.00, 0, '2026-01-03 10:59:00'),
(12, 1, 3, 'Clavumix (Amoxicilina + Clavulánico) Susp.', 'DRO-CLA-250MG', '750123456012', 'Drotafarma', 'Drotafarma.png', 'Antibiótico de amplio espectro.', 7.69, 30.00, 1, '2026-01-03 11:05:24'),
(13, 1, 2, 'Cetirizina 5 mg / 5 ml Jarabe 60 ml', 'DRO-CET-60ML', '750123456013', 'Drotafarma', 'Drotafarma.png', 'Antihistamínico para niños.', 0.00, 30.00, 0, '2026-01-03 11:09:06'),
(14, 1, 12, 'Salbumed 100 mcg Inhalador 200 dosis', 'AVA-SAL-200D', '750123456014', 'Avalon', 'Avalon.png', 'Broncodilatador para asma.', 7.69, 30.00, 1, '2026-01-03 11:12:34'),
(15, 1, 17, 'Bromhexina 4 mg /5 ml Jarabe 120 ml', 'DRO-BRO-120ML', '750123456015', 'Drotafarma', 'Drotafarma.png', 'Expectorante y mucolítico.', 0.00, 30.00, 0, '2026-01-03 11:14:21'),
(16, 1, 3, 'Metronidazol 250mg / 5ml Susp. 100 ml', 'DRO-MET-100ML', '750123456016', 'Drotafarma', 'Drotafarma.png', 'Antibiótico y antiparasitario.', 0.00, 30.00, 0, '2026-01-03 11:18:00'),
(17, 1, 6, 'Hioscina N-Butilbromuro 10mg', 'BLU-HIO-10MG', '750123456017', 'BlueMedical', 'Bluemedical.png', 'Antiespasmódico abdominal.', 2.31, 30.00, 2, '2026-01-03 11:22:14'),
(18, 1, 7, 'Clotrimazol 1% Crema x 20 gr', 'DRO-CLO-20GR', '750123456018', 'Drotafarma', 'Drotafarma.png', 'Antifúngico tópico.', 3.87, 30.00, 1, '2026-01-03 11:25:22'),
(19, 1, 9, 'Hidrocortisona 1% Crema 15 gr', 'DRO-HID-15GR', '750123456019', 'Drotafarma', 'Drotafarma.png', 'Corticosteroide para piel.', 3.69, 30.00, 0, '2026-01-03 11:28:06'),
(20, 1, 3, 'Amoxicilina + Clavulánico 875/125mg 10 Tab.', 'DRO-AMO-10TAB', '750123456020', 'Drotafarma', 'Drotafarma.png', 'Antibiótico potente.', 7.69, 30.00, 2, '2026-01-03 11:29:34'),
(21, 1, 17, 'Ambroxol 30 mg / 5 ml Jarabe 120 ml', 'DRO-AMB-120ML', '750123456021', 'Drotafarma', 'Drotafarma.png', 'Expectorante pediátrico.', 0.00, 30.00, 0, '2026-01-03 11:31:29'),
(22, 1, 3, 'Amoxicilina 250 mg / 5 ml Polvo 60 ml', 'DRO-AMO-60ML', '750123456022', 'Drotafarma', 'Drotafarma.png', 'Antibiótico para infecciones comunes.', 3.85, 30.00, 2, '2026-01-03 11:33:50'),
(23, 1, 3, 'Azitromicina 500Mg X 3 Tabletas', 'DRO-AZI-500MG3', '750123456023', 'Drotafarma', 'Drotafarma.png', 'Antibiótico macrólido.', 3.85, 30.00, 1, '2026-01-03 11:36:49'),
(24, 1, 16, 'Jeringa Desechable 10 Ml', 'GEN-JER-10ML', '750123456024', 'Genérica', 'Jeringa-10-Ml.png', 'Uso hospitalario.', 0.39, 30.00, 109, '2026-01-04 21:38:36'),
(25, 1, 16, 'Jeringa Desechable 20 Ml', 'GEN-JER-20ML', '750123456025', 'Genérica', 'Jeringa-20-Ml.png', 'Uso hospitalario.', 0.45, 30.00, 39, '2026-01-04 21:38:36'),
(26, 1, 16, 'Jeringa Desechable 3 Ml', 'GEN-JER-3ML', '750123456026', 'Genérica', 'Jeringa-3-Ml.png', 'Uso hospitalario.', 0.29, 30.00, 17, '2026-01-04 21:38:36'),
(27, 1, 16, 'Jeringa Desechable 5 Ml', 'GEN-JER-5ML', '750123456027', 'Genérica', 'Jeringa-5-Ml.png', 'Uso hospitalario.', 0.39, 30.00, 14, '2026-01-04 21:38:36'),
(28, 1, 25, 'Miovit Complejo B+Lidocaína 3 Ampollas', 'COF-MIO-3AMP', '750123456028', 'Cofasa', 'Cofasa.png', 'Vitaminas B1, B6, B12.', 0.00, 30.00, 1, '2026-01-08 05:05:02'),
(29, 1, 24, 'Solución Cloruro De Sodio 0.9% 500 ml', 'PIS-SOD-500ML', '750123456029', 'Pisa', 'Solucion-Cloruro.jpg', 'Aporte de electrolitos.', 2.31, 30.00, 6, '2026-01-08 05:10:13'),
(30, 1, 1, 'Hidrocortisona Ampolla 100Mg I.M/I.V', 'KMP-HID-100MG', '750123456030', 'KMPLUS', 'Kmplus.png', 'Antiinflamatorio esteroideo.', 5.00, 50.00, 3, '2026-01-08 05:13:17'),
(31, 1, 1, 'Hidrocortisona Ampolla 500Mg I.M/I.V', 'KMP-HID-500MG', '750123456031', 'KMPLUS', 'Kmplus.png', 'Tratamiento de shock.', 5.00, 50.00, 1, '2026-01-08 05:14:07'),
(32, 1, 22, 'Omeprazol 40 ML Inyectable', 'DRO-OME-40ML', '750123456032', 'Drotafarma', 'Drotafarma.png', 'Protector gástrico.', 2.31, 30.00, 5, '2026-01-08 05:21:08'),
(33, 1, 3, 'Penicilina G benzatínica 2.4 Millones U.I.', 'DRO-PEN-2.4M', '750123456033', 'Drotafarma', 'Drotafarma.png', 'Antibiótico inyectable.', 0.96, 424.00, 8, '2026-01-08 05:29:15'),
(34, 1, 3, 'Penicilina G benzatínica 1.2 Millones U.I.', 'DRO-PEN-1.2M', '750123456034', 'Drotafarma', 'Drotafarma.png', 'Antibiótico inyectable.', 3.85, 30.00, 2, '2026-01-08 05:36:02'),
(35, 1, 16, 'Obturador Fidem', 'FID-OBT-UNIT', '750123456035', 'Fidem', 'Obturador.jpg', 'Uso en cateterismo.', 0.37, 30.00, 13, '2026-01-08 05:38:13'),
(36, 1, 20, 'Equipo Pericraneal Scalp Vein 21G', 'GEN-SCA-21G', '750123456036', 'Genérica', '', 'Para venofleclisis.', 0.55, 25.00, 17, '2026-01-08 05:47:29'),
(37, 1, 20, 'Equipo Pericraneal Scalp Vein 25G', 'GEN-SCA-25G', '750123456037', 'Genérica', '', 'Para venofleclisis.', 0.55, 25.00, 35, '2026-01-08 05:50:02'),
(38, 1, 1, 'Ibuprofeno 600 mg 100 Tabletas', 'DRO-IBU-600MG', '750123456038', 'Drotafarma', 'Drotafarma.png', 'AINE fuerte.', 2.50, 30.00, 1, '2026-01-08 06:10:45'),
(39, 1, 22, 'Omeprazol 20 mg 60 Cápsulas', 'DRO-OME-20MG', '750123456039', 'Drotafarma', 'Drotafarma.png', 'Reductor de ácido gástrico.', 1.50, 30.00, 3, '2026-01-08 06:13:29'),
(40, 1, 1, 'Diclofenac Potásico 50mg x 10 Tabletas', 'MED-DIP-50MG', '750123456040', 'Medigen', 'Medigen.png', 'Analgésico potente.', 1.48, 30.00, 5, '2026-01-08 06:17:39'),
(41, 1, 23, 'Diapost Levonorgestrel 1.5 mg 1 Tab.', 'DRO-DIA-1.5MG', '750123456041', 'Drotafarma', 'Drotafarma.png', 'Anticonceptivo de emergencia.', 3.85, 30.00, 3, '2026-01-08 06:20:31'),
(42, 1, 3, 'Azitromicina 500 mg 3 Tabletas', 'DRO-AZI-500MG', '750123456042', 'Drotafarma', 'Drotafarma.png', 'Antibiótico de amplio espectro.', 3.10, 30.00, 2, '2026-01-08 06:22:32'),
(43, 1, 3, 'Amoxicilina 500 mg', 'DRO-AMO-500MG', '750123456043', 'Drotafarma', '', 'Antibiótico.', 1.48, 30.00, 1, '2026-01-08 06:25:05'),
(44, 1, 1, 'Ketoprofeno 100 mg 100 Tabletas', 'GEN-KET-100MG', '750123456044', 'Genérica', 'Ketoprofeno.png', 'Para dolor moderado.', 2.31, 30.00, 2, '2026-01-08 06:28:17'),
(45, 1, 2, 'Loratadina 10 mg 100 Tabletas', 'DRO-LOR-10MG', '750123456045', 'Drotafarma', 'Drotafarma.png', 'Antialérgico sin sueño.', 1.48, 30.00, 2, '2026-01-08 06:29:27'),
(46, 1, 2, 'Clorfeniramina 4 mg 100 Tabletas', 'DRO-CLO-4MG', '750123456046', 'Drotafarma', 'Drotafarma.png', 'Antialérgico clásico.', 1.48, 30.00, 2, '2026-01-08 06:30:24'),
(47, 1, 5, 'Loperamida 2Mg X 10 Cápsulas', 'DRO-LOP-2MG', '750123456047', 'Drotafarma', 'Drotafarma.png', 'Antidiarreico.', 1.48, 30.00, 2, '2026-01-08 06:31:39'),
(48, 1, 1, 'Dexametasona 4Mg X 10 Tabletas', 'LAN-DEX-4MG', '750123456048', 'Land', 'Land.png', 'Corticosteroide.', 1.99, 30.00, 1, '2026-01-08 06:36:21'),
(49, 1, 16, 'Recolector de Heces', 'GEN-REC-HECES', '750123456049', 'Genérica', 'recolector.jpg', 'Para exámenes de lab.', 0.75, 30.00, 5, '2026-01-08 06:46:07'),
(50, 1, 20, 'Nylon Monofilamento 3-0 a 6-0', 'GEN-NYL-USP', '750123456050', 'Genérica', '', 'Sutura quirúrgica.', 3.85, 30.00, 13, '2026-01-08 06:55:23'),
(51, 1, 20, 'Guante Quirúrgico Estéril Látex', 'SEB-GUA-UNIT', '750123456051', 'Sebis', 'Sebis.png', 'Guantes estériles.', 1.92, 30.00, 10, '2026-01-08 06:58:16'),
(52, 1, 20, 'Gasa Esteril (3X3) 2 Unidades', 'GAE-GAS-3X3', '750123456052', 'Gaesca', 'Gaesca.png', 'Para curación de heridas.', 0.77, 30.01, 0, '2026-01-08 07:02:36'),
(53, 1, 20, 'Macrogotero con Punto Inyección', 'GEN-MAC-UNIT', '750123456053', 'Genérica', 'macro1.jpg', 'Equipo de venoclisis.', 1.93, 30.00, 26, '2026-01-08 07:33:13'),
(54, 1, 2, 'Clorfeniramina Jarabe 10 ml', 'KWA-CLO-10ML', '750123456054', 'Kwality', 'Kwality.png', 'Antihistamínico.', 2.31, 30.00, 6, '2026-01-09 08:37:13'),
(55, 1, 6, 'Butilbromuro de Hioscina 20 mg', 'KWA-HIO-20MG', '750123456055', 'Kwality', 'Kwality.png', 'Antiespasmódico.', 2.31, 30.00, 6, '2026-01-09 08:47:47'),
(56, 1, 1, 'Ondansetron IV 8 ml', 'LIF-OND-8ML', '750123456056', 'Lifesciencies', 'Lifesciencies.png', 'Antiemético (náuseas).', 3.46, 30.00, 1, '2026-01-09 08:50:44'),
(57, 1, 1, 'Ondansetron IV 4 ml', 'LIF-OND-4ML', '750123456057', 'Lifesciencies', 'Lifesciencies.png', 'Antiemético.', 3.46, 30.00, 2, '2026-01-09 08:52:58'),
(58, 1, 25, 'Vitamina K1 Inyectable 10 ml', 'KWA-VK1-10ML', '750123456058', 'Kwality', 'Kwality.png', 'Coagulante.', 2.31, 30.00, 3, '2026-01-09 08:54:50'),
(59, 1, 1, 'Dipirona Solución Inyectable 1 g', 'LIF-DIP-1G', '750123456059', 'Lifesciencies', 'Lifesciencies.png', 'Analgésico potente.', 2.31, 30.00, 10, '2026-01-09 08:57:13'),
(60, 1, 1, 'Metoclopramida 10Mg/2Ml Ampolla', 'KMP-MET-10MG', '750123456060', 'KMPLUS', 'Kmplus.png', 'Para náuseas y vómitos.', 0.68, 342.00, 11, '2026-01-09 09:03:54'),
(61, 1, 1, 'Dexametasona Ampolla 8 Mg/2Ml', 'DRO-DEX-8MG', '750123456061', 'Drotafarma', 'Drotafarma.png', 'Corticosteroide inyectable.', 2.31, 30.00, 2, '2026-01-09 09:06:16'),
(62, 1, 10, 'Alcohol Antiséptico 95% 100 ml', 'ALN-ALC-100ML', '750123456062', 'ALNA', 'Alna.png', 'Desinfección de piel.', 1.15, 30.00, 6, '2026-01-09 09:13:51'),
(63, 1, 10, 'Agua Oxigenada 120 ml', 'GUA-H2O-120ML', '750123456063', 'El Guardián', 'Elguardian.png', 'Antiséptico heridas.', 1.31, 30.00, 3, '2026-01-09 09:19:14'),
(64, 1, 1, 'Ibuprofeno 800Mg (Unidad)', 'LAP-IBU-800MG-U', '750123456064', 'Laproff', 'Laproff.png', 'Analgésico y antiinflamatorio de alta concentración.', 0.11, 67.00, 19, '2026-01-11 07:02:59'),
(65, 1, 1, 'Prednisolona 50Mg X 10 Tabletas', 'LAN-PRE-50MG', '750123456065', 'Land', 'Land.png', 'Corticosteroide para reducir inflamación y enrojecimiento.', 2.97, 69.00, 1, '2026-01-11 07:24:39'),
(66, 1, 1, 'Torsilax Blister 10 Tabletas', 'NEO-TOR-10TAB', '750123456066', 'Neo Quimica', 'Neoquimica.png', 'Relajante muscular con cafeína, diclofenac y paracetamol.', 2.01, 66.00, 0, '2026-01-11 07:30:06'),
(67, 1, 1, 'Torsilax (Unidad)', 'NEO-TOR-UNIT', '750123456067', 'Neo Quimica', 'Neoquimica.png', 'Relajante muscular (Venta por unidad).', 0.20, 68.00, 19, '2026-01-11 07:34:28'),
(68, 1, 2, 'Desloratadina 5mg Blíster 10 Tabletas', 'DRO-DES-5MG', '750123456068', 'Drotafarma', 'Drotafarma.png', 'Antihistamínico de nueva generación, no produce somnolencia.', 0.72, 153.00, 0, '2026-01-11 07:37:30'),
(69, 1, 19, 'Ibuprofeno 100mg/5ml Suspensión 60ml', 'SAN-IBU-60ML', '750123456069', 'La Santé', 'Lasante.png', 'Antiinflamatorio y antipirético pediátrico.', 5.13, 57.00, 0, '2026-01-11 07:55:21'),
(70, 1, 20, 'Cateter Iv O Jelco Ecomed (Varios Calibres)', 'VAL-JEL-MULT', '750123456070', 'Valenmedic', 'Valenmedic.png', 'Cateter intravenoso calibres 18g, 20g, 22g, 24g.', 1.93, 30.00, 14, '2026-01-13 06:40:10'),
(71, 1, 1, 'Acetaminofén 650Mg X 10 Comprimidos', 'MED-ACE-650MG', '750123456071', 'Medigen', 'Medigen.png', 'Analgésico y antipirético para alivio rápido.', 1.17, 30.00, 0, '2026-01-13 08:03:48'),
(72, 1, 20, 'Adhesivo De Plástico Transparente', 'BRI-ADH-TRA', '750123456072', 'Briutcare', 'Briutcare.png', 'Cinta adhesiva de plástico grado médico.', 3.85, 30.00, 0, '2026-01-13 08:10:14'),
(73, 1, 20, 'Venda Elástica 10Cm X 4M', 'GRO-VEN-10X4', '750123456073', 'Grossmed', 'Grossmed.png', 'Ideal para soporte en lesiones articulares.', 0.76, 229.00, 0, '2026-01-14 01:19:33'),
(74, 1, 20, 'Esponja de Gasa 100% Algodón 3x3', 'ALG-GAS-3X3', '750123456074', 'Algobap', 'Algobap.png', 'Máxima suavidad y absorción para curas.', 0.77, 30.00, 0, '2026-01-14 01:37:48'),
(75, 1, 1, 'Prfiscirt 50mg', 'GEN-PRF-50MG', '750123456075', 'Genérico', '', 'Medicamento en tabletas de 50mg.', 5.00, 30.00, 6, '2026-02-20 20:38:54'),
(159, 11, 33, 'Copia B/N Carta (Una cara)', 'IMP_BN_CARTA_1C', '00001', 'Servicio local', 'print_bn.png', 'Fotocopia blanco y negro, tamaño carta, por una cara.', 0.06, 30.00, 100, '2026-03-21 02:10:07'),
(160, 11, 33, 'Copia B/N Carta (Doble cara)', 'IMP_BN_CARTA_2C', '00002', 'Servicio local', 'print_bn.png', 'Fotocopia blanco y negro, tamaño carta, por ambas caras.', 0.12, 30.00, 100, '2026-03-21 02:10:07'),
(161, 11, 33, 'Copia B/N Oficio', 'IMP_BN_OFICIO', '00003', 'Servicio local', 'print_bn.png', 'Fotocopia blanco y negro, tamaño oficio.', 0.09, 30.00, 100, '2026-03-21 02:10:07'),
(162, 11, 33, 'Copia Color Carta', 'IMP_COL_CARTA', '00004', 'Servicio local', 'print_color.png', 'Fotocopia a color tamaño carta.', 0.11, 30.00, 100, '2026-03-21 02:10:07'),
(163, 11, 33, 'Copia Color Oficio', 'IMP_COL_OFICIO', '00005', 'Servicio local', 'print_color.png', 'Fotocopia a color tamaño oficio.', 0.14, 30.00, 100, '2026-03-21 02:10:07'),
(164, 11, 33, 'Impresión B/N (USB/WhatsApp)', 'IMP_BN_DIGITAL', '00006', 'Servicio local', 'print_bn.png', 'Impresión blanco y negro desde archivo digital.', 0.12, 30.00, 83, '2026-03-21 02:10:07'),
(165, 11, 33, 'Impresión Color (Texto/Gráficos)', 'IMP_COL_TEXTO', '00007', 'Servicio local', 'print_color.png', 'Impresión a color de textos y gráficos.', 0.16, 30.00, 94, '2026-03-21 02:10:07'),
(166, 11, 33, 'Impresión Color (Imagen/Foto)', 'IMP_COL_FULL', '00008', 'Servicio local', 'print_color.png', 'Impresión a color de imagen o foto full página.', 0.18, 30.00, 92, '2026-03-21 02:10:07'),
(167, 11, 33, 'Transcripción Texto Simple', 'TRA_TEXTO_SIMP', '00009', 'Servicio local', '', 'Transcripción de cartas o trabajos por página.', 0.13, 30.00, 100, '2026-03-21 02:10:07'),
(168, 11, 33, 'Transcripción Texto Complejo', 'TRA_TEXTO_COMP', '00010', 'Servicio local', '', 'Transcripción con fórmulas o tablas por página.', 0.15, 30.00, 100, '2026-03-21 02:10:07'),
(169, 11, 33, 'Transcripción Manuscrito', 'TRA_MANUSCRITO', '00011', 'Servicio local', '', 'Paso de papel a digital por página.', 0.16, 30.00, 100, '2026-03-21 02:10:07'),
(170, 11, 33, 'Diseño Currículum Vitae', 'TRA_CV_DISENO', '00012', 'Servicio local', '', 'Redacción y diseño de CV.', 1.00, 30.00, 100, '2026-03-21 02:10:07'),
(171, 11, 33, 'Escaneo Documentos', 'DIG_ESC_PDF', '00013', 'Servicio local', '', 'Escaneo de documento a PDF o imagen.', 0.15, 30.00, 100, '2026-03-21 02:10:07'),
(172, 11, 33, 'Lámina Simple (PowerPoint/Canva)', 'DIA_LAM_SIMP', '00014', 'Servicio local', 'laminas.png', 'Lámina con solo texto y viñetas.', 0.16, 30.00, 100, '2026-03-21 02:10:07'),
(173, 11, 33, 'Lámina Diseño (Imágenes/Animación)', 'DIA_LAM_DIS', '00015', 'Servicio local', 'laminas.png', 'Lámina con imágenes y animaciones.', 0.18, 30.00, 100, '2026-03-21 02:10:07'),
(174, 11, 33, 'Investigación de Tema', 'DIA_INVEST', '00016', 'Servicio local', '', 'Desarrollo del tema por lámina (+0.16).', 0.16, 30.00, 100, '2026-03-21 02:10:07'),
(175, 11, 33, 'SENIAT - RIF (Consulta/Impresión)', 'TRA_RIF_IMP', '00017', 'Servicio local', 'seniat.png', 'Consulta e impresión simple de RIF.', 0.50, 30.00, 100, '2026-03-21 02:10:07'),
(176, 11, 33, 'SENIAT - RIF (Actualización)', 'TRA_RIF_ACT', '00018', 'Servicio local', 'seniat.png', 'Recuperación o actualización de RIF.', 1.50, 30.00, 93, '2026-03-21 02:10:07'),
(177, 11, 33, 'SAIME - Solicitud Citas', 'TRA_SAI_CITA', '00019', 'Servicio local', 'saime.png', 'Gestión de citas para Cédula o Pasaporte.', 1.54, 30.00, 99, '2026-03-21 02:10:07'),
(178, 11, 33, 'SAIME - Recuperación Usuario', 'TRA_SAI_USER', '00020', 'Servicio local', 'saime.png', 'Recuperación de cuenta SAIME.', 1.54, 30.00, 96, '2026-03-21 02:10:07'),
(179, 11, 33, 'INTT - Renovación Licencia', 'TRA_INTT_LIC', '00021', 'Servicio local', '', 'Gestión de renovación de licencia.', 1.54, 30.00, 99, '2026-03-21 02:10:07'),
(180, 11, 33, 'Antecedentes Penales', 'TRA_ANTEC_CERT', '00022', 'Servicio local', '', 'Solicitud de certificado de antecedentes.', 1.54, 30.00, 96, '2026-03-21 02:10:07'),
(181, 11, 33, 'Bancos - Planilla Apertura', 'TRA_BAN_PLAN', '00023', 'Servicio local', 'banco.png', 'Carga de planilla para apertura de cuenta.', 1.54, 30.00, 92, '2026-03-21 02:10:07'),
(182, 11, 33, 'Otros Trámites Web', 'TRA_OTROS_WEB', '00024', 'Servicio local', '', 'Carga de documentos web (p/doc).', 0.16, 30.00, 84, '2026-03-21 02:10:07'),
(183, 11, 37, 'FRP Bypass (Factory Reset Protection)', 'FRP-B01', '01020304', 'Servicio local', 'frp.png', 'El FRP Bypass (Factory Reset Protection) es el proceso para omitir la cuenta de Google que bloquea un dispositivo Android tras un restablecimiento de fábrica forzado. Esta medida de seguridad evita que alguien use el teléfono si fue robado o perdido, pero también afecta a dueños legítimos que olvidaron sus credenciales.', 9.23, 30.00, 99, '2026-03-27 23:02:11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount_usd` decimal(10,2) DEFAULT NULL,
  `total_amount_bs` decimal(12,2) DEFAULT NULL,
  `exchange_rate` decimal(10,4) DEFAULT NULL,
  `payment_method` enum('efectivo_usd','efectivo_bs','pago_movil','credito','punto') DEFAULT 'efectivo_bs',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'completada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sales`
--

INSERT INTO `sales` (`id`, `tenant_id`, `user_id`, `total_amount_usd`, `total_amount_bs`, `exchange_rate`, `payment_method`, `created_at`, `status`) VALUES
(39, 1, 1, 6.50, 2959.16, 455.2547, 'efectivo_bs', '2026-03-20 18:18:01', 'completada'),
(40, 1, 1, 6.50, 2959.16, 455.2547, 'efectivo_bs', '2026-03-20 18:18:36', 'completada'),
(41, 1, 1, 2.51, 1142.23, 455.2547, 'efectivo_bs', '2026-03-20 18:20:39', 'completada'),
(42, 1, 1, 6.50, 2970.99, 457.0757, 'efectivo_bs', '2026-03-22 16:48:46', 'completada'),
(70, 11, 12, 2.00, 947.74, 473.8702, 'credito', '2026-03-31 03:44:32', 'completada'),
(71, 11, 12, 1.95, 924.05, 473.8702, 'credito', '2026-03-31 03:45:32', 'completada'),
(72, 11, 12, 1.95, 924.05, 473.8702, 'efectivo_bs', '2026-03-31 03:45:50', 'completada'),
(73, 11, 12, 1.95, 924.05, 473.8702, 'credito', '2026-03-31 03:46:15', 'completada'),
(74, 11, 12, 1.95, 924.05, 473.8702, 'credito', '2026-03-31 03:50:16', 'completada'),
(75, 11, 12, 1.95, 924.05, 473.8702, 'credito', '2026-03-31 03:51:41', 'completada'),
(76, 11, 12, 1.95, 924.05, 473.8702, 'efectivo_bs', '2026-03-31 03:52:27', 'completada'),
(77, 11, 12, 2.00, 947.74, 473.8702, 'efectivo_bs', '2026-03-31 03:57:46', 'completada'),
(78, 11, 12, 2.00, 947.74, 473.8702, 'credito', '2026-03-31 04:20:30', 'completada'),
(79, 11, 12, 4.00, 1895.48, 473.8702, 'efectivo_bs', '2026-03-31 04:21:21', 'completada'),
(80, 11, 12, 2.00, 947.74, 473.8702, 'efectivo_bs', '2026-03-31 04:24:47', 'completada'),
(81, 11, 12, 2.00, 947.74, 473.8702, 'efectivo_bs', '2026-03-31 04:25:48', 'completada'),
(82, 11, 12, 2.00, 947.74, 473.8702, 'efectivo_bs', '2026-03-31 04:32:30', 'completada'),
(83, 11, 12, 2.00, 947.74, 473.8702, 'efectivo_bs', '2026-03-31 04:35:24', 'completada');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_at_moment_usd` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `price_at_moment_usd`) VALUES
(15, 39, 75, 1, 6.50),
(16, 40, 75, 1, 6.50),
(17, 41, 70, 1, 2.51),
(18, 42, 75, 1, 6.50),
(52, 70, 178, 1, 2.00),
(53, 71, 176, 1, 1.95),
(54, 72, 176, 1, 1.95),
(55, 73, 176, 1, 1.95),
(56, 74, 176, 1, 1.95),
(57, 75, 176, 1, 1.95),
(58, 76, 176, 1, 1.95),
(59, 77, 180, 1, 2.00),
(60, 78, 178, 1, 2.00),
(61, 79, 178, 1, 2.00),
(62, 79, 181, 1, 2.00),
(63, 80, 181, 1, 2.00),
(64, 81, 178, 1, 2.00),
(65, 82, 181, 1, 2.00),
(66, 83, 181, 1, 2.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `bcv_rate` decimal(10,4) DEFAULT NULL,
  `last_update` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `system_settings`
--

INSERT INTO `system_settings` (`id`, `bcv_rate`, `last_update`) VALUES
(1, 473.8702, '2026-03-31 15:20:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tenants`
--

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL,
  `business_name` varchar(100) NOT NULL,
  `rif` varchar(20) DEFAULT NULL,
  `license_key` varchar(50) DEFAULT NULL,
  `status` enum('active','suspended','expired') DEFAULT 'active',
  `expiration_date` date NOT NULL,
  `plan_type` enum('basic','premium','unlimited') DEFAULT 'basic',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `ticket_footer` text DEFAULT NULL,
  `show_logo` tinyint(1) DEFAULT 1,
  `theme` varchar(10) DEFAULT 'dark',
  `compact_tables` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tenants`
--

INSERT INTO `tenants` (`id`, `business_name`, `rif`, `license_key`, `status`, `expiration_date`, `plan_type`, `created_at`, `address`, `phone`, `currency`, `ticket_footer`, `show_logo`, `theme`, `compact_tables`) VALUES
(1, 'Luciana Market', 'J-30001578', 'F8F0B460BFDC', 'active', '2026-04-30', 'basic', '2026-02-01 15:09:48', 'Manzanita Sector II', '04161607891', 'VES', 'a la mierda con el mundo', 0, 'dark', 1),
(11, 'Ad Service Center', 'V-19551521', 'F8F0B460BFDS', 'active', '2026-04-30', 'basic', '2026-02-01 15:09:48', 'Manzanita Sector \"El Picure\"', '04161607891', 'VES', '¡Gracias por su compra!', 1, 'dark', 1),
(12, 'Demo', '123456789', '0EC63BC04B', 'active', '2027-03-30', 'basic', '2026-03-30 16:24:42', NULL, NULL, 'USD', NULL, 1, 'dark', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('admin','seller') DEFAULT 'seller'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `username`, `password`, `created_at`, `role`) VALUES
(1, 1, 'admin', '$2y$10$vVsR1hxRqzU2PRtSByw.HuH1knl6XNpHGpqlmY3LZhQ7FncVGS4CS', '2026-02-01 15:09:48', 'admin'),
(5, 1, 'Cajero_1', '$2y$10$.KDL1ZektfVPma81zvW8DeVX1DDApgiTvwwz6HqOVNPAxVqNJr2DK', '2026-02-06 19:12:39', 'seller'),
(12, 11, 'adolfojos', '$2y$10$vVsR1hxRqzU2PRtSByw.HuH1knl6XNpHGpqlmY3LZhQ7FncVGS4CS', '2026-02-01 15:09:48', 'admin'),
(13, 12, 'demo', '$2y$10$TdQ7IHFbbfT757xo7meB.OwjtrzSyQTILLT83KEuWdjJ1K13m2gEW', '2026-03-30 16:24:42', 'seller');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indices de la tabla `credits`
--
ALTER TABLE `credits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indices de la tabla `credit_payments`
--
ALTER TABLE `credit_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `credit_id` (`credit_id`);

--
-- Indices de la tabla `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_product` (`tenant_id`,`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indices de la tabla `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indices de la tabla `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indices de la tabla `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_key` (`license_key`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT de la tabla `credits`
--
ALTER TABLE `credits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `credit_payments`
--
ALTER TABLE `credit_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=184;

--
-- AUTO_INCREMENT de la tabla `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT de la tabla `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT de la tabla `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `credits`
--
ALTER TABLE `credits`
  ADD CONSTRAINT `credits_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `credits_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Filtros para la tabla `credit_payments`
--
ALTER TABLE `credit_payments`
  ADD CONSTRAINT `credit_payments_ibfk_1` FOREIGN KEY (`credit_id`) REFERENCES `credits` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
