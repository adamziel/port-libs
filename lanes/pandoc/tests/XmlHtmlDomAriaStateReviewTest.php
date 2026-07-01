<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html aria state tokens and numeric values for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="status" role="region" aria-describedby="note" aria-expanded="TRUE" aria-current="PAGE" aria-sort="descending" aria-level="02" aria-valuenow=" 42.500 " aria-valuemin=".5" aria-valuemax="100.0" aria-busy="maybe" aria-relevant="additions text additions bogus">'
                . '<h2 id="note">Import status</h2></section>'
                . '<button id="toggle" aria-pressed="mixed" aria-haspopup="dialog" aria-checked="maybe true" aria-colcount="-1" aria-rowindex="0">Toggle</button>',
            'ARIA state review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/aria-state-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $section = $summary[0];
        $button = $summary[1];

        $t->same('html-aria-state-token-review', $section['ariaStateReviewPolicy']);
        $t->same([
            'aria-busy',
            'aria-current',
            'aria-expanded',
            'aria-level',
            'aria-relevant',
            'aria-sort',
            'aria-valuemax',
            'aria-valuemin',
            'aria-valuenow',
        ], $section['ariaStateAttributes']);
        $t->same([
            'aria-current' => 'page',
            'aria-expanded' => 'true',
            'aria-level' => '2',
            'aria-relevant' => 'additions text',
            'aria-sort' => 'descending',
            'aria-valuemax' => '100',
            'aria-valuemin' => '0.5',
            'aria-valuenow' => '42.5',
        ], $section['ariaStateValues']);
        $t->same([
            'invalid-aria-state-token',
            'duplicate-aria-state-token',
        ], $section['ariaStateIssueCodes']);
        $t->same(false, $section['ariaStateValid']);

        $busy = $section['ariaStateRecords'][0];
        $current = $section['ariaStateRecords'][1];
        $level = $section['ariaStateRecords'][3];
        $relevant = $section['ariaStateRecords'][4];
        $valueNow = $section['ariaStateRecords'][8];

        $t->same('aria-busy', $busy['attribute']);
        $t->same('maybe', $busy['raw']);
        $t->same(null, $busy['state']);
        $t->same(['maybe'], $busy['invalidTokens']);
        $t->same(['invalid-aria-state-token'], $busy['issueCodes']);
        $t->same(false, $busy['valid']);

        $t->same('aria-current', $current['attribute']);
        $t->same('PAGE', $current['raw']);
        $t->same('page', $current['state']);
        $t->same(['PAGE'], $current['tokens']);
        $t->same(['page'], $current['states']);
        $t->same(true, $current['valid']);

        $t->same('integer', $level['kind']);
        $t->same('02', $level['raw']);
        $t->same('2', $level['state']);
        $t->same(2, $level['integer']);
        $t->same(true, $level['valid']);

        $t->same('token-list', $relevant['kind']);
        $t->same(['additions', 'text', 'additions', 'bogus'], $relevant['tokens']);
        $t->same(['additions', 'text'], $relevant['states']);
        $t->same('additions text', $relevant['state']);
        $t->same(['bogus'], $relevant['invalidTokens']);
        $t->same(['additions'], $relevant['duplicateTokens']);
        $t->same([
            'invalid-aria-state-token',
            'duplicate-aria-state-token',
        ], $relevant['issueCodes']);

        $t->same('number', $valueNow['kind']);
        $t->same(' 42.500 ', $valueNow['raw']);
        $t->same('42.5', $valueNow['state']);
        $t->same(42.5, $valueNow['number']);
        $t->same(true, $valueNow['valid']);

        $t->same('html-aria-state-token-review', $button['ariaStateReviewPolicy']);
        $t->same([
            'aria-checked',
            'aria-colcount',
            'aria-haspopup',
            'aria-pressed',
            'aria-rowindex',
        ], $button['ariaStateAttributes']);
        $t->same([
            'aria-checked' => 'true',
            'aria-colcount' => '-1',
            'aria-haspopup' => 'dialog',
            'aria-pressed' => 'mixed',
        ], $button['ariaStateValues']);
        $t->same([
            'invalid-aria-state-token',
            'multiple-aria-state-tokens',
            'out-of-range-aria-integer',
        ], $button['ariaStateIssueCodes']);
        $t->same(false, $button['ariaStateValid']);

        $checked = $button['ariaStateRecords'][0];
        $colCount = $button['ariaStateRecords'][1];
        $rowIndex = $button['ariaStateRecords'][4];
        $t->same(['maybe', 'true'], $checked['tokens']);
        $t->same(['true'], $checked['states']);
        $t->same('true', $checked['state']);
        $t->same([
            'invalid-aria-state-token',
            'multiple-aria-state-tokens',
        ], $checked['issueCodes']);
        $t->same('-1', $colCount['state']);
        $t->same(-1, $colCount['integer']);
        $t->same(true, $colCount['allowsMinusOne']);
        $t->same(true, $colCount['valid']);
        $t->same('aria-rowindex', $rowIndex['attribute']);
        $t->same(null, $rowIndex['state']);
        $t->same(['out-of-range-aria-integer'], $rowIndex['issueCodes']);
        $t->same(false, $rowIndex['valid']);

        $t->same(['aria-describedby'], $section['ariaReferenceAttributes']);
        $t->same(true, $section['ariaReferencesResolved']);
        $t->contains('aria-relevant="additions text additions bogus"', $html);
        $t->contains('aria-checked="maybe true"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/aria-state-review.html', $document->children[0]->attr('part'));
    },
];
