<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@tokens wp {
  --gap: 24px;
  accent: yellow;
}

@mixin card {
  border-color: token('wp.accent');
  padding: token('wp.--gap');

  & .wp-block-button__link {
    color: token('wp.accent');
  }
}

.wp-block-card {
  @apply card;

  @breakpoint 782px {
    display: grid;

    &.is-style-featured {
      color: token('wp.accent');
    }
  }
}
CSS;

$tokens = [];
$mixins = [];
$transformer = new CustomAtRuleTransformer();

$result = $transformer->transform($css, [
    'tokens' => [
        'prelude' => '<custom-ident>',
        'body' => 'declaration-list',
    ],
    'mixin' => [
        'prelude' => '<custom-ident>',
        'body' => 'style-block',
    ],
    'apply' => [
        'prelude' => '<custom-ident>',
    ],
    'breakpoint' => [
        'prelude' => '<length>',
        'body' => 'style-block',
    ],
], [
    'Rule' => [
        'custom' => [
            'tokens' => static function (array $rule) use (&$tokens): array {
                foreach ($rule['declarations'] as $declaration) {
                    $tokens[$rule['prelude'] . '.' . $declaration['property']] = $declaration['value'];
                }

                return [];
            },
            'mixin' => static function (array $rule) use (&$mixins): array {
                $mixins[$rule['prelude']] = $rule['body'];

                return [];
            },
            'apply' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$mixins): array {
                return $transformer->styleBlock($mixins[$rule['prelude']] ?? '');
            },
            'breakpoint' => static fn (array $rule, CustomAtRuleTransformer $transformer): array => $transformer->media(
                '(width <= ' . $rule['prelude'] . ')',
                $transformer->styleBlock($rule['body'])
            ),
        ],
    ],
    'Function' => [
        'token' => static function (array $arguments) use (&$tokens): ?string {
            return $tokens[$arguments[0] ?? ''] ?? null;
        },
    ],
]);

$expected = '.wp-block-card{border-color:#ff0;padding:24px}.wp-block-card .wp-block-button__link{color:#ff0}@media (width<=782px){.wp-block-card{display:grid}.wp-block-card.is-style-featured{color:#ff0}}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule transform output:\n{$result}\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
