<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  background: white;
  view-transition-name: card-enter;
  view-transition-class: card page;

  .cardIcon {
    color: yellow;
  }

  composes: reset from "./core.module.css";
  composes: has-spacing from global;
}

:global(.wp-block-button) .card {
  border-radius: 4px;
}

:local(.cardTitle) {
  composes: heading from "./typography.module.css";
  color: yellow;
}

:global(.wp-block-button :local(.legacyButton)) .cardTitle {
  text-decoration: none;
}

@media (min-width: 600px) {
  .cardCompact {
    composes: card;
    gap: 8px;
  }
}

@view-transition {
  types: card-enter page;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

try {
    (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  :global {
    .legacyUtility {
      color: red;
    }
  }
}
CSS);
    $bareGlobal = 'accepted';
} catch (InvalidArgumentException) {
    $bareGlobal = 'rejected';
}

try {
    (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  composes: reset from global legacy;
  color: red;
}
CSS);
    $invalidComposes = 'accepted';
} catch (InvalidArgumentException) {
    $invalidComposes = 'rejected';
}

try {
    (new CssModulesTransformer())->transform(<<<'CSS'
.card :global(.wp-block-button, .wp-block-file) {
  color: red;
}
CSS);
    $invalidGlobalList = 'accepted';
} catch (InvalidArgumentException) {
    $invalidGlobalList = 'rejected';
}

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'bareGlobal' => $bareGlobal,
    'invalidComposes' => $invalidComposes,
    'invalidGlobalList' => $invalidGlobalList,
];

$expected = [
    'code' => '.BlockA_card{background:#fff;view-transition-name:BlockA_card-enter;view-transition-class:BlockA_card BlockA_page}.BlockA_card .BlockA_cardIcon{color:#ff0}.wp-block-button .BlockA_card{border-radius:4px}.BlockA_cardTitle{color:#ff0}.wp-block-button .legacyButton .BlockA_cardTitle{text-decoration:none}@media (width>=600px){.BlockA_cardCompact{gap:8px}}@view-transition{types:BlockA_card-enter BlockA_page}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [
                [
                    'type' => 'dependency',
                    'name' => 'reset',
                    'specifier' => './core.module.css',
                ],
                [
                    'type' => 'global',
                    'name' => 'has-spacing',
                ],
            ],
            'isReferenced' => false,
        ],
        'card-enter' => [
            'name' => 'BlockA_card-enter',
            'composes' => [],
            'isReferenced' => false,
        ],
        'page' => [
            'name' => 'BlockA_page',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardIcon' => [
            'name' => 'BlockA_cardIcon',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardTitle' => [
            'name' => 'BlockA_cardTitle',
            'composes' => [
                [
                    'type' => 'dependency',
                    'name' => 'heading',
                    'specifier' => './typography.module.css',
                ],
            ],
            'isReferenced' => false,
        ],
        'cardCompact' => [
            'name' => 'BlockA_cardCompact',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_card',
                ],
            ],
            'isReferenced' => false,
        ],
    ],
    'bareGlobal' => 'rejected',
    'invalidComposes' => 'rejected',
    'invalidGlobalList' => 'rejected',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules transformer output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'bare-global: ' . $actual['bareGlobal'] . PHP_EOL;
echo 'invalid-composes: ' . $actual['invalidComposes'] . PHP_EOL;
echo 'invalid-global-list: ' . $actual['invalidGlobalList'] . PHP_EOL;
