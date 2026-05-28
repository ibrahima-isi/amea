<?php

require_once __DIR__ . '/../functions/utility-functions.php';

$passed = 0;
$failed = 0;

function expect(string $name, bool $result): void
{
    global $passed, $failed;
    if ($result) {
        echo "\033[32m  ✓ {$name}\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m  ✗ {$name}\033[0m\n";
        $failed++;
    }
}

echo "\nJS string literal encoding\n";

expect('null value uses fallback label', jsStringLiteral(null, 'Non renseigné') === '"Non renseigné"');
expect('blank value uses fallback label', jsStringLiteral('   ', 'Non renseigné') === '"Non renseigné"');
expect('unicode text is preserved', jsStringLiteral('Université Cheikh Anta Diop') === '"Université Cheikh Anta Diop"');

$unsafe = jsStringLiteral('</script>\'"&');
expect('HTML/script-sensitive characters are hex escaped', $unsafe === '"\\u003C\\/script\\u003E\\u0027\\u0022\\u0026"');

echo "\n";
$total = $passed + $failed;
echo "\033[" . ($failed > 0 ? '31' : '32') . "m  {$passed}/{$total} tests passed\033[0m\n\n";
exit($failed > 0 ? 1 : 0);
