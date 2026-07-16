<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@tokens editor {
  accent: #056ef0;
}

.wp-block-query a:nth-child(even of a),
.wp-block-query .wp-block-post-title {
  color: token('editor.accent');
}
CSS;

$tokens = [];
$seenNthSelector = null;
$transformer = new CustomAtRuleTransformer();
$result = $transformer->transform($css, [
    'tokens' => [
        'prelude' => '<custom-ident>',
        'body' => 'declaration-list',
    ],
], CustomAtRuleTransformer::composeVisitors([
    [
        'Rule' => [
            'custom' => [
                'tokens' => static function (array $rule) use (&$tokens): array {
                    foreach ($rule['declarations'] as $declaration) {
                        $tokens[$rule['prelude'] . '.' . $declaration['property']] = $declaration['value'];
                    }

                    return [];
                },
            ],
        ],
    ],
    [
        'Function' => [
            'token' => static function (array $arguments) use (&$tokens): ?string {
                return $tokens[$arguments[0] ?? ''] ?? null;
            },
        ],
    ],
    [
        'Selector' => static function (array $selector) use (&$seenNthSelector): array {
            foreach ($selector as &$component) {
                if (($component['type'] ?? null) === 'pseudo-class' && ($component['kind'] ?? null) === 'nth-child' && isset($component['of'])) {
                    $seenNthSelector = $component;
                    unset($component['of']);
                    $component['kind'] = 'nth-of-type';
                }
            }
            unset($component);

            return array_merge([
                ['type' => 'class', 'name' => 'editor-styles-wrapper'],
                ['type' => 'combinator', 'value' => 'descendant'],
            ], $selector);
        },
    ],
]));

$expected = '.editor-styles-wrapper .wp-block-query a:nth-of-type(2n),.editor-styles-wrapper .wp-block-query .wp-block-post-title{color:#056ef0}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected selector visitor output:\n{$result}\n");
        exit(1);
    }
    if (($seenNthSelector['formula'] ?? null) !== '2n') {
        fwrite(STDERR, "Unexpected nth selector AST:\n" . json_encode($seenNthSelector) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
