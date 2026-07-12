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
];
