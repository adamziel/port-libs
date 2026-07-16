<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-card {
  color: raw-token('accent');
  background: raw-token('danger');
}
CSS;

$seenDashedIdents = [];
$result = (new CustomAtRuleTransformer())->transform($css, [], [
    'Function' => [
        'raw-token' => static function (array $arguments): ?array {
            if (($arguments[0] ?? null) === 'accent') {
                return ['raw' => 'var(--wp-card-accent)'];
            }
            if (($arguments[0] ?? null) === 'danger') {
                return ['raw' => 'rgba(255, 0, 0)'];
            }

            return null;
        },
    ],
    'DashedIdent' => static function (string $ident) use (&$seenDashedIdents): string {
        $seenDashedIdents[] = $ident;

        return str_replace('--wp-card-', '--theme-card-', $ident);
    },
]);

$expected = '.wp-block-card{color:var(--theme-card-accent);background:red}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected raw function variable output:\n{$result}\n");
        exit(1);
    }
    if ($seenDashedIdents !== ['--wp-card-accent']) {
        fwrite(STDERR, "Unexpected raw function dashed identifiers: " . json_encode($seenDashedIdents) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
