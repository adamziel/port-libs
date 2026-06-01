<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card:nth-child(2n + 1 of :global(.wp-block-post) + .child) {
  color: red;
}

.card:nth-last-child(even of :local(.slot), :global(.is-layout-flow)) {
  color: blue;
}

.card:nth-child(0n + 3 of .item) {
  color: green;
}

.button {
  composes: card;
  color: white;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$diagnostics = [];
foreach ([
    '.card:nth-child(:local(.item)) { color: red }',
    '.card:nth-last-child(2n + :global(.wp-block-post)) { color: red }',
    '.card:nth-child(.ghost) { color: red } .button { composes: card; color: white }',
    '.card:nth-child(+ 2) { color: red } .button { composes: card; color: white }',
] as $invalidCss) {
    try {
        (new CssModulesTransformer())->transform($invalidCss, [
            'hash' => 'BlockA',
        ]);
        $diagnostics[$invalidCss] = 'accepted';
    } catch (InvalidArgumentException $exception) {
        $diagnostics[$invalidCss] = $exception->getMessage();
    }
}

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'buttonClassList' => CssModulesTransformer::exportClassList($result['exports'], 'button'),
    'diagnostics' => $diagnostics,
];

$expected = [
    'code' => '.BlockA_card:nth-child(odd of .wp-block-post+.BlockA_child){color:red}.BlockA_card:nth-last-child(2n of .BlockA_slot,.is-layout-flow){color:#00f}.BlockA_card:nth-child(3 of .BlockA_item){color:green}.BlockA_button{color:#fff}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'child' => [
            'name' => 'BlockA_child',
            'composes' => [],
            'isReferenced' => false,
        ],
        'slot' => [
            'name' => 'BlockA_slot',
            'composes' => [],
            'isReferenced' => false,
        ],
        'item' => [
            'name' => 'BlockA_item',
            'composes' => [],
            'isReferenced' => false,
        ],
        'button' => [
            'name' => 'BlockA_button',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_card',
                ],
            ],
            'isReferenced' => false,
        ],
    ],
    'buttonClassList' => 'BlockA_button BlockA_card',
    'diagnostics' => [
        '.card:nth-child(:local(.item)) { color: red }' => 'Unexpected token Colon',
        '.card:nth-last-child(2n + :global(.wp-block-post)) { color: red }' => 'Unexpected token Colon',
        '.card:nth-child(.ghost) { color: red } .button { composes: card; color: white }' => "Unexpected token Delim('.')",
        '.card:nth-child(+ 2) { color: red } .button { composes: card; color: white }' => 'Unexpected token WhiteSpace(" ")',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules nth-child formula output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'button-class-list: ' . $actual['buttonClassList'] . PHP_EOL;
echo json_encode($actual['diagnostics'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
