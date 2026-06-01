<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@slot café;
@tokens --wp-échelle --wp-accent;
@mode édition édition;

.wp-block-card {
  color: red;
}
CSS;

$preludes = [];
$result = (new CustomAtRuleTransformer())->transform($css, [
    'slot' => ['prelude' => '<custom-ident>'],
    'tokens' => ['prelude' => '<dashed-ident>+'],
    'mode' => ['prelude' => 'édition+'],
], [
    'CustomIdent' => static fn (string $ident): string => 'wp-' . $ident,
    'DashedIdent' => static fn (string $ident): string => str_starts_with($ident, '--wp-')
        ? '--theme-' . substr($ident, 5)
        : $ident,
    'Rule' => [
        'custom' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$preludes): array {
            $preludes[$rule['name']] = $rule['preludeAst'];

            if ($rule['name'] !== 'slot') {
                return [];
            }

            return $transformer->styleRule('.wp-block-card.is-style-' . $rule['prelude'], [
                'outline-color' => 'var(--wp-échelle)',
            ]);
        },
    ],
]);

$expected = '.wp-block-card.is-style-wp-café{outline-color:var(--theme-échelle)}.wp-block-card{color:red}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected unicode custom at-rule output:\n{$result}\n");
        exit(1);
    }
    if (($preludes['slot']['value'] ?? null) !== 'wp-café') {
        fwrite(STDERR, "Unexpected unicode slot prelude AST:\n" . json_encode($preludes['slot']) . "\n");
        exit(1);
    }
    $tokenNames = array_column($preludes['tokens']['value']['components'] ?? [], 'value');
    if ($tokenNames !== ['--theme-échelle', '--theme-accent']) {
        fwrite(STDERR, "Unexpected unicode token prelude AST:\n" . json_encode($preludes['tokens']) . "\n");
        exit(1);
    }
    $literalNames = array_column($preludes['mode']['value']['components'] ?? [], 'value');
    if ($literalNames !== ['édition', 'édition']) {
        fwrite(STDERR, "Unexpected unicode literal prelude AST:\n" . json_encode($preludes['mode']) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
