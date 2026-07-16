<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-link-nested-inline-boundary.md'
);

$collectLinks = static function (AstNode $node) use (&$collectLinks): array {
    $links = [];
    if ($node->type === 'link') {
        $links[] = $node;
    }

    foreach ($node->children as $child) {
        array_push($links, ...$collectLinks($child));
    }

    return $links;
};

return [
    'maps upstream markdown nested inline link boundary fixture' =>
        static function (TestRunner $t) use ($fixture, $collectLinks): void {
            $document = (new MarkdownReader())->read($fixture());
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $links = $collectLinks($document);
            $link = $links[0] ?? new AstNode('missing');

            $t->same('paragraph', $paragraph->type);
            $t->same(1, count($links));
            $t->same('url', $link->attr('url'));
            $t->same('[a](url2)', ($link->children[0] ?? new AstNode('missing'))->attr('text'));

            $native = (new NativeWriter())->write($document);
            $t->contains('[ Str "[a](url2)" ] ( "url" , "" )', $native);
        },
];
