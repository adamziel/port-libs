<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes progress and meter measurement provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<label for="upload">Upload</label>'
                . '<progress id="upload" value="3" max="4">75%</progress>'
                . '<progress id="late" value="7" max="4">175%</progress>'
                . '<progress id="bad-progress" value="soon" max="0">Bad</progress>'
                . '<label>Quality <meter id="quality" value="0.82" min="0" max="1" low="0.4" high="0.9" optimum="0.95">82%</meter></label>'
                . '<meter id="clamped" value="12" min="2" max="10" low="9" high="4" optimum="nope">Too high</meter>'
                . '<meter id="defaulted">Defaulted</meter>',
            'measurement review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/measurement-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $uploadLabel = $summary[0];
        $upload = $summary[1];
        $late = $summary[2];
        $badProgress = $summary[3];
        $qualityLabel = $summary[4];
        $quality = $qualityLabel['children'][1];
        $clamped = $summary[5];
        $defaulted = $summary[6];

        $t->same('progress-element-static-value-review', $upload['progressReviewPolicy']);
        $t->same('for-attribute', $uploadLabel['labeledControlSource']);
        $t->same('progress', $uploadLabel['labeledControl']['measurement']);
        $t->same('4', $upload['progressMaxRaw']);
        $t->same(4.0, $upload['progressMaxParsed']);
        $t->same(4.0, $upload['progressMax']);
        $t->same(true, $upload['progressMaxValid']);
        $t->same(false, $upload['progressMaxDefaulted']);
        $t->same('3', $upload['progressValueRaw']);
        $t->same(3.0, $upload['progressValueParsed']);
        $t->same(3.0, $upload['progressValue']);
        $t->same(false, $upload['progressValueClamped']);
        $t->same(0.75, $upload['progressPosition']);
        $t->same(false, $upload['progressIndeterminate']);
        $t->same(true, $upload['progressReviewOnlyNoBrowserValidation']);
        $t->same([], $upload['progressIssueCodes']);
        $t->same(true, $upload['progressValid']);

        $t->same(7.0, $late['progressValueParsed']);
        $t->same(4.0, $late['progressValue']);
        $t->same(true, $late['progressValueOverflow']);
        $t->same(true, $late['progressValueClamped']);
        $t->same(['progress-value-overflow'], $late['progressIssueCodes']);
        $t->same(false, $late['progressValid']);

        $t->same('0', $badProgress['progressMaxRaw']);
        $t->same(0.0, $badProgress['progressMaxParsed']);
        $t->same(1.0, $badProgress['progressMax']);
        $t->same(false, $badProgress['progressMaxValid']);
        $t->same(true, $badProgress['progressMaxDefaulted']);
        $t->same('soon', $badProgress['progressValueRaw']);
        $t->same(null, $badProgress['progressValueParsed']);
        $t->same(null, $badProgress['progressValue']);
        $t->same(true, $badProgress['progressIndeterminate']);
        $t->same([
            'non-positive-progress-max',
            'invalid-progress-value',
        ], $badProgress['progressIssueCodes']);

        $t->same('label', $qualityLabel['formLabel']);
        $t->same('descendant', $qualityLabel['labeledControlSource']);
        $t->same('meter-element-static-range-review', $quality['meterReviewPolicy']);
        $t->same('0', $quality['meterMinRaw']);
        $t->same(0.0, $quality['meterMinParsed']);
        $t->same(0.0, $quality['meterMin']);
        $t->same('1', $quality['meterMaxRaw']);
        $t->same(1.0, $quality['meterMaxParsed']);
        $t->same(1.0, $quality['meterMax']);
        $t->same('0.82', $quality['meterValueRaw']);
        $t->same(0.82, $quality['meterValueParsed']);
        $t->same(0.82, $quality['meterValue']);
        $t->same(false, $quality['meterValueClamped']);
        $t->same(0.4, $quality['meterLowBoundary']);
        $t->same(0.9, $quality['meterHighBoundary']);
        $t->same(0.95, $quality['meterOptimumEffective']);
        $t->same('between-low-high', $quality['meterValueZone']);
        $t->same('above-high', $quality['meterOptimumZone']);
        $t->same(true, $quality['meterThresholdOrderValid']);
        $t->same([], $quality['meterIssueCodes']);
        $t->same(true, $quality['meterValid']);

        $t->same(12.0, $clamped['meterValueParsed']);
        $t->same(10.0, $clamped['meterValue']);
        $t->same(true, $clamped['meterValueOverflow']);
        $t->same(true, $clamped['meterValueClamped']);
        $t->same(9.0, $clamped['meterLowBoundary']);
        $t->same(4.0, $clamped['meterHighBoundary']);
        $t->same(false, $clamped['meterThresholdOrderValid']);
        $t->same(null, $clamped['meterOptimumParsed']);
        $t->same(6.0, $clamped['meterOptimumEffective']);
        $t->same('above-high', $clamped['meterValueZone']);
        $t->same('between-low-high', $clamped['meterOptimumZone']);
        $t->same([
            'meter-value-overflow',
            'invalid-meter-optimum',
            'meter-low-greater-than-high',
        ], $clamped['meterIssueCodes']);
        $t->same(false, $clamped['meterValid']);

        $t->same(null, $defaulted['meterMinRaw']);
        $t->same(0.0, $defaulted['meterMin']);
        $t->same(true, $defaulted['meterMinDefaulted']);
        $t->same(null, $defaulted['meterMaxRaw']);
        $t->same(1.0, $defaulted['meterMax']);
        $t->same(true, $defaulted['meterMaxDefaulted']);
        $t->same(null, $defaulted['meterValueRaw']);
        $t->same(0.0, $defaulted['meterValue']);
        $t->same(true, $defaulted['meterValueDefaulted']);
        $t->same('between-low-high', $defaulted['meterValueZone']);
        $t->same([], $defaulted['meterIssueCodes']);

        $t->contains('<progress id="late" max="4" value="7">175%</progress>', $html);
        $t->contains('<meter high="4" id="clamped" low="9" max="10" min="2" optimum="nope" value="12">Too high</meter>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/measurement-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
