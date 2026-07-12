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
        $t->same('right', $cell->attr('align', 'default'));
        $t->same("content:'text-align:center'", $cell->attr('htmlAttributes')['style'] ?? '');
        $t->same('default', $list->children[0]->attr('style'));
    },
];
