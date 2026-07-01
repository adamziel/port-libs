<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html data attribute dataset metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="packet" data-review-id="A-42" data-review--id="B" data--raw="C" data-xml:bad="namespaced" data-="empty"><p data-stage="preflight">Body</p></article>',
            'data attribute dataset review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/data-attribute-dataset-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $article = $summary[0];
        $paragraph = $article['children'][0];

        $t->same([
            'data--raw' => 'C',
            'data-review--id' => 'B',
            'data-review-id' => 'A-42',
            'data-xml:bad' => 'namespaced',
        ], $article['dataAttributes']);
        $t->same([
            'Raw' => 'C',
            'review-Id' => 'B',
            'reviewId' => 'A-42',
            'xml:bad' => 'namespaced',
        ], $article['dataset']);
        $t->same('html-data-attribute-dataset-review', $article['dataAttributeReviewPolicy']);
        $t->same([
            'data-',
            'data--raw',
            'data-review--id',
            'data-review-id',
            'data-xml:bad',
        ], $article['dataAttributeNames']);
        $t->same(5, $article['dataAttributeCount']);
        $t->same([
            'data--raw',
            'data-review--id',
            'data-review-id',
            'data-xml:bad',
        ], $article['dataAttributeCustomNames']);
        $t->same(4, $article['dataAttributeCustomCount']);
        $t->same(['Raw', 'review-Id', 'reviewId', 'xml:bad'], $article['dataAttributeDatasetNames']);
        $t->same([
            'Raw' => 1,
            'review-Id' => 1,
            'reviewId' => 1,
            'xml:bad' => 1,
        ], $article['dataAttributeDatasetNameCounts']);
        $t->same(['data-'], $article['dataAttributeEmptyNames']);
        $t->same(['empty-data-attribute-name'], $article['dataAttributeIssueCodes']);
        $t->same(false, $article['dataAttributeValid']);

        $emptyRecord = $article['dataAttributeRecords'][0];
        $rawRecord = $article['dataAttributeRecords'][1];
        $reviewRecord = $article['dataAttributeRecords'][3];
        $t->same('data-', $emptyRecord['attribute']);
        $t->same('', $emptyRecord['suffix']);
        $t->same(null, $emptyRecord['datasetName']);
        $t->same(false, $emptyRecord['validCustomDataAttribute']);
        $t->same(['empty-data-attribute-name'], $emptyRecord['issueCodes']);
        $t->same('data--raw', $rawRecord['attribute']);
        $t->same('-raw', $rawRecord['suffix']);
        $t->same('Raw', $rawRecord['datasetName']);
        $t->same(1, $rawRecord['valueByteLength']);
        $t->same(hash('sha256', 'C'), $rawRecord['valueSha256']);
        $t->same('data-review-id', $reviewRecord['attribute']);
        $t->same('review-id', $reviewRecord['suffix']);
        $t->same('reviewId', $reviewRecord['datasetName']);

        $t->same(['stage' => 'preflight'], $paragraph['dataset']);
        $t->same('html-data-attribute-dataset-review', $paragraph['dataAttributeReviewPolicy']);
        $t->same(['stage'], $paragraph['dataAttributeDatasetNames']);
        $t->same([], $paragraph['dataAttributeIssueCodes']);
        $t->same(true, $paragraph['dataAttributeValid']);

        $t->contains('data-="empty"', $html);
        $t->contains('data-review-id="A-42"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/data-attribute-dataset-review.html', $document->children[0]->attr('part'));
    },
];
