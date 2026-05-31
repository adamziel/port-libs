<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@space 50%;
@motion 250ms;
@tilt 6deg;
@density 2dppx;
@shift translateX(12px);
@hero url(block-card.png);
@palette red blue;

.wp-block-card {
  display: block;
}
CSS;

$seen = [];
$transformer = new CustomAtRuleTransformer();
$result = $transformer->transform($css, [
    'space' => ['prelude' => '<length-percentage>'],
    'motion' => ['prelude' => '<time>'],
    'tilt' => ['prelude' => '<angle>'],
    'density' => ['prelude' => '<resolution>'],
    'shift' => ['prelude' => '<transform-function>'],
    'hero' => ['prelude' => '<image>'],
    'palette' => ['prelude' => '<color>+'],
], [
    'Rule' => [
        'custom' => static function (array $rule) use (&$seen): array {
            $seen[$rule['name']] = $rule['preludeAst'];

            return [];
        },
    ],
    'StyleSheetExit' => static function (array $stylesheet) use (&$seen): array {
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
                        ['property' => '--wp-card-space', 'value' => $seen['space']],
                        ['property' => '--wp-card-motion', 'value' => $seen['motion']],
                        ['property' => '--wp-card-tilt', 'value' => $seen['tilt']],
                        ['property' => '--wp-card-density', 'value' => $seen['density']],
                        ['property' => '--wp-card-shift', 'value' => $seen['shift']],
                        ['property' => '--wp-card-hero', 'value' => $seen['hero']],
                        ['property' => '--wp-card-palette', 'value' => $seen['palette']],
                    ],
                    'importantDeclarations' => [],
                ],
            ],
        ];

        return $stylesheet;
    },
]);

$expected = '.wp-block-card{display:block}:root{--wp-card-space:50%;--wp-card-motion:250ms;--wp-card-tilt:6deg;--wp-card-density:2dppx;--wp-card-shift:translateX(12px);--wp-card-hero:url(block-card.png);--wp-card-palette:#ff0000 #0000ff}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule syntax component output:\n{$result}\n");
        exit(1);
    }
    if (($seen['space']['value']['type'] ?? null) !== 'percentage') {
        fwrite(STDERR, "Unexpected length-percentage AST:\n" . json_encode($seen['space']) . "\n");
        exit(1);
    }
    if (($seen['motion']['value']['type'] ?? null) !== 'milliseconds') {
        fwrite(STDERR, "Unexpected time AST:\n" . json_encode($seen['motion']) . "\n");
        exit(1);
    }
    if (($seen['shift']['value']['type'] ?? null) !== 'translateX') {
        fwrite(STDERR, "Unexpected transform-function AST:\n" . json_encode($seen['shift']) . "\n");
        exit(1);
    }
    if (($seen['palette']['type'] ?? null) !== 'repeated') {
        fwrite(STDERR, "Unexpected repeated color AST:\n" . json_encode($seen['palette']) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
