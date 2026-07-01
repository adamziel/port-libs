<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html label control association diagnostics for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<label id="explicit" for="email">Email address</label>'
                . '<input id="email" name="email" value="a@example.test">'
                . '<label id="invalid" for="missing id">Broken <input id="nested" name="nested" value="x"></label>'
                . '<label id="dupe-label" for="dupe">Duplicate</label><div id="dupe">Not control</div><input id="dupe" name="dupe" value="ok">'
                . '<label id="multi">Choice <input id="first" name="first"><select id="second" name="second"><option selected>Two</option></select></label>'
                . '<label id="empty">Standalone</label>',
            'label control association review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/label-control-association-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $explicit = $summary[0];
        $email = $summary[1];
        $invalid = $summary[2];
        $duplicate = $summary[3];
        $multi = $summary[6];
        $empty = $summary[7];

        $t->same('html-label-control-association-review', $explicit['labelAssociationReviewPolicy']);
        $t->same('explicit', $explicit['labelAssociationMode']);
        $t->same('resolved-explicit', $explicit['labelAssociationState']);
        $t->same(true, $explicit['labelAssociationResolved']);
        $t->same('email', $explicit['labelAssociatedControlId']);
        $t->same('email', $explicit['labelForReferenceId']);
        $t->same(true, $explicit['labelForReferenceValid']);
        $t->same(1, $explicit['labelForTargetCount']);
        $t->same(1, $explicit['labelForLabelableTargetCount']);
        $t->same(true, $explicit['labelForFirstTargetLabelable']);
        $t->same(['input'], $explicit['labelForTargetElementNames']);
        $t->same(true, $explicit['labelForTargets'][0]['selectedForAssociation']);
        $t->same([], $explicit['labelAssociationIssueCodes']);
        $t->same(true, $explicit['labelAssociationValid']);
        $t->same(['Email address'], $email['labels']);

        $t->same('missing-for-target', $invalid['labeledControlSource']);
        $t->same('invalid-reference', $invalid['labelAssociationState']);
        $t->same(false, $invalid['labelAssociationResolved']);
        $t->same(false, $invalid['labelForReferenceValid']);
        $t->same(0, $invalid['labelForTargetCount']);
        $t->same(['nested'], $invalid['labelNestedControlIds']);
        $t->same(1, $invalid['labelNestedUnassociatedControlCount']);
        $t->same([
            'invalid-label-for-reference',
            'missing-label-for-target',
            'label-for-with-nested-unassociated-control',
        ], $invalid['labelAssociationIssueCodes']);
        $t->same(false, $invalid['labelAssociationValid']);

        $t->same('non-labelable-target', $duplicate['labelAssociationState']);
        $t->same(true, $duplicate['labelForReferenceValid']);
        $t->same(2, $duplicate['labelForTargetCount']);
        $t->same(1, $duplicate['labelForLabelableTargetCount']);
        $t->same(false, $duplicate['labelForFirstTargetLabelable']);
        $t->same(['div', 'input'], $duplicate['labelForTargetElementNames']);
        $t->same([
            'non-labelable-label-for-target',
            'duplicate-label-for-target-id',
        ], $duplicate['labelAssociationIssueCodes']);
        $t->same(false, $duplicate['labelAssociationResolved']);

        $t->same('implicit', $multi['labelAssociationMode']);
        $t->same('resolved-implicit', $multi['labelAssociationState']);
        $t->same('first', $multi['labelAssociatedControlId']);
        $t->same(['first', 'second'], $multi['labelNestedControlIds']);
        $t->same(2, $multi['labelNestedControlCount']);
        $t->same(['multiple-nested-labelable-controls'], $multi['labelAssociationIssueCodes']);
        $t->same(false, $multi['labelAssociationValid']);

        $t->same('none', $empty['labelAssociationMode']);
        $t->same('missing-control', $empty['labelAssociationState']);
        $t->same(['missing-label-control'], $empty['labelAssociationIssueCodes']);
        $t->same(false, $empty['labelAssociationValid']);

        $t->contains('for="missing id"', $html);
        $t->contains('id="dupe"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/label-control-association-review.html', $document->children[0]->attr('part'));
        json_encode([$explicit, $invalid, $duplicate, $multi, $empty], JSON_THROW_ON_ERROR);
    },
];
