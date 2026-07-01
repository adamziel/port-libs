<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html selectedcontent select bridge for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<select id="status" name="status"><button type="button"><selectedcontent></selectedcontent></button>'
                . '<option value="draft">Draft</option><option selected value="review" label="Ready for review">Review</option></select>'
                . '<select id="dupe"><button><selectedcontent>Fallback</selectedcontent><selectedcontent></selectedcontent></button>'
                . '<option selected value="a">A</option><option selected value="b">B</option></select>'
                . '<selectedcontent id="loose">Loose</selectedcontent>',
            'selectedcontent review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/selectedcontent-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $statusSelectedContent = $summary[0]['children'][0]['children'][0];
        $dupeFirst = $summary[1]['children'][0]['children'][0];
        $dupeSecond = $summary[1]['children'][0]['children'][1];
        $loose = $summary[2];

        $t->same('selectedcontent', $statusSelectedContent['name']);
        $t->same('html-select-selectedcontent-review', $statusSelectedContent['selectedContentReviewPolicy']);
        $t->same('', $statusSelectedContent['selectedContentStaticText']);
        $t->same(true, $statusSelectedContent['selectedContentInSelect']);
        $t->same('status', $statusSelectedContent['selectedContentSelectId']);
        $t->same('status', $statusSelectedContent['selectedContentSelectName']);
        $t->same(false, $statusSelectedContent['selectedContentSelectMultiple']);
        $t->same(true, $statusSelectedContent['selectedContentInButton']);
        $t->same(1, $statusSelectedContent['selectedContentElementIndex']);
        $t->same(1, $statusSelectedContent['selectedContentCount']);
        $t->same(2, $statusSelectedContent['selectedContentOptionCount']);
        $t->same(1, $statusSelectedContent['selectedContentSelectedOptionCount']);
        $t->same(['review'], $statusSelectedContent['selectedContentSelectedValues']);
        $t->same(['Ready for review'], $statusSelectedContent['selectedContentSelectedLabels']);
        $t->same(['Review'], $statusSelectedContent['selectedContentSelectedTexts']);
        $t->same('Review', $statusSelectedContent['selectedContentEffectiveText']);
        $t->same([
            ['value' => 'review', 'label' => 'Ready for review', 'text' => 'Review', 'selected' => true, 'disabled' => false],
        ], $statusSelectedContent['selectedContentSelectedOptions']);
        $t->same([], $statusSelectedContent['selectedContentIssueCodes']);
        $t->same(true, $statusSelectedContent['selectedContentValid']);

        $t->same('Fallback', $dupeFirst['selectedContentStaticText']);
        $t->same(1, $dupeFirst['selectedContentElementIndex']);
        $t->same(2, $dupeSecond['selectedContentElementIndex']);
        $t->same(2, $dupeFirst['selectedContentCount']);
        $t->same(['a', 'b'], $dupeFirst['selectedContentSelectedValues']);
        $t->same(['A', 'B'], $dupeFirst['selectedContentSelectedTexts']);
        $t->same('A B', $dupeFirst['selectedContentEffectiveText']);
        $t->same([
            'duplicate-selectedcontent-element',
            'multiple-selected-options-for-single-select',
        ], $dupeFirst['selectedContentIssueCodes']);
        $t->same(false, $dupeFirst['selectedContentValid']);

        $t->same('selectedcontent', $loose['selectedContent']);
        $t->same('Loose', $loose['selectedContentStaticText']);
        $t->same(false, $loose['selectedContentInSelect']);
        $t->same(null, $loose['selectedContentSelectId']);
        $t->same(false, $loose['selectedContentInButton']);
        $t->same(null, $loose['selectedContentEffectiveText']);
        $t->same([
            'selectedcontent-missing-select-ancestor',
            'selectedcontent-outside-select-button',
        ], $loose['selectedContentIssueCodes']);

        $t->contains($html, $blocks);
        $t->same('/migration/selectedcontent-review.html', $document->children[0]->attr('part'));
        $t->same(
            '<select id="status" name="status"><button type="button"><selectedcontent></selectedcontent></button>'
                . '<option value="draft">Draft</option><option label="Ready for review" selected value="review">Review</option></select>'
                . '<select id="dupe"><button><selectedcontent>Fallback</selectedcontent><selectedcontent></selectedcontent></button>'
                . '<option selected value="a">A</option><option selected value="b">B</option></select>'
                . '<selectedcontent id="loose">Loose</selectedcontent>',
            $html
        );
    },
];
