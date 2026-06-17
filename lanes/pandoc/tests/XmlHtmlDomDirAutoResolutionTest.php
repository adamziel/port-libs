<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html dir auto first strong direction for reviewer handoff' => static function (TestRunner $t): void {
        $rtlWord = "\u{0645}\u{0631}\u{062D}\u{0628}\u{0627}";
        $rtlFirst = "\u{0645}";
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="auto-ar" dir="auto">123 ' . $rtlWord . ' <span id="auto-inherited">child</span></article>'
                . '<section id="auto-en" dir="auto">?! Review text</section>'
                . '<p id="neutral" dir="auto">123 -- !!</p>'
                . '<aside id="bad-dir" dir="sideways">Fallback</aside>',
            'dir auto first strong review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/dir-auto-first-strong-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $inherited = $article['children'][1];
        $section = $summary[1];
        $neutral = $summary[2];
        $bad = $summary[3];

        $t->same(4, count($summary));

        $t->same('article', $article['name']);
        $t->same('auto', $article['dirRaw']);
        $t->same('auto', $article['direction']);
        $t->same('auto', $article['effectiveDirection']);
        $t->same(false, $article['directionInherited']);
        $t->same('self-dir', $article['directionSource']);
        $t->same('html-dir-auto-first-strong-review', $article['directionAutoReviewPolicy']);
        $t->same('first-strong-rtl', $article['directionAutoState']);
        $t->same('rtl', $article['directionAutoResolvedDirection']);
        $t->same('rtl', $article['directionAutoFirstStrongDirection']);
        $t->same($rtlFirst, $article['directionAutoFirstStrongCharacter']);
        $t->same(4, $article['directionAutoFirstStrongIndex']);
        $t->same(15, $article['directionAutoTextLength']);
        $t->same(false, $article['directionAutoInherited']);

        $t->same('span', $inherited['name']);
        $t->same('auto', $inherited['effectiveDirection']);
        $t->same(true, $inherited['directionInherited']);
        $t->same('ancestor-dir', $inherited['directionSource']);
        $t->same('auto-ar', $inherited['directionSourceElementId']);
        $t->same('first-strong-rtl', $inherited['directionAutoState']);
        $t->same('rtl', $inherited['directionAutoResolvedDirection']);
        $t->same(true, $inherited['directionAutoInherited']);

        $t->same('section', $section['name']);
        $t->same('first-strong-ltr', $section['directionAutoState']);
        $t->same('ltr', $section['directionAutoResolvedDirection']);
        $t->same('ltr', $section['directionAutoFirstStrongDirection']);
        $t->same('R', $section['directionAutoFirstStrongCharacter']);
        $t->same(3, $section['directionAutoFirstStrongIndex']);
        $t->same(14, $section['directionAutoTextLength']);

        $t->same('p', $neutral['name']);
        $t->same('no-strong-character', $neutral['directionAutoState']);
        $t->same('ltr', $neutral['directionAutoResolvedDirection']);
        $t->same(null, $neutral['directionAutoFirstStrongDirection']);
        $t->same(null, $neutral['directionAutoFirstStrongCharacter']);
        $t->same(null, $neutral['directionAutoFirstStrongIndex']);
        $t->same(9, $neutral['directionAutoTextLength']);

        $t->same('aside', $bad['name']);
        $t->same('sideways', $bad['dirRaw']);
        $t->same(null, $bad['direction']);
        $t->true(!array_key_exists('directionAutoReviewPolicy', $bad));
        $t->true(!array_key_exists('effectiveDirection', $bad));

        $t->contains('<article dir="auto" id="auto-ar">', $html);
        $t->contains('<section dir="auto" id="auto-en">?! Review text</section>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/dir-auto-first-strong-review.html', $document->children[0]->attr('part'));
    },
];
