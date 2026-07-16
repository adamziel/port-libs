<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@slot h\65 ro;
@tokens --wp\2d accent --wp\-spacing;
@mode comp\61 ct compact;

.wp-block-card {
  color: red;
}
CSS;

$seen = [];
$result = (new CustomAtRuleTransformer())->transform($css, [
    'slot' => ['prelude' => '<custom-ident>'],
    'tokens' => ['prelude' => '<dashed-ident>+'],
    'mode' => ['prelude' => 'compact+'],
], [
    'CustomIdent' => static fn (string $ident): string => 'wp-' . $ident,
    'DashedIdent' => static fn (string $ident): string => '--theme-' . substr($ident, 2),
    'Rule' => [
        'custom' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$seen): array {
            $seen[$rule['name']] = [
                'prelude' => $rule['prelude'],
                'preludeAst' => $rule['preludeAst'],
            ];

            if ($rule['name'] !== 'slot') {
                return [];
            }

            return $transformer->styleRule('.wp-block-card.is-style-' . $rule['prelude'], [
                'outline-color' => 'var(--wp-accent)',
            ]);
        },
    ],
]);

$expected = '.wp-block-card.is-style-wp-hero{outline-color:var(--theme-wp-accent)}.wp-block-card{color:red}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected escaped custom at-rule output:\n{$result}\n");
        exit(1);
    }
    if (($seen['slot']['prelude'] ?? null) !== 'wp-hero') {
        fwrite(STDERR, 'Unexpected escaped slot prelude: ' . json_encode($seen['slot'] ?? null) . "\n");
        exit(1);
    }

    $tokenComponents = $seen['tokens']['preludeAst']['value']['components'] ?? [];
    $tokenNames = array_map(static fn (array $component): string => $component['value'], $tokenComponents);
    if ($tokenNames !== ['--theme-wp-accent', '--theme-wp-spacing']) {
        fwrite(STDERR, 'Unexpected escaped token preludes: ' . json_encode($seen['tokens'] ?? null) . "\n");
        exit(1);
    }
    if (($seen['mode']['prelude'] ?? null) !== 'compact compact') {
        fwrite(STDERR, 'Unexpected escaped literal prelude: ' . json_encode($seen['mode'] ?? null) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
