<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@asset-base "https://cdn.example.com/wp-content/themes/twentytwentyfour/";

.wp-block-cover {
  background-image: url(assets/hero image.png);
}
CSS;

$assetBase = '';
$seenUrls = [];
$transformer = new CustomAtRuleTransformer();
$result = $transformer->transform($css, [], CustomAtRuleTransformer::composeVisitors([
    [
        'Rule' => [
            'unknown' => [
                'asset-base' => static function (array $rule) use (&$assetBase): array {
                    $assetBase = $rule['preludeTokens'][0]['value']['value'];

                    return [];
                },
            ],
        ],
    ],
    [
        'Url' => static function (array $url) use (&$assetBase, &$seenUrls): array {
            $seenUrls[] = $url['url'];
            $url['url'] = $assetBase . str_replace(' ', '%20', $url['url']);

            return $url;
        },
    ],
]));

$expected = '.wp-block-cover{background-image:url(https://cdn.example.com/wp-content/themes/twentytwentyfour/assets/hero%20image.png)}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom URL visitor output:\n{$result}\n");
        exit(1);
    }
    if ($assetBase !== 'https://cdn.example.com/wp-content/themes/twentytwentyfour/') {
        fwrite(STDERR, "Unexpected asset base: {$assetBase}\n");
        exit(1);
    }
    if ($seenUrls !== ['assets/hero image.png']) {
        fwrite(STDERR, "Unexpected URL visitor inputs: " . json_encode($seenUrls) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
