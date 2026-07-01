<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html time datetime issue codes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>'
                . '<time id="attr-date" datetime=" 2026-07-01 ">July 1</time>'
                . '<time id="text-month">2026-07</time>'
                . '<time id="empty" datetime="">Empty attr</time>'
                . '<time id="bad-date" datetime="2026-02-30">Bad date</time>'
                . '<time id="unsafe" datetime="bad&lt;tag">Unsafe</time>'
                . '<time id="bad-text">later-ish</time>'
                . '<time id="missing"></time>'
                . '</p>',
            'time datetime issue-code review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/time-datetime-issue-codes.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $date = $paragraph['children'][0];
        $textMonth = $paragraph['children'][1];
        $empty = $paragraph['children'][2];
        $badDate = $paragraph['children'][3];
        $unsafe = $paragraph['children'][4];
        $badText = $paragraph['children'][5];
        $missing = $paragraph['children'][6];

        $t->same('html-time-datetime-value-review', $date['timeDatetimeReviewPolicy']);
        $t->same('2026-07-01', $date['timeDatetime']);
        $t->same('date', $date['timeDatetimeKind']);
        $t->same([], $date['timeDatetimeIssueCodes']);
        $t->same(0, $date['timeDatetimeIssueCount']);
        $t->same(true, $date['timeDatetimeConforming']);
        $t->same(true, $date['timeValueConforming']);

        $t->same('text', $textMonth['timeDatetimeSource']);
        $t->same('2026-07', $textMonth['timeDatetime']);
        $t->same('month', $textMonth['timeDatetimeKind']);
        $t->same([], $textMonth['timeValueIssueCodes']);
        $t->same(true, $textMonth['timeValueConforming']);

        $t->same('', $empty['timeDatetimeRaw']);
        $t->same('invalid', $empty['timeDatetimeKind']);
        $t->same(['empty-time-datetime'], $empty['timeDatetimeIssueCodes']);
        $t->same(1, $empty['timeDatetimeIssueCount']);
        $t->same('datetime-attribute', $empty['timeDatetimeIssues'][0]['source']);
        $t->same(0, $empty['timeDatetimeIssues'][0]['byteLength']);
        $t->same(['empty-time-datetime'], $empty['timeValueIssueCodes']);
        $t->same(false, $empty['timeDatetimeConforming']);

        $t->same('2026-02-30', $badDate['timeDatetimeRaw']);
        $t->same(['invalid-time-datetime'], $badDate['timeDatetimeIssueCodes']);
        $t->same('datetime-attribute', $badDate['timeDatetimeIssues'][0]['source']);
        $t->same(10, $badDate['timeDatetimeIssues'][0]['byteLength']);
        $t->same(false, $badDate['timeValueConforming']);

        $t->same('bad<tag', $unsafe['timeDatetimeRaw']);
        $t->same(['unsafe-time-datetime-token'], $unsafe['timeDatetimeIssueCodes']);
        $t->same('datetime-attribute', $unsafe['timeDatetimeIssues'][0]['source']);
        $t->same(7, $unsafe['timeDatetimeIssues'][0]['byteLength']);
        $t->same(false, $unsafe['timeDatetimeValid']);

        $t->same(null, $badText['timeDatetimeRaw']);
        $t->same('text', $badText['timeDatetimeSource']);
        $t->same('later-ish', $badText['timeValueRaw']);
        $t->same(['invalid-time-text'], $badText['timeDatetimeIssueCodes']);
        $t->same('text', $badText['timeDatetimeIssues'][0]['source']);
        $t->same(9, $badText['timeDatetimeIssues'][0]['byteLength']);

        $t->same('', $missing['timeText']);
        $t->same('missing', $missing['timeDatetimeSource']);
        $t->same('missing', $missing['timeDatetimeKind']);
        $t->same(['missing-time-datetime'], $missing['timeDatetimeIssueCodes']);
        $t->same('missing', $missing['timeDatetimeIssues'][0]['source']);
        $t->same(false, $missing['timeValueConforming']);

        $t->contains('datetime=" 2026-07-01 "', $html);
        $t->contains('datetime=""', $html);
        $t->contains('datetime="bad&lt;tag"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/time-datetime-issue-codes.html', $document->children[0]->attr('part'));
        json_encode([$date, $empty, $badDate, $unsafe, $badText, $missing], JSON_THROW_ON_ERROR);
    },
];
