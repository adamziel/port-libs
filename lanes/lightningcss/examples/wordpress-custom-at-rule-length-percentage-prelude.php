<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@card-gap 24px;
@stack-gap 50%;

.wp-block-card {
  gap: var(--wp-card-gap);
}
CSS;

$seenPreludeAst = [];
$transformer = new CustomAtRuleTransformer();
$result = $transformer->transform($css, [
    'card-gap' => ['prelude' => '<length-percentage>'],
    'stack-gap' => ['prelude' => '<length-percentage>'],
], [
    'Length' => static fn (array $length): ?array => $length['unit'] === 'px'
        ? ['unit' => 'rem', 'value' => $length['value'] / 16]
        : null,
    'Rule' => [
        'custom' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$seenPreludeAst): array {
            $seenPreludeAst[$rule['name']] = $rule['preludeAst'];

            return $transformer->styleRule(':root', [
                [
                    'property' => '--wp-' . $rule['name'],
                    'value' => $rule['prelude'],
                    'important' => false,
                ],
            ]);
        },
    ],
]);

$expected = ':root{--wp-card-gap:1.5rem;--wp-stack-gap:50%}.wp-block-card{gap:var(--wp-card-gap)}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom length-percentage prelude output:\n{$result}\n");
        exit(1);
    }

    $cardValue = $seenPreludeAst['card-gap']['value'] ?? null;
    if (
        !is_array($cardValue)
        || ($cardValue['type'] ?? null) !== 'dimension'
        || ($cardValue['value']['unit'] ?? null) !== 'rem'
        || ($cardValue['value']['value'] ?? null) !== 1.5
    ) {
        fwrite(STDERR, "Unexpected card-gap prelude AST:\n" . json_encode($seenPreludeAst['card-gap'] ?? null) . "\n");
        exit(1);
    }

    $stackValue = $seenPreludeAst['stack-gap']['value'] ?? null;
    if (!is_array($stackValue) || ($stackValue['type'] ?? null) !== 'percentage' || ($stackValue['value'] ?? null) !== 0.5) {
        fwrite(STDERR, "Unexpected stack-gap prelude AST:\n" . json_encode($seenPreludeAst['stack-gap'] ?? null) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
