<?php
header('Content-Type: application/manifest+json; charset=utf-8');
?>
{
  "name": "MultiPOS - Gestión Unificada",
  "short_name": "MultiPOS",
  "description": "Sistema de Punto de Venta e Inventario",
  "start_url": "./admin.php",
  "display": "standalone",
  "background_color": "#f8f9fa",
  "theme_color": "#080808",
  "orientation": "any",
  "icons": [
    {
      "src": "./assets/icon-192x192.png",
      "sizes": "192x192",
      "type": "image/png"
    },
    {
      "src": "./assets/icon-512x512.png",
      "sizes": "512x512",
      "type": "image/png"
    }
  ]
}