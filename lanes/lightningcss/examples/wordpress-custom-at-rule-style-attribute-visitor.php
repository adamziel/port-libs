<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$style = 'height: calc(100vh - 64px); padding: 24px';
$seenLengths = [];
$dependencyRegistered = false;

$transformer = new CustomAtRuleTransformer();
$result = $transformer->transformStyleAttributeWithDependencies(
    $style,
    static function (array $context) use (&$seenLengths, &$dependencyRegistered): array {
        $addDependency = $context['addDependency'];

        return [
            'Length' => static function (array $length) use (&$seenLengths, &$dependencyRegistered, $addDependency): ?array {
                $seenLengths[] = [
                    'unit' => $length['unit'],
                    'value' => $length['value'],
                ];

                if (!$dependencyRegistered) {
                    $addDependency([
                        'type' => 'file',
                        'filePath' => 'theme-spacing.json',
                    ]);
                    $dependencyRegistered = true;
                }

                return $length['unit'] === 'px'
                    ? ['unit' => 'rem', 'value' => $length['value'] / 16]
                    : null;
            },
        ];
    }
);

$expectedCode = 'height:calc(100vh - 4rem);padding:1.5rem';
$expectedDependencies = [
    ['type' => 'file', 'filePath' => 'theme-spacing.json'],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result['code'] !== $expectedCode) {
        fwrite(STDERR, "Unexpected style attribute output:\n{$result['code']}\n");
        exit(1);
    }
    if ($result['dependencies'] !== $expectedDependencies) {
        fwrite(STDERR, "Unexpected style attribute dependencies:\n" . json_encode($result['dependencies']) . "\n");
        exit(1);
    }
    if ($seenLengths !== [
        ['unit' => 'vh', 'value' => 100.0],
        ['unit' => 'px', 'value' => 64.0],
        ['unit' => 'px', 'value' => 24.0],
    ]) {
        fwrite(STDERR, "Unexpected style attribute lengths:\n" . json_encode($seenLengths) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result['code'] . PHP_EOL;
