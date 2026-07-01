<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html script attributionsrc issue codes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<script id="good" src="/good.js" attributionsrc="https://report.example.test/register /local-report"></script>'
                . '<script id="bad" src="/bad.js" attributionsrc="javascript:alert(1) mailto:ops@example.test https://safe.example.test/register"></script>'
                . '<script id="empty" src="/empty.js" attributionsrc></script>'
                . '<script id="plain" src="/plain.js"></script>',
            'script attributionsrc issue-code review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/script-attributionsrc-issue-codes.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $good = $summary[0];
        $bad = $summary[1];
        $empty = $summary[2];
        $plain = $summary[3];

        $t->same('script-attributionsrc-provenance-review', $good['scriptAttributionSrcReviewPolicy']);
        $t->same(true, $good['scriptAttributionSrcRequested']);
        $t->same(false, $good['scriptAttributionSrcEmpty']);
        $t->same(['https://report.example.test/register', '/local-report'], $good['scriptAttributionSrcUrls']);
        $t->same(2, $good['scriptAttributionSrcUrlCount']);
        $t->same('absolute', $good['scriptAttributionSrcUrlRecords'][0]['kind']);
        $t->same('https', $good['scriptAttributionSrcUrlRecords'][0]['scheme']);
        $t->same('relative', $good['scriptAttributionSrcUrlRecords'][1]['kind']);
        $t->same([], $good['scriptAttributionSrcIssues']);
        $t->same([], $good['scriptAttributionSrcIssueCodes']);
        $t->same(0, $good['scriptAttributionSrcIssueCount']);
        $t->same(true, $good['scriptAttributionSrcValid']);

        $t->same('script-attributionsrc-provenance-review', $bad['scriptAttributionSrcReviewPolicy']);
        $t->same([
            'javascript:alert(1)',
            'mailto:ops@example.test',
            'https://safe.example.test/register',
        ], $bad['scriptAttributionSrcUrls']);
        $t->same(['javascript:alert(1)'], $bad['unsafeScriptAttributionSrcUrls']);
        $t->same(['mailto:ops@example.test'], $bad['nonHttpScriptAttributionSrcUrls']);
        $t->same(true, $bad['scriptAttributionSrcUrlRecords'][0]['unsafe']);
        $t->same('javascript', $bad['scriptAttributionSrcUrlRecords'][0]['scheme']);
        $t->same('mailto', $bad['scriptAttributionSrcUrlRecords'][1]['scheme']);
        $t->same(false, $bad['scriptAttributionSrcUrlRecords'][2]['unsafe']);
        $t->same([
            ['code' => 'unsafe-script-attributionsrc-url', 'url' => 'javascript:alert(1)', 'scheme' => 'javascript'],
            ['code' => 'non-http-script-attributionsrc-url', 'url' => 'mailto:ops@example.test', 'scheme' => 'mailto'],
        ], $bad['scriptAttributionSrcIssues']);
        $t->same([
            'unsafe-script-attributionsrc-url',
            'non-http-script-attributionsrc-url',
        ], $bad['scriptAttributionSrcIssueCodes']);
        $t->same(2, $bad['scriptAttributionSrcIssueCount']);
        $t->same(false, $bad['scriptAttributionSrcValid']);

        $t->same('', $empty['scriptAttributionSrcRaw']);
        $t->same(true, $empty['scriptAttributionSrcEmpty']);
        $t->same([], $empty['scriptAttributionSrcUrls']);
        $t->same([['code' => 'empty-script-attributionsrc']], $empty['scriptAttributionSrcIssues']);
        $t->same(['empty-script-attributionsrc'], $empty['scriptAttributionSrcIssueCodes']);
        $t->same(1, $empty['scriptAttributionSrcIssueCount']);
        $t->same(false, $empty['scriptAttributionSrcValid']);

        $t->true(!array_key_exists('scriptAttributionSrcReviewPolicy', $plain));
        $t->contains('attributionsrc=""', $html);
        $t->contains('attributionsrc="javascript:alert(1) mailto:ops@example.test https://safe.example.test/register"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/script-attributionsrc-issue-codes.html', $document->children[0]->attr('part'));
    },
];
