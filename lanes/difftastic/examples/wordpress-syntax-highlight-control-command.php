<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\DiffCommandRunner;

$before = "<?php\nfunction render_card(): string {\n    return esc_html(\"legacy\");\n}\n";
$after = "<?php\nfunction render_card(): string {\n    return esc_html(\"modern\");\n}\n";
$runner = new DiffCommandRunner();

$syntaxOn = $runner->runTextDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/src/render.php',
    'PHP',
    ['language' => 'php'],
    [
        'DFT_DISPLAY' => 'side-by-side',
        'DFT_CONTEXT' => '1',
        'DFT_COLOR' => 'always',
        'DFT_SYNTAX_HIGHLIGHT' => 'on',
    ],
);
$syntaxOff = $runner->runTextDiff(
    $before,
    $after,
    'wp-content/plugins/acme-card/src/render.php',
    'PHP',
    ['language' => 'php'],
    [
        'DFT_DISPLAY' => 'side-by-side',
        'DFT_CONTEXT' => '1',
        'DFT_COLOR' => 'always',
        'DFT_SYNTAX_HIGHLIGHT' => 'off',
    ],
);

echo "syntax_highlight=on\n";
echo $syntaxOn['stdout'];
echo "syntax_highlight=off\n";
echo $syntaxOff['stdout'];
