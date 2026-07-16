<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-card {
  --wp-state: draft;
  --wp-color-token: #056ef0;
  --wp-anchor-token: #card;
  --wp-label: "draft";
  --wp-columns: 3;
  --wp-progress: 25%;
}
CSS;

$seen = [];
$visitor = CustomAtRuleTransformer::composeVisitors([
    [
        'Token' => [
            'ident' => static function (array $token) use (&$seen): ?string {
                $seen[] = $token['type'];

                return $token['value'] === 'draft' ? 'published' : null;
            },
            'hash' => static function (array $token) use (&$seen): array {
                $seen[] = $token['type'];

                return [
                    'type' => 'token',
                    'value' => [
                        'type' => 'hash',
                        'value' => '123456',
                    ],
                ];
            },
        ],
    ],
    [
        'Token' => [
            'id-hash' => static function (array $token) use (&$seen): array {
                $seen[] = $token['type'];

                return [
                    'type' => 'token',
                    'value' => [
                        'type' => 'id-hash',
                        'value' => 'wp-card-live',
                    ],
                ];
            },
            'string' => static function (array $token) use (&$seen): array {
                $seen[] = $token['type'];

                return [
                    'type' => 'token',
                    'value' => [
                        'type' => 'string',
                        'value' => 'live',
                    ],
                ];
            },
            'number' => static function (array $token) use (&$seen): array {
                $seen[] = $token['type'];

                return [
                    'type' => 'token',
                    'value' => [
                        'type' => 'number',
                        'value' => $token['value'] * 2,
                    ],
                ];
            },
            'percentage' => static function (array $token) use (&$seen): array {
                $seen[] = $token['type'];

                return [
                    'type' => 'token',
                    'value' => [
                        'type' => 'percentage',
                        'value' => $token['value'] * 2,
                    ],
                ];
            },
        ],
    ],
]);

$result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);
$expected = '.wp-block-card{--wp-state:published;--wp-color-token:#123456;--wp-anchor-token:#wp-card-live;--wp-label:"live";--wp-columns:6;--wp-progress:50%}';
$expectedSeen = ['ident', 'hash', 'id-hash', 'string', 'number', 'percentage'];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected token visitor output:\n{$result}\n");
        exit(1);
    }
    if ($seen !== $expectedSeen) {
        fwrite(STDERR, "Unexpected token visitor sequence:\n" . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
