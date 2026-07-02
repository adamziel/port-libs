<?php

declare(strict_types=1);

use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html fragment id definitions and idrefs for reader handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="intro"><h2 id="title">Title</h2><p id="dup">First duplicate</p><aside id="dup">Second duplicate</aside><span id="bad id">Bad id</span></section>'
                . '<label for="email">Email</label><input id="email" form="checkout" list="countries" value="name"><datalist id="countries"></datalist><form id="checkout"></form>'
                . '<output id="total" for="email missing email bad&lt;token">Total</output>'
                . '<button id="open" popovertarget="panel" commandfor="dialog">Open</button><div id="panel" popover>Panel</div><dialog id="dialog">Dialog</dialog>'
                . '<div id="described" aria-labelledby="title missing dup dup" aria-controls="panel bad&lt;target" aria-details="detail"></div><p id="detail">Detail</p>'
                . '<table><tr><th id="head">Head</th><td headers="head missing-head dup">Cell</td></tr></table>',
            'fragment id reference review'
        );

        $packet = XmlHtmlDom::summarizeHtmlFragmentIdReferences($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);

        $t->same('xml-html5-dom', $packet['formatFamily']);
        $t->same('html', $packet['format']);
        $t->same('html-fragment-id-idref-review', $packet['idReferenceReviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same(['html-fragment-id-reference-review-only'], $packet['directReaderDiagnosticCodes']);
        $t->same(15, $packet['idDefinitionCount']);
        $t->same(14, $packet['validIdDefinitionCount']);
        $t->same(1, $packet['invalidIdDefinitionCount']);
        $t->same(1, $packet['duplicateIdDefinitionCount']);
        $t->same(2, $packet['duplicateIdDefinitionOccurrenceCount']);
        $t->same(['dup'], $packet['duplicateIdDefinitionIds']);
        $t->same('bad id', $packet['invalidIdDefinitions'][0]['idRaw'] ?? null);
        $t->same('span', $packet['invalidIdDefinitions'][0]['element'] ?? null);
        $t->same('section[1]/span[1]', $packet['invalidIdDefinitions'][0]['elementPath'] ?? null);

        $t->same(10, $packet['idReferenceCount']);
        $t->same(6, $packet['resolvedIdReferenceCount']);
        $t->same(4, $packet['unresolvedIdReferenceCount']);
        $t->same(19, $packet['idReferenceTokenCount']);
        $t->same([
            'for',
            'form',
            'list',
            'popovertarget',
            'commandfor',
            'aria-controls',
            'aria-details',
            'aria-labelledby',
            'headers',
        ], $packet['idReferenceAttributeNames']);
        $t->same([
            'label-for',
            'form-owner',
            'datalist',
            'output-for',
            'popover-target',
            'button-command-target',
            'aria-id-reference',
            'table-headers',
        ], $packet['idReferenceKinds']);
        $t->same(['missing', 'missing-head'], $packet['missingReferenceIds']);
        $t->same(['bad<token', 'bad<target'], $packet['invalidReferenceTokens']);
        $t->same(['email', 'dup'], $packet['duplicateReferenceTokens']);
        $t->same(['dup'], $packet['duplicateReferenceTargetIds']);
        $t->same([
            'invalid-html-id-definition',
            'duplicate-html-id-definition',
            'missing-html-id-reference-target',
            'duplicate-html-id-reference-token',
            'invalid-html-id-reference-token',
            'duplicate-html-id-reference-target',
        ], $packet['idReferencePacketIssueCodes']);
        $t->same(false, $packet['idReferencePacketValid']);

        $output = $packet['idReferences'][3];
        $t->same('output', $output['element']);
        $t->same('for', $output['attribute']);
        $t->same('output-for', $output['kind']);
        $t->same(true, $output['multiple']);
        $t->same(['email', 'missing', 'email', 'bad<token'], $output['tokens']);
        $t->same(['email'], $output['resolvedIds']);
        $t->same(['missing'], $output['missingIds']);
        $t->same(['bad<token'], $output['invalidTokens']);
        $t->same(['email'], $output['duplicateTokens']);
        $t->same([
            'missing-html-id-reference-target',
            'duplicate-html-id-reference-token',
            'invalid-html-id-reference-token',
        ], $output['issueCodes']);
        $t->same('missing-target', $output['references'][1]['state'] ?? null);
        $t->same('invalid-token', $output['references'][3]['state'] ?? null);

        $labelledBy = $packet['idReferences'][8];
        $t->same('aria-labelledby', $labelledBy['attribute']);
        $t->same(['title', 'missing', 'dup', 'dup'], $labelledBy['tokens']);
        $t->same(['dup'], $labelledBy['duplicateTargetIds']);
        $t->same('duplicate-target-id', $labelledBy['references'][2]['state'] ?? null);
        $t->same(2, $labelledBy['references'][2]['targetCount'] ?? null);
        $t->same('section[1]/p[1]', $labelledBy['references'][2]['targets'][0]['elementPath'] ?? null);
        $t->same('section[1]/aside[1]', $labelledBy['references'][2]['targets'][1]['elementPath'] ?? null);

        $headers = $packet['idReferences'][9];
        $t->same('headers', $headers['attribute']);
        $t->same('table-headers', $headers['kind']);
        $t->same(['head', 'missing-head', 'dup'], $headers['tokens']);
        $t->same(['head'], $headers['resolvedIds']);
        $t->same(['missing-head'], $headers['missingIds']);
        $t->same(['dup'], $headers['duplicateTargetIds']);

        $t->contains('for="email missing email bad&lt;token"', $html);
        $t->contains('aria-controls="panel bad&lt;target"', $html);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
];
