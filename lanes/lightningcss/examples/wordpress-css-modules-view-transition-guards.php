<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  composes: reset from "./core.module.css";
  view-transition-name: card-enter;
  color: red;
}

:root::VIEW-transition-\67 roup(card) {
  opacity: .9;
}

:root:active-view-transition-\74 ype(card-enter, page) {
  opacity: .7;
}

:global(:root::view-transition-old(public-card)) {
  opacity: .4;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$invalidRejected = false;
try {
    (new CssModulesTransformer())->transform(':root::view-transition-\67 roup(:global(public-card)) { opacity: .5 }', [
        'hash' => 'BlockA',
    ]);
} catch (InvalidArgumentException) {
    $invalidRejected = true;
}

$actual = [
    'code' => $result['code'],
    'cardClassList' => CssModulesTransformer::exportClassList(
        $result['exports'],
        'card',
        static fn (string $name, string $specifier): ?string => $name === 'reset' && $specifier === './core.module.css'
            ? 'Core_reset'
            : null
    ),
    'invalidRejected' => $invalidRejected,
];

$expected = [
    'code' => '.BlockA_card{view-transition-name:BlockA_card-enter;color:red}:root::view-transition-group(BlockA_card){opacity:.9}:root:active-view-transition-type(BlockA_card-enter,BlockA_page){opacity:.7}:root::view-transition-old(public-card){opacity:.4}',
    'cardClassList' => 'BlockA_card Core_reset',
    'invalidRejected' => true,
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules view-transition guard output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
