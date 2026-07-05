<?php

declare(strict_types=1);

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;
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
    'renders upstream typed yaml metadata constructors in standalone native output' => static function (TestRunner $t) use ($nativeTokenStream): void {
        $fixtureRoot = dirname(__DIR__) . '/fixtures';
        $markdown = (string) file_get_contents($fixtureRoot . '/upstream-markdown-yaml-typed-metadata.md');

        $document = (new MarkdownReader())->read($markdown);
        $meta = $document->attr('meta') ?? [];
        $native = (new NativeWriter(['standalone' => true]))->write($document);
        $tokens = $nativeTokenStream($native);

        $t->same([], $meta['foo'] ?? null);
        $t->same('7', $meta['int'] ?? null);
        $t->same(true, $meta['bool'] ?? null);
        $t->same('', $meta['nothing'] ?? null);
        $t->contains($nativeTokenStream('( "foo" , MetaMap (fromList []) )'), $tokens);
        $t->contains($nativeTokenStream('( "int" , MetaInlines [ Str "7" ] )'), $tokens);
        $t->contains($nativeTokenStream('( "float" , MetaInlines [ Str "1.5" ] )'), $tokens);
        $t->contains($nativeTokenStream('( "scientific" , MetaInlines [ Str "3.7e-5" ] )'), $tokens);
        $t->contains($nativeTokenStream('( "bool" , MetaBool True )'), $tokens);
        $t->contains($nativeTokenStream('( "more" , MetaBool False )'), $tokens);
        $t->contains($nativeTokenStream('( "nothing" , MetaString "" )'), $tokens);
        $t->contains($nativeTokenStream('( "empty" , MetaList [] )'), $tokens);
        $t->contains($nativeTokenStream('( "nested" , MetaMap (fromList [ ( "bool" , MetaBool True ) , ( "empty" , MetaList [] ) , ( "float" , MetaInlines [ Str "2.5" ] ) , ( "int" , MetaInlines [ Str "8" ] ) , ( "more" , MetaBool False ) , ( "nothing" , MetaString "" ) , ( "scientific" , MetaInlines [ Str "3.7e-5" ] ) ]) )'), $tokens);
        $t->contains($nativeTokenStream('( "array" , MetaList [ MetaMap (fromList [ ( "foo" , MetaInlines [ Str "bar" ] ) ]) , MetaMap (fromList [ ( "bool" , MetaBool True ) ]) ] )'), $tokens);
    },
];
