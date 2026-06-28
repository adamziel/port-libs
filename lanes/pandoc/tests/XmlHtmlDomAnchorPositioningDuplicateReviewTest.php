<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html anchor positioning duplicate target provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<button id="badge">Primary badge</button>'
                . '<span id="badge">Duplicate badge</span>'
                . '<aside id="tooltip" anchor="badge" popover>Tooltip copy</aside>'
                . '<button id="solo">Solo target</button>'
                . '<div id="solo-panel" anchor="solo">Solo panel</div>',
            'anchor positioning duplicate target review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/anchor-positioning-duplicate-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $tooltip = $summary[2];
        $soloPanel = $summary[4];

        $t->same('html-anchor-positioning-target-review', $tooltip['anchorPositioningReviewPolicy']);
        $t->same('badge', $tooltip['anchorRaw']);
        $t->same('badge', $tooltip['anchorTarget']);
        $t->same(true, $tooltip['anchorTargetValid']);
        $t->same(true, $tooltip['anchorTargetFound']);
        $t->same(2, $tooltip['anchorTargetCount']);
        $t->same(1, $tooltip['anchorTargetDuplicateCount']);
        $t->same('duplicate-target-id', $tooltip['anchorTargetKind']);
        $t->same('button', $tooltip['anchorTargetElement']['tag'] ?? null);
        $t->same('badge', $tooltip['anchorTargetElement']['id'] ?? null);
        $t->same('Primary badge', $tooltip['anchorTargetElement']['text'] ?? null);
        $t->same([
            ['tag' => 'button', 'id' => 'badge', 'text' => 'Primary badge'],
            ['tag' => 'span', 'id' => 'badge', 'text' => 'Duplicate badge'],
        ], $tooltip['anchorTargetElements']);
        $t->same([[
            'code' => 'duplicate-html-anchor-positioning-target-id',
            'anchorTarget' => 'badge',
            'count' => 2,
        ]], $tooltip['anchorIssues']);
        $t->same(['duplicate-html-anchor-positioning-target-id'], $tooltip['anchorIssueCodes']);
        $t->same(false, $tooltip['anchorReferencesTarget']);
        $t->same('', $tooltip['popoverRaw']);
        $t->same('auto', $tooltip['popoverState']);

        $t->same('solo', $soloPanel['anchorRaw']);
        $t->same(1, $soloPanel['anchorTargetCount']);
        $t->same(0, $soloPanel['anchorTargetDuplicateCount']);
        $t->same('element', $soloPanel['anchorTargetKind']);
        $t->same([], $soloPanel['anchorIssues']);
        $t->same([], $soloPanel['anchorIssueCodes']);
        $t->same(true, $soloPanel['anchorReferencesTarget']);

        $t->same(
            '<button id="badge">Primary badge</button>'
                . '<span id="badge">Duplicate badge</span>'
                . '<aside anchor="badge" id="tooltip" popover="">Tooltip copy</aside>'
                . '<button id="solo">Solo target</button>'
                . '<div anchor="solo" id="solo-panel">Solo panel</div>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/anchor-positioning-duplicate-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
