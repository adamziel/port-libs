<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes standalone html track element review metadata' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<video id="packet">'
                . '<track id="captions" kind="CAPTIONS" srclang="EN-us" label="English captions" src="captions.vtt" default></track>'
                . '<track id="missing-lang" kind="subtitles" label="Missing language" src="missing.vtt"></track>'
                . '<track id="bad-track" kind="Transcript" srclang="bad&lt;tag" label="" src="bad.vtt"></track>'
                . '</video>',
            'track element review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/track-element-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $video = $summary[0];
        $caption = $video['children'][0];
        $missingLanguage = $video['children'][1];
        $badTrack = $video['children'][2];

        $t->same('track', $caption['embeddedResource']);
        $t->same('html-track-element-review', $caption['textTrackReviewPolicy']);
        $t->same(0, $caption['index']);
        $t->same('CAPTIONS', $caption['kindRaw']);
        $t->same('captions', $caption['kind']);
        $t->same(true, $caption['kindValid']);
        $t->same('EN-us', $caption['srclangRaw']);
        $t->same('en-US', $caption['srclang']);
        $t->same(true, $caption['srclangValid']);
        $t->same(true, $caption['languageRequired']);
        $t->same(false, $caption['languageMissing']);
        $t->same([], $caption['textTrackIssueCodes']);
        $t->same(true, $caption['textTrackValid']);

        $t->same('html-track-element-review', $missingLanguage['textTrackReviewPolicy']);
        $t->same(1, $missingLanguage['index']);
        $t->same('subtitles', $missingLanguage['kind']);
        $t->same(null, $missingLanguage['srclangRaw']);
        $t->same(null, $missingLanguage['srclang']);
        $t->same(true, $missingLanguage['languageRequired']);
        $t->same(true, $missingLanguage['languageMissing']);
        $t->same(['missing-text-track-language'], $missingLanguage['textTrackIssueCodes']);
        $t->same([
            ['code' => 'missing-text-track-language', 'trackIndex' => 1, 'kind' => 'subtitles', 'label' => 'Missing language', 'src' => 'missing.vtt'],
        ], $missingLanguage['textTrackIssues']);
        $t->same(false, $missingLanguage['textTrackValid']);

        $t->same(2, $badTrack['index']);
        $t->same('Transcript', $badTrack['kindRaw']);
        $t->same('subtitles', $badTrack['kind']);
        $t->same(false, $badTrack['kindValid']);
        $t->same('bad<tag', $badTrack['srclangRaw']);
        $t->same(null, $badTrack['srclang']);
        $t->same(false, $badTrack['srclangValid']);
        $t->same([
            'invalid-text-track-kind',
            'invalid-text-track-language',
            'missing-text-track-language',
        ], $badTrack['textTrackIssueCodes']);
        $t->same([
            ['code' => 'invalid-text-track-kind', 'trackIndex' => 2, 'kindRaw' => 'Transcript', 'normalizedKind' => 'subtitles'],
            ['code' => 'invalid-text-track-language', 'trackIndex' => 2, 'srclangRaw' => 'bad<tag'],
            ['code' => 'missing-text-track-language', 'trackIndex' => 2, 'kind' => 'subtitles', 'label' => '', 'src' => 'bad.vtt'],
        ], $badTrack['textTrackIssues']);
        $t->same(3, $badTrack['textTrackIssueCount']);
        $t->same(false, $badTrack['textTrackValid']);

        $t->same('/migration/track-element-review.html', $document->children[0]->attr('part'));
        $t->contains($html, $blocks);
    },
];
