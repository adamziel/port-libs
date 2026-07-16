<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@m\69 xin card {
  border-color: yellow;

  & .wp-block-button__link {
    color: yellow;
  }
}

.wp-block-card {
  @ap\70 ly card;
}

@bl\6f ck hero {
  outline-color: yellow;
}
CSS;

$mixins = [];
$seen = [];
$transformer = new CustomAtRuleTransformer();
$result = $transformer->transform($css, [
    'mixin' => [
        'prelude' => '<custom-ident>',
        'body' => 'style-block',
    ],
    'apply' => [
        'prelude' => '<custom-ident>',
    ],
    'block' => [
        'prelude' => '<custom-ident>',
        'body' => 'declaration-list',
    ],
], [
    'Rule' => [
        'custom' => [
            'mixin' => static function (array $rule) use (&$mixins, &$seen): array {
                $seen[] = $rule['name'] . ':' . $rule['prelude'];
                $mixins[$rule['prelude']] = $rule['body'];

                return [];
            },
            'apply' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$mixins, &$seen): array {
                $seen[] = $rule['name'] . ':' . $rule['prelude'];

                return $transformer->styleBlock($mixins[$rule['prelude']] ?? '');
            },
        ],
    ],
]);

$expected = '.wp-block-card{border-color:#ff0}.wp-block-card .wp-block-button__link{color:#ff0}@block hero{outline-color:#ff0}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected escaped custom at-rule name output:\n{$result}\n");
        exit(1);
    }

    if ($seen !== ['mixin:card', 'apply:card']) {
        fwrite(STDERR, 'Unexpected escaped custom at-rule visitor order: ' . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
