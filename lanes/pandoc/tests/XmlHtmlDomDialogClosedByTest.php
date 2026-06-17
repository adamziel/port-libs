<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html dialog closedby policy for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<dialog id="light" closedby="Any" open><h2>Light dismiss</h2><form method="dialog"><button value="ok">OK</button></form></dialog>'
                . '<dialog id="request" closedby="closerequest">Close request</dialog>'
                . '<dialog id="locked" closedby="none">Locked</dialog>'
                . '<dialog id="bad" closedby="dismiss">Bad policy</dialog>'
                . '<dialog id="plain">Default policy</dialog><p id="not-dialog" closedby="none">Plain element</p>',
            'dialog closedby policy review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/dialog-closedby-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $light = $summary[0];
        $request = $summary[1];
        $locked = $summary[2];
        $bad = $summary[3];
        $plain = $summary[4];
        $notDialog = $summary[5];

        $t->same('html-dialog-closedby-policy-review', $light['dialogClosedByReviewPolicy']);
        $t->same('Any', $light['dialogClosedByRaw']);
        $t->same('any', $light['dialogClosedByKeyword']);
        $t->same('any', $light['dialogClosedByState']);
        $t->same(true, $light['dialogClosedByValid']);
        $t->same(false, $light['dialogClosedByDefaulted']);
        $t->same(false, $light['dialogClosedByAutoState']);
        $t->same(null, $light['dialogClosedByAutoReason']);
        $t->same('any', $light['dialogClosedByComputedStaticState']);
        $t->same(true, $light['dialogCloseRequestAllowed']);
        $t->same(true, $light['dialogLightDismissAllowed']);
        $t->same(false, $light['dialogDeveloperCloseRequired']);
        $t->same([], $light['dialogClosedByIssueCodes']);
        $t->same(['ok'], $light['dialogCloseValues']);

        $t->same('closerequest', $request['dialogClosedByRaw']);
        $t->same('closerequest', $request['dialogClosedByKeyword']);
        $t->same('close-request', $request['dialogClosedByState']);
        $t->same(true, $request['dialogClosedByValid']);
        $t->same(false, $request['dialogClosedByDefaulted']);
        $t->same(false, $request['dialogClosedByAutoState']);
        $t->same('closerequest', $request['dialogClosedByComputedStaticState']);
        $t->same(true, $request['dialogCloseRequestAllowed']);
        $t->same(false, $request['dialogLightDismissAllowed']);

        $t->same('none', $locked['dialogClosedByRaw']);
        $t->same('none', $locked['dialogClosedByKeyword']);
        $t->same('none', $locked['dialogClosedByState']);
        $t->same(true, $locked['dialogClosedByValid']);
        $t->same('none', $locked['dialogClosedByComputedStaticState']);
        $t->same(false, $locked['dialogCloseRequestAllowed']);
        $t->same(false, $locked['dialogLightDismissAllowed']);
        $t->same(true, $locked['dialogDeveloperCloseRequired']);

        $t->same('dismiss', $bad['dialogClosedByRaw']);
        $t->same(null, $bad['dialogClosedByKeyword']);
        $t->same('auto', $bad['dialogClosedByState']);
        $t->same(false, $bad['dialogClosedByValid']);
        $t->same(true, $bad['dialogClosedByDefaulted']);
        $t->same(true, $bad['dialogClosedByAutoState']);
        $t->same('invalid-value-default', $bad['dialogClosedByAutoReason']);
        $t->same('closerequest', $bad['dialogClosedByAutoModalState']);
        $t->same('none', $bad['dialogClosedByAutoModelessState']);
        $t->same('none', $bad['dialogClosedByComputedStaticState']);
        $t->same(null, $bad['dialogCloseRequestAllowed']);
        $t->same(null, $bad['dialogLightDismissAllowed']);
        $t->same([['code' => 'invalid-dialog-closedby', 'closedByRaw' => 'dismiss']], $bad['dialogClosedByIssues']);
        $t->same(['invalid-dialog-closedby'], $bad['dialogClosedByIssueCodes']);

        $t->same(null, $plain['dialogClosedByRaw']);
        $t->same(null, $plain['dialogClosedByKeyword']);
        $t->same('auto', $plain['dialogClosedByState']);
        $t->same(null, $plain['dialogClosedByValid']);
        $t->same(true, $plain['dialogClosedByDefaulted']);
        $t->same(true, $plain['dialogClosedByAutoState']);
        $t->same('missing-value-default', $plain['dialogClosedByAutoReason']);
        $t->same('closerequest', $plain['dialogClosedByAutoModalState']);
        $t->same('none', $plain['dialogClosedByAutoModelessState']);
        $t->same('none', $plain['dialogClosedByComputedStaticState']);
        $t->same(true, $plain['dialogDeveloperCloseRequired']);
        $t->same([], $plain['dialogClosedByIssues']);
        $t->same([], $plain['dialogClosedByIssueCodes']);

        $t->same('p', $notDialog['name']);
        $t->true(!array_key_exists('dialogClosedByReviewPolicy', $notDialog));
        $t->true(!array_key_exists('dialogClosedByState', $notDialog));

        $t->contains('<dialog closedby="Any" id="light" open>', $html);
        $t->contains('<dialog closedby="dismiss" id="bad">Bad policy</dialog>', $html);
        $t->contains('<p closedby="none" id="not-dialog">Plain element</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/dialog-closedby-review.html', $document->children[0]->attr('part'));
    },
];
