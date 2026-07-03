<?php

declare(strict_types=1);

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PandocConverter;

$nativeTokenStream = static function (string $native): string {
    $native = (string) preg_replace('/\[\s*\]/', '[]', $native);
    $native = (string) preg_replace('/\(\s*""\s*,\s*\[\]\s*,\s*\[\]\s*\)/', '("",[],[])', $native);

    return (string) preg_replace('/\s+/', ' ', trim($native));
};

return [
    'imports upstream markdown yaml metadata fixture body and metadata' => static function (TestRunner $t) use ($nativeTokenStream): void {
        $fixtureRoot = dirname(__DIR__) . '/fixtures';
        $markdown = (string) file_get_contents($fixtureRoot . '/upstream-markdown-yaml-metadata.md');
        $expectedNative = (string) file_get_contents($fixtureRoot . '/upstream-markdown-yaml-metadata.native');
        $document = (new MarkdownReader())->read($markdown);
        $meta = $document->attr('meta') ?? [];
        $native = PandocConverter::write($document, 'native');

        $t->same('Metadata Title', $meta['title'] ?? null);
        $t->same(['Ada', 'Grace'], $meta['author'] ?? null);
        $t->same(['reader', 'parity'], $meta['keywords'] ?? null);
        $t->same("First line.\nSecond line.\n", $meta['abstract'] ?? null);
        $t->same('paragraph', $meta['abstractBlocks'][0]->type ?? null);
        $t->same('First line. Second line.', $meta['abstractBlocks'][0]->attr('text') ?? null);
        $t->same('softbreak', $meta['abstractBlocks'][0]->children[1]->type ?? null);
        $t->same('heading', $document->children[0]->type ?? null);
        $t->same(1, $document->children[0]->attr('level'));
        $t->same('body', $document->children[0]->attr('id'));
        $t->same('paragraph', $document->children[1]->type ?? null);
        $t->same('Content.', $document->children[1]->attr('text'));
        $t->contains($nativeTokenStream($expectedNative), $nativeTokenStream($native));
    },
];
