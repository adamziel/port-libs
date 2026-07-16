<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@position-try --popover-below {
  composes: legacy-popover;
  top: anchor(bottom);
  margin: 0;
}

@supports (anchor-name: --wp-menu-anchor) {
  @position-try --popover-above {
    composes: legacy-popover from global;
    bottom: anchor(top);
  }
}

.flyout {
  position-try-fallbacks: --popover-below, --popover-above flip-block;
  composes: reset from "./core.module.css";
  color: yellow;
}

.flyoutVar {
  position-try-fallbacks: var(--popover-below);
  color: red;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
    'dashedIdents' => true,
]);

$classList = CssModulesTransformer::exportClassList(
    $result['exports'],
    'flyout',
    static fn (string $name, string $specifier): ?string => $name === 'reset' && $specifier === './core.module.css'
        ? 'Core_reset'
        : null
);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'classList' => $classList,
];

$expected = [
    'code' => '@position-try --BlockA_popover-below{top:anchor(bottom);margin:0}@supports (anchor-name:--wp-menu-anchor){@position-try --BlockA_popover-above{bottom:anchor(top)}}.BlockA_flyout{position-try-fallbacks:--BlockA_popover-below,--BlockA_popover-above flip-block;color:#ff0}.BlockA_flyoutVar{position-try-fallbacks:var(--BlockA_popover-below);color:red}',
    'exports' => [
        '--popover-below' => [
            'name' => '--BlockA_popover-below',
            'composes' => [],
            'isReferenced' => true,
        ],
        '--popover-above' => [
            'name' => '--BlockA_popover-above',
            'composes' => [],
            'isReferenced' => false,
        ],
        'flyout' => [
            'name' => 'BlockA_flyout',
            'composes' => [
                [
                    'type' => 'dependency',
                    'name' => 'reset',
                    'specifier' => './core.module.css',
                ],
            ],
            'isReferenced' => false,
        ],
        'flyoutVar' => [
            'name' => 'BlockA_flyoutVar',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'classList' => 'BlockA_flyout Core_reset',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules position-try output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'class-list: ' . $actual['classList'] . PHP_EOL;
