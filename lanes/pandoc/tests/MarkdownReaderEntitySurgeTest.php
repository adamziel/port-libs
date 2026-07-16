<?php

declare(strict_types=1);

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$numericReferenceCases = [
    '&#35;' => '#',
    '&#1234;' => "\u{04D2}",
    '&#992;' => "\u{03E0}",
    '&#0;' => "\u{FFFD}",
    '&#0000000;' => "\u{FFFD}",
    '&#x22;' => '"',
    '&#XD06;' => "\u{0D06}",
    '&#xcab;' => "\u{0CAB}",
    '&#x0;' => "\u{FFFD}",
    '&#x000000;' => "\u{FFFD}",
    '&#xD800;' => "\u{FFFD}",
    '&#xDFFF;' => "\u{FFFD}",
    '&#55296;' => "\u{FFFD}",
    '&#57343;' => "\u{FFFD}",
    '&#x110000;' => "\u{FFFD}",
    '&#1114112;' => "\u{FFFD}",
    '&#x1F600;' => "\u{1F600}",
    '&#128512;' => "\u{1F600}",
    '&#169;' => "\u{00A9}",
    '&#x2122;' => "\u{2122}",
];

$namedReferenceCases = [
    '&amp;' => '&',
    '&lt;' => '<',
    '&gt;' => '>',
    '&quot;' => '"',
    '&apos;' => "'",
    '&nbsp;' => "\u{00A0}",
    '&copy;' => "\u{00A9}",
    '&reg;' => "\u{00AE}",
    '&trade;' => "\u{2122}",
    '&ndash;' => "\u{2013}",
    '&mdash;' => "\u{2014}",
    '&hellip;' => "\u{2026}",
    '&lsquo;' => "\u{2018}",
    '&rsquo;' => "\u{2019}",
    '&ldquo;' => "\u{201C}",
    '&rdquo;' => "\u{201D}",
    '&laquo;' => "\u{00AB}",
    '&raquo;' => "\u{00BB}",
    '&sect;' => "\u{00A7}",
    '&para;' => "\u{00B6}",
    '&middot;' => "\u{00B7}",
    '&bull;' => "\u{2022}",
    '&dagger;' => "\u{2020}",
    '&Dagger;' => "\u{2021}",
    '&euro;' => "\u{20AC}",
    '&pound;' => "\u{00A3}",
    '&yen;' => "\u{00A5}",
    '&cent;' => "\u{00A2}",
    '&plusmn;' => "\u{00B1}",
    '&times;' => "\u{00D7}",
    '&divide;' => "\u{00F7}",
    '&micro;' => "\u{00B5}",
    '&alpha;' => "\u{03B1}",
    '&beta;' => "\u{03B2}",
    '&gamma;' => "\u{03B3}",
    '&Delta;' => "\u{0394}",
    '&Omega;' => "\u{03A9}",
    '&sum;' => "\u{2211}",
    '&le;' => "\u{2264}",
];

return [
    'maps commonmark numeric character references including invalid code points' => static function (TestRunner $t) use ($numericReferenceCases): void {
        $reader = new MarkdownReader();

        foreach ($numericReferenceCases as $reference => $expected) {
            $document = $reader->read('Value: ' . $reference);
            $t->same('Value: ' . $expected, $document->children[0]->children[0]->attr('text'), 'numeric reference ' . $reference);
        }
    },
    'maps commonmark named character references through paragraph text' => static function (TestRunner $t) use ($namedReferenceCases): void {
        $reader = new MarkdownReader();

        foreach ($namedReferenceCases as $reference => $expected) {
            $document = $reader->read('Value: ' . $reference);
            $t->same('Value: ' . $expected, $document->children[0]->children[0]->attr('text'), 'named reference ' . $reference);
        }
    },
    'maps commonmark entity references in links titles and guarded literal contexts' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n\n", [
            'Code span `&amp; &#35; &#0;` keeps literal references.',
            '[entity link](/search?q=&amp;tag=&#35; "&copy; &#0;")',
            '[ref]',
            '[ref]: /review?flag=&#x1F600; "title &trade; &#x0;"',
            'Numeric ampersand &#38;copy; and named ampersand &amp;copy; decode once.',
            'No semicolon &copy and overlong numeric &#12345678; stay literal.',
        ]));
        $blocks = (new WordPressBlockWriter())->write($document);

        $code = $document->children[0]->children[1];
        $inlineLink = $document->children[1]->children[0];
        $referenceLink = $document->children[2]->children[0];
        $ampersand = $document->children[3]->children[0];
        $literal = $document->children[4]->children[0];

        $t->same('&amp; &#35; &#0;', $code->attr('text'));
        $t->same('/search?q=&tag=#', $inlineLink->attr('url'));
        $t->same("\u{00A9} \u{FFFD}", $inlineLink->attr('title'));
        $t->same('/review?flag=' . "\u{1F600}", $referenceLink->attr('url'));
        $t->same('title ' . "\u{2122} \u{FFFD}", $referenceLink->attr('title'));
        $t->same('Numeric ampersand &copy; and named ampersand &copy; decode once.', $ampersand->attr('text'));
        $t->same('No semicolon &copy and overlong numeric &#12345678; stay literal.', $literal->attr('text'));
        $t->contains('<code>&amp;amp; &amp;#35; &amp;#0;</code>', $blocks);
        $t->contains('<a href="/search?q=&amp;tag=#" title="' . "\u{00A9} \u{FFFD}" . '">entity link</a>', $blocks);
    },
];
