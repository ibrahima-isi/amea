<?php
http_response_code(404);
header("HTTP/1.1 404 Not Found", true, 404);
header("X-Robots-Tag: noindex, nofollow");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <title>Page introuvable</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 4rem; color: #333; }
        h1 { font-size: 2rem; }
        a { color: #555; }
    </style>
</head>
<body>
    <h1>404 &mdash; Page introuvable</h1>
    <p>La page que vous cherchez n&rsquo;existe pas.</p>
    <a href="/">Retour &agrave; l&rsquo;accueil</a>
</body>
</html>
