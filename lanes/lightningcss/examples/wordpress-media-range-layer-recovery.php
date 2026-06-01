<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme.blocks {
  @media (not unknown(foo)) {
    .wp-block-query.is-experimental {
      color: red;
    }
  }

  @media (width >= calc(1px + 2px)) {
    .wp-block-query {
      color: yellow;
    }
  }
}
CSS;

$result = (new CssMinifier())->minifyWithErrorRecovery($css, 'wp-block-theme.css');
$actual = [
    'code' => $result['code'],
    'warnings' => array_map(
        static fn (array $warning): array => [
            'message' => $warning['message'],
            'line' => $warning['loc']['line'],
            'column' => $warning['loc']['column'],
        ],
        $result['warnings']
    ),
];

$expected = [
    'code' => '@layer theme.blocks{@media (width>=3px){.wp-block-query{color:#ff0}}}',
    'warnings' => [
        [
            'message' => 'Unexpected token Function("unknown")',
            'line' => 2,
            'column' => 14,
        ],
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected media range layer recovery output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . PHP_EOL;
