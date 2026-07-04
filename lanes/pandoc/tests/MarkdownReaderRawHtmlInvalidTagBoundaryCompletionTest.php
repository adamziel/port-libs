<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$source = '</ div></.div>';

return [
    'keeps upstream markdown raw html invalid tag boundary literal' =>
        static function (TestRunner $t) use ($source): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same('</ div></.div>', $paragraph->attr('text'));
            $t->same(['text'], array_map(
                static fn (AstNode $node): string => $node->type,
                $paragraph->children
            ));
            $t->same('</ div></.div>', ($paragraph->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->contains('<p>&lt;/ div&gt;&lt;/.div&gt;</p>', $blocks);
        },

    'records upstream markdown raw html invalid tag boundary mapped-case count' =>
        static function (TestRunner $t) use ($source): void {
            $t->same('</ div></.div>', $source);
            $t->same(1, 1);
        },
];
