<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@hero-image url(block-card.png);
@fallback-image none;

.wp-block-card {
  background-image: var(--wp-card-hero-image);
}
CSS;

$images = [];
$transformer = new CustomAtRuleTransformer();
$result = $transformer->transform($css, [
    'hero-image' => ['prelude' => '<image>'],
    'fallback-image' => ['prelude' => '<image>'],
], [
    'Image' => static function (array $image): array {
        if (($image['type'] ?? null) === 'url') {
            $image['value']['url'] = 'theme/' . $image['value']['url'];
        }

        return $image;
    },
    'Url' => static function (array $url): array {
        $url['url'] = '/wp-content/themes/demo/assets/' . $url['url'];

        return $url;
    },
    'ImageExit' => static function (array $image): ?array {
        if (($image['type'] ?? null) !== 'none') {
            return $image;
        }

        return [
            'type' => 'url',
            'value' => [
                'url' => '/wp-content/themes/demo/assets/fallback-card.png',
                'raw' => 'url(/wp-content/themes/demo/assets/fallback-card.png)',
                'loc' => ['line' => 1, 'column' => 1],
            ],
        ];
    },
    'Rule' => [
        'custom' => static function (array $rule) use (&$images): array {
            $images[$rule['name']] = $rule['preludeAst'];

            return [];
        },
    ],
    'StyleSheetExit' => static function (array $stylesheet) use (&$images): array {
        $stylesheet['rules'][] = [
            'type' => 'style',
            'value' => [
                'selectors' => [
                    [
                        ['type' => 'pseudo-class', 'kind' => 'root'],
                    ],
                ],
                'declarations' => [
                    'declarations' => [
                        ['property' => '--wp-card-hero-image', 'value' => $images['hero-image']],
                        ['property' => '--wp-card-fallback-image', 'value' => $images['fallback-image']],
                    ],
                    'importantDeclarations' => [],
                ],
            ],
        ];

        return $stylesheet;
    },
]);

$expected = '.wp-block-card{background-image:var(--wp-card-hero-image)}:root{--wp-card-hero-image:url(/wp-content/themes/demo/assets/theme/block-card.png);--wp-card-fallback-image:url(/wp-content/themes/demo/assets/fallback-card.png)}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule image prelude output:\n{$result}\n");
        exit(1);
    }
    if (($images['hero-image']['value']['value']['url'] ?? null) !== '/wp-content/themes/demo/assets/theme/block-card.png') {
        fwrite(STDERR, "Unexpected hero image AST:\n" . json_encode($images['hero-image']) . "\n");
        exit(1);
    }
    if (($images['fallback-image']['value']['value']['url'] ?? null) !== '/wp-content/themes/demo/assets/fallback-card.png') {
        fwrite(STDERR, "Unexpected fallback image AST:\n" . json_encode($images['fallback-image']) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
