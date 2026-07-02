<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html fragment outline and landmark handoff metadata' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<main id="content"><h1>Migration Packet</h1>'
                . '<section id="overview"><h3>Skipped Detail</h3><p>Body</p></section>'
                . '<nav id="toc" aria-label="Contents"><h2>Contents</h2><a href="#overview">Overview</a></nav>'
                . '<aside id="notes"><p>Loose note</p></aside>'
                . '<search id="lookup"><form><label for="q">Find</label><input id="q" name="q" type="search"></form></search>'
                . '<hgroup id="intro"><p>Draft</p><h2>Secondary</h2><h1>Main Title</h1><p>Checkpoint</p></hgroup>'
                . '</main>',
            'outline landmark review fragment'
        );

        $packet = XmlHtmlDom::summarizeHtmlFragmentOutlineReviewPacket($dom);
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/outline-landmark-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('xml-html5-dom', $packet['formatFamily']);
        $t->same('html', $packet['format']);
        $t->same('html-fragment-outline-landmark-review', $packet['outlineReviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same(['html-fragment-outline-review-only'], $packet['directReaderDiagnosticCodes']);

        $t->same(5, $packet['headingCount']);
        $t->same([1, 3, 2, 2, 1], $packet['headingLevels']);
        $t->same(2, $packet['headingLevelCounts'][1] ?? null);
        $t->same(2, $packet['headingLevelCounts'][2] ?? null);
        $t->same(1, $packet['headingLevelCounts'][3] ?? null);
        $t->same([
            'Migration Packet',
            'Skipped Detail',
            'Contents',
            'Secondary',
            'Main Title',
        ], $packet['headingTexts']);
        $t->same('main[1]/section[1]/h3[1]', $packet['headings'][1]['nodePath'] ?? null);

        $t->same(4, $packet['outlineRootCount']);
        $t->same(['main', 'section', 'nav', 'aside'], $packet['outlineRootNames']);
        $t->same(['Migration Packet', 'Skipped Detail', 'Contents'], $packet['outlineRootHeadingTexts']);
        $t->same('navigation', $packet['outlineRoots'][2]['documentOutline'] ?? null);
        $t->same('Contents', $packet['outlineRoots'][2]['sectionHeadingText'] ?? null);
        $t->same(null, $packet['outlineRoots'][3]['sectionHeadingText'] ?? null);

        $t->same(4, $packet['landmarkCount']);
        $t->same(['main', 'navigation', 'complementary', 'search'], $packet['landmarkNames']);
        $t->same('Contents', $packet['landmarks'][1]['landmarkLabel'] ?? null);
        $t->same(null, $packet['landmarks'][3]['landmarkLabel'] ?? null);

        $t->same(1, $packet['headingGroupCount']);
        $t->same('Main Title', $packet['headingGroups'][0]['headingGroupHeadingText'] ?? null);
        $t->same(['Secondary', 'Main Title'], $packet['headingGroups'][0]['headingGroupHeadingTexts'] ?? null);
        $t->same(['Draft', 'Checkpoint'], $packet['headingGroups'][0]['headingGroupSubtitleTexts'] ?? null);

        $t->same([
            'skipped-heading-level',
            'missing-outline-root-heading',
            'unlabeled-landmark',
            'multiple-top-level-headings',
        ], $packet['outlineIssueCodes']);
        $t->same(4, $packet['outlineIssueCount']);
        $t->same('skipped-heading-level', $packet['outlineIssues'][0]['code'] ?? null);
        $t->same('missing-outline-root-heading', $packet['outlineIssues'][1]['code'] ?? null);
        $t->same('unlabeled-landmark', $packet['outlineIssues'][2]['code'] ?? null);
        $t->same('multiple-top-level-headings', $packet['outlineIssues'][3]['code'] ?? null);

        $t->same('main', $summary[0]['name'] ?? null);
        $t->contains('<search id="lookup">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/outline-landmark-review.html', $document->children[0]->attr('part'));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
];
