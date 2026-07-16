<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html popover control issue codes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="panel" popover="manual"><h2>Panel</h2></section>'
                . '<button id="show-panel" type="button" popovertarget="panel" popovertargetaction="show">Show</button>'
                . '<button id="bad-action" type="button" popovertarget="panel" popovertargetaction="dismiss">Dismiss</button>'
                . '<button id="action-only" type="button" popovertargetaction="hide">Hide targetless</button>'
                . '<aside id="bad-state" popover="invalid">Bad state</aside>'
                . '<button id="bad-target-state" type="button" popovertarget="bad-state">Bad target state</button>',
            'popover control issue-code review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/popover-control-issue-codes.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $panel = $summary[0];
        $show = $summary[1];
        $badAction = $summary[2];
        $actionOnly = $summary[3];
        $badState = $summary[4];
        $badTargetState = $summary[5];

        $t->same('html-popover-control-issue-review', $panel['popoverControlReviewPolicy']);
        $t->same(['popover'], $panel['popoverControlAttributes']);
        $t->same('manual', $panel['popoverState']);
        $t->same(true, $panel['popoverValid']);
        $t->same(true, $panel['popoverControlDefinesPopover']);
        $t->same(false, $panel['popoverControlInvokesPopover']);
        $t->same(true, $panel['popoverControlValid']);
        $t->same([], $panel['popoverControlIssueCodes']);

        $t->same(['popovertarget', 'popovertargetaction'], $show['popoverControlAttributes']);
        $t->same('panel', $show['popoverTarget']);
        $t->same('show', $show['popoverTargetAction']);
        $t->same(true, $show['popoverTargetActionValid']);
        $t->same(true, $show['popoverTargetInvokesPopover']);
        $t->same(true, $show['popoverControlInvokesPopover']);
        $t->same(true, $show['popoverControlValid']);
        $t->same([], $show['popoverControlIssueCodes']);

        $t->same('dismiss', $badAction['popoverTargetActionRaw']);
        $t->same(null, $badAction['popoverTargetAction']);
        $t->same(false, $badAction['popoverTargetActionValid']);
        $t->same(true, $badAction['popoverTargetInvokesPopover']);
        $t->same(false, $badAction['popoverControlInvokesPopover']);
        $t->same(false, $badAction['popoverControlValid']);
        $t->same(['invalid-popover-target-action'], $badAction['popoverControlIssueCodes']);
        $t->same([[
            'code' => 'invalid-popover-target-action',
            'actionRaw' => 'dismiss',
        ]], $badAction['popoverControlIssues']);

        $t->same(['popovertargetaction'], $actionOnly['popoverControlAttributes']);
        $t->same('hide', $actionOnly['popoverTargetAction']);
        $t->same(true, $actionOnly['popoverTargetActionValid']);
        $t->same(false, $actionOnly['popoverControlInvokesPopover']);
        $t->same(false, $actionOnly['popoverControlValid']);
        $t->same(['popover-target-action-without-target'], $actionOnly['popoverControlIssueCodes']);
        $t->same([[
            'code' => 'popover-target-action-without-target',
            'actionRaw' => 'hide',
        ]], $actionOnly['popoverControlIssues']);

        $t->same('invalid', $badState['popoverRaw']);
        $t->same(null, $badState['popoverState']);
        $t->same(false, $badState['popoverValid']);
        $t->same(false, $badState['popoverControlDefinesPopover']);
        $t->same(false, $badState['popoverControlValid']);
        $t->same(['invalid-popover-state'], $badState['popoverControlIssueCodes']);
        $t->same([[
            'code' => 'invalid-popover-state',
            'popoverRaw' => 'invalid',
        ]], $badState['popoverControlIssues']);

        $t->same('bad-state', $badTargetState['popoverTarget']);
        $t->same('popover', $badTargetState['popoverTargetKind']);
        $t->same(false, $badTargetState['popoverTargetElement']['popoverValid'] ?? null);
        $t->same(['invalid-popover-target-state'], $badTargetState['popoverTargetIssueCodes']);
        $t->same(false, $badTargetState['popoverControlInvokesPopover']);
        $t->same(false, $badTargetState['popoverControlValid']);
        $t->same(['invalid-popover-target-state'], $badTargetState['popoverControlIssueCodes']);
        $t->same([[
            'code' => 'invalid-popover-target-state',
            'targetId' => 'bad-state',
            'popoverRaw' => 'invalid',
        ]], $badTargetState['popoverControlIssues']);

        $t->contains('<button id="bad-action" popovertarget="panel" popovertargetaction="dismiss" type="button">Dismiss</button>', $html);
        $t->contains('<button id="action-only" popovertargetaction="hide" type="button">Hide targetless</button>', $html);
        $t->contains('<aside id="bad-state" popover="invalid">Bad state</aside>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/popover-control-issue-codes.html', $document->children[0]->attr('part'));
    },
];
