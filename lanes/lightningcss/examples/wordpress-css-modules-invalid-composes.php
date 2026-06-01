<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  composes: from global;
  color: red;
}

.button {
  composes: reset;
  color: blue;
}

.reset {
  color: white;
}

.cardLegacy {
  composes: heading from "./typography.css" extra;
  color: yellow;
}

.cardList {
  composes: utility, "legacy-card";
  color: purple;
}

.cardEscaped {
  c\6f mposes: utility \66 rom;
  color: green;
}

.utility {
  color: white;
}

.\66 rom {
  color: blue;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'buttonClassList' => CssModulesTransformer::exportClassList($result['exports'], 'button'),
];

$expected = [
    'code' => '.BlockA_card{composes:from global;color:red}.BlockA_button{color:#00f}.BlockA_reset{color:#fff}.BlockA_cardLegacy{composes:heading from "./typography.css" extra;color:#ff0}.BlockA_cardList{composes:utility, "legacy-card";color:purple}.BlockA_cardEscaped{composes:utility from;color:green}.BlockA_utility{color:#fff}.BlockA_from{color:#00f}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'button' => [
            'name' => 'BlockA_button',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_reset',
                ],
            ],
            'isReferenced' => false,
        ],
        'reset' => [
            'name' => 'BlockA_reset',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardLegacy' => [
            'name' => 'BlockA_cardLegacy',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardList' => [
            'name' => 'BlockA_cardList',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardEscaped' => [
            'name' => 'BlockA_cardEscaped',
            'composes' => [],
            'isReferenced' => false,
        ],
        'utility' => [
            'name' => 'BlockA_utility',
            'composes' => [],
            'isReferenced' => false,
        ],
        'from' => [
            'name' => 'BlockA_from',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'buttonClassList' => 'BlockA_button BlockA_reset',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules invalid composes output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'button-class-list: ' . $actual['buttonClassList'] . PHP_EOL;
