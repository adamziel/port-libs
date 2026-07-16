<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$transformer = new CustomAtRuleTransformer();
$message = null;

try {
    $transformer->transform('@mode -compact; .wp-block-card { color: red; }', [
        'mode' => [
            'prelude' => '-compact',
        ],
    ], [
        'Rule' => [
            'custom' => [
                'mode' => static fn (): array => [],
            ],
        ],
    ]);
} catch (InvalidArgumentException $exception) {
    $message = $exception->getMessage();
}

$valid = $transformer->transform('@mode compact; .wp-block-card { color: red; }', [
    'mode' => [
        'prelude' => 'compact',
    ],
], [
    'Rule' => [
        'custom' => [
            'mode' => static fn (): array => [],
        ],
    ],
]);

if (($argv[1] ?? null) === '--self-test') {
    if ($message !== 'Invalid custom at-rule prelude for -compact: -compact') {
        fwrite(STDERR, "Unexpected literal SyntaxString guard message: " . ($message ?? 'none') . "\n");
        exit(1);
    }

    if ($valid !== '.wp-block-card{color:red}') {
        fwrite(STDERR, "Unexpected valid literal SyntaxString output: {$valid}\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo ($message ?? 'not guarded') . PHP_EOL;
