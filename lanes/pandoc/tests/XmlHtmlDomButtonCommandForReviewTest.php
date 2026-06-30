<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html button commandfor targets for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<div id="menu" popover>Menu panel</div>'
                . '<button id="toggle" command="toggle-popover" commandfor="menu">Toggle</button>'
                . '<dialog id="confirm">Confirm action</dialog>'
                . '<button id="open" command="show-modal" commandfor="confirm">Open</button>'
                . '<section id="dupe">First duplicate</section>'
                . '<section id="dupe" popover>Second duplicate</section>'
                . '<button id="bad" command="show-popover" commandfor="dupe">Bad</button>'
                . '<button id="custom" command="--review-save" commandfor="confirm">Custom</button>'
                . '<button id="unknown" command="launch" commandfor="missing id">Unknown</button>',
            'button commandfor review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/button-commandfor-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $toggle = $summary[1];
        $open = $summary[3];
        $bad = $summary[6];
        $custom = $summary[7];
        $unknown = $summary[8];

        $t->same('button-commandfor-target-review', $toggle['buttonCommandReviewPolicy']);
        $t->same('toggle-popover', $toggle['commandRaw']);
        $t->same('toggle-popover', $toggle['command']);
        $t->same('toggle-popover', $toggle['commandState']);
        $t->same('popover', $toggle['commandActionFamily']);
        $t->same(false, $toggle['commandCustom']);
        $t->same(true, $toggle['commandKnown']);
        $t->same('menu', $toggle['commandFor']);
        $t->same(true, $toggle['commandForValid']);
        $t->same(true, $toggle['commandTargetFound']);
        $t->same(1, $toggle['commandTargetCount']);
        $t->same('popover', $toggle['commandTargetKind']);
        $t->same('div', $toggle['commandTarget']['tag'] ?? null);
        $t->same('menu', $toggle['commandTarget']['id'] ?? null);
        $t->same('', $toggle['commandTarget']['popoverRaw'] ?? null);
        $t->same('auto', $toggle['commandTarget']['popoverState'] ?? null);
        $t->same([
            ['tag' => 'div', 'id' => 'menu', 'text' => 'Menu panel', 'popoverRaw' => '', 'popoverState' => 'auto', 'popoverValid' => true],
        ], $toggle['commandTargetElements']);
        $t->same([], $toggle['commandIssueCodes']);
        $t->same(true, $toggle['commandInvokesTarget']);
        $t->same(false, $toggle['buttonSubmitButton']);

        $t->same('show-modal', $open['command']);
        $t->same('dialog', $open['commandActionFamily']);
        $t->same('dialog', $open['commandTargetKind']);
        $t->same('dialog', $open['commandTarget']['tag'] ?? null);
        $t->same(false, $open['commandTarget']['dialogOpen'] ?? null);
        $t->same('closed', $open['commandTarget']['dialogState'] ?? null);
        $t->same([], $open['commandIssueCodes']);
        $t->same(true, $open['commandInvokesTarget']);

        $t->same('show-popover', $bad['command']);
        $t->same('dupe', $bad['commandFor']);
        $t->same(true, $bad['commandTargetFound']);
        $t->same(2, $bad['commandTargetCount']);
        $t->same('duplicate-target', $bad['commandTargetKind']);
        $t->same('section', $bad['commandTarget']['tag'] ?? null);
        $t->same([
            ['tag' => 'section', 'id' => 'dupe', 'text' => 'First duplicate'],
            ['tag' => 'section', 'id' => 'dupe', 'text' => 'Second duplicate', 'popoverRaw' => '', 'popoverState' => 'auto', 'popoverValid' => true],
        ], $bad['commandTargetElements']);
        $t->same([
            [
                'code' => 'duplicate-button-commandfor-target-element',
                'commandFor' => 'dupe',
                'count' => 2,
            ],
            [
                'code' => 'non-popover-button-command-target',
                'command' => 'show-popover',
                'targetName' => 'section',
                'targetId' => 'dupe',
            ],
        ], $bad['commandIssues']);
        $t->same([
            'duplicate-button-commandfor-target-element',
            'non-popover-button-command-target',
        ], $bad['commandIssueCodes']);
        $t->same(false, $bad['commandInvokesTarget']);

        $t->same('--review-save', $custom['commandRaw']);
        $t->same('--review-save', $custom['command']);
        $t->same('custom', $custom['commandState']);
        $t->same('custom', $custom['commandActionFamily']);
        $t->same(true, $custom['commandCustom']);
        $t->same(true, $custom['commandKnown']);
        $t->same('dialog', $custom['commandTargetKind']);
        $t->same([], $custom['commandIssueCodes']);
        $t->same(true, $custom['commandInvokesTarget']);

        $t->same('launch', $unknown['commandRaw']);
        $t->same(null, $unknown['command']);
        $t->same('unknown', $unknown['commandState']);
        $t->same(false, $unknown['commandKnown']);
        $t->same('missing id', $unknown['commandFor']);
        $t->same(false, $unknown['commandForValid']);
        $t->same(false, $unknown['commandTargetFound']);
        $t->same(0, $unknown['commandTargetCount']);
        $t->same('invalid-reference', $unknown['commandTargetKind']);
        $t->same([
            'unknown-button-command',
            'invalid-button-commandfor-target',
        ], $unknown['commandIssueCodes']);
        $t->same(false, $unknown['commandInvokesTarget']);

        $t->contains('command="toggle-popover"', $html);
        $t->contains('commandfor="dupe"', $html);
        $t->contains('command="--review-save"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/button-commandfor-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
