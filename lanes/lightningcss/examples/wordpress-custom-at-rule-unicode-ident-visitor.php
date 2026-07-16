<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@slot café;
@tokens --wp-échelle --wp-accent;
@mode édition édition;
@thème carte {
  outline-color: yellow;
}
@design-tokens café --wp-échelle @--wp\2d accent;

.wp-block-card {
  color: red;
}
CSS;

$preludes = [];
$result = (new CustomAtRuleTransformer())->transform($css, [
    'slot' => ['prelude' => '<custom-ident>'],
    'tokens' => ['prelude' => '<dashed-ident>+'],
    'mode' => ['prelude' => 'édition+'],
    'thème' => [
        'prelude' => '<custom-ident>',
        'body' => 'declaration-list',
    ],
    'design-tokens' => ['prelude' => '*'],
], [
    'CustomIdent' => static fn (string $ident): string => 'wp-' . $ident,
    'DashedIdent' => static fn (string $ident): string => str_starts_with($ident, '--wp-')
        ? '--theme-' . substr($ident, 5)
        : $ident,
    'Token' => [
        'ident' => static fn (array $token): ?array => $token['value'] === 'café'
            ? ['type' => 'ident', 'value' => 'wp-café']
            : null,
        'at-keyword' => static fn (array $token): ?array => $token['value'] === '--wp-accent'
            ? ['type' => 'token', 'value' => ['type' => 'at-keyword', 'value' => '--theme-accent']]
            : null,
    ],
    'Rule' => [
        'custom' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$preludes): array {
            $preludes[$rule['name']] = $rule['preludeAst'];

            if ($rule['name'] === 'slot') {
                return $transformer->styleRule('.wp-block-card.is-style-' . $rule['prelude'], [
                    'outline-color' => 'var(--wp-échelle)',
                ]);
            }

            if ($rule['name'] === 'thème') {
                return $transformer->styleRule('.wp-block-card.has-theme-' . $rule['prelude'], $rule['body']);
            }

            return [];
        },
    ],
]);

$expected = '.wp-block-card.is-style-wp-café{outline-color:var(--theme-échelle)}.wp-block-card.has-theme-wp-carte{outline-color:#ff0}.wp-block-card{color:red}';

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
    if (($preludes['thème']['value'] ?? null) !== 'wp-carte') {
        fwrite(STDERR, "Unexpected unicode at-rule name prelude AST:\n" . json_encode($preludes['thème']) . "\n");
        exit(1);
    }
    $universalTokens = array_map(
        static fn (array $component): mixed => $component['type'] === 'token'
            ? [$component['value']['type'], $component['value']['value']]
            : [$component['type'], $component['value']],
        $preludes['design-tokens']['value'] ?? []
    );
    if ($universalTokens !== [
        ['ident', 'wp-café'],
        ['dashed-ident', '--theme-échelle'],
        ['at-keyword', '--theme-accent'],
    ]) {
        fwrite(STDERR, "Unexpected unicode universal token prelude AST:\n" . json_encode($preludes['design-tokens']) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
