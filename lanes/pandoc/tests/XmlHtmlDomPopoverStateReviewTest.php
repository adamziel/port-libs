<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html popover state metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<aside id="auto" popover>Auto panel</aside>'
                . '<section id="manual" popover="manual">Manual panel</section>'
                . '<div id="explicit-auto" popover="auto">Explicit auto</div>'
                . '<div id="bad" popover="hint">Bad panel</div>',
            'popover state review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/popover-state-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $auto = $summary[0];
        $manual = $summary[1];
        $explicitAuto = $summary[2];
        $bad = $summary[3];

        $t->same('html-popover-state-review', $auto['popoverReviewPolicy']);
        $t->same('ok', $auto['popoverReviewStatus']);
        $t->same('', $auto['popoverRaw']);
        $t->same('auto', $auto['popoverState']);
        $t->same('auto', $auto['popoverKeyword']);
        $t->same(true, $auto['popoverAuto']);
        $t->same(false, $auto['popoverManual']);
        $t->same(true, $auto['popoverValid']);
        $t->same(false, $auto['popoverInvalidValueDefaulted']);
        $t->same([], $auto['popoverIssueCodes']);
        $t->same(0, $auto['popoverIssueCount']);
        $t->same(true, $auto['popoverReviewOnlyNoPopoverEngine']);
        $t->same('aside', $auto['popoverElement']);
        $t->same('auto', $auto['popoverElementId']);

        $t->same('manual', $manual['popoverRaw']);
        $t->same('manual', $manual['popoverState']);
        $t->same('manual', $manual['popoverKeyword']);
        $t->same(false, $manual['popoverAuto']);
        $t->same(true, $manual['popoverManual']);
        $t->same(true, $manual['popoverValid']);
        $t->same([], $manual['popoverIssueCodes']);

        $t->same('auto', $explicitAuto['popoverRaw']);
        $t->same('auto', $explicitAuto['popoverState']);
        $t->same(true, $explicitAuto['popoverAuto']);
        $t->same(false, $explicitAuto['popoverManual']);

        $t->same('review', $bad['popoverReviewStatus']);
        $t->same('hint', $bad['popoverRaw']);
        $t->same(null, $bad['popoverState']);
        $t->same(null, $bad['popoverKeyword']);
        $t->same(false, $bad['popoverAuto']);
        $t->same(false, $bad['popoverManual']);
        $t->same(false, $bad['popoverValid']);
        $t->same(true, $bad['popoverInvalidValueDefaulted']);
        $t->same(['invalid-html-popover-token'], $bad['popoverIssueCodes']);
        $t->same(1, $bad['popoverIssueCount']);
        $t->same([['code' => 'invalid-html-popover-token', 'popoverRaw' => 'hint']], $bad['popoverIssues']);

        $t->same(
            '<aside id="auto" popover="">Auto panel</aside><section id="manual" popover="manual">Manual panel</section><div id="explicit-auto" popover="auto">Explicit auto</div><div id="bad" popover="hint">Bad panel</div>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/popover-state-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
