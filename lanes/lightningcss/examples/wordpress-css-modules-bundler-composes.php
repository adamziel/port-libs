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

$optionResult = (new CssBundler())->bundleCssModules('/block.module.css', [
    '/block.module.css' => <<<'CSS'
.card {
  composes: wp-alignwide from global;
  composes: token from "./tokens.module.css";
  animation: card-pop 1s;
  list-style: inside card-steps;
}

@keyframes card-pop {
  from { opacity: 0; }
  to { opacity: 1; }
}

@counter-style card-steps {
  system: cyclic;
  symbols: A B;
}
CSS,
    '/tokens.module.css' => <<<'CSS'
.token {
  composes: wp-token from global;
  animation-name: token-pop;
}

@keyframes token-pop {
  from { opacity: 0; }
  to { opacity: 1; }
}
CSS,
], null, [
    'hashes' => [
        '/block.module.css' => 'block',
        '/tokens.module.css' => 'tok',
    ],
    'animation' => false,
    'customIdents' => false,
]);

$expectedOptionResult = [
    'code' => '.tok_token{animation-name:token-pop}@keyframes token-pop{0%{opacity:0}to{opacity:1}}.block_card{animation:1s card-pop;list-style:inside card-steps}@keyframes card-pop{0%{opacity:0}to{opacity:1}}@counter-style card-steps{system:cyclic;symbols:A B}',
    'exports' => [
        'card' => [
            'name' => 'block_card',
            'composes' => [
                ['type' => 'global', 'name' => 'wp-alignwide'],
                ['type' => 'local', 'name' => 'tok_token'],
                ['type' => 'global', 'name' => 'wp-token'],
            ],
            'isReferenced' => false,
        ],
        'card-steps' => [
            'name' => 'block_card-steps',
            'composes' => [],
            'isReferenced' => true,
        ],
    ],
];

if ($optionResult !== $expectedOptionResult) {
    fwrite(STDERR, "Unexpected CSS Modules bundled option output:\n" . var_export($optionResult, true) . "\n");
    exit(1);
}

echo $result['code'] . PHP_EOL;
echo 'source-index-composes: preserved' . PHP_EOL;
echo 'css-module-options: forwarded' . PHP_EOL;
