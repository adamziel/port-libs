<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@media (max-width: env(--block-card-breakpoint 1, 640px)) {
  .wp-block-card {
    padding: var(--block-card-gap);
  }
}
CSS;

$seen = [];
$visitor = CustomAtRuleTransformer::composeVisitors([
    [
        'EnvironmentVariableExit' => [
            '--block-card-breakpoint' => static function (array $environmentVariable) use (&$seen): array {
                $seen[] = [
                    'EnvironmentVariableExit',
                    $environmentVariable['name']['ident'],
                    $environmentVariable['indices'] ?? [],
                    $environmentVariable['fallback'][0]['value'] ?? null,
                ];

                return [
                    'type' => 'length',
                    'value' => [
                        'unit' => 'px',
                        'value' => 782,
                    ],
                ];
            },
        ],
    ],
    [
        'VariableExit' => [
            '--block-card-gap' => static function (array $variable) use (&$seen): array {
                $seen[] = ['VariableExit', $variable['name']['ident']];

                return ['raw' => '24px'];
            },
        ],
    ],
]);

$result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);
$expected = '@media (width<=782px){.wp-block-card{padding:24px}}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected variable exit visitor output:\n{$result}\n");
        exit(1);
    }

    if ($seen !== [
        ['EnvironmentVariableExit', '--block-card-breakpoint', [1], '640px'],
        ['VariableExit', '--block-card-gap'],
    ]) {
        fwrite(STDERR, "Unexpected variable exit visitor state:\n" . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
