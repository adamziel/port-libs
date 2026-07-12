<?php

declare(strict_types=1);

use PortLibs\Pandoc\CssDeclarationScanner;
use PortLibs\Pandoc\HtmlReader;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\MarkdownReader;

return [
    'keeps raw HTML block delimiters out of script and comment text' => static function (TestRunner $t): void {
        $source = implode("\n", [
            '<details>',
            '<script>const marker = "</details>";</script>',
            '<!-- </details> -->',
            '<p>inside</p>',
            '</details>',
            '',
            'after',
        ]);
        $html = (new HtmlWriter())->write((new MarkdownReader(['format' => 'commonmark']))->read($source));

        $t->contains('<script>const marker = "</details>";</script>', $html);
        $t->contains('<!-- </details> -->', $html);
        $t->contains('<p>inside</p>', $html);
        $t->contains('</details>', $html);
        $t->contains('<p>after</p>', $html);
    },

    'preserves a complete html document when a raw-text string imitates its closing tag' => static function (TestRunner $t): void {
        $source = implode("\n", [
            '<html>',
            '<body>',
            '<script>const marker = "</html>";</script>',
            '<p id="after">after</p>',
            '</body>',
            '</html>',
        ]);
        $html = (new HtmlWriter())->write((new MarkdownReader(['format' => 'commonmark']))->read($source));

        $t->same($source, $html);
    },

    'keeps an outer HTML block open across template content' => static function (TestRunner $t): void {
        $source = "<div>\n<template></div></template>\n<p>inside</p>\n</div>\n\nafter";
        $html = (new HtmlWriter())->write((new MarkdownReader(['format' => 'commonmark']))->read($source));

        $t->contains('<template></div></template>', $html);
        $t->contains("<p>inside</p>\n</div>", $html);
        $t->contains('<p>after</p>', $html);
    },

    'tokenizes CSS declarations before extracting presentation hints' => static function (TestRunner $t): void {
        $declarations = CssDeclarationScanner::declarations(
            "content:'text-align:center; caption-side:bottom'; background:url(foo\\;bar); /* declaration comment */ text-align/**/: /**/right"
        );
        $table = (new HtmlReader())->read(
            '<table><tr><td style="content:\'text-align:center\'; /* comment */ text-align/**/:right">x</td></tr></table>'
        );
        $cell = $table->children[0]->children[1]->children[0]->children[0];
        $list = (new HtmlReader())->read(
            '<ol style="content:foo\\;list-style-type:upper-roman"><li>x</li></ol>'
        );

        $t->same(['content', 'background', 'text-align'], array_column($declarations, 'name'));
        $t->same('right', CssDeclarationScanner::firstValue("content:'text-align:center'; /* comment */ text-align/**/: /**/right", 'text-align'));
        $t->same('left', CssDeclarationScanner::firstValue('text-align:left; text-align:right', 'text-align'));
        $t->same('right', $cell->attr('align', 'default'));
        $t->same("content:'text-align:center'", $cell->attr('htmlAttributes')['style'] ?? '');
        $t->same('default', $list->children[0]->attr('style'));
    },

    'resolves inline presentation hints by CSS cascade rather than last text match' => static function (TestRunner $t): void {
        $validAlignment = static fn (string $value): bool => preg_match('/^(?:left|right|center)\s*$/i', $value) === 1;
        $validWidth = static fn (string $value): bool => preg_match('/^[0-9]+(?:\.[0-9]+)?\s*%\s*$/', $value) === 1;
        $isAutoWidth = static fn (string $value): bool => strcasecmp(trim($value), 'auto') === 0;
        $invalidLater = (new HtmlReader())->read(
            '<table><tr><td style="text-align:left; text-align:bogus">x</td></tr></table>'
        );
        $importantEarlier = (new HtmlReader())->read(
            '<table><tr><td style="text-align:left !important; text-align:right">x</td></tr></table>'
        );
        $initialLater = (new HtmlReader())->read(
            '<table><tr><td style="text-align:right; text-align:initial">x</td></tr></table>'
        );
        $normalInitialAfterImportant = (new HtmlReader())->read(
            '<table><tr><td style="text-align:right !important; text-align:initial">x</td></tr></table>'
        );
        $importantInitial = (new HtmlReader())->read(
            '<table><tr><td style="text-align:right; text-align:initial !important">x</td></tr></table>'
        );
        $autoWidth = (new HtmlReader())->read(
            '<table><col style="width:50%; width:auto !important"><tr><td>x</td></tr></table>'
        );
        $normalAutoAfterImportantWidth = (new HtmlReader())->read(
            '<table><col style="width:50% !important; width:auto"><tr><td>x</td></tr></table>'
        );
        $list = (new HtmlReader())->read(
            '<ol style="list-style-type:decimal; list-style-type:upper-roman"><li>x</li></ol>'
        );
        $listWithInvalidLaterValue = (new HtmlReader())->read(
            '<ol style="list-style-type:decimal; list-style-type:upper-roman invalid"><li>x</li></ol>'
        );

        $t->same('left', CssDeclarationScanner::lastValidValue('text-align:left; text-align:bogus', 'text-align', $validAlignment));
        $t->same('left', CssDeclarationScanner::lastValidValue('text-align:left !important; text-align:right', 'text-align', $validAlignment));
        $t->same(null, CssDeclarationScanner::lastValidValue('text-align:right; text-align:initial', 'text-align', $validAlignment));
        $t->same('right', CssDeclarationScanner::lastValidValue('text-align:right !important; text-align:initial', 'text-align', $validAlignment));
        $t->same(null, CssDeclarationScanner::lastValidValue('text-align:right; text-align:initial !important', 'text-align', $validAlignment));
        $t->same(null, CssDeclarationScanner::lastValidValue('width:50%; width:auto', 'width', $validWidth, $isAutoWidth));
        $t->same('50%', CssDeclarationScanner::lastValidValue('width:50% !important; width:auto', 'width', $validWidth, $isAutoWidth));
        $t->same(null, CssDeclarationScanner::lastValidValue('width:50%; width:auto !important', 'width', $validWidth, $isAutoWidth));
        $t->same('left', $invalidLater->children[0]->children[1]->children[0]->children[0]->attr('align', 'default'));
        $t->same('left', $importantEarlier->children[0]->children[1]->children[0]->children[0]->attr('align', 'default'));
        $t->same('default', $initialLater->children[0]->children[1]->children[0]->children[0]->attr('align', 'default'));
        $t->same('right', $normalInitialAfterImportant->children[0]->children[1]->children[0]->children[0]->attr('align', 'default'));
        $t->same('default', $importantInitial->children[0]->children[1]->children[0]->children[0]->attr('align', 'default'));
        $t->same([1], $autoWidth->children[0]->attr('widths'));
        $t->same([0.5], $normalAutoAfterImportantWidth->children[0]->attr('widths'));
        $t->same('upper_roman', $list->children[0]->attr('style'));
        $t->same('decimal', $listWithInvalidLaterValue->children[0]->attr('style'));
    },

    'handles a large non-important CSS value without repeatedly rescanning its suffix' => static function (TestRunner $t): void {
        $bangCount = 1_000_000;
        $declarations = CssDeclarationScanner::declarations('width:' . str_repeat('!', $bangCount));

        $t->same(1, count($declarations));
        $t->same($bangCount, strlen($declarations[0]['value']));
        $t->same(false, $declarations[0]['important']);
    },

    'preserves standalone doctypes and source after same-line HTML block closes' => static function (TestRunner $t): void {
        $doctype = (new HtmlWriter())->write((new MarkdownReader(['format' => 'commonmark']))->read("<!DOCTYPE html>\nafter"));
        $dtdDoctype = (new HtmlWriter())->write(
            (new MarkdownReader(['format' => 'commonmark']))->read("<!DOCTYPE html [\n<!-- >\n-->\n]>\n\nAfter")
        );
        $doctypeDocument = (new MarkdownReader(['format' => 'commonmark']))->read(
            "<!doctype html>\n<html><body><table><tr><td>x</td></tr></table></body></html>"
        );
        $divTail = (new HtmlWriter())->write((new MarkdownReader(['format' => 'commonmark']))->read('<div>x</div>tail'));
        $documentTail = (new HtmlWriter())->write(
            (new MarkdownReader(['format' => 'commonmark']))->read('<html><body><p>inside</p></body></html>tail')
        );
        $paragraphTail = (new HtmlWriter())->write((new MarkdownReader(['format' => 'commonmark']))->read('<p>x</p>tail'));
        $headingTail = (new HtmlWriter())->write((new MarkdownReader(['format' => 'commonmark']))->read('<h2>x</h2>tail'));
        $preTail = (new HtmlWriter())->write((new MarkdownReader(['format' => 'commonmark']))->read('<pre><code>x</code></pre>tail'));
        $inlineTail = (new HtmlWriter())->write((new MarkdownReader(['format' => 'commonmark']))->read('<del>x</del> tail'));
        $chainedTail = (new HtmlWriter())->write((new MarkdownReader(['format' => 'commonmark']))->read('<div>x</div><div>y</div>tail'));
        $mainTail = (new HtmlWriter())->write(
            (new MarkdownReader(['format' => 'commonmark', 'htmlNativeDivs' => true]))->read('<main><p>x</p></main>tail')
        );
        $docBookTail = (new HtmlWriter())->write(
            (new MarkdownReader(['format' => 'commonmark']))->read(
                '<informaltable><tgroup cols="1"><tbody><row><entry>x</entry></row></tbody></tgroup></informaltable>tail'
            )
        );

        $t->same("<!DOCTYPE html>\n<p>after</p>", $doctype);
        $t->same("<!DOCTYPE html [\n<!-- >\n-->\n]>\n<p>After</p>", $dtdDoctype);
        $t->same(1, count($doctypeDocument->children));
        $t->same('table', $doctypeDocument->children[0]->type);
        $t->contains("<div>\nx\n</div>", $divTail);
        $t->contains('<p>tail</p>', $divTail);
        $t->contains('<p>inside</p>', $documentTail);
        $t->contains('<p>tail</p>', $documentTail);
        $t->contains("<p>x</p>\n<p>tail</p>", $paragraphTail);
        $t->contains('<h2 id="x">x</h2>', $headingTail);
        $t->contains('<p>tail</p>', $headingTail);
        $t->contains("<pre><code>x</code></pre>\n<p>tail</p>", $preTail);
        $t->same('<p><del>x</del> tail</p>', $inlineTail);
        $t->contains("<div>\nx\n</div>\n<div>\ny\n</div>\n<p>tail</p>", $chainedTail);
        $t->same("<p>x</p>\n<p>tail</p>", $mainTail);
        $t->contains("<table>\n<tbody>\n<tr><td>x</td></tr>\n</tbody>\n</table>\n<p>tail</p>", $docBookTail);
    },

    'reads many adjacent balanced HTML blocks without rebuilding the remaining source' => static function (TestRunner $t): void {
        $source = str_repeat("<div>x</div>\n", 1024);
        $document = (new MarkdownReader(['format' => 'commonmark']))->read($source);

        $t->same(1024, count($document->children));
        $t->same('div', $document->children[0]->type);
        $t->same('div', $document->children[1023]->type);
    },

    'keeps malformed HTML starts bounded by their first blank line' => static function (TestRunner $t): void {
        $repetitions = 256;
        foreach (['p', 'article', 'section', 'blockquote', 'figure', 'ol', 'button', 'del', 'ins', 'html'] as $tag) {
            $source = str_repeat('<' . $tag . ">\n\n", $repetitions) . 'after';
            $document = (new MarkdownReader(['format' => 'commonmark']))->read($source);

            $t->same($repetitions + 1, count($document->children), $tag);
        }

        $closedArticle = (new HtmlWriter())->write(
            (new MarkdownReader(['format' => 'commonmark']))->read("<article>\n\ninside\n</article>\n\nafter")
        );

        $t->contains("<article>\n\ninside\n</article>", $closedArticle);
        $t->contains('<p>after</p>', $closedArticle);
    },

    'keeps boundary caches scoped to the main source lines' => static function (TestRunner $t): void {
        $writer = new HtmlWriter();
        $referenceBefore = $writer->write((new MarkdownReader(['format' => 'commonmark']))->read("[r]: /u\n<div>hello</div>"));
        $referenceBetween = $writer->write((new MarkdownReader(['format' => 'commonmark']))->read("<div>a</div>\n[r]: /u\n<div>b</div>"));

        $t->same("<div>\nhello\n</div>", $referenceBefore);
        $t->same("<div>\na\n</div>\n<div>\nb\n</div>", $referenceBetween);
    },

    'normalizes full-document doctypes through declaration-aware boundaries' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $writer = new HtmlWriter();
        $sources = [
            "<!DOCTYPE html [<!-- ]> -->]>\n<html><body><p>comment subset</p></body></html>",
            "<!DOCTYPE html [<!ENTITY sample \"]>\">]>\n<html><body><p>quoted subset</p></body></html>",
        ];

        foreach ($sources as $source) {
            $html = $writer->write($reader->readHtml($source));

            $t->true(!str_contains($html, '--&gt;]&gt;'));
            $t->true(!str_contains($html, '&quot;]&gt;'));
        }

        $t->same('<p>comment subset</p>', $writer->write($reader->readHtml($sources[0])));
        $t->same('<p>quoted subset</p>', $writer->write($reader->readHtml($sources[1])));
    },
];
