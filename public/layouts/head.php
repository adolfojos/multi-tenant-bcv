<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
   <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no">
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <meta name="theme-color" content="#1877F2">
    <link rel="manifest" href="./manifest.json">
    <title><?= isset($pageTitle) ? $pageTitle : 'Mi Negocio' ?></title>
    <!-- Script de Tema (Ejecutar lo antes posible para evitar parpadeo blanco) -->
    <script>
        const theme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-bs-theme', theme);
    </script>
   
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q=" crossorigin="anonymous" />
    <link rel="stylesheet" href="assets/vendor/overlayscrollbars/overlayscrollbars.min.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/vendor/apexcharts/apexcharts.css">
    <link rel="stylesheet" href="assets/vendor/datatables/dataTables.bootstrap5.min.css">
    <link rel="preload" href="./css/adminlte.css" as="style" />
    <link rel="stylesheet" href="./css/adminlte.css" />
    <link rel="stylesheet" href="./css/custom.css" />
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-mini sidebar-collapse bg-body-tertiary">
    <div class="app-wrapper">