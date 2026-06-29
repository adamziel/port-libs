<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes duplicate html anchor positioning targets for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<button id="badge">Primary anchor</button>'
                . '<span id="badge">Duplicate anchor</span>'
                . '<aside id="tooltip" anchor="badge" popover>Tooltip copy</aside>'
                . '<button id="clean-anchor">Clean anchor</button>'
                . '<aside id="clean-tooltip" anchor="clean-anchor">Clean copy</aside>',
            'anchor positioning duplicate target review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/anchor-positioning-duplicate-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $duplicate = $summary[2];
        $clean = $summary[4];

        $t->same('html-anchor-positioning-target-review', $duplicate['anchorPositioningReviewPolicy']);
        $t->same('badge', $duplicate['anchorRaw']);
        $t->same('badge', $duplicate['anchorTarget']);
        $t->same(true, $duplicate['anchorTargetValid']);
        $t->same(true, $duplicate['anchorTargetFound']);
        $t->same(2, $duplicate['anchorTargetCount']);
        $t->same('duplicate-target', $duplicate['anchorTargetKind']);
        $t->same('button', $duplicate['anchorTargetElement']['tag'] ?? null);
        $t->same('Primary anchor', $duplicate['anchorTargetElement']['text'] ?? null);
        $t->same([
            ['tag' => 'button', 'id' => 'badge', 'text' => 'Primary anchor'],
            ['tag' => 'span', 'id' => 'badge', 'text' => 'Duplicate anchor'],
        ], $duplicate['anchorTargetElements']);
        $t->same([
            [
                'code' => 'duplicate-html-anchor-positioning-target-element',
                'anchorTarget' => 'badge',
                'count' => 2,
            ],
        ], $duplicate['anchorIssues']);
        $t->same(['duplicate-html-anchor-positioning-target-element'], $duplicate['anchorIssueCodes']);
        $t->same(false, $duplicate['anchorReferencesTarget']);
        $t->same('', $duplicate['popoverRaw']);
        $t->same('auto', $duplicate['popoverState']);

        $t->same('clean-anchor', $clean['anchorRaw']);
        $t->same(true, $clean['anchorTargetFound']);
        $t->same(1, $clean['anchorTargetCount']);
        $t->same('element', $clean['anchorTargetKind']);
        $t->same([['tag' => 'button', 'id' => 'clean-anchor', 'text' => 'Clean anchor']], $clean['anchorTargetElements']);
        $t->same([], $clean['anchorIssueCodes']);
        $t->same(true, $clean['anchorReferencesTarget']);

        $t->same(
            '<button id="badge">Primary anchor</button>'
                . '<span id="badge">Duplicate anchor</span>'
                . '<aside anchor="badge" id="tooltip" popover="">Tooltip copy</aside>'
                . '<button id="clean-anchor">Clean anchor</button>'
                . '<aside anchor="clean-anchor" id="clean-tooltip">Clean copy</aside>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/anchor-positioning-duplicate-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
