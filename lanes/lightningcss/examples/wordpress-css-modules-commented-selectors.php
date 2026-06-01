<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card/* block build marker */.is-wide {
  color: red;
}

:global(.wp-block/* block build marker */.legacy) .card {
  color: yellow;
}

.button {
  composes: card;
  color: blue;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$invalidCommentBoundaryRejected = false;
try {
    (new CssModulesTransformer())->transform(<<<'CSS'
:global(.wp-block/* generated marker */button) .card {
  color: red;
}
CSS, [
        'hash' => 'BlockA',
    ]);
} catch (InvalidArgumentException $exception) {
    $invalidCommentBoundaryRejected = $exception->getMessage() === 'CSS comments cannot split selector identifiers';
}

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'buttonClassList' => CssModulesTransformer::exportClassList($result['exports'], 'button'),
    'invalidCommentBoundaryRejected' => $invalidCommentBoundaryRejected,
];

$expected = [
    'code' => '.BlockA_card.BlockA_is-wide{color:red}.wp-block.legacy .BlockA_card{color:#ff0}.BlockA_button{color:#00f}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'is-wide' => [
            'name' => 'BlockA_is-wide',
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
    'invalidCommentBoundaryRejected' => true,
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules commented selector output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'button-class-list: ' . $actual['buttonClassList'] . PHP_EOL;
echo 'invalid-comment-boundary-rejected: ' . ($actual['invalidCommentBoundaryRejected'] ? 'yes' : 'no') . PHP_EOL;
