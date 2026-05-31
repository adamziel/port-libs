<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-gallery__track {
  overflow-x: auto;
  scroll-snap-type: x mandatory;
}
CSS;

$seen = [];
$visitOverflow = static function (array $declaration) use (&$seen): array {
    $seen[] = $declaration['property'];

    return [
        $declaration,
        [
            'property' => '-webkit-overflow-scrolling',
            'raw' => 'touch',
        ],
    ];
};

$result = (new CustomAtRuleTransformer())->transform($css, [], [
    'Declaration' => [
        'overflow' => $visitOverflow,
        'overflow-x' => $visitOverflow,
        'overflow-y' => $visitOverflow,
    ],
]);

$expected = '.wp-block-gallery__track{-webkit-overflow-scrolling:touch;overflow-x:auto;scroll-snap-type:x mandatory}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected overflow scrolling visitor output:\n{$result}\n");
        exit(1);
    }
    if ($seen !== ['overflow-x']) {
        fwrite(STDERR, "Unexpected overflow visitor declarations: " . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
