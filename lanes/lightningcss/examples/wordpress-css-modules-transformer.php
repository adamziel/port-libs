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

.card\,legacy {
  composes: card;
  color: red;
}

:global(.wp-block-button :local(.legacyButton)) .cardTitle {
  text-decoration: none;
}

:global(.wp-block-button\,legacy) .card\,legacy {
  border-color: yellow;
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
    gap: 8px;
  }
}

@scope (.cardScope) to (:global(.wp-block-buttons)) {
  .cardScoped {
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
$dependencyClassName = static fn (string $name, string $specifier): ?string => [
    './core.module.css' => [
        'reset' => 'Core_reset',
    ],
    './typography components.module.css' => [
        'heading' => 'Type_heading',
    ],
][$specifier][$name] ?? null;

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

$rawPseudoFunctionResult = (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  composes: cardBase;
  color: red;
}

.card:--block-state(.legacy, :hover) {
  color: yellow;
}

.cardBase {
  color: blue;
}
CSS, [
    'hash' => 'BlockA',
    'pseudoClasses' => [
        'hover' => 'is-hovered',
    ],
]);

$composeOnlyResult = (new CssModulesTransformer())->transform(<<<'CSS'
.cardShell {
  composes: card from "./cards.module.css";
}
CSS, [
    'hash' => 'BlockA',
]);

$commentedComposesResult = (new CssModulesTransformer())->transform(<<<'CSS'
.commentCard {
  composes: card/* local separator */cardBase;
  composes: utility/* global separator */from/* global separator */global;
  composes: reset/* dependency separator */from/* dependency separator */"./core.module.css";
  color: yellow;
}

.card {
  color: red;
}

.cardBase {
  color: blue;
}
CSS, [
    'hash' => 'BlockA',
]);

$pseudoElementBoundaryResult = (new CssModulesTransformer())->transform(<<<'CSS'
:host(:global(.wp-block-button)) .card,
::slotted(.card),
.card::before:hover {
  color: yellow;
}

.card {
  composes: cardBase;
  color: red;
}

.cardBase {
  color: blue;
}
CSS, [
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

try {
    (new CssModulesTransformer())->transform(<<<'CSS'
@media (min-width: 1px) {
  .card {
    composes: reset;
    color: red;
  }
}
CSS);
    $nestedComposes = 'accepted';
} catch (InvalidArgumentException) {
    $nestedComposes = 'rejected';
}

try {
    (new CssModulesTransformer())->transform(<<<'CSS'
::slotted(:global(.wp-block-button)) .card {
  color: red;
}
CSS);
    $invalidPseudoElementBoundary = 'accepted';
} catch (InvalidArgumentException) {
    $invalidPseudoElementBoundary = 'rejected';
}

try {
    (new CssModulesTransformer())->transform(<<<'CSS'
:host(.card) {
  composes: cardBase;
  color: red;
}
CSS);
    $invalidPseudoElementComposes = 'accepted';
} catch (InvalidArgumentException) {
    $invalidPseudoElementComposes = 'rejected';
}

try {
    (new CssModulesTransformer())->transform(<<<'CSS'
@value compact: (max-width: 37.4375em);

.card {
  composes: reset;
  color: red;
}
CSS);
    $deprecatedValueRule = 'accepted';
} catch (InvalidArgumentException $exception) {
    $deprecatedValueRule = $exception->getMessage();
}

try {
    (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  composes: reset;
  color: red;
}

.reset {
  color: blue;
}
CSS, [
        'hash' => 'BlockA',
        'pattern' => '[block]-[local]',
    ]);
    $invalidPattern = 'accepted';
} catch (InvalidArgumentException $exception) {
    $invalidPattern = $exception->getMessage();
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
    'classLists' => [
        'card' => CssModulesTransformer::exportClassList($result['exports'], 'card', $dependencyClassName),
        'cardTitle' => CssModulesTransformer::exportClassList($result['exports'], 'cardTitle', $dependencyClassName),
        'card:featured' => CssModulesTransformer::exportClassList($result['exports'], 'card:featured', $dependencyClassName),
        'cardGrid' => CssModulesTransformer::exportClassList($result['exports'], 'cardGrid', $dependencyClassName),
    ],
    'bareGlobal' => $bareGlobal,
    'invalidComposes' => $invalidComposes,
    'invalidGlobalList' => $invalidGlobalList,
    'invalidLocalComposes' => $invalidLocalComposes,
    'nestedComposes' => $nestedComposes,
    'invalidPseudoElementBoundary' => $invalidPseudoElementBoundary,
    'invalidPseudoElementComposes' => $invalidPseudoElementComposes,
    'deprecatedValueRule' => $deprecatedValueRule,
    'invalidPattern' => $invalidPattern,
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
    'rawPseudoFunction' => $rawPseudoFunctionResult['code'],
    'rawPseudoFunctionExports' => $rawPseudoFunctionResult['exports'],
    'composeOnly' => $composeOnlyResult['code'],
    'composeOnlyExports' => $composeOnlyResult['exports'],
    'commentedComposes' => $commentedComposesResult['code'],
    'commentedComposesExports' => $commentedComposesResult['exports'],
    'pseudoElementBoundary' => $pseudoElementBoundaryResult['code'],
    'pseudoElementBoundaryExports' => $pseudoElementBoundaryResult['exports'],
];

$expected = [
    'code' => '.BlockA_card{background:#fff;view-transition-name:BlockA_card-enter;view-transition-class:BlockA_card BlockA_page;animation:.24s ease-out BlockA_card-pop}.BlockA_card .BlockA_cardIcon{color:#ff0}.wp-block-button .BlockA_card{border-radius:4px}.BlockA_cardTitle{color:#ff0}.BlockA_card\:featured{outline:1px solid #ff0}.BlockA_card\,legacy{color:red}.wp-block-button .legacyButton .BlockA_cardTitle{text-decoration:none}.wp-block-button\,legacy .BlockA_card\,legacy{border-color:#ff0}@counter-style BlockA_card-steps{system:cyclic;symbols:A B C;suffix:". "}.BlockA_cardSteps{list-style:inside BlockA_card-steps}.BlockA_cardGrid{grid-template-areas:"BlockA_media BlockA_content";grid-template-columns:[BlockA_media-start]96px[BlockA_content-start]1fr[BlockA_content-end]}.BlockA_cardMedia{grid-area:BlockA_media}.BlockA_cardContent{grid-column:BlockA_content-start/BlockA_content-end}@container BlockA_card-layout (width>=320px){.BlockA_cardCompact{padding:12px}}@media (width>=600px){.BlockA_cardCompact{gap:8px}}@scope(.BlockA_cardScope) to (.wp-block-buttons){:scope .BlockA_cardScoped{color:#ff0}}@view-transition{types:BlockA_card-enter BlockA_page}@keyframes BlockA_card-pop{0%{opacity:0}to{opacity:1}}',
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
        'card,legacy' => [
            'name' => 'BlockA_card,legacy',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_card',
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
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardScope' => [
            'name' => 'BlockA_cardScope',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardScoped' => [
            'name' => 'BlockA_cardScoped',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'classLists' => [
        'card' => 'BlockA_card Core_reset has-spacing',
        'cardTitle' => 'BlockA_cardTitle Type_heading',
        'card:featured' => 'BlockA_card:featured BlockA_card Core_reset has-spacing wp:alignwide',
        'cardGrid' => 'BlockA_cardGrid BlockA_card Core_reset has-spacing',
    ],
    'bareGlobal' => 'rejected',
    'invalidComposes' => 'accepted',
    'invalidGlobalList' => 'rejected',
    'invalidLocalComposes' => 'rejected',
    'nestedComposes' => 'rejected',
    'invalidPseudoElementBoundary' => 'rejected',
    'invalidPseudoElementComposes' => 'rejected',
    'deprecatedValueRule' => 'The @value rule is deprecated',
    'invalidPattern' => 'Error parsing CSS modules pattern: unknown placeholder "[block]" at index 0',
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
            'composes' => [],
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
    'rawPseudoFunction' => '.BlockA_card{color:red}.BlockA_card:--block-state(.legacy,:hover){color:#ff0}.BlockA_cardBase{color:#00f}',
    'rawPseudoFunctionExports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_cardBase',
                ],
            ],
            'isReferenced' => false,
        ],
        'cardBase' => [
            'name' => 'BlockA_cardBase',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'composeOnly' => '.BlockA_cardShell{}',
    'composeOnlyExports' => [
        'cardShell' => [
            'name' => 'BlockA_cardShell',
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
    'commentedComposes' => '.BlockA_commentCard{color:#ff0}.BlockA_card{color:red}.BlockA_cardBase{color:#00f}',
    'commentedComposesExports' => [
        'commentCard' => [
            'name' => 'BlockA_commentCard',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_card',
                ],
                [
                    'type' => 'local',
                    'name' => 'BlockA_cardBase',
                ],
                [
                    'type' => 'global',
                    'name' => 'utility',
                ],
                [
                    'type' => 'dependency',
                    'name' => 'reset',
                    'specifier' => './core.module.css',
                ],
            ],
            'isReferenced' => false,
        ],
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardBase' => [
            'name' => 'BlockA_cardBase',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'pseudoElementBoundary' => ':host(.wp-block-button) .BlockA_card,::slotted(.BlockA_card),.BlockA_card:before:hover{color:#ff0}.BlockA_card{color:red}.BlockA_cardBase{color:#00f}',
    'pseudoElementBoundaryExports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_cardBase',
                ],
            ],
            'isReferenced' => false,
        ],
        'cardBase' => [
            'name' => 'BlockA_cardBase',
            'composes' => [],
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
echo 'class-lists: ' . json_encode($actual['classLists'], JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'bare-global: ' . $actual['bareGlobal'] . PHP_EOL;
echo 'invalid-composes: ' . $actual['invalidComposes'] . PHP_EOL;
echo 'invalid-global-list: ' . $actual['invalidGlobalList'] . PHP_EOL;
echo 'invalid-local-composes: ' . $actual['invalidLocalComposes'] . PHP_EOL;
echo 'nested-composes: ' . $actual['nestedComposes'] . PHP_EOL;
echo 'invalid-pseudo-element-boundary: ' . $actual['invalidPseudoElementBoundary'] . PHP_EOL;
echo 'invalid-pseudo-element-composes: ' . $actual['invalidPseudoElementComposes'] . PHP_EOL;
echo 'deprecated-value-rule: ' . $actual['deprecatedValueRule'] . PHP_EOL;
echo 'invalid-pattern: ' . $actual['invalidPattern'] . PHP_EOL;
echo 'pure-no-check: ' . $actual['pureNoCheck'] . PHP_EOL;
echo 'license-pure-no-check: ' . $actual['licensePureNoCheck'] . PHP_EOL;
echo 'pure-global: ' . $actual['pureGlobal'] . PHP_EOL;
echo 'content-hash: ' . $actual['contentHash'] . PHP_EOL;
echo 'dashed-idents: ' . $actual['dashedIdents'] . PHP_EOL;
echo 'pseudo-classes: ' . $actual['pseudoClasses'] . PHP_EOL;
echo 'raw-pseudo-function: ' . $actual['rawPseudoFunction'] . PHP_EOL;
echo 'compose-only: ' . $actual['composeOnly'] . PHP_EOL;
echo 'commented-composes: ' . $actual['commentedComposes'] . PHP_EOL;
echo 'pseudo-element-boundary: ' . $actual['pseudoElementBoundary'] . PHP_EOL;
