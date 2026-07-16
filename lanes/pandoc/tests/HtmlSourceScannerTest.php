<?php

declare(strict_types=1);

use PortLibs\Pandoc\HtmlSourceScanner;

return [
    'finds an outer raw HTML boundary without treating script and comment text as tags' => static function (TestRunner $t): void {
        $source = implode("\n", [
            '<div>',
            '<script>const marker = "</div>";</script>',
            '<!-- </div> -->',
            '<p>safe</p>',
            '</div>',
            '<p>after</p>',
        ]);
        $bounds = HtmlSourceScanner::matchingElementBounds($source, 'div');

        $t->same('<div>', substr($source, $bounds['openStart'], $bounds['openEnd'] - $bounds['openStart'] + 1));
        $t->same('</div>', substr($source, $bounds['closeStart'], $bounds['closeEnd'] - $bounds['closeStart'] + 1));
        $t->same(4, substr_count(substr($source, 0, $bounds['closeEnd'] + 1), "\n"));
        $t->same(true, HtmlSourceScanner::rawTextContainsClosingTag($source, 'div'));
    },

    'finds an HTML document close outside raw text and nested same-name elements' => static function (TestRunner $t): void {
        $html = '<html><body><script>const marker = "</html>";</script><html><body>x</body></html></body></html>';
        $bounds = HtmlSourceScanner::matchingElementBounds($html, 'html');

        $t->same('<html>', substr($html, $bounds['openStart'], $bounds['openEnd'] - $bounds['openStart'] + 1));
        $t->same('</html>', substr($html, $bounds['closeStart'], $bounds['closeEnd'] - $bounds['closeStart'] + 1));
        $t->same(strlen($html) - strlen('</html>'), $bounds['closeStart']);
        $t->same(true, HtmlSourceScanner::rawTextContainsClosingTag($html, 'html'));
    },
    'does not let template-content tags close an outer raw HTML block' => static function (TestRunner $t): void {
        $source = "<div>\n<template></div></template>\n<p>inside</p>\n</div>\n<p>after</p>";
        $bounds = HtmlSourceScanner::matchingElementBounds($source, 'div');

        $t->same('</div>', substr($source, $bounds['closeStart'], $bounds['closeEnd'] - $bounds['closeStart'] + 1));
        $t->same(3, substr_count(substr($source, 0, $bounds['closeEnd'] + 1), "\n"));
    },
    'keeps DTD-internal-subset comments from exposing fake closing tags' => static function (TestRunner $t): void {
        $source = "<div>\n<!DOCTYPE html [<!-- ] > </div> -->]>\n<p>inside</p>\n</div>\n<p>after</p>";
        $bounds = HtmlSourceScanner::matchingElementBounds($source, 'div');

        $t->same("<div>\n<!DOCTYPE html [<!-- ] > </div> -->]>\n<p>inside</p>\n</div>", substr($source, 0, $bounds['closeEnd'] + 1));
        $t->same(3, substr_count(substr($source, 0, $bounds['closeEnd'] + 1), "\n"));
    },
    'finds a standalone declaration close beyond DTD comments and quoted markers' => static function (TestRunner $t): void {
        $source = "<!DOCTYPE html [\n<!-- > -->\n<!ENTITY sample \">\">\n]>\nAfter";
        $end = HtmlSourceScanner::declarationEndOffset($source);
        $lineEnd = HtmlSourceScanner::declarationEndLineInLines(explode("\n", $source), 0);

        $t->same("<!DOCTYPE html [\n<!-- > -->\n<!ENTITY sample \">\">\n]>", substr($source, 0, $end + 1));
        $t->same(3, $lineEnd);
        $t->same(null, HtmlSourceScanner::declarationEndOffset('<!DOCTYPE html [<!-- >'));
    },
    'collects source lines once through the closing tag and retains a same-line tail' => static function (TestRunner $t): void {
        $collected = HtmlSourceScanner::matchingElementBoundsInLines([
            '<div>',
            '<script>const marker = "</div>";</script>',
            '</div>tail',
            'after',
        ], 0, 'div');

        $t->same("<div>\n<script>const marker = \"</div>\";</script>\n</div>", $collected['source']);
        $t->same(2, $collected['end']);
        $t->same('tail', $collected['tail']);
        $t->same('</div>', substr($collected['source'], $collected['bounds']['closeStart'], 6));
    },
    'keeps split DTD comments inert while streaming source lines' => static function (TestRunner $t): void {
        $collected = HtmlSourceScanner::matchingElementBoundsInLines([
            '<div>',
            '<!DOCTYPE html [<!--',
            '] > </div>',
            '-->]>',
            '<p>inside</p>',
            '</div>tail',
        ], 0, 'div');

        $t->same("<div>\n<!DOCTYPE html [<!--\n] > </div>\n-->]>\n<p>inside</p>\n</div>", $collected['source']);
        $t->same(5, $collected['end']);
        $t->same('tail', $collected['tail']);
    },
    'stops source scanning immediately after plaintext begins' => static function (TestRunner $t): void {
        $source = '<plaintext><div>x</div>';
        $streamed = HtmlSourceScanner::matchingElementBoundsInLines([
            '<plaintext>',
            '<div>x</div>',
        ], 0, 'div');

        $t->same(null, HtmlSourceScanner::matchingElementBounds($source, 'div'));
        $t->same(null, $streamed);
    },
    'indexes every matched element boundary in one source pass' => static function (TestRunner $t): void {
        $source = "<article>outer<article>inner</article></article>\n<article>next</article>";
        $lines = [
            '<article>',
            '',
            'inside',
            '</article>',
            '<article>next</article>',
        ];
        $matches = HtmlSourceScanner::matchingElementBoundsAll($source, 'article');
        $byLine = HtmlSourceScanner::matchingElementBoundsInLinesByOpeningLine($lines, 0, 'article');

        $t->same(3, count($matches));
        $t->same('<article>outer<article>inner</article></article>', substr($source, $matches[0]['openStart'], $matches[0]['closeEnd'] - $matches[0]['openStart'] + 1));
        $t->same('<article>inner</article>', substr($source, $matches[1]['openStart'], $matches[1]['closeEnd'] - $matches[1]['openStart'] + 1));
        $t->same(false, array_key_exists('source', $byLine[0]));
        $t->same(
            "<article>\n\ninside\n</article>",
            HtmlSourceScanner::sourcePrefixInLines($lines, 0, $byLine[0]['end'], $byLine[0]['bounds']['closeEnd'])
        );
        $t->same(
            '<article>next</article>',
            HtmlSourceScanner::sourcePrefixInLines($lines, 4, $byLine[4]['end'], $byLine[4]['bounds']['closeEnd'])
        );
    },
    'indexes deeply nested boundaries without retaining one source copy per nesting level' => static function (TestRunner $t): void {
        $depth = 2_048;
        $lines = array_merge(
            array_fill(0, $depth, '<div>'),
            ['payload'],
            array_fill(0, $depth, '</div>')
        );
        $byLine = HtmlSourceScanner::matchingElementBoundsInLinesByOpeningLine($lines, 0, 'div');

        $t->same($depth, count($byLine));
        $t->same(false, array_key_exists('source', $byLine[0]));
        $t->same(false, array_key_exists('source', $byLine[$depth - 1]));
        $t->same(
            $depth * 2,
            $byLine[0]['end']
        );
        $t->same(
            "<div>\npayload\n</div>",
            HtmlSourceScanner::sourcePrefixInLines(
                $lines,
                $depth - 1,
                $byLine[$depth - 1]['end'],
                $byLine[$depth - 1]['bounds']['closeEnd']
            )
        );
    },
];
