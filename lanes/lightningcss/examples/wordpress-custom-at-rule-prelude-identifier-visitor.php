<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@slot hero;
@tokens --accent --focus-ring;

.wp-block-card {
  color: red;
}
CSS;

$preludes = [];
$transformer = new CustomAtRuleTransformer();
$result = $transformer->transform($css, [
    'slot' => ['prelude' => '<custom-ident>'],
    'tokens' => ['prelude' => '<dashed-ident>+'],
], [
    'CustomIdent' => static fn (string $ident): string => 'wp-' . $ident,
    'DashedIdent' => static fn (string $ident): string => '--wp-card-' . substr($ident, 2),
    'Rule' => [
        'custom' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$preludes): array {
            $preludes[$rule['name']] = $rule['preludeAst'];

            if ($rule['name'] !== 'slot') {
                return [];
            }

            return $transformer->styleRule('.wp-block-card.is-style-' . $rule['prelude'], [
                'outline-color' => 'var(--accent)',
            ]);
        },
    ],
]);

$expected = '.wp-block-card.is-style-wp-hero{outline-color:var(--wp-card-accent)}.wp-block-card{color:red}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule identifier prelude output:\n{$result}\n");
        exit(1);
    }
    if (($preludes['slot']['value'] ?? null) !== 'wp-hero') {
        fwrite(STDERR, "Unexpected slot prelude AST:\n" . json_encode($preludes['slot']) . "\n");
        exit(1);
    }
    $tokenComponents = $preludes['tokens']['value']['components'] ?? [];
    $tokenNames = array_map(static fn (array $component): string => $component['value'], $tokenComponents);
    if ($tokenNames !== ['--wp-card-accent', '--wp-card-focus-ring']) {
        fwrite(STDERR, "Unexpected token prelude AST:\n" . json_encode($preludes['tokens']) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
