<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@editor-mode compact compact;
@editor-states preview, preview;

.wp-block-card {
  color: red;
}
CSS;

$seen = [];
$result = (new CustomAtRuleTransformer())->transform($css, [
    'editor-mode' => [
        'prelude' => 'compact+',
    ],
    'editor-states' => [
        'prelude' => 'preview#',
    ],
], [
    'Rule' => [
        'custom' => [
            'editor-mode' => static function (array $rule) use (&$seen): array {
                $seen['mode'] = [
                    'prelude' => $rule['prelude'],
                    'components' => array_column($rule['preludeAst']['value']['components'], 'value'),
                    'multiplier' => $rule['preludeAst']['value']['multiplier']['type'] ?? null,
                ];

                return [];
            },
            'editor-states' => static function (array $rule) use (&$seen): array {
                $seen['states'] = [
                    'prelude' => $rule['prelude'],
                    'components' => array_column($rule['preludeAst']['value']['components'], 'value'),
                    'multiplier' => $rule['preludeAst']['value']['multiplier']['type'] ?? null,
                ];

                return [];
            },
        ],
    ],
]);

$expected = '.wp-block-card{color:red}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected literal-repetition custom at-rule output:\n{$result}\n");
        exit(1);
    }
    if (($seen['mode']['components'] ?? null) !== ['compact', 'compact'] || ($seen['mode']['multiplier'] ?? null) !== 'space') {
        fwrite(STDERR, 'Unexpected editor-mode prelude AST: ' . json_encode($seen['mode'] ?? null) . "\n");
        exit(1);
    }
    if (($seen['states']['components'] ?? null) !== ['preview', 'preview'] || ($seen['states']['multiplier'] ?? null) !== 'comma') {
        fwrite(STDERR, 'Unexpected editor-states prelude AST: ' . json_encode($seen['states'] ?? null) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
