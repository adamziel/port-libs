<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  composes: reset;
  color: red;
}

.reset {
  margin: 0;
}

:global(.legacyCard) .card {
  outline: 1px solid green;
}

.legacyCard {
  composes: wp-alignwide from global;
  color: green;
}

@keyframes legacy-fade {
  from { opacity: 0 }
  to { opacity: 1 }
}

@property --legacy-accent {
  syntax: '<color>';
  inherits: false;
  initial-value: yellow;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
    'dashedIdents' => true,
    'unusedSymbols' => ['legacyCard', 'legacy-fade', '--legacy-accent'],
]);

$expected = [
    'code' => '.BlockA_card{color:red}.BlockA_reset{margin:0}.legacyCard .BlockA_card{outline:1px solid green}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [
                ['type' => 'local', 'name' => 'BlockA_reset'],
            ],
            'isReferenced' => false,
        ],
        'reset' => [
            'name' => 'BlockA_reset',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'references' => [],
];

if (in_array('--self-test', $argv, true)) {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules unused-symbol pruning output:\n" . var_export($result, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result['code'] . PHP_EOL;
