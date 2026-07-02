<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html ordered list container ordinal rollups for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<ol id="reverse" reversed type="A"><li id="review">Review</li><li id="patch" value="4">Patch</li><li id="verify">Verify</li><li id="invalid" value="later">Invalid</li></ol>'
                . '<ol id="forward" start="2"><li id="draft">Draft</li><li id="pin" value="-2">Pinned</li><li id="next">Next</li></ol>'
                . '<ol id="bad-start" start="soon"><li id="fallback">Fallback</li></ol>',
            'ordered list container ordinal review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/ordered-list-ordinal-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $reverse = $summary[0];
        $forward = $summary[1];
        $badStart = $summary[2];

        $t->same('ordered', $reverse['list']);
        $t->same('html-ordered-list-ordinal-review', $reverse['orderedListReviewPolicy']);
        $t->same(true, $reverse['orderedListReversed']);
        $t->same(null, $reverse['orderedListStartRaw']);
        $t->same(null, $reverse['orderedListStart']);
        $t->same(true, $reverse['orderedListStartValid']);
        $t->same('reversed-count', $reverse['orderedListStartSource']);
        $t->same(4, $reverse['orderedListItemCount']);
        $t->same([4, 4, 3, 2], $reverse['orderedListOrdinals']);
        $t->same(['reversed-count', 'value-attribute', 'previous-value', 'previous-value'], $reverse['orderedListOrdinalSources']);
        $t->same(1, $reverse['orderedListExplicitValueCount']);
        $t->same([4], $reverse['orderedListExplicitValueOrdinals']);
        $t->same(1, $reverse['orderedListInvalidValueCount']);
        $t->same([['index' => 3, 'id' => 'invalid', 'valueRaw' => 'later']], $reverse['orderedListInvalidValues']);
        $t->same([4], $reverse['orderedListDuplicateOrdinals']);
        $t->same(true, $reverse['orderedListHasDuplicateOrdinals']);
        $t->same(2, $reverse['orderedListIssueCount']);
        $t->same(['invalid-list-item-value', 'duplicate-list-item-ordinal'], $reverse['orderedListIssueCodes']);
        $t->same(false, $reverse['orderedListValid']);
        $t->same('review', $reverse['orderedListItems'][0]['id']);
        $t->same('Review', $reverse['orderedListItems'][0]['text']);
        $t->same(null, $reverse['orderedListItems'][0]['valueRaw']);
        $t->same(null, $reverse['orderedListItems'][0]['value']);
        $t->same(true, $reverse['orderedListItems'][0]['valueValid']);
        $t->same(false, $reverse['orderedListItems'][0]['explicitValue']);
        $t->same(4, $reverse['orderedListItems'][0]['listOrdinal']);
        $t->same('reversed-count', $reverse['orderedListItems'][0]['listOrdinalSource']);
        $t->same(4, $reverse['children'][0]['listOrdinal']);
        $t->same('reversed-count', $reverse['children'][0]['listOrdinalSource']);
        $t->same('invalid-list-item-value', $reverse['orderedListIssues'][0]['code']);
        $t->same(3, $reverse['orderedListIssues'][0]['itemIndex']);
        $t->same('duplicate-list-item-ordinal', $reverse['orderedListIssues'][1]['code']);
        $t->same(4, $reverse['orderedListIssues'][1]['ordinal']);
        $t->same([0, 1], $reverse['orderedListIssues'][1]['itemIndexes']);
        $t->same(['review', 'patch'], $reverse['orderedListIssues'][1]['itemIds']);

        $t->same('html-ordered-list-ordinal-review', $forward['orderedListReviewPolicy']);
        $t->same(false, $forward['orderedListReversed']);
        $t->same('2', $forward['orderedListStartRaw']);
        $t->same(2, $forward['orderedListStart']);
        $t->same(true, $forward['orderedListStartValid']);
        $t->same('start-attribute', $forward['orderedListStartSource']);
        $t->same([2, -2, -1], $forward['orderedListOrdinals']);
        $t->same(['start-attribute', 'value-attribute', 'previous-value'], $forward['orderedListOrdinalSources']);
        $t->same(1, $forward['orderedListExplicitValueCount']);
        $t->same([-2], $forward['orderedListExplicitValueOrdinals']);
        $t->same([], $forward['orderedListDuplicateOrdinals']);
        $t->same(0, $forward['orderedListInvalidValueCount']);
        $t->same([], $forward['orderedListIssueCodes']);
        $t->same(true, $forward['orderedListValid']);
        $t->same(-2, $forward['children'][1]['listOrdinal']);
        $t->same('value-attribute', $forward['children'][1]['listOrdinalSource']);

        $t->same('soon', $badStart['startRaw']);
        $t->same(1, $badStart['start']);
        $t->same('soon', $badStart['orderedListStartRaw']);
        $t->same(null, $badStart['orderedListStart']);
        $t->same(false, $badStart['orderedListStartValid']);
        $t->same('default-start', $badStart['orderedListStartSource']);
        $t->same([1], $badStart['orderedListOrdinals']);
        $t->same(['default-start'], $badStart['orderedListOrdinalSources']);
        $t->same(['invalid-ordered-list-start'], $badStart['orderedListIssueCodes']);
        $t->same('invalid-ordered-list-start', $badStart['orderedListIssues'][0]['code']);
        $t->same('soon', $badStart['orderedListIssues'][0]['startRaw']);
        $t->same(false, $badStart['orderedListValid']);

        $t->same('<ol id="reverse" reversed type="A"><li id="review">Review</li><li id="patch" value="4">Patch</li><li id="verify">Verify</li><li id="invalid" value="later">Invalid</li></ol><ol id="forward" start="2"><li id="draft">Draft</li><li id="pin" value="-2">Pinned</li><li id="next">Next</li></ol><ol id="bad-start" start="soon"><li id="fallback">Fallback</li></ol>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/ordered-list-ordinal-review.html', $document->children[0]->attr('part'));
    },
];
