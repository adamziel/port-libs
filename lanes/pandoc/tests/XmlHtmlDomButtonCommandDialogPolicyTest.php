<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'carries dialog closedby policy into html button command targets' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<dialog id="locked" closedby="none" open><form method="dialog"><button value="done">Done</button></form></dialog>'
                . '<dialog id="loose" closedby="Any">Loose</dialog>'
                . '<dialog id="bad-policy" closedby="dismiss">Bad</dialog>'
                . '<button id="request-close" commandfor="locked" command="request-close">Request close</button>'
                . '<button id="show-modal" commandfor="loose" command="show-modal">Show modal</button>'
                . '<button id="bad-dialog-policy" commandfor="bad-policy" command="close">Close bad policy</button>',
            'button command dialog closedby review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/button-command-dialog-policy.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $requestClose = $summary[3];
        $showModal = $summary[4];
        $badPolicy = $summary[5];

        $t->same('button-commandfor-target-review', $requestClose['buttonCommandReviewPolicy']);
        $t->same('request-close', $requestClose['command']);
        $t->same('dialog', $requestClose['commandActionFamily']);
        $t->same('dialog', $requestClose['commandTargetKind']);
        $t->same(true, $requestClose['commandInvokesTarget']);
        $t->same('dialog', $requestClose['commandTarget']['tag']);
        $t->same('locked', $requestClose['commandTarget']['id']);
        $t->same(true, $requestClose['commandTarget']['dialogOpen']);
        $t->same('open', $requestClose['commandTarget']['dialogState']);
        $t->same(1, $requestClose['commandTarget']['dialogMethodFormCount']);
        $t->same(['done'], $requestClose['commandTarget']['dialogCloseValues']);
        $t->same('html-dialog-closedby-policy-review', $requestClose['commandTarget']['dialogClosedByReviewPolicy']);
        $t->same('none', $requestClose['commandTarget']['dialogClosedByRaw']);
        $t->same('none', $requestClose['commandTarget']['dialogClosedByState']);
        $t->same(true, $requestClose['commandTarget']['dialogClosedByValid']);
        $t->same(false, $requestClose['commandTarget']['dialogCloseRequestAllowed']);
        $t->same(false, $requestClose['commandTarget']['dialogLightDismissAllowed']);
        $t->same(true, $requestClose['commandTarget']['dialogDeveloperCloseRequired']);
        $t->same([], $requestClose['commandTarget']['dialogClosedByIssueCodes']);

        $t->same('show-modal', $showModal['command']);
        $t->same('dialog', $showModal['commandTargetKind']);
        $t->same(false, $showModal['commandTarget']['dialogOpen']);
        $t->same('closed', $showModal['commandTarget']['dialogState']);
        $t->same('Any', $showModal['commandTarget']['dialogClosedByRaw']);
        $t->same('any', $showModal['commandTarget']['dialogClosedByState']);
        $t->same(true, $showModal['commandTarget']['dialogCloseRequestAllowed']);
        $t->same(true, $showModal['commandTarget']['dialogLightDismissAllowed']);
        $t->same(false, $showModal['commandTarget']['dialogDeveloperCloseRequired']);

        $t->same('close', $badPolicy['command']);
        $t->same('bad-policy', $badPolicy['commandTarget']['id']);
        $t->same('dismiss', $badPolicy['commandTarget']['dialogClosedByRaw']);
        $t->same('auto', $badPolicy['commandTarget']['dialogClosedByState']);
        $t->same(false, $badPolicy['commandTarget']['dialogClosedByValid']);
        $t->same(['invalid-dialog-closedby'], $badPolicy['commandTarget']['dialogClosedByIssueCodes']);
        $t->same([[
            'code' => 'invalid-dialog-closedby',
            'closedByRaw' => 'dismiss',
        ]], $badPolicy['commandTarget']['dialogClosedByIssues']);
        $t->same([], $badPolicy['commandIssueCodes']);

        $t->contains('<button command="request-close" commandfor="locked" id="request-close">', $html);
        $t->contains('<dialog closedby="dismiss" id="bad-policy">Bad</dialog>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/button-command-dialog-policy.html', $document->children[0]->attr('part'));
    },
];
