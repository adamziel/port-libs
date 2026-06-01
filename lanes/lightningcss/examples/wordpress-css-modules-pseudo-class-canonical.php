<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
:global(.wp-block-button:LOCAL-LINK) .button:READ-ONLY {
  color: red;
}

.button:l\6f cal-link {
  color: yellow;
}

.cta {
  composes: button;
  color: blue;
}
CSS;

$transformer = new CssModulesTransformer();
$result = $transformer->transform($css, [
    'hash' => 'BlockA',
]);

$stateResult = $transformer->transform(<<<'CSS'
.button:h\6f ver {
  color: red;
}
CSS, [
    'hash' => 'BlockA',
    'pseudoClasses' => [
        'hover' => 'is-hovered',
    ],
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'ctaClassList' => CssModulesTransformer::exportClassList($result['exports'], 'cta'),
    'stateCode' => $stateResult['code'],
    'stateExports' => $stateResult['exports'],
];

$expected = [
    'code' => '.wp-block-button:local-link .BlockA_button:read-only{color:red}.BlockA_button:local-link{color:#ff0}.BlockA_cta{color:#00f}',
    'exports' => [
        'button' => [
            'name' => 'BlockA_button',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cta' => [
            'name' => 'BlockA_cta',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_button',
                ],
            ],
            'isReferenced' => false,
        ],
    ],
    'ctaClassList' => 'BlockA_cta BlockA_button',
    'stateCode' => '.BlockA_button.BlockA_is-hovered{color:red}',
    'stateExports' => [
        'button' => [
            'name' => 'BlockA_button',
            'composes' => [],
            'isReferenced' => false,
        ],
        'is-hovered' => [
            'name' => 'BlockA_is-hovered',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules pseudo-class canonical output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'cta-class-list: ' . $actual['ctaClassList'] . PHP_EOL;
echo $actual['stateCode'] . PHP_EOL;
echo json_encode($actual['stateExports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
