<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html label control association diagnostics for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="profile">'
                . '<label for="email">Email</label><input id="email" name="email" type="email" value="editor@example.test">'
                . '<label id="nested">Name <input id="name" name="name" value="Ada"></label>'
                . '<label for="missing">Missing</label>'
                . '<label for="bad id">Bad reference</label>'
                . '<label for="dup">Duplicate</label><input id="dup" name="first"><button id="dup">Second</button>'
                . '<label for="panel">Panel</label><span id="panel">Not a control</span>'
                . '<label for="mixed">Mixed <input id="nested-conflict" name="nested"></label><textarea id="mixed" name="mixed">Text</textarea>'
                . '<label id="multi"><input id="one" name="one"><button id="two">Two</button></label>'
                . '</form>',
            'label association review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/label-association-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $email = $form['children'][0];
        $nested = $form['children'][2];
        $missing = $form['children'][3];
        $bad = $form['children'][4];
        $duplicate = $form['children'][5];
        $nonLabelable = $form['children'][8];
        $mixed = $form['children'][10];
        $multi = $form['children'][12];

        $t->same('form-label-control-association-review', $email['labelReviewPolicy']);
        $t->same('for-attribute', $email['labelControlAssociationState']);
        $t->same(true, $email['labelControlAssociated']);
        $t->same(true, $email['labelForPresent']);
        $t->same('email', $email['labelForId']);
        $t->same(true, $email['labelForValid']);
        $t->same(1, $email['labelForTargetCount']);
        $t->same(1, $email['labelForLabelableTargetCount']);
        $t->same('input', $email['labelForTargets'][0]['tag']);
        $t->same('email', $email['labelForTargets'][0]['id']);
        $t->same(true, $email['labelForTargets'][0]['selected']);
        $t->same('email', $email['labeledControl']['type']);
        $t->same([], $email['labelIssueCodes']);
        $t->same(true, $email['labelValid']);
        $t->same(true, $email['labelReviewOnlyNoFormSubmission']);

        $t->same('descendant', $nested['labelControlAssociationState']);
        $t->same(false, $nested['labelForPresent']);
        $t->same(null, $nested['labelForValid']);
        $t->same(1, $nested['labelNestedControlCount']);
        $t->same('name', $nested['labeledControl']['id']);
        $t->same([], $nested['labelIssueCodes']);

        $t->same('missing-for-target', $missing['labelControlAssociationState']);
        $t->same(false, $missing['labelControlAssociated']);
        $t->same('missing-for-target', $missing['labeledControlSource']);
        $t->same(0, $missing['labelForTargetCount']);
        $t->same(['missing-label-for-target'], $missing['labelIssueCodes']);
        $t->same(false, $missing['labelValid']);

        $t->same('invalid-for-reference', $bad['labelControlAssociationState']);
        $t->same(false, $bad['labelForValid']);
        $t->same(['invalid-label-for-reference'], $bad['labelIssueCodes']);

        $t->same('duplicate-for-target-id', $duplicate['labelControlAssociationState']);
        $t->same(true, $duplicate['labelControlAssociated']);
        $t->same(2, $duplicate['labelForTargetCount']);
        $t->same(2, $duplicate['labelForLabelableTargetCount']);
        $t->same(['input', 'button'], array_map(static fn (array $target): string => (string) $target['tag'], $duplicate['labelForTargets']));
        $t->same([true, false], array_map(static fn (array $target): bool => (bool) $target['selected'], $duplicate['labelForTargets']));
        $t->same(['duplicate-label-for-target-id'], $duplicate['labelIssueCodes']);

        $t->same('non-labelable-for-target', $nonLabelable['labelControlAssociationState']);
        $t->same(1, $nonLabelable['labelForTargetCount']);
        $t->same(0, $nonLabelable['labelForLabelableTargetCount']);
        $t->same('span', $nonLabelable['labelForTargets'][0]['tag']);
        $t->same(false, $nonLabelable['labelForTargets'][0]['labelable']);
        $t->same(['non-labelable-label-for-target'], $nonLabelable['labelIssueCodes']);

        $t->same('for-and-nested-control', $mixed['labelControlAssociationState']);
        $t->same('mixed', $mixed['labeledControl']['id']);
        $t->same('nested-conflict', $mixed['nestedControls'][0]['id']);
        $t->same(['label-for-with-nested-control'], $mixed['labelIssueCodes']);

        $t->same('multiple-nested-controls', $multi['labelControlAssociationState']);
        $t->same('one', $multi['labeledControl']['id']);
        $t->same(2, $multi['labelNestedControlCount']);
        $t->same(['label-multiple-nested-controls'], $multi['labelIssueCodes']);

        $t->contains('<label for="email">Email</label>', $html);
        $t->contains('<label for="mixed">Mixed <input id="nested-conflict" name="nested"></label>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/label-association-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
