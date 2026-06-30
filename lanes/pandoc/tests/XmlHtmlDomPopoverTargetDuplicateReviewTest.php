<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes duplicate popover target ids for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<button id="open-menu" type="button" popovertarget="menu" popovertargetaction="show">Open</button>'
                . '<button id="plain-toggle" type="button" popovertarget="plain">Plain</button>'
                . '<section id="menu" popover="manual">Primary menu</section>'
                . '<aside id="menu" popover>Duplicate menu</aside>'
                . '<div id="plain">Plain target</div>'
                . '<div id="plain" popover>Duplicate plain</div>',
            'popover duplicate target review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/popover-target-duplicates-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $open = $summary[0];
        $plain = $summary[1];

        $t->same('popover-target-idref-review', $open['popoverTargetReviewPolicy']);
        $t->same('menu', $open['popoverTargetRaw']);
        $t->same('menu', $open['popoverTarget']);
        $t->same(true, $open['popoverTargetValid']);
        $t->same(true, $open['popoverTargetFound']);
        $t->same(2, $open['popoverTargetCount']);
        $t->same('duplicate-target', $open['popoverTargetKind']);
        $t->same('section', $open['popoverTargetElement']['tag'] ?? null);
        $t->same('menu', $open['popoverTargetElement']['id'] ?? null);
        $t->same('manual', $open['popoverTargetElement']['popoverState'] ?? null);
        $t->same([
            ['tag' => 'section', 'id' => 'menu', 'text' => 'Primary menu', 'popoverRaw' => 'manual', 'popoverState' => 'manual', 'popoverValid' => true],
            ['tag' => 'aside', 'id' => 'menu', 'text' => 'Duplicate menu', 'popoverRaw' => '', 'popoverState' => 'auto', 'popoverValid' => true],
        ], $open['popoverTargetElements']);
        $t->same([
            [
                'code' => 'duplicate-popover-target-element',
                'targetId' => 'menu',
                'count' => 2,
            ],
        ], $open['popoverTargetIssues']);
        $t->same(['duplicate-popover-target-element'], $open['popoverTargetIssueCodes']);
        $t->same(false, $open['popoverTargetInvokesPopover']);
        $t->same('show', $open['popoverTargetAction']);

        $t->same('plain', $plain['popoverTarget']);
        $t->same(true, $plain['popoverTargetFound']);
        $t->same(2, $plain['popoverTargetCount']);
        $t->same('duplicate-target', $plain['popoverTargetKind']);
        $t->same('div', $plain['popoverTargetElement']['tag'] ?? null);
        $t->same(null, $plain['popoverTargetElement']['popoverRaw'] ?? null);
        $t->same([
            'duplicate-popover-target-element',
            'non-popover-target',
        ], $plain['popoverTargetIssueCodes']);
        $t->same(false, $plain['popoverTargetInvokesPopover']);

        $t->same(
            '<button id="open-menu" popovertarget="menu" popovertargetaction="show" type="button">Open</button>'
                . '<button id="plain-toggle" popovertarget="plain" type="button">Plain</button>'
                . '<section id="menu" popover="manual">Primary menu</section>'
                . '<aside id="menu" popover="">Duplicate menu</aside>'
                . '<div id="plain">Plain target</div>'
                . '<div id="plain" popover="">Duplicate plain</div>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/popover-target-duplicates-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
