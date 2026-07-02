<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html dataset property names for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="packet" data-review-id="A-42" data-empty="" data-review--stage="queued" data-9bad="numeric" data-_safe="yes">Packet</section>'
                . '<p id="plain">Plain</p>',
            'dataset property review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/dataset-property-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $section = $summary[0];
        $plain = $summary[1];

        $t->same('html-data-attribute-dataset-property-review', $section['dataAttributeReviewPolicy']);
        $t->same(5, $section['dataAttributeCount']);
        $t->same([
            'data-9bad',
            'data-_safe',
            'data-empty',
            'data-review--stage',
            'data-review-id',
        ], $section['dataAttributeNames']);
        $t->same([
            '9bad',
            '_safe',
            'empty',
            'review-Stage',
            'reviewId',
        ], $section['datasetPropertyNames']);
        $t->same([
            '9bad' => 'numeric',
            '_safe' => 'yes',
            'empty' => '',
            'review-Stage' => 'queued',
            'reviewId' => 'A-42',
        ], $section['dataset']);
        $t->same(['_safe', 'empty', 'reviewId'], $section['datasetDotNotationSafePropertyNames']);
        $t->same(['9bad', 'review-Stage'], $section['datasetBracketOnlyPropertyNames']);
        $t->same(true, $section['datasetRequiresBracketNotation']);
        $t->same(['data-empty'], $section['emptyDataAttributeNames']);
        $t->same(1, $section['emptyDataAttributeCount']);
        $t->same(strlen('numeric') + strlen('yes') + strlen('queued') + strlen('A-42'), $section['dataAttributeValueBytes']);
        $t->same([
            'dataset-property-bracket-notation-required',
            'empty-data-attribute-value',
        ], $section['dataAttributeReviewCodes']);
        $t->same(2, $section['dataAttributeReviewCodeCount']);
        $t->same([
            'name' => 'data-9bad',
            'datasetPropertyName' => '9bad',
            'dotNotationSafe' => false,
            'requiresBracketNotation' => true,
            'emptyValue' => false,
            'valueBytes' => strlen('numeric'),
        ], $section['dataAttributeRecords'][0]);
        $t->same([
            'name' => 'data-empty',
            'datasetPropertyName' => 'empty',
            'dotNotationSafe' => true,
            'requiresBracketNotation' => false,
            'emptyValue' => true,
            'valueBytes' => 0,
        ], $section['dataAttributeRecords'][2]);
        $t->true(!array_key_exists('dataAttributeReviewPolicy', $plain));
        $t->contains('<section data-9bad="numeric" data-_safe="yes" data-empty="" data-review--stage="queued" data-review-id="A-42" id="packet">Packet</section>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/dataset-property-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
