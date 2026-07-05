<?php

declare(strict_types=1);

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocConverter;

$nativeTokenStream = static function (string $native): string {
    $native = (string) preg_replace('/\[\s*\]/', '[]', $native);

    return (string) preg_replace('/\s+/', ' ', trim($native));
};

return [
    'imports upstream command multiple metadata blocks with later duplicate wins' => static function (TestRunner $t) use ($nativeTokenStream): void {
        $fixtureRoot = dirname(__DIR__) . '/fixtures';
        $markdown = (string) file_get_contents($fixtureRoot . '/upstream-command-multiple-metadata-blocks.md');
        $expectedNative = (string) file_get_contents($fixtureRoot . '/upstream-command-multiple-metadata-blocks.native');

        $document = (new MarkdownReader())->read($markdown);
        $meta = $document->attr('meta') ?? [];
        $native = PandocConverter::write($document, 'native');
        $standaloneNative = (new NativeWriter(['standalone' => true]))->write($document);

        $t->same([], $document->children);
        $t->same('bim', $meta['foo'] ?? null);
        $t->contains($nativeTokenStream($expectedNative), $nativeTokenStream($native));
        $t->contains(
            $nativeTokenStream('( "foo" , MetaInlines [ Str "bim" ] )'),
            $nativeTokenStream($standaloneNative)
        );
        $t->true(!str_contains($standaloneNative, 'Str "bar"'));
    },
];
