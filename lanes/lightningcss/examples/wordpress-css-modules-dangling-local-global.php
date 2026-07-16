<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card:is(:local(.draft >), .published) {
  color: red;
}

.card:where(:global(.wp-block-button +), .soft) {
  color: yellow;
}

.button {
  composes: card;
  color: white;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$errors = [];
foreach ([
    ':local(.draft >) { color: red }',
    ':global(.wp-block-button +) .card { color: red }',
] as $invalidCss) {
    try {
        (new CssModulesTransformer())->transform($invalidCss, [
            'hash' => 'BlockA',
        ]);
    } catch (InvalidArgumentException $exception) {
        $errors[$invalidCss] = $exception->getMessage();
    }
}

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'buttonClassList' => CssModulesTransformer::exportClassList($result['exports'], 'button'),
    'errors' => $errors,
];

$expected = [
    'code' => '.BlockA_card.BlockA_published{color:red}.BlockA_card:where(.BlockA_soft){color:#ff0}.BlockA_button{color:#fff}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'published' => [
            'name' => 'BlockA_published',
            'composes' => [],
            'isReferenced' => false,
        ],
        'soft' => [
            'name' => 'BlockA_soft',
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
    'errors' => [
        ':local(.draft >) { color: red }' => 'Invalid dangling combinator in selector',
        ':global(.wp-block-button +) .card { color: red }' => 'Invalid dangling combinator in selector',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules dangling local/global output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'button-class-list: ' . $actual['buttonClassList'] . PHP_EOL;
echo json_encode($actual['errors'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
