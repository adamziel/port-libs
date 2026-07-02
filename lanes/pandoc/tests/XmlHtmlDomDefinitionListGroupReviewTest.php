<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html definition list malformed group diagnostics for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<dl id="packet"><dd>Leading orphan</dd><dt>Term</dt><dd>Definition</dd><dt>Loose</dt><dt>Alias</dt></dl>'
                . '<dl id="empty"></dl>',
            'definition list malformed group review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/definition-list-group-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $packet = $summary[0];
        $leadingDefinition = $packet['children'][0];
        $term = $packet['children'][1];
        $body = $packet['children'][2];
        $loose = $packet['children'][3];
        $alias = $packet['children'][4];
        $orphanItem = $packet['items'][0];
        $pairedItem = $packet['items'][1];
        $unterminatedItem = $packet['items'][2];
        $empty = $summary[1];

        $t->same('html-definition-list-group-review', $packet['definitionListReviewPolicy']);
        $t->same(3, $packet['termCount']);
        $t->same(2, $packet['definitionCount']);
        $t->same(3, $packet['itemCount']);
        $t->same(['Term', 'Loose', 'Alias'], $packet['terms']);
        $t->same(['Leading orphan', 'Definition'], $packet['definitions']);
        $t->same(false, $packet['definitionListValid']);
        $t->same([
            'definition-list-item-missing-term',
            'definition-list-item-missing-definition',
        ], $packet['definitionListIssueCodes']);
        $t->same(2, $packet['definitionListIssueCount']);
        $t->same('definition-list-item-missing-term', $packet['definitionListIssues'][0]['code']);
        $t->same(0, $packet['definitionListIssues'][0]['itemIndex']);
        $t->same(1, $packet['definitionListIssues'][0]['definitionCount']);
        $t->same('definition-list-item-missing-definition', $packet['definitionListIssues'][1]['code']);
        $t->same(2, $packet['definitionListIssues'][1]['itemIndex']);
        $t->same(2, $packet['definitionListIssues'][1]['termCount']);

        $t->same([], $orphanItem['terms']);
        $t->same(['Leading orphan'], $orphanItem['definitions']);
        $t->same(false, $orphanItem['definitionListItemValid']);
        $t->same(['definition-list-item-missing-term'], $orphanItem['definitionListItemIssueCodes']);
        $t->same([[
            'code' => 'definition-list-item-missing-term',
            'termCount' => 0,
            'definitionCount' => 1,
        ]], $orphanItem['definitionListItemIssues']);

        $t->same(['Term'], $pairedItem['terms']);
        $t->same(['Definition'], $pairedItem['definitions']);
        $t->same(true, $pairedItem['definitionListItemValid']);
        $t->same([], $pairedItem['definitionListItemIssueCodes']);
        $t->same([], $pairedItem['definitionListItemIssues']);

        $t->same(['Loose', 'Alias'], $unterminatedItem['terms']);
        $t->same([], $unterminatedItem['definitions']);
        $t->same(false, $unterminatedItem['definitionListItemValid']);
        $t->same(['definition-list-item-missing-definition'], $unterminatedItem['definitionListItemIssueCodes']);
        $t->same([[
            'code' => 'definition-list-item-missing-definition',
            'termCount' => 2,
            'definitionCount' => 0,
        ]], $unterminatedItem['definitionListItemIssues']);

        $t->same('definition', $leadingDefinition['definitionListPart']);
        $t->same('Leading orphan', $leadingDefinition['definitionText']);
        $t->same('term', $term['definitionListPart']);
        $t->same('Term', $term['termText']);
        $t->same('definition', $body['definitionListPart']);
        $t->same('Definition', $body['definitionText']);
        $t->same('Loose', $loose['termText']);
        $t->same('Alias', $alias['termText']);

        $t->same('dl', $empty['definitionList']);
        $t->same('html-definition-list-group-review', $empty['definitionListReviewPolicy']);
        $t->same(0, $empty['termCount']);
        $t->same(0, $empty['definitionCount']);
        $t->same(0, $empty['itemCount']);
        $t->same([], $empty['terms']);
        $t->same([], $empty['definitions']);
        $t->same([], $empty['items']);
        $t->same(false, $empty['definitionListValid']);
        $t->same(['empty-definition-list'], $empty['definitionListIssueCodes']);
        $t->same(1, $empty['definitionListIssueCount']);
        $t->same([['code' => 'empty-definition-list']], $empty['definitionListIssues']);

        $t->same(
            '<dl id="packet"><dd>Leading orphan</dd><dt>Term</dt><dd>Definition</dd><dt>Loose</dt><dt>Alias</dt></dl><dl id="empty"></dl>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/definition-list-group-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
