<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.button {
  composes: reset;
  color: red;
}

:global(.wp-block-button) .button {
  color: yellow;
}

.reset {
  color: white;
}
CSS;

$transformer = new CssModulesTransformer();
$result = $transformer->transform($css, [
    'hash' => 'BlockA',
]);

$diagnostics = [];
foreach ([
    ':global .wp-block-button { color: red }',
    ':local .button { color: red }',
    ':global(.wp-block-button, .wp-block-file) .button { color: red }',
    ':local() { color: red }',
] as $invalidCss) {
    try {
        $transformer->transform($invalidCss, [
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
    'code' => '.BlockA_button{color:red}.wp-block-button .BlockA_button{color:#ff0}.BlockA_reset{color:#fff}',
    'exports' => [
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
    ],
    'buttonClassList' => 'BlockA_button BlockA_reset',
    'diagnostics' => [
        ':global .wp-block-button { color: red }' => 'Ambiguous CSS module class not supported',
        ':local .button { color: red }' => 'Ambiguous CSS module class not supported',
        ':global(.wp-block-button, .wp-block-file) .button { color: red }' => 'Unexpected token Comma',
        ':local() { color: red }' => 'Invalid empty selector',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules local/global diagnostics output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'button-class-list: ' . $actual['buttonClassList'] . PHP_EOL;
echo json_encode($actual['diagnostics'], JSON_PRETTY_PRINT) . PHP_EOL;
