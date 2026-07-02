<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html form control constraint issue codes for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="constraints">'
                . '<input id="valid-slug" name="slug" type="text" value="post-42" minlength="3" maxlength="12" pattern="[a-z0-9-]+" autocomplete="section-review shipping url" dirname="slug.dir" readonly size="24">'
                . '<input id="bad-number" name="bad" type="number" min="9" max="2" step="0" value="4">'
                . '<textarea id="bad-text" name="text" minlength="10" maxlength="5" dirname="bad name" autocomplete="on email email unknown-token bad&lt;token" required>Text</textarea>'
                . '<select id="bad-select" name="choice" multiple size="0" autocomplete=""><option>A</option></select>'
                . '<input id="publish-date" name="date" type="date" value="2026-07-01" min="2026-01-01" max="2026-12-31">'
                . '</form>',
            'form control constraint issue review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/form-control-constraint-issue-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $form = $summary[0];
        $validSlug = $form['children'][0];
        $badNumber = $form['children'][1];
        $badText = $form['children'][2];
        $badSelect = $form['children'][3];
        $publishDate = $form['children'][4];

        $t->same('html-form-control-constraint-attribute-review', $validSlug['constraintReviewPolicy']);
        $t->same('form-control', $validSlug['constraintValidation']);
        $t->same(['autocomplete', 'dirname', 'maxlength', 'minlength', 'pattern', 'size', 'readonly'], $validSlug['constraintAttributeNames']);
        $t->same(7, $validSlug['constraintAttributeCount']);
        $t->same([], $validSlug['constraintIssues']);
        $t->same([], $validSlug['constraintIssueCodes']);
        $t->same(0, $validSlug['constraintIssueCount']);
        $t->same(true, $validSlug['constraintValid']);
        $t->same(true, $validSlug['constraintReviewOnlyNoBrowserStateMutation']);

        $t->same('number', $badNumber['inputType']);
        $t->same(9.0, $badNumber['constraintMin']);
        $t->same(2.0, $badNumber['constraintMax']);
        $t->same(false, $badNumber['constraintRangeValid']);
        $t->same(null, $badNumber['constraintStep']);
        $t->same(false, $badNumber['constraintStepValid']);
        $t->same(['constraint-min-exceeds-max', 'invalid-constraint-step'], $badNumber['constraintIssueCodes']);
        $t->same('constraint-min-exceeds-max', $badNumber['constraintIssues'][0]['code']);
        $t->same('max', $badNumber['constraintIssues'][0]['attribute']);
        $t->same(false, $badNumber['constraintValid']);

        $t->same('textarea', $badText['formControl']);
        $t->same(false, $badText['lengthRangeValid']);
        $t->same('bad name', $badText['dirnameRaw']);
        $t->same(false, $badText['dirnameValid']);
        $t->same(['bad<token'], $badText['invalidAutocompleteTokens']);
        $t->same(['email'], $badText['duplicateAutocompleteTokens']);
        $t->same(['unknown-token'], $badText['autocompleteUnknownTokens']);
        $t->same([
            'constraint-minlength-exceeds-maxlength',
            'invalid-form-control-autocomplete-token',
            'duplicate-form-control-autocomplete-token',
            'unknown-form-control-autocomplete-token',
            'state-form-control-autocomplete-token-with-details',
            'invalid-constraint-dirname',
        ], $badText['constraintIssueCodes']);
        $t->same('maxlength', $badText['constraintIssues'][0]['attribute']);
        $t->same('autocomplete', $badText['constraintIssues'][1]['attribute']);
        $t->same('dirname', $badText['constraintIssues'][5]['attribute']);
        $t->same(6, $badText['constraintIssueCount']);
        $t->same(false, $badText['constraintValid']);

        $t->same('select', $badSelect['formControl']);
        $t->same(true, $badSelect['multiple']);
        $t->same('', $badSelect['autocompleteRaw']);
        $t->same(false, $badSelect['autocompleteValid']);
        $t->same('0', $badSelect['controlSizeRaw']);
        $t->same(false, $badSelect['controlSizeValid']);
        $t->same(['empty-form-control-autocomplete', 'invalid-constraint-size'], $badSelect['constraintIssueCodes']);
        $t->same(false, $badSelect['constraintValid']);

        $t->same('date', $publishDate['inputType']);
        $t->same('2026-01-01', $publishDate['constraintMinRaw']);
        $t->same(false, $publishDate['constraintMinValid']);
        $t->same('2026-01-01', $publishDate['typedInputMin']);
        $t->same(true, $publishDate['typedInputMinValid']);
        $t->same(true, $publishDate['typedInputRangeValid']);
        $t->same([], $publishDate['constraintIssueCodes']);
        $t->same(true, $publishDate['constraintValid']);
        $t->same([], $publishDate['typedInputIssueCodes']);

        $t->contains('<input id="bad-number" max="2" min="9" name="bad" step="0" type="number" value="4">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/form-control-constraint-issue-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
