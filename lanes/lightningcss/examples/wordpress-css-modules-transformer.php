<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  background: white;
  view-transition-name: card-enter;
  view-transition-class: card page;
  animation: card-pop 240ms ease-out;

  .cardIcon {
    color: yellow;
  }

  composes: reset from "./core.module.css";
  composes: has-spacing from global ! important;
}

:global(.wp-block-button) .card {
  border-radius: 4px;
}

.cardTitle {
  composes: heading from "./typography\ components.module.css" !important;
  color: yellow;
}

.card\:featured {
  composes: card;
  composes: wp\:alignwide from global;
  outline: 1px solid yellow;
}

:global(.wp-block-button :local(.legacyButton)) .cardTitle {
  text-decoration: none;
}

@counter-style card-steps {
  system: cyclic;
  symbols: A B C;
  suffix: ". ";
}

.cardSteps {
  list-style: card-steps inside;
  composes: card;
}

.cardGrid {
  composes: card;
  grid-template-areas: "media content";
  grid-template-columns: [media-start] 96px [content-start] 1fr [content-end];
}

.cardMedia {
  grid-area: media;
}

.cardContent {
  grid-column: content-start / content-end;
}

@container card-layout (width >= 320px) {
  .cardCompact {
    padding: 12px;
  }
}

@media (min-width: 600px) {
  .cardCompact {
    composes: card;
    gap: 8px;
  }
}

@scope (.cardScope) to (:global(.wp-block-buttons)) {
  .cardScoped {
    composes: card;
    color: yellow;
  }
}

@view-transition {
  types: card-enter page;
}

