<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  animation: c\61 rd-pop 1s;
  view-transition-name: c\61 rd-enter;
  view-transition-class: nav\2d menu;
  view-transition-group: n\65 arest;
  composes: base from "./core.module.css";
  color: red;
}

:root::view-transition-group(c\61 rd-enter.t\68 umb) {
  opacity: .5;
}

@keyframes c\61 rd-pop {
  from { opacity: 0 }
  to { opacity: 1 }
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'cardClassList' => CssModulesTransformer::exportClassList(
        $result['exports'],
        'card',
        static fn (string $name, string $specifier): ?string => $name === 'base' && $specifier === './core.module.css'
            ? 'Core_base'
            : null
    ),
];

$expected = [
    'code' => '.BlockA_card{animation:1s BlockA_card-pop;view-transition-name:BlockA_card-enter;view-transition-class:BlockA_nav-menu;view-transition-group:nearest;color:red}:root::view-transition-group(BlockA_card-enter.BlockA_thumb){opacity:.5}@keyframes BlockA_card-pop{0%{opacity:0}to{opacity:1}}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [
                [
                    'type' => 'dependency',
                    'name' => 'base',
                    'specifier' => './core.module.css',
                ],
            ],
            'isReferenced' => false,
        ],
        'card-pop' => [
            'name' => 'BlockA_card-pop',
            'composes' => [],
            'isReferenced' => true,
        ],
        'card-enter' => [
            'name' => 'BlockA_card-enter',
            'composes' => [],
            'isReferenced' => false,
        ],
        'nav-menu' => [
            'name' => 'BlockA_nav-menu',
            'composes' => [],
            'isReferenced' => false,
        ],
        'thumb' => [
            'name' => 'BlockA_thumb',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'cardClassList' => 'BlockA_card Core_base',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected escaped CSS Modules custom-ident output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
