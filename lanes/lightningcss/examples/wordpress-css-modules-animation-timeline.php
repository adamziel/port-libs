<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.blockMotion {
  animation: block-fade 320ms ease-out --block-scroll;
  composes: blockShell from "./block.module.css";
  color: white;
}

:global(.wp-block-query) .blockMotion {
  animation-timeline: --query-scroll;
  color: yellow;
}

@keyframes block-fade {
  from { opacity: 0 }
  to { opacity: 1 }
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
    'dashedIdents' => true,
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'classList' => CssModulesTransformer::exportClassList(
        $result['exports'],
        'blockMotion',
        static fn (string $name, string $specifier): ?string => $name === 'blockShell' && $specifier === './block.module.css'
            ? 'Theme_blockShell'
            : null
    ),
];

$expected = [
    'code' => '.BlockA_blockMotion{animation:.32s ease-out BlockA_block-fade --BlockA_block-scroll;color:#fff}.wp-block-query .BlockA_blockMotion{animation-timeline:--BlockA_query-scroll;color:#ff0}@keyframes BlockA_block-fade{0%{opacity:0}to{opacity:1}}',
    'exports' => [
        'blockMotion' => [
            'name' => 'BlockA_blockMotion',
            'composes' => [
                [
                    'type' => 'dependency',
                    'name' => 'blockShell',
                    'specifier' => './block.module.css',
                ],
            ],
            'isReferenced' => false,
        ],
        'block-fade' => [
            'name' => 'BlockA_block-fade',
            'composes' => [],
            'isReferenced' => true,
        ],
        '--block-scroll' => [
            'name' => '--BlockA_block-scroll',
            'composes' => [],
            'isReferenced' => false,
        ],
        '--query-scroll' => [
            'name' => '--BlockA_query-scroll',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'classList' => 'BlockA_blockMotion Theme_blockShell',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules animation-timeline output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'class-list: ' . $actual['classList'] . PHP_EOL;
