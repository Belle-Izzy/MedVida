<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#087e8b">
        <meta name="description" content="MedVida — agendamento médico simples, acessível e conectado.">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <meta name="medvida-teste-local" content="{{ \App\Support\DemoMode::enabled() ? '1' : '0' }}">
        <meta name="medvida-prototipo" content="{{ \App\Support\DemoMode::prototype() ? '1' : '0' }}">
        <title>MedVida - Sua saúde, conectada</title>
        <link rel="stylesheet" href="/app.css">
        <script type="module" src="/app.js"></script>
    </head>
    <body>
        <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
        <div id="app" aria-live="polite"></div>
    </body>
</html>