@keyframes card-pop {
  from { opacity: 0 }
  to { opacity: 1 }
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$contentHashResult = (new CssModulesTransformer())->transform(<<<'CSS'
.cardHash {
  composes: reset from "./core.module.css";
  background: white;
}
CSS, [
    'pattern' => '[content-hash]-[local]',
]);

$dashedIdentResult = (new CssModulesTransformer())->transform(<<<'CSS'
@property --card-accent {
  syntax: '<color>';
  inherits: false;
  initial-value: yellow;
}

@font-palette-values --card-palette {
  font-family: Bixa;
  base-palette: 1;
  override-colors: 1 #7EB7E4;
}

.paletteCard {
  --card-accent: red;
  font-palette: --card-palette;
  composes: card from "./cards.module.css";
  color: var(--card-accent);
}

@media (max-width: env(--card-breakpoint)) {
  .paletteCardCompact {
    color: env(--card-accent);
    composes: card from "./cards.module.css";
  }
}
CSS, [
    'hash' => 'BlockA',
    'dashedIdents' => true,
]);

$pseudoClassResult = (new CssModulesTransformer())->transform(<<<'CSS'
.card:hover {
  color: yellow;
}

:global(.wp-block-button:hover) .card:focus-visible {
  outline-color: yellow;
}

.cardInteractive {
  composes: card;
  color: red;
}
CSS, [
    'hash' => 'BlockA',
    'pseudoClasses' => [
        'hover' => 'is-hovered',
        'focusVisible' => 'is-focus-visible',
    ],
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

try {
    (new CssModulesTransformer())->transform(<<<'CSS'
:local(.card) {
  composes: reset;
  color: red;
}
CSS);
    $invalidLocalComposes = 'accepted';
} catch (InvalidArgumentException) {
    $invalidLocalComposes = 'rejected';
}

$pureNoCheck = (new CssModulesTransformer())->transform(<<<'CSS'
/* cssmodules-pure-no-check */ :global(.wp-block-button) {
  color: red;
}
CSS, [
    'hash' => 'BlockA',
    'pure' => true,
]);

$licensePureNoCheck = (new CssModulesTransformer())->transform(<<<'CSS'
/*! Block CSS delivery license */
/* cssmodules-pure-no-check */ :global(.wp-block-button) {
  color: red;
}

.licenseCard {
  composes: card;
  color: yellow;
}

.card {
  color: blue;
}
CSS, [
    'hash' => 'BlockA',
    'pure' => true,
]);

try {
    (new CssModulesTransformer())->transform(<<<'CSS'
:global(.wp-block-button) {
  color: red;
}
CSS, [
        'hash' => 'BlockA',
        'pure' => true,
    ]);
    $pureGlobal = 'accepted';
} catch (InvalidArgumentException) {
    $pureGlobal = 'rejected';
}

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'bareGlobal' => $bareGlobal,
    'invalidComposes' => $invalidComposes,
    'invalidGlobalList' => $invalidGlobalList,
    'invalidLocalComposes' => $invalidLocalComposes,
    'pureNoCheck' => $pureNoCheck['code'],
    'licensePureNoCheck' => $licensePureNoCheck['code'],
    'licensePureNoCheckExports' => $licensePureNoCheck['exports'],
    'pureGlobal' => $pureGlobal,
    'contentHash' => $contentHashResult['code'],
    'contentHashExports' => $contentHashResult['exports'],
    'dashedIdents' => $dashedIdentResult['code'],
    'dashedIdentExports' => $dashedIdentResult['exports'],
    'pseudoClasses' => $pseudoClassResult['code'],
    'pseudoClassExports' => $pseudoClassResult['exports'],
];

$expected = [
    'code' => '.BlockA_card{background:#fff;view-transition-name:BlockA_card-enter;view-transition-class:BlockA_card BlockA_page;animation:.24s ease-out BlockA_card-pop}.BlockA_card .BlockA_cardIcon{color:#ff0}.wp-block-button .BlockA_card{border-radius:4px}.BlockA_cardTitle{color:#ff0}.BlockA_card\:featured{outline:1px solid #ff0}.wp-block-button .legacyButton .BlockA_cardTitle{text-decoration:none}@counter-style BlockA_card-steps{system:cyclic;symbols:A B C;suffix:". "}.BlockA_cardSteps{list-style:inside BlockA_card-steps}.BlockA_cardGrid{grid-template-areas:"BlockA_media BlockA_content";grid-template-columns:[BlockA_media-start]96px[BlockA_content-start]1fr[BlockA_content-end]}.BlockA_cardMedia{grid-area:BlockA_media}.BlockA_cardContent{grid-column:BlockA_content-start/BlockA_content-end}@container BlockA_card-layout (width>=320px){.BlockA_cardCompact{padding:12px}}@media (width>=600px){.BlockA_cardCompact{gap:8px}}@scope(.BlockA_cardScope) to (.wp-block-buttons){:scope .BlockA_cardScoped{color:#ff0}}@view-transition{types:BlockA_card-enter BlockA_page}@keyframes BlockA_card-pop{0%{opacity:0}to{opacity:1}}',
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
        'card-pop' => [
            'name' => 'BlockA_card-pop',
            'composes' => [],
            'isReferenced' => true,
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
                    'specifier' => './typography components.module.css',
                ],
            ],
            'isReferenced' => false,
        ],
        'card:featured' => [
            'name' => 'BlockA_card:featured',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_card',
                ],
                [
                    'type' => 'global',
                    'name' => 'wp:alignwide',
                ],
            ],
            'isReferenced' => false,
        ],
        'card-steps' => [
            'name' => 'BlockA_card-steps',
            'composes' => [],
            'isReferenced' => true,
        ],
        'cardSteps' => [
            'name' => 'BlockA_cardSteps',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_card',
                ],
            ],
            'isReferenced' => false,
        ],
        'cardGrid' => [
            'name' => 'BlockA_cardGrid',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_card',
                ],
            ],
            'isReferenced' => false,
        ],
        'media' => [
            'name' => 'BlockA_media',
            'composes' => [],
            'isReferenced' => false,
        ],
        'content' => [
            'name' => 'BlockA_content',
            'composes' => [],
            'isReferenced' => false,
        ],
        'media-start' => [
            'name' => 'BlockA_media-start',
            'composes' => [],
            'isReferenced' => false,
        ],
        'content-start' => [
            'name' => 'BlockA_content-start',
            'composes' => [],
            'isReferenced' => false,
        ],
        'content-end' => [
            'name' => 'BlockA_content-end',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardMedia' => [
            'name' => 'BlockA_cardMedia',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardContent' => [
            'name' => 'BlockA_cardContent',
            'composes' => [],
            'isReferenced' => false,
        ],
        'card-layout' => [
            'name' => 'BlockA_card-layout',
            'composes' => [],
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
        'cardScope' => [
            'name' => 'BlockA_cardScope',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardScoped' => [
            'name' => 'BlockA_cardScoped',
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
    'invalidLocalComposes' => 'rejected',
    'pureNoCheck' => '.wp-block-button{color:red}',
    'licensePureNoCheck' => "/*! Block CSS delivery license */\n.wp-block-button{color:red}.BlockA_licenseCard{color:#ff0}.BlockA_card{color:#00f}",
    'licensePureNoCheckExports' => [
        'licenseCard' => [
            'name' => 'BlockA_licenseCard',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_card',
                ],
            ],
            'isReferenced' => false,
        ],
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'pureGlobal' => 'rejected',
    'contentHash' => '.hePdSq-cardHash{background:#fff}',
    'contentHashExports' => [
        'cardHash' => [
            'name' => 'hePdSq-cardHash',
            'composes' => [
                [
                    'type' => 'dependency',
                    'name' => 'reset',
                    'specifier' => './core.module.css',
                ],
            ],
            'isReferenced' => false,
        ],
    ],
    'dashedIdents' => '@property --BlockA_card-accent{syntax:"<color>";inherits:false;initial-value:#ff0}@font-palette-values --BlockA_card-palette{font-family:Bixa;base-palette:1;override-colors:1 #7eb7e4}.BlockA_paletteCard{--BlockA_card-accent:red;font-palette:--BlockA_card-palette;color:var(--BlockA_card-accent)}@media (width<=env(--BlockA_card-breakpoint)){.BlockA_paletteCardCompact{color:env(--BlockA_card-accent)}}',
    'dashedIdentExports' => [
        '--card-accent' => [
            'name' => '--BlockA_card-accent',
            'composes' => [],
            'isReferenced' => true,
        ],
        '--card-palette' => [
            'name' => '--BlockA_card-palette',
            'composes' => [],
            'isReferenced' => true,
        ],
        'paletteCard' => [
            'name' => 'BlockA_paletteCard',
            'composes' => [
                [
                    'type' => 'dependency',
                    'name' => 'card',
                    'specifier' => './cards.module.css',
                ],
            ],
            'isReferenced' => false,
        ],
        '--card-breakpoint' => [
            'name' => '--BlockA_card-breakpoint',
            'composes' => [],
            'isReferenced' => true,
        ],
        'paletteCardCompact' => [
            'name' => 'BlockA_paletteCardCompact',
            'composes' => [
                [
                    'type' => 'dependency',
                    'name' => 'card',
                    'specifier' => './cards.module.css',
                ],
            ],
            'isReferenced' => false,
        ],
    ],
    'pseudoClasses' => '.BlockA_card.BlockA_is-hovered{color:#ff0}.wp-block-button.is-hovered .BlockA_card.BlockA_is-focus-visible{outline-color:#ff0}.BlockA_cardInteractive{color:red}',
    'pseudoClassExports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'is-hovered' => [
            'name' => 'BlockA_is-hovered',
            'composes' => [],
            'isReferenced' => false,
        ],
        'is-focus-visible' => [
            'name' => 'BlockA_is-focus-visible',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardInteractive' => [
            'name' => 'BlockA_cardInteractive',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_card',
                ],
            ],
            'isReferenced' => false,
        ],
    ],
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
echo 'invalid-local-composes: ' . $actual['invalidLocalComposes'] . PHP_EOL;
echo 'pure-no-check: ' . $actual['pureNoCheck'] . PHP_EOL;
echo 'license-pure-no-check: ' . $actual['licensePureNoCheck'] . PHP_EOL;
echo 'pure-global: ' . $actual['pureGlobal'] . PHP_EOL;
echo 'content-hash: ' . $actual['contentHash'] . PHP_EOL;
echo 'dashed-idents: ' . $actual['dashedIdents'] . PHP_EOL;
echo 'pseudo-classes: ' . $actual['pseudoClasses'] . PHP_EOL;
