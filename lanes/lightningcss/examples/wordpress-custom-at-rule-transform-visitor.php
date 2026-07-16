<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-media-card {
  transform: translateX(50px);
}

.wp-block-media-card__caption {
  transform: translateX(20%);
}

.wp-block-media-card__overlay {
  transform: translateX(calc(100vw - 20px));
}
CSS;

$seenArguments = [];
$result = (new CustomAtRuleTransformer())->transform($css, [], [
    'Rule' => [
        'style' => static function (array $style) use (&$seenArguments): array {
            $clone = null;
            foreach ($style['value']['declarations']['declarations'] as $property) {
                if (($property['property'] ?? null) !== 'transform') {
                    continue;
                }

                $transforms = [];
                foreach ($property['value'] as $transform) {
                    $seenArguments[] = $transform['value']['type'] ?? 'unknown';
                    if (($transform['type'] ?? null) !== 'translateX') {
                        $transforms[] = $transform;
                        continue;
                    }

                    if ($clone === null) {
                        $clone = $style;
                        $clone['value']['declarations']['declarations'] = [];
                    }

                    $argument = $transform['value'];
                    if (($argument['type'] ?? null) === 'dimension') {
                        $argument['value']['value'] = -$argument['value']['value'];
                    } elseif (($argument['type'] ?? null) === 'percentage') {
                        $argument['value'] = -$argument['value'];
                    } elseif (($argument['type'] ?? null) === 'calc') {
                        $argument = [
                            'type' => 'calc',
                            'value' => [
                                'type' => 'product',
                                'value' => [-1, $argument],
                            ],
                        ];
                    }

                    $transforms[] = [
                        'type' => 'translateX',
                        'value' => $argument,
                    ];
                }

                if ($clone !== null) {
                    $clone['value']['declarations']['declarations'][] = [
                        'property' => 'transform',
                        'value' => $transforms,
                    ];
                }
            }

            if ($clone === null) {
                return $style;
            }

            $selector = array_key_last($clone['value']['selectors']);
            $clone['value']['selectors'][$selector][] = [
                'type' => 'pseudo-class',
                'kind' => 'dir',
                'direction' => 'rtl',
            ];

            return [$style, $clone];
        },
    ],
]);

$expected = '.wp-block-media-card{transform:translate(50px)}.wp-block-media-card:dir(rtl){transform:translate(-50px)}.wp-block-media-card__caption{transform:translate(20%)}.wp-block-media-card__caption:dir(rtl){transform:translate(-20%)}.wp-block-media-card__overlay{transform:translate(calc(100vw - 20px))}.wp-block-media-card__overlay:dir(rtl){transform:translate(-1*calc(100vw - 20px))}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected transform visitor output:\n{$result}\n");
        exit(1);
    }
    if ($seenArguments !== ['dimension', 'percentage', 'calc']) {
        fwrite(STDERR, "Unexpected transform visitor arguments: " . json_encode($seenArguments) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
