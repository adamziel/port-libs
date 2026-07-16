<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@theme-parts heading body;
@theme-breakpoints 480px, 782px;

.wp-block-card {
  color: red;
}
CSS;

$seen = [];
$result = (new CustomAtRuleTransformer())->transform($css, [
    'theme-parts' => [
        'prelude' => '<custom-ident>+',
    ],
    'theme-breakpoints' => [
        'prelude' => '<length>#',
    ],
], [
    'Rule' => [
        'custom' => [
            'theme-parts' => static function (array $rule) use (&$seen): array {
                $seen['parts'] = array_column($rule['preludeAst']['value']['components'], 'value');

                return [];
            },
            'theme-breakpoints' => static function (array $rule) use (&$seen): array {
                $seen['breakpointUnits'] = array_map(
                    static fn (array $component): string => $component['value']['value']['unit'],
                    $rule['preludeAst']['value']['components']
                );

                return [];
            },
        ],
    ],
]);

$expected = '.wp-block-card{color:red}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected repeated-prelude transform output:\n{$result}\n");
        exit(1);
    }
    if (($seen['parts'] ?? null) !== ['heading', 'body']) {
        fwrite(STDERR, 'Unexpected theme parts: ' . json_encode($seen['parts'] ?? null) . "\n");
        exit(1);
    }
    if (($seen['breakpointUnits'] ?? null) !== ['px', 'px']) {
        fwrite(STDERR, 'Unexpected breakpoint units: ' . json_encode($seen['breakpointUnits'] ?? null) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
