<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.button {
  composes: base;
  composes: wp-block-button from global;
  color: red;
}

.base {
  color: blue;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'filename' => '/srv/www/current/wp-content/themes/block-theme/blocks/card.module.css',
    'project_root' => '/srv/www/current/wp-content/themes/block-theme',
    'pattern' => '[name]__[hash]__[local]',
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'buttonClassList' => CssModulesTransformer::exportClassList($result['exports'], 'button'),
];

$expected = [
    'code' => '.card-module__VKU3mq__button{color:red}.card-module__VKU3mq__base{color:#00f}',
    'exports' => [
        'button' => [
            'name' => 'card-module__VKU3mq__button',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'card-module__VKU3mq__base',
                ],
                [
                    'type' => 'global',
                    'name' => 'wp-block-button',
                ],
            ],
            'isReferenced' => false,
        ],
        'base' => [
            'name' => 'card-module__VKU3mq__base',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'buttonClassList' => 'card-module__VKU3mq__button card-module__VKU3mq__base wp-block-button',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules project-root compose output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'button-class-list: ' . $actual['buttonClassList'] . PHP_EOL;
