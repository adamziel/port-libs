<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-bare-uri-git-file-schemes.md'
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
    'maps upstream markdown bare URI git and file schemes' =>
        static function (TestRunner $t) use ($fixture, $collectLinks): void {
            $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris']))->read($fixture());
            $links = $collectLinks($document);

            $t->same(2, count($document->children));
            $t->same(2, count($links));
            $t->same('git://github.com/foo/bar.git', $links[0]->attr('url'));
            $t->same('git://github.com/foo/bar.git', $links[0]->children[0]->attr('text'));
            $t->same(['uri'], $links[0]->attr('classes'));
            $t->same('file:///Users/joe/joe.txt', $links[1]->attr('url'));
            $t->same('file:///Users/joe/joe.txt', $links[1]->children[0]->attr('text'));
            $t->same(['uri'], $links[1]->attr('classes'));
        },

    'serializes upstream bare URI git and file schemes through native handoff' =>
        static function (TestRunner $t) use ($fixture): void {
            $native = (new NativeWriter())->write(
                (new MarkdownReader(['format' => 'markdown+autolink_bare_uris']))->read($fixture())
            );

            $t->contains('Link ( "" , [ "uri" ] , [  ] ) [ Str "git://github.com/foo/bar.git" ] ( "git://github.com/foo/bar.git" , "" )', $native);
            $t->contains('Link ( "" , [ "uri" ] , [  ] ) [ Str "file:///Users/joe/joe.txt" ] ( "file:///Users/joe/joe.txt" , "" )', $native);
        },

    'records upstream bare URI git and file scheme fixture literals' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = preg_split('/\R\R/', trim($fixture())) ?: [];

            $t->same(2, count($cases));
            $t->same('git://github.com/foo/bar.git,', $cases[0]);
            $t->same('file:///Users/joe/joe.txt, and', $cases[1]);
        },
];
