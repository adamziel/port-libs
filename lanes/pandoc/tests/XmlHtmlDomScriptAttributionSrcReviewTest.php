<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes script attributionsrc issue codes for reviewer handoff' => static function (TestRunner $t): void {
        $attributionSrc = 'https://metrics.example.test/register /local javascript:alert(1) mailto:ops@example.test';
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script id="report" src="/asset.js" attributionsrc="' . $attributionSrc . '"></script>'
                . '<script id="empty" src="/empty.js" attributionsrc></script>'
                . '<script id="plain" src="/plain.js"></script>',
            'script attributionsrc issue-code review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/script-attributionsrc-issue-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $report = $summary[0];
        $empty = $summary[1];
        $plain = $summary[2];

        $t->same('script-attributionsrc-provenance-review', $report['scriptAttributionSrcReviewPolicy']);
        $t->same(true, $report['scriptAttributionSrcRequested']);
        $t->same($attributionSrc, $report['scriptAttributionSrcRaw']);
        $t->same(false, $report['scriptAttributionSrcEmpty']);
        $t->same([
            'https://metrics.example.test/register',
            '/local',
            'javascript:alert(1)',
            'mailto:ops@example.test',
        ], $report['scriptAttributionSrcUrls']);
        $t->same(4, $report['scriptAttributionSrcUrlCount']);
        $t->same(['javascript:alert(1)'], $report['unsafeScriptAttributionSrcUrls']);
        $t->same(['mailto:ops@example.test'], $report['nonHttpScriptAttributionSrcUrls']);
        $t->same([
            'unsafe-script-attributionsrc-url',
            'non-http-script-attributionsrc-url',
        ], $report['scriptAttributionSrcIssueCodes']);
        $t->same(2, $report['scriptAttributionSrcIssueCount']);
        $t->same(false, $report['scriptAttributionSrcValid']);
        $t->same('absolute', $report['scriptAttributionSrcUrlRecords'][0]['kind']);
        $t->same('https', $report['scriptAttributionSrcUrlRecords'][0]['scheme']);
        $t->same(false, $report['scriptAttributionSrcUrlRecords'][0]['unsafe']);
        $t->same('relative', $report['scriptAttributionSrcUrlRecords'][1]['kind']);
        $t->same(true, $report['scriptAttributionSrcUrlRecords'][2]['unsafe']);

        $t->same('', $empty['scriptAttributionSrcRaw']);
        $t->same(true, $empty['scriptAttributionSrcEmpty']);
        $t->same([], $empty['scriptAttributionSrcUrls']);
        $t->same(0, $empty['scriptAttributionSrcUrlCount']);
        $t->same(['empty-script-attributionsrc'], $empty['scriptAttributionSrcIssueCodes']);
        $t->same(1, $empty['scriptAttributionSrcIssueCount']);
        $t->same(false, $empty['scriptAttributionSrcValid']);

        $t->true(!array_key_exists('scriptAttributionSrcIssueCodes', $plain));
        $t->same('external', $plain['scriptSourceKind']);
        $t->contains('attributionsrc="https://metrics.example.test/register /local javascript:alert(1) mailto:ops@example.test"', $html);
        $t->contains('attributionsrc=""', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/script-attributionsrc-issue-review.html', $document->children[0]->attr('part'));
        json_encode($report, JSON_THROW_ON_ERROR);
    },
];
