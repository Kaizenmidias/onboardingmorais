<?php
declare(strict_types=1);

// Entrada da prévia visual. Não há autenticação nem conexão com banco nesta etapa.
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; media-src 'self' blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'none'");
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#0f172b">
    <meta name="description" content="Prévia visual do portal de onboarding Morais Contabilidade.">
    <title>Área do cliente · Morais Contabilidade</title>
    <link rel="icon" href="assets/brand/favicon.jpg">
    <link rel="preload" href="assets/fonts/manrope-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="assets/app.css?v=layout-20261105c">
    <link rel="stylesheet" href="assets/academy.css?v=layout-20261105c">
    <script type="module" src="assets/app.js?v=deploy-20261105o"></script>
</head>
<body>
    <a class="skip-link" href="#main-content" data-action="skip-main">Pular para o conteúdo</a>
    <div id="app"><div class="boot"><img src="assets/brand/morais-logo.png" width="174" height="52" alt="Morais Contabilidade"><p>Preparando sua área…</p></div></div>
    <dialog id="modal" aria-labelledby="modal-title"></dialog>
    <div id="toast" role="status" aria-live="polite"></div>
    <noscript>Ative o JavaScript para navegar pela prévia visual do portal.</noscript>
</body>
</html>
