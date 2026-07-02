<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html form control constraint issue codes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="constraints">'
                . '<input id="slug" name="slug" value="draft" minlength="5" maxlength="3" min="10" max="2" step="0" size="0" dirname="bad name" autocomplete="email EMAIL">'
                . '<textarea id="notes" name="notes" minlength="bad" maxlength="4" dirname="notes.dir" autocomplete="bad&lt;tag">Copy</textarea>'
                . '<select id="choices" name="choices" multiple size="2" autocomplete="off"><option selected>A</option><option>B</option></select>'
                . '</form>',
            'form control constraint issue-code review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-constraint-issue-codes.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $slug = $form['children'][0];
        $notes = $form['children'][1];
        $choices = $form['children'][2];

        $t->same('html-form-control-constraint-attribute-review', $slug['formControlConstraintReviewPolicy']);
        $t->same(['autocomplete', 'dirname', 'max', 'maxlength', 'min', 'minlength', 'size', 'step'], $slug['formControlConstraintAttributes']);
        $t->same(false, $slug['formControlConstraintValid']);
        $t->same(false, $slug['formControlConstraintConforming']);
        $t->same(6, $slug['formControlConstraintIssueCount']);
        $t->same([
            'form-control-minlength-exceeds-maxlength',
            'form-control-min-exceeds-max',
            'invalid-form-control-step',
            'invalid-form-control-dirname',
            'invalid-form-control-size',
            'duplicate-form-control-autocomplete-token',
        ], $slug['formControlConstraintIssueCodes']);
        $t->same('form-control-minlength-exceeds-maxlength', $slug['formControlConstraintIssues'][0]['code']);
        $t->same(5, $slug['minLength']);
        $t->same(3, $slug['maxLength']);
        $t->same(false, $slug['lengthRangeValid']);
        $t->same(10.0, $slug['constraintMin']);
        $t->same(2.0, $slug['constraintMax']);
        $t->same(false, $slug['constraintRangeValid']);
        $t->same('0', $slug['constraintStepRaw']);
        $t->same(false, $slug['constraintStepValid']);
        $t->same('bad name', $slug['dirname']);
        $t->same(false, $slug['dirnameValid']);
        $t->same('0', $slug['controlSizeRaw']);
        $t->same(false, $slug['controlSizeValid']);
        $t->same(['email'], $slug['duplicateAutocompleteTokens']);
        $t->same(['duplicate-form-control-autocomplete-token'], $slug['autocompleteIssueCodes']);

        $t->same('html-form-control-constraint-attribute-review', $notes['formControlConstraintReviewPolicy']);
        $t->same(['autocomplete', 'dirname', 'maxlength', 'minlength'], $notes['formControlConstraintAttributes']);
        $t->same('bad', $notes['minLengthRaw']);
        $t->same(false, $notes['minLengthValid']);
        $t->same(4, $notes['maxLength']);
        $t->same(null, $notes['lengthRangeValid']);
        $t->same('notes.dir', $notes['dirname']);
        $t->same(true, $notes['dirnameValid']);
        $t->same([
            'invalid-form-control-minlength',
            'invalid-form-control-autocomplete-token',
        ], $notes['formControlConstraintIssueCodes']);
        $t->same(2, $notes['formControlConstraintIssueCount']);
        $t->same(['bad<tag'], $notes['invalidAutocompleteTokens']);
        $t->same(false, $notes['autocompleteValid']);

        $t->same('html-form-control-constraint-attribute-review', $choices['formControlConstraintReviewPolicy']);
        $t->same(['autocomplete', 'multiple', 'size'], $choices['formControlConstraintAttributes']);
        $t->same(true, $choices['multiple']);
        $t->same(2, $choices['controlSize']);
        $t->same('off', $choices['autocompleteState']);
        $t->same([], $choices['formControlConstraintIssueCodes']);
        $t->same(0, $choices['formControlConstraintIssueCount']);
        $t->same(true, $choices['formControlConstraintValid']);
        $t->same(['A'], $choices['selectedValues']);

        $t->contains('dirname="bad name"', $html);
        $t->contains('autocomplete="bad&lt;tag"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-constraint-issue-codes.html', $document->children[0]->attr('part'));
        json_encode([$slug, $notes, $choices], JSON_THROW_ON_ERROR);
    },
];
