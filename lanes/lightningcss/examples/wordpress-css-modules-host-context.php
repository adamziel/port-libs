<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
:host-context(.is-editor-preview) .card {
  color: red;
}

:host-context(.is-editor-preview :global(.wp-block-group)) .card {
  border-color: red;
}

:host-context(.is-editor-preview :local(.legacy-card)) .card {
  outline-color: yellow;
}

:host(.block-card) .cardHost {
  color: yellow;
}

.card {
  composes: base;
  color: green;
}

.base {
  color: white;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'cardClassList' => CssModulesTransformer::exportClassList($result['exports'], 'card'),
];

$expected = [
    'code' => ':host-context(.is-editor-preview) .BlockA_card{color:red}:host-context(.is-editor-preview :global(.wp-block-group)) .BlockA_card{border-color:red}:host-context(.is-editor-preview :local(.legacy-card)) .BlockA_card{outline-color:#ff0}:host(.BlockA_block-card) .BlockA_cardHost{color:#ff0}.BlockA_card{color:green}.BlockA_base{color:#fff}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_base',
                ],
            ],
            'isReferenced' => false,
        ],
        'block-card' => [
            'name' => 'BlockA_block-card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardHost' => [
            'name' => 'BlockA_cardHost',
            'composes' => [],
            'isReferenced' => false,
        ],
        'base' => [
            'name' => 'BlockA_base',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'cardClassList' => 'BlockA_card BlockA_base',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules host-context output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'card-class-list: ' . $actual['cardClassList'] . PHP_EOL;
