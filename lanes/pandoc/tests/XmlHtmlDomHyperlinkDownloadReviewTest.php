<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html hyperlink download filename provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p><a id="safe" href="/reports/q2.csv" download="q2.csv">Safe</a>'
                . '<a id="empty" href="/reports/latest" download>Latest</a>'
                . '<a id="path" href="/reports/archive" download="../archive.zip">Archive</a>'
                . '<a id="control" href="/reports/bad" download="bad&#10;name.pdf">Bad</a>'
                . '<a id="missing" download="orphan.txt">Missing</a>'
                . '<a id="unsafe" href="javascript:alert(1)" download="unsafe.txt">Unsafe</a></p>',
            'hyperlink download filename review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hyperlink-download-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $safe = $summary[0]['children'][0];
        $empty = $summary[0]['children'][1];
        $path = $summary[0]['children'][2];
        $control = $summary[0]['children'][3];
        $missing = $summary[0]['children'][4];
        $unsafe = $summary[0]['children'][5];

        $t->same('hyperlink-download-filename-review', $safe['downloadReviewPolicy']);
        $t->same(true, $safe['downloadRequested']);
        $t->same('q2.csv', $safe['downloadRaw']);
        $t->same('q2.csv', $safe['downloadSuggestedFilename']);
        $t->same(true, $safe['downloadHasSuggestedFilename']);
        $t->same(true, $safe['downloadSuggestedFilenameValid']);
        $t->same(true, $safe['downloadWouldRequestNavigationDownload']);
        $t->same([], $safe['downloadIssueCodes']);
        $t->same(true, $safe['downloadValid']);
        $t->same(true, $safe['downloadReviewOnlyNoNetworkRequest']);

        $t->same('', $empty['downloadRaw']);
        $t->same(null, $empty['downloadSuggestedFilename']);
        $t->same(false, $empty['downloadHasSuggestedFilename']);
        $t->same(null, $empty['downloadSuggestedFilenameValid']);
        $t->same(true, $empty['downloadWouldRequestNavigationDownload']);
        $t->same([], $empty['downloadIssueCodes']);

        $t->same('../archive.zip', $path['downloadSuggestedFilename']);
        $t->same(false, $path['downloadSuggestedFilenameValid']);
        $t->same([
            ['code' => 'download-filename-path-separator', 'filename' => '../archive.zip'],
        ], $path['downloadIssues']);
        $t->same(['download-filename-path-separator'], $path['downloadIssueCodes']);
        $t->same(['download-filename-path-separator'], $path['navigationIssueCodes']);
        $t->same(false, $path['downloadValid']);

        $t->same("bad\nname.pdf", $control['downloadSuggestedFilename']);
        $t->same(false, $control['downloadSuggestedFilenameValid']);
        $t->same(['download-filename-control-character'], $control['downloadIssueCodes']);
        $t->same(['download-filename-control-character'], $control['navigationIssueCodes']);

        $t->same('missing', $missing['hrefKind']);
        $t->same(false, $missing['downloadWouldRequestNavigationDownload']);
        $t->same(['download-without-href'], $missing['downloadIssueCodes']);
        $t->same(['download-without-href'], $missing['navigationIssueCodes']);

        $t->same('javascript', $unsafe['hrefScheme']);
        $t->same(true, $unsafe['hrefUnsafe']);
        $t->same(false, $unsafe['downloadWouldRequestNavigationDownload']);
        $t->same(['unsafe-download-href'], $unsafe['downloadIssueCodes']);
        $t->same(['unsafe-href', 'unsafe-download-href'], $unsafe['navigationIssueCodes']);

        $t->contains('download="../archive.zip"', $html);
        $t->contains('download="unsafe.txt"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/hyperlink-download-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
