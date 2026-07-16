<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card:D\49 R(r\74 l) {
  color: red;
}

.card:l\61 ng("en-US", "fr") {
  color: yellow;
}

.card:lang("en US") {
  border-color: blue;
}

.button {
  composes: card;
  color: white;
}
CSS;

$transformer = new CssModulesTransformer();
$result = $transformer->transform($css, [
    'hash' => 'BlockA',
]);

$diagnostics = [];
foreach ([
    '.card:dir(:global(ltr)) { color: red }',
    '.card:l\61 ng(en, :local(fr)) { color: red }',
    '.card:dir("ltr") { color: red }',
    '.card:lang(1) { color: red }',
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
    'code' => '.BlockA_card:dir(rtl){color:red}.BlockA_card:lang(en-US,fr){color:#ff0}.BlockA_card:lang(en\ US){border-color:#00f}.BlockA_button{color:#fff}',
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
                    'name' => 'BlockA_card',
                ],
            ],
            'isReferenced' => false,
        ],
    ],
    'buttonClassList' => 'BlockA_button BlockA_card',
    'diagnostics' => [
        '.card:dir(:global(ltr)) { color: red }' => 'Unexpected token Colon',
        '.card:l\61 ng(en, :local(fr)) { color: red }' => 'Unexpected token Colon',
        '.card:dir("ltr") { color: red }' => 'Invalid CSS direction selector argument',
        '.card:lang(1) { color: red }' => 'Invalid CSS language selector argument',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules language pseudo output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'button-class-list: ' . $actual['buttonClassList'] . PHP_EOL;
echo json_encode($actual['diagnostics'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
