<?php

declare(strict_types=1);

use PortLibs\Esbuild\JsLexer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-view.js');
$tokens = (new JsLexer())->tokenize($source);
$imports = array_values(array_filter(
    $tokens,
    static fn ($token): bool => $token->kind === 'string' && str_starts_with($token->text, "'@wordpress/")
));

printf("WordPress asset tokens: %d\n", count($tokens));
printf("WordPress package imports: %d\n", count($imports));
