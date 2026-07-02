<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html ordered list marker ordinal review metadata' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<ol id="agenda" start="3" type="A"><li>Plan</li><li value="7">Review</li><li>Ship</li></ol>'
                . '<ol id="reverse" reversed><li>Third</li><li value="1">First</li><li>Zero</li></ol>'
                . '<ol id="bad" start="soon" type="z"><li value="2">Alpha</li><li value="2">Beta</li><li value="oops">Gamma</li></ol>',
            'ordered list marker ordinal review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/ordered-list-marker-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $agenda = $summary[0];
        $agendaPlan = $agenda['children'][0];
        $agendaReview = $agenda['children'][1];
        $agendaShip = $agenda['children'][2];
        $reverse = $summary[1];
        $bad = $summary[2];

        $t->same('html-ordered-list-marker-ordinal-review', $agenda['orderedListReviewPolicy']);
        $t->same(false, $agenda['orderedListReversed']);
        $t->same('3', $agenda['orderedListStartRaw']);
        $t->same(3, $agenda['orderedListStart']);
        $t->same(true, $agenda['orderedListStartValid']);
        $t->same('A', $agenda['orderedListTypeRaw']);
        $t->same('A', $agenda['orderedListMarkerType']);
        $t->same(true, $agenda['orderedListMarkerTypeValid']);
        $t->same(3, $agenda['orderedListItemCount']);
        $t->same(1, $agenda['orderedListExplicitValueCount']);
        $t->same([3, 7, 8], $agenda['orderedListOrdinalValues']);
        $t->same(['start-attribute', 'value-attribute', 'previous-value'], $agenda['orderedListOrdinalSources']);
        $t->same(1, $agenda['orderedListOrdinalGapCount']);
        $t->same([
            'index' => 1,
            'previousOrdinal' => 3,
            'expectedOrdinal' => 4,
            'actualOrdinal' => 7,
        ], $agenda['orderedListOrdinalGaps'][0]);
        $t->same(['ordered-list-ordinal-gap'], $agenda['orderedListIssueCodes']);
        $t->same(false, $agenda['orderedListOrdinalContiguous']);
        $t->same(false, $agenda['orderedListValid']);
        $t->same('Plan', $agenda['orderedListItems'][0]['text']);
        $t->same('7', $agenda['orderedListItems'][1]['valueRaw']);
        $t->same(true, $agenda['orderedListItems'][1]['valueValid']);
        $t->same(3, $agendaPlan['listOrdinal']);
        $t->same(7, $agendaReview['listOrdinal']);
        $t->same(8, $agendaShip['listOrdinal']);

        $t->same(true, $reverse['orderedListReversed']);
        $t->same(null, $reverse['orderedListStartRaw']);
        $t->same(null, $reverse['orderedListStartValid']);
        $t->same([3, 1, 0], $reverse['orderedListOrdinalValues']);
        $t->same(['reversed-count', 'value-attribute', 'previous-value'], $reverse['orderedListOrdinalSources']);
        $t->same([
            'index' => 1,
            'previousOrdinal' => 3,
            'expectedOrdinal' => 2,
            'actualOrdinal' => 1,
        ], $reverse['orderedListOrdinalGaps'][0]);
        $t->same(['ordered-list-ordinal-gap'], $reverse['orderedListIssueCodes']);

        $t->same('soon', $bad['orderedListStartRaw']);
        $t->same(1, $bad['orderedListStart']);
        $t->same(false, $bad['orderedListStartValid']);
        $t->same('z', $bad['orderedListTypeRaw']);
        $t->same(null, $bad['orderedListMarkerType']);
        $t->same(false, $bad['orderedListMarkerTypeValid']);
        $t->same(3, $bad['orderedListExplicitValueCount']);
        $t->same(1, $bad['orderedListInvalidValueCount']);
        $t->same([2, 2, 3], $bad['orderedListOrdinalValues']);
        $t->same([2], $bad['orderedListDuplicateOrdinals']);
        $t->same([
            'invalid-ordered-list-start',
            'invalid-ordered-list-marker-type',
            'invalid-list-item-value',
            'duplicate-ordered-list-ordinal',
            'ordered-list-ordinal-gap',
        ], $bad['orderedListIssueCodes']);
        $t->same('oops', $bad['orderedListItems'][2]['valueRaw']);
        $t->same(false, $bad['orderedListItems'][2]['valueValid']);
        $t->same('invalid-list-item-value', $bad['orderedListIssues'][2]['code']);
        $t->same('duplicate-ordered-list-ordinal', $bad['orderedListIssues'][3]['code']);

        $t->same(
            '<ol id="agenda" start="3" type="A"><li>Plan</li><li value="7">Review</li><li>Ship</li></ol><ol id="reverse" reversed><li>Third</li><li value="1">First</li><li>Zero</li></ol><ol id="bad" start="soon" type="z"><li value="2">Alpha</li><li value="2">Beta</li><li value="oops">Gamma</li></ol>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/ordered-list-marker-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
