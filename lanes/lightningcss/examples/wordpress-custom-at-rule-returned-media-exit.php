<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-viewport-fix {
  .wp-block-cover {
    width: 16px;
  }
}
CSS;

$seenExit = null;
$result = (new CustomAtRuleTransformer())->transform($css, [
    'wp-viewport-fix' => [
        'prelude' => null,
        'body' => 'rule-list',
    ],
], [
    'Rule' => [
        'custom' => [
            'wp-viewport-fix' => static fn (array $rule): array => [
                'type' => 'media',
                'value' => [
                    'query' => [
                        'mediaQueries' => [
                            ['raw' => '(min-width: 40rem)'],
                        ],
                    ],
                    'rules' => $rule['bodyRules'],
                ],
            ],
        ],
    ],
    'Length' => static fn (array $length): ?array => ($length['unit'] ?? null) === 'px'
        ? ['unit' => 'rem', 'value' => ((float) $length['value']) / 16]
        : null,
    'RuleExit' => [
        'media' => static function (array $rule) use (&$seenExit): array {
            $seenExit = [
                'type' => $rule['type'] ?? null,
                'declaration' => $rule['value']['rules'][0]['value']['declarations']['declarations'][0] ?? null,
            ];
            $rule['value']['query']['mediaQueries'][0]['raw'] = '(min-width: 48rem)';

            return $rule;
        },
    ],
]);

$expected = '@media (width>=48rem){.wp-block-cover{width:1rem}}';
$expectedExit = [
    'type' => 'media',
    'declaration' => [
        'property' => 'width',
        'raw' => '1rem',
        'important' => false,
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected returned media custom at-rule output:\n{$result}\n");
        exit(1);
    }

    if ($seenExit !== $expectedExit) {
        fwrite(STDERR, "Unexpected returned media exit payload:\n" . json_encode($seenExit) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
