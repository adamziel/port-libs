<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssBundler;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$result = (new CssBundler())->bundleCssModules('/entry.module.css', [
    '/entry.module.css' => <<<'CSS'
.button {
  composes: card card from "./card.module.css";
  color: red;
}
CSS,
    '/card.module.css' => <<<'CSS'
.card {
  composes: wp-alignwide from global;
  composes: spacing from "./tokens.module.css";
  background: white;
}
CSS,
    '/tokens.module.css' => <<<'CSS'
.spacing {
  margin: 0;
}
CSS,
], null, [
    'hashes' => [
        '/entry.module.css' => 'entry',
        '/card.module.css' => 'card',
        '/tokens.module.css' => 'tok',
    ],
]);

$expected = [
    'code' => '.tok_spacing{margin:0}.card_card{background:#fff}.entry_button{color:red}',
    'exports' => [
        'button' => [
            'name' => 'entry_button',
            'composes' => [
                ['type' => 'local', 'name' => 'card_card'],
                ['type' => 'global', 'name' => 'wp-alignwide'],
                ['type' => 'local', 'name' => 'tok_spacing'],
                ['type' => 'local', 'name' => 'card_card'],
                ['type' => 'global', 'name' => 'wp-alignwide'],
                ['type' => 'local', 'name' => 'tok_spacing'],
            ],
            'isReferenced' => false,
        ],
    ],
];

if ($result !== $expected) {
    fwrite(STDERR, "Unexpected CSS Modules bundled composes output:\n" . var_export($result, true) . "\n");
    exit(1);
}

echo $result['code'] . PHP_EOL;
echo 'source-index-composes: preserved' . PHP_EOL;
