<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html select static selection provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="review">'
                . '<select id="format" name="format" required><option value="">Choose format</option><option value="docx">DOCX</option></select>'
                . '<select id="conflict" name="state"><option selected value="draft">Draft</option><option selected value="review">Review</option></select>'
                . '<select id="multi" name="targets" multiple required><option selected value="docx">DOCX</option><option selected disabled value="epub">EPUB</option><option value="odt">ODT</option></select>'
                . '<select id="disabled-only" name="locked" required><option selected disabled value="locked">Locked</option></select>'
                . '<select id="sized" name="sized" required size="2"><option value="">Empty</option><option value="ok">OK</option></select>'
                . '</form>',
            'select static selection review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/select-selection-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $required = $form['children'][0];
        $conflict = $form['children'][1];
        $multiple = $form['children'][2];
        $disabledOnly = $form['children'][3];
        $sized = $form['children'][4];

        $t->same('select-option-selection-review', $required['selectSelectionReviewPolicy']);
        $t->same(true, $required['selectRequired']);
        $t->same(false, $required['selectMultiple']);
        $t->same(2, $required['selectOptionCount']);
        $t->same(0, $required['selectExplicitSelectedCount']);
        $t->same([], $required['selectExplicitSelectedValues']);
        $t->same(1, $required['selectEffectiveSelectedCount']);
        $t->same([''], $required['selectEffectiveSelectedValues']);
        $t->same([0], $required['selectEffectiveSelectedIndexes']);
        $t->same(true, $required['selectPlaceholderOptionPresent']);
        $t->same(0, $required['selectPlaceholderOption']['index']);
        $t->same('Choose format', $required['selectPlaceholderOption']['text']);
        $t->same(true, $required['selectPlaceholderSelected']);
        $t->same(true, $required['selectValueMissing']);
        $t->same([
            'required-select-placeholder-selected',
            'required-select-empty-value',
        ], $required['selectSelectionIssueCodes']);
        $t->same(false, $required['selectSelectionValid']);

        $t->same(['draft', 'review'], $conflict['selectedValues']);
        $t->same(2, $conflict['selectExplicitSelectedCount']);
        $t->same(['draft', 'review'], $conflict['selectExplicitSelectedValues']);
        $t->same(['review'], $conflict['selectEffectiveSelectedValues']);
        $t->same([1], $conflict['selectEffectiveSelectedIndexes']);
        $t->same(null, $conflict['selectValueMissing']);
        $t->same(['single-select-multiple-selected-options'], $conflict['selectSelectionIssueCodes']);
        $t->same(false, $conflict['selectSelectionValid']);

        $t->same(true, $multiple['selectMultiple']);
        $t->same(true, $multiple['selectRequired']);
        $t->same(4, $multiple['selectDisplaySize']);
        $t->same(['docx', 'epub'], $multiple['selectExplicitSelectedValues']);
        $t->same(['docx', 'epub'], $multiple['selectEffectiveSelectedValues']);
        $t->same(1, $multiple['selectSelectedEnabledValueCount']);
        $t->same(1, $multiple['selectSelectedDisabledValueCount']);
        $t->same(['epub'], $multiple['selectSelectedDisabledValues']);
        $t->same(false, $multiple['selectValueMissing']);
        $t->same([], $multiple['selectSelectionIssueCodes']);
        $t->same(true, $multiple['selectSelectionValid']);

        $t->same(['locked'], $disabledOnly['selectEffectiveSelectedValues']);
        $t->same(0, $disabledOnly['selectSelectedEnabledValueCount']);
        $t->same(1, $disabledOnly['selectSelectedDisabledValueCount']);
        $t->same(['locked'], $disabledOnly['selectSelectedDisabledValues']);
        $t->same(true, $disabledOnly['selectValueMissing']);
        $t->same(['required-select-disabled-selection'], $disabledOnly['selectSelectionIssueCodes']);
        $t->same(false, $disabledOnly['selectSelectionValid']);

        $t->same('2', $sized['selectSizeRaw']);
        $t->same(2, $sized['selectSize']);
        $t->same(true, $sized['selectSizeValid']);
        $t->same(2, $sized['selectDisplaySize']);
        $t->same(false, $sized['selectPlaceholderOptionPresent']);
        $t->same([''], $sized['selectEffectiveSelectedValues']);
        $t->same(['required-select-empty-value'], $sized['selectSelectionIssueCodes']);
        $t->same(true, $sized['selectValueMissing']);

        $t->contains('id="format"', $html);
        $t->contains('multiple', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/select-selection-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
