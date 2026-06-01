<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.caption::cue(:global(.wp-caption) .line) {
  color: red;
}

.caption::cue-region(.activeRegion):hover {
  color: yellow;
}

.button {
  composes: caption;
  color: white;
}

.caption {
  color: blue;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'buttonClassList' => CssModulesTransformer::exportClassList($result['exports'], 'button'),
];

$expected = [
    'code' => '.BlockA_caption::cue(.wp-caption .BlockA_line){color:red}.BlockA_caption::cue-region(.BlockA_activeRegion):hover{color:#ff0}.BlockA_button{color:#fff}.BlockA_caption{color:#00f}',
    'exports' => [
        'caption' => [
            'name' => 'BlockA_caption',
            'composes' => [],
            'isReferenced' => false,
        ],
        'line' => [
            'name' => 'BlockA_line',
            'composes' => [],
            'isReferenced' => false,
        ],
        'activeRegion' => [
            'name' => 'BlockA_activeRegion',
            'composes' => [],
            'isReferenced' => false,
        ],
        'button' => [
            'name' => 'BlockA_button',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_caption',
                ],
            ],
            'isReferenced' => false,
        ],
    ],
    'buttonClassList' => 'BlockA_button BlockA_caption',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules WebVTT cue output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
