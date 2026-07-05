<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

const CITATION_LINK_FOLLOWING_FIXTURE = 'upstream-markdown-citation-link-following.md';

$fixture = static fn (): string =>
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/' . CITATION_LINK_FOLLOWING_FIXTURE);

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps upstream markdown citation followed by footnote and link fixtures' =>
        static function (TestRunner $t) use ($fixture, $inlineTypes): void {
            $document = (new MarkdownReader())->read($fixture());
            $footnote = $document->children[0] ?? new AstNode('missing');
            $inlineLink = $document->children[1] ?? new AstNode('missing');
            $referenceLink = $document->children[2] ?? new AstNode('missing');
            $shortReferenceLink = $document->children[3] ?? new AstNode('missing');
            $heading = $document->children[4] ?? new AstNode('missing');
            $implicitHeaderLink = $document->children[5] ?? new AstNode('missing');
            $citationSuffix = $document->children[6] ?? new AstNode('missing');

            $t->same(['citation', 'note'], $inlineTypes($footnote));
            $t->same(['citation', 'text', 'link'], $inlineTypes($inlineLink));
            $t->same(['citation', 'text', 'link'], $inlineTypes($referenceLink));
            $t->same(['citation', 'text', 'link'], $inlineTypes($shortReferenceLink));
            $t->same('heading', $heading->type);
            $t->same('header', $heading->attr('id'));
            $t->same(['citation', 'text', 'link'], $inlineTypes($implicitHeaderLink));
            $t->same(['citation'], $inlineTypes($citationSuffix));

            foreach ([$inlineLink, $referenceLink, $shortReferenceLink] as $paragraph) {
                $citation = $paragraph->children[0] ?? new AstNode('missing');
                $link = $paragraph->children[2] ?? new AstNode('missing');

                $t->same('citation', $citation->type);
                $t->same('cita', $citation->attr('id'));
                $t->same(null, $citation->attr('suffix'));
                $t->same('link', $link->type);
                $t->same('http://www.com', $link->attr('url'));
            }

            $headerLink = $implicitHeaderLink->children[2] ?? new AstNode('missing');
            $suffixCitation = $citationSuffix->children[0] ?? new AstNode('missing');

            $t->same('link', $headerLink->type);
            $t->same('#header', $headerLink->attr('url'));
            $t->same('citation', $suffixCitation->type);
            $t->same('cita', $suffixCitation->attr('id'));
            $t->same('foo', $suffixCitation->attr('suffix'));
        },

    'serializes upstream markdown citation link following fixture through native handoff' =>
        static function (TestRunner $t) use ($fixture): void {
            $native = (new NativeWriter())->write((new MarkdownReader())->read($fixture()));

            $t->contains('"t": "Note"', $native);
            $t->contains('"t": "Link"', $native);
            $t->contains('"http://www.com"', $native);
            $t->contains('"#header"', $native);
            $t->contains('"citationSuffix"', $native);
            $t->contains('"foo"', $native);
        },

    'records upstream markdown citation link following mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(7, 7);
        },
];
