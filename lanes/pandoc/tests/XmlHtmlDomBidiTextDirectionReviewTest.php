<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html bidi text direction validity for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><bdi id="implicit">SKU-42</bdi> <bdi id="rtl" dir="RTL">RTL label</bdi> <bdi id="bad" dir="sideways">Bad isolate</bdi> <bdo id="ltr" dir="ltr">abc</bdo> <bdo id="auto" dir="auto">auto</bdo> <bdo id="missing">missing</bdo></p>',
            'bidi text direction review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/bidi-text-direction-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $implicit = $paragraph['children'][0];
        $rtl = $paragraph['children'][2];
        $bad = $paragraph['children'][4];
        $ltr = $paragraph['children'][6];
        $auto = $paragraph['children'][8];
        $missing = $paragraph['children'][10];

        $t->same('p', $paragraph['name']);
        $t->same('bidirectional-isolate', $implicit['textSemantic']);
        $t->same('auto', $implicit['textDirection']);
        $t->same(true, $implicit['textDirectionImplicitDefault']);
        $t->same('html-bidi-text-direction-review', $implicit['bidiTextReviewPolicy']);
        $t->same('bdi', $implicit['bidiElement']);
        $t->same('isolate', $implicit['bidiMode']);
        $t->same('SKU-42', $implicit['bidiText']);
        $t->same(null, $implicit['bidiDirectionRaw']);
        $t->same('auto', $implicit['bidiDirection']);
        $t->same('implicit-bdi-auto', $implicit['bidiDirectionSource']);
        $t->same(true, $implicit['bidiDirectionDefaulted']);
        $t->same(true, $implicit['bidiDirectionValid']);
        $t->same(['auto', 'ltr', 'rtl'], $implicit['bidiAllowedDirections']);
        $t->same([], $implicit['bidiIssueCodes']);
        $t->same(0, $implicit['bidiIssueCount']);
        $t->same([], $implicit['bidiIssues']);

        $t->same('bdi', $rtl['name']);
        $t->same('RTL', $rtl['bidiDirectionRaw']);
        $t->same('rtl', $rtl['textDirection']);
        $t->same('rtl', $rtl['bidiDirection']);
        $t->same('dir-attribute', $rtl['bidiDirectionSource']);
        $t->same(false, $rtl['bidiDirectionDefaulted']);
        $t->same(true, $rtl['bidiDirectionValid']);
        $t->same(['auto', 'ltr', 'rtl'], $rtl['bidiAllowedDirections']);
        $t->same([], $rtl['bidiIssueCodes']);

        $t->same('bdi', $bad['name']);
        $t->same('sideways', $bad['bidiDirectionRaw']);
        $t->same(null, $bad['textDirection']);
        $t->same(null, $bad['bidiDirection']);
        $t->same(false, $bad['bidiDirectionValid']);
        $t->same(['invalid-bidi-dir'], $bad['bidiIssueCodes']);
        $t->same(1, $bad['bidiIssueCount']);
        $t->same([['code' => 'invalid-bidi-dir', 'dirRaw' => 'sideways']], $bad['bidiIssues']);

        $t->same('bdo', $ltr['name']);
        $t->same('bidirectional-override', $ltr['textSemantic']);
        $t->same('ltr', $ltr['textDirection']);
        $t->same('override', $ltr['bidiMode']);
        $t->same('ltr', $ltr['bidiDirection']);
        $t->same(['ltr', 'rtl'], $ltr['bidiAllowedDirections']);
        $t->same(true, $ltr['bidiDirectionValid']);
        $t->same([], $ltr['bidiIssues']);

        $t->same('bdo', $auto['name']);
        $t->same('auto', $auto['bidiDirectionRaw']);
        $t->same(null, $auto['textDirection']);
        $t->same(null, $auto['bidiDirection']);
        $t->same(false, $auto['bidiDirectionValid']);
        $t->same(['invalid-bdo-dir'], $auto['bidiIssueCodes']);
        $t->same([['code' => 'invalid-bdo-dir', 'dirRaw' => 'auto']], $auto['bidiIssues']);

        $t->same('bdo', $missing['name']);
        $t->same(null, $missing['bidiDirectionRaw']);
        $t->same(null, $missing['textDirection']);
        $t->same(null, $missing['bidiDirection']);
        $t->same('missing', $missing['bidiDirectionSource']);
        $t->same(false, $missing['bidiDirectionValid']);
        $t->same(['missing-bdo-dir'], $missing['bidiIssueCodes']);
        $t->same([['code' => 'missing-bdo-dir']], $missing['bidiIssues']);

        $t->same('<p><bdi id="implicit">SKU-42</bdi> <bdi dir="RTL" id="rtl">RTL label</bdi> <bdi dir="sideways" id="bad">Bad isolate</bdi> <bdo dir="ltr" id="ltr">abc</bdo> <bdo dir="auto" id="auto">auto</bdo> <bdo id="missing">missing</bdo></p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/bidi-text-direction-review.html', $document->children[0]->attr('part'));
    },
];
