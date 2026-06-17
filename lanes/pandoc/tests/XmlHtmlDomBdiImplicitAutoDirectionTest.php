<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes bdi missing dir as implicit auto direction for reviewer handoff' => static function (TestRunner $t): void {
        $rtlWord = "\u{05E9}\u{05DC}\u{05D5}\u{05DD}";
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<div id="root" dir="rtl">'
                . '<bdi id="auto-ltr">123 Review</bdi>'
                . '<bdi id="auto-rtl">123 ' . $rtlWord . '</bdi>'
                . '<span id="plain">123 Review</span>'
                . '<bdi id="explicit" dir="ltr">' . $rtlWord . '</bdi>'
                . '</div>',
            'bdi implicit auto direction fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/bdi-implicit-auto-direction.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $root = $summary[0];
        $autoLtr = $root['children'][0];
        $autoRtl = $root['children'][1];
        $plain = $root['children'][2];
        $explicit = $root['children'][3];

        $t->same('div', $root['name']);
        $t->same('rtl', $root['effectiveDirection']);
        $t->same(4, count($root['children']));

        $t->same('bdi', $autoLtr['name']);
        $t->true(!array_key_exists('dirRaw', $autoLtr));
        $t->true(!array_key_exists('direction', $autoLtr));
        $t->same('auto', $autoLtr['effectiveDirectionRaw']);
        $t->same('auto', $autoLtr['effectiveDirection']);
        $t->same('ltr', $autoLtr['effectiveDirectionResolved']);
        $t->same(false, $autoLtr['directionInherited']);
        $t->same('implicit-bdi-dir-auto', $autoLtr['directionSource']);
        $t->same('auto-ltr', $autoLtr['directionSourceElementId']);
        $t->same(true, $autoLtr['directionImplicitDefault']);
        $t->same('bdi', $autoLtr['directionImplicitDefaultElement']);
        $t->same('first-strong-ltr', $autoLtr['directionAutoState']);
        $t->same('ltr', $autoLtr['directionAutoResolvedDirection']);
        $t->same('R', $autoLtr['directionAutoFirstStrongCharacter']);
        $t->same(4, $autoLtr['directionAutoFirstStrongIndex']);
        $t->same(false, $autoLtr['directionAutoInherited']);
        $t->same('ltr', $autoLtr['dirAutoResolvedDirection']);
        $t->same(false, $autoLtr['dirAutoInherited']);
        $t->same('bidirectional-isolate', $autoLtr['textSemantic']);
        $t->same('auto', $autoLtr['textDirection']);
        $t->same(true, $autoLtr['textDirectionImplicitDefault']);

        $t->same('bdi', $autoRtl['name']);
        $t->same('auto', $autoRtl['effectiveDirection']);
        $t->same('rtl', $autoRtl['effectiveDirectionResolved']);
        $t->same('implicit-bdi-dir-auto', $autoRtl['directionSource']);
        $t->same('first-strong-rtl', $autoRtl['directionAutoState']);
        $t->same('rtl', $autoRtl['directionAutoResolvedDirection']);
        $t->same("\u{05E9}", $autoRtl['directionAutoFirstStrongCharacter']);
        $t->same(4, $autoRtl['directionAutoFirstStrongIndex']);
        $t->same('rtl', $autoRtl['dirAutoResolvedDirection']);
        $t->same('R', $autoRtl['dirAutoFirstStrongBidiClass']);
        $t->same('auto', $autoRtl['textDirection']);

        $t->same('span', $plain['name']);
        $t->same('rtl', $plain['effectiveDirection']);
        $t->same('rtl', $plain['effectiveDirectionResolved']);
        $t->same(true, $plain['directionInherited']);
        $t->same('ancestor-dir', $plain['directionSource']);
        $t->same('root', $plain['directionSourceElementId']);

        $t->same('bdi', $explicit['name']);
        $t->same('ltr', $explicit['dirRaw']);
        $t->same('ltr', $explicit['effectiveDirection']);
        $t->same('ltr', $explicit['effectiveDirectionResolved']);
        $t->same(false, $explicit['directionInherited']);
        $t->same('self-dir', $explicit['directionSource']);
        $t->true(!array_key_exists('directionImplicitDefault', $explicit));
        $t->same('ltr', $explicit['textDirection']);
        $t->true(!array_key_exists('textDirectionImplicitDefault', $explicit));

        $t->contains('<bdi id="auto-ltr">123 Review</bdi>', $html);
        $t->true(!str_contains($html, '<bdi dir="auto" id="auto-ltr">'));
        $t->contains($html, $blocks);
        $t->same('/migration/bdi-implicit-auto-direction.html', $document->children[0]->attr('part'));
    },
];
