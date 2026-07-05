<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static fn (string $name): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/' . $name
);

$plainText = static function (AstNode $node) use (&$plainText): string {
    if (in_array($node->type, ['text', 'code'], true)) {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

return [
    'maps selected upstream markdown_in_html_blocks fixture profile' =>
        static function (TestRunner $t) use ($fixture, $plainText): void {
            $document = (new MarkdownReader(['format' => 'markdown+markdown_in_html_blocks']))->read($fixture(
                'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-in-html-blocks-profile.md'
            ));
            $opening = $document->children[0] ?? new AstNode('missing');
            $heading = $document->children[1] ?? new AstNode('missing');
            $list = $document->children[2] ?? new AstNode('missing');
            $closing = $document->children[3] ?? new AstNode('missing');
            $firstItem = $list->children[0] ?? new AstNode('missing');
            $firstInline = $firstItem->children[0] ?? new AstNode('missing');

            $t->same(['raw_html', 'heading', 'bullet_list', 'raw_html'], array_map(
                static fn (AstNode $node): string => $node->type,
                $document->children
            ));
            $t->same('<section id="review" class="packet" data-source="fixture">', $opening->attr('html'));
            $t->same('Packet Title', $heading->attr('text'));
            $t->same('strong', $firstInline->type);
            $t->same('ready', $plainText($firstInline));
            $t->same('</section>', $closing->attr('html'));
        },

    'maps selected upstream markdown_attribute fixture profile' =>
        static function (TestRunner $t) use ($fixture, $plainText): void {
            $document = (new MarkdownReader(['format' => 'markdown_phpextra']))->read($fixture(
                'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-attribute-profile.md'
            ));
            $opening = $document->children[0] ?? new AstNode('missing');
            $heading = $document->children[1] ?? new AstNode('missing');
            $list = $document->children[2] ?? new AstNode('missing');
            $firstItem = $list->children[0] ?? new AstNode('missing');
            $firstInline = $firstItem->children[0] ?? new AstNode('missing');

            $t->same(['raw_html', 'heading', 'bullet_list', 'raw_html'], array_map(
                static fn (AstNode $node): string => $node->type,
                $document->children
            ));
            $t->same('<section id="review" class="packet" data-source="fixture">', $opening->attr('html'));
            $t->true(!str_contains((string) $opening->attr('html'), 'markdown='), 'Expected markdown attribute to be stripped from raw opening tag');
            $t->same('Packet Title', $heading->attr('text'));
            $t->same('strong', $firstInline->type);
            $t->same('ready', $plainText($firstInline));
        },

    'keeps disabled markdown_attribute html blocks raw' =>
        static function (TestRunner $t): void {
            $source = "<section markdown=\"0\" id=\"review\">\n\n## Raw title\n\n</section>";
            $document = (new MarkdownReader(['format' => 'markdown_phpextra']))->read($source);
            $block = $document->children[0] ?? new AstNode('missing');

            $t->same(1, count($document->children));
            $t->same('raw_html', $block->type);
            $t->contains('markdown="0"', (string) $block->attr('html'));
            $t->contains('## Raw title', (string) $block->attr('html'));
        },

    'keeps verbatim html blocks raw even with markdown attributes' =>
        static function (TestRunner $t): void {
            $source = "<pre markdown=\"1\">\n## Not a heading\n</pre>";
            $document = (new MarkdownReader(['format' => 'markdown+markdown_in_html_blocks+markdown_attribute']))->read($source);
            $block = $document->children[0] ?? new AstNode('missing');

            $t->same(1, count($document->children));
            $t->same('raw_html', $block->type);
            $t->contains('## Not a heading', (string) $block->attr('html'));
        },
];
