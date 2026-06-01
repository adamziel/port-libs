<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@tokens theme {
  .wp-block-card {
    width: 16px;
  }
}

@alias accent;
CSS;

$seen = [];
$result = (new CustomAtRuleTransformer())->transform($css, [
    'tokens' => [
        'prelude' => '<custom-ident>',
        'body' => 'rule-list',
    ],
    'alias' => [
        'prelude' => '<custom-ident>',
    ],
], [
    'CustomIdent' => static fn (string $ident): string => 'wp-' . $ident,
    'Length' => static function (array $length): ?array {
        if (($length['unit'] ?? null) !== 'px') {
            return null;
        }

        return [
            'unit' => 'rem',
            'value' => ((float) $length['value']) / 16,
        ];
    },
    'RuleExit' => [
        'custom' => static function (array $rule) use (&$seen): ?array {
            $seen[$rule['name']] = [
                'prelude' => $rule['prelude'],
                'firstDeclaration' => $rule['bodyRules'][0]['value']['declarations']['declarations'][0]['raw'] ?? null,
            ];

            return null;
        },
    ],
]);

if (($argv[1] ?? null) === '--self-test') {
    $expected = '@tokens wp-theme{.wp-block-card{width:1rem}}@alias wp-accent;';
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected CSS output:\n{$result}\n");
        exit(1);
    }
    if (($seen['tokens']['prelude'] ?? null) !== 'wp-theme' || ($seen['tokens']['firstDeclaration'] ?? null) !== '1rem') {
        fwrite(STDERR, "RuleExit did not observe the visited custom at-rule body.\n");
        exit(1);
    }
    if (($seen['alias']['prelude'] ?? null) !== 'wp-accent') {
        fwrite(STDERR, "RuleExit did not observe the visited custom at-rule statement prelude.\n");
        exit(1);
    }
}

echo $result . PHP_EOL;
