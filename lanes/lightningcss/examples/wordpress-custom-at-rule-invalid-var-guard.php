<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$transformer = new CustomAtRuleTransformer();
$message = null;

try {
    $transformer->transform('.wp-block-card { background: opacity(accent); }', [], [
        'Function' => static function (array $arguments, string $raw, string $name): ?array {
            if (($arguments[0] ?? null) !== 'accent') {
                return null;
            }

            return [
                'type' => 'function',
                'value' => [
                    'name' => $name,
                    'arguments' => [[
                        'type' => 'var',
                        'value' => [
                            'name' => ['ident' => $arguments[0]],
                        ],
                    ]],
                ],
            ];
        },
    ]);
} catch (InvalidArgumentException $exception) {
    $message = $exception->getMessage();
}

if (($argv[1] ?? null) === '--self-test') {
    if ($message !== 'Dashed idents must start with --') {
        fwrite(STDERR, "Unexpected invalid dashed identifier guard message: " . ($message ?? 'none') . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo ($message ?? 'not guarded') . PHP_EOL;
