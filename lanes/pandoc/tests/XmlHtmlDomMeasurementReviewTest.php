<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html progress and meter validity provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<label for="good-progress">Upload</label>'
                . '<progress id="good-progress" value="3" max="4">75%</progress>'
                . '<progress id="overflow-progress" value="9" max="4">Over</progress>'
                . '<progress id="invalid-progress" value="soon" max="0">Pending</progress>'
                . '<label>Quality <meter id="quality" value="0.82" min="0" max="1" low="0.4" high="0.9" optimum="0.95">82%</meter></label>'
                . '<meter id="balanced-meter" value="0.6" min="0" max="1" low="0.25" high="0.75" optimum="0.5">Balanced</meter>'
                . '<meter id="bad-meter" value="12" min="10" max="2" low="11" high="9" optimum="oops">Bad</meter>'
                . '<meter id="invalid-meter" value="NaN" min="-1" max="1">Invalid</meter>',
            'progress meter measurement review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/measurement-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $progressLabel = $summary[0];
        $goodProgress = $summary[1];
        $overflowProgress = $summary[2];
        $invalidProgress = $summary[3];
        $qualityLabel = $summary[4];
        $quality = $summary[4]['children'][1];
        $balancedMeter = $summary[5];
        $badMeter = $summary[6];
        $invalidMeter = $summary[7];

        $t->same('html-progress-measurement-review', $goodProgress['progressReviewPolicy']);
        $t->same('3', $goodProgress['progressValueRaw']);
        $t->same('4', $goodProgress['progressMaxRaw']);
        $t->same(3.0, $goodProgress['value']);
        $t->same(4.0, $goodProgress['max']);
        $t->same(0.75, $goodProgress['position']);
        $t->same(false, $goodProgress['indeterminate']);
        $t->same(true, $goodProgress['progressValueValid']);
        $t->same(true, $goodProgress['progressMaxValid']);
        $t->same(false, $goodProgress['progressValueClamped']);
        $t->same([], $goodProgress['progressIssueCodes']);
        $t->same(true, $goodProgress['progressValid']);
        $t->same(true, $progressLabel['labeledControl']['progressValid']);

        $t->same('9', $overflowProgress['progressValueRaw']);
        $t->same(4.0, $overflowProgress['value']);
        $t->same(1.0, $overflowProgress['position']);
        $t->same(false, $overflowProgress['progressValueValid']);
        $t->same(true, $overflowProgress['progressValueClamped']);
        $t->same(['progress-value-overflow'], $overflowProgress['progressIssueCodes']);
        $t->same('progress-value-overflow', $overflowProgress['progressIssues'][0]['code']);
        $t->same(9.0, $overflowProgress['progressIssues'][0]['value']);
        $t->same(false, $overflowProgress['progressValid']);

        $t->same('soon', $invalidProgress['progressValueRaw']);
        $t->same('0', $invalidProgress['progressMaxRaw']);
        $t->same(null, $invalidProgress['value']);
        $t->same(1.0, $invalidProgress['max']);
        $t->same(true, $invalidProgress['indeterminate']);
        $t->same(false, $invalidProgress['progressValueValid']);
        $t->same(false, $invalidProgress['progressMaxValid']);
        $t->same([
            'nonpositive-progress-max',
            'invalid-progress-value',
        ], $invalidProgress['progressIssueCodes']);
        $t->same(false, $invalidProgress['progressValid']);

        $t->same('html-meter-measurement-review', $quality['meterReviewPolicy']);
        $t->same('0.82', $quality['meterValueRaw']);
        $t->same('0', $quality['meterMinRaw']);
        $t->same('1', $quality['meterMaxRaw']);
        $t->same('0.4', $quality['meterLowRaw']);
        $t->same('0.9', $quality['meterHighRaw']);
        $t->same('0.95', $quality['meterOptimumRaw']);
        $t->same(0.82, $quality['value']);
        $t->same(0.0, $quality['min']);
        $t->same(1.0, $quality['max']);
        $t->same(0.4, $quality['low']);
        $t->same(0.9, $quality['high']);
        $t->same(0.95, $quality['optimum']);
        $t->same(0.82, $quality['meterPosition']);
        $t->same('middle', $quality['meterValueRegion']);
        $t->same('high', $quality['meterOptimumRegion']);
        $t->same(false, $quality['meterValueMatchesOptimumRegion']);
        $t->same(true, $quality['meterValueValid']);
        $t->same(true, $quality['meterRangeValid']);
        $t->same(true, $quality['meterThresholdsValid']);
        $t->same([], $quality['meterIssueCodes']);
        $t->same(true, $quality['meterValid']);
        $t->same(true, $qualityLabel['labeledControl']['meterValid']);
        $t->same('middle', $qualityLabel['labeledControl']['meterValueRegion']);
        $t->same($qualityLabel['labeledControl'], $qualityLabel['nestedControls'][0]);

        $t->same('0.6', $balancedMeter['meterValueRaw']);
        $t->same(0.6, $balancedMeter['meterPosition']);
        $t->same('middle', $balancedMeter['meterValueRegion']);
        $t->same('middle', $balancedMeter['meterOptimumRegion']);
        $t->same(true, $balancedMeter['meterValueMatchesOptimumRegion']);
        $t->same([], $balancedMeter['meterIssueCodes']);
        $t->same(true, $balancedMeter['meterValid']);

        $t->same('html-meter-measurement-review', $badMeter['meterReviewPolicy']);
        $t->same('12', $badMeter['meterValueRaw']);
        $t->same('10', $badMeter['meterMinRaw']);
        $t->same('2', $badMeter['meterMaxRaw']);
        $t->same(10.0, $badMeter['min']);
        $t->same(10.0, $badMeter['max']);
        $t->same(10.0, $badMeter['value']);
        $t->same(false, $badMeter['meterValueValid']);
        $t->same(true, $badMeter['meterValueClamped']);
        $t->same(false, $badMeter['meterRangeValid']);
        $t->same(false, $badMeter['meterThresholdsValid']);
        $t->same(null, $badMeter['meterPosition']);
        $t->same(null, $badMeter['meterValueRegion']);
        $t->same(null, $badMeter['meterOptimumRegion']);
        $t->same(null, $badMeter['meterValueMatchesOptimumRegion']);
        $t->same([
            'meter-min-exceeds-max',
            'meter-value-overflow',
            'meter-low-out-of-range',
            'meter-high-out-of-range',
            'invalid-meter-optimum',
            'meter-low-exceeds-high',
        ], $badMeter['meterIssueCodes']);
        $t->same('meter-min-exceeds-max', $badMeter['meterIssues'][0]['code']);
        $t->same(2.0, $badMeter['meterIssues'][0]['value']);
        $t->same(10.0, $badMeter['meterIssues'][0]['min']);
        $t->same(false, $badMeter['meterValid']);

        $t->same('NaN', $invalidMeter['meterValueRaw']);
        $t->same(-1.0, $invalidMeter['min']);
        $t->same(1.0, $invalidMeter['max']);
        $t->same(-1.0, $invalidMeter['value']);
        $t->same(false, $invalidMeter['meterValueValid']);
        $t->same(true, $invalidMeter['meterRangeValid']);
        $t->same(true, $invalidMeter['meterThresholdsValid']);
        $t->same(null, $invalidMeter['meterPosition']);
        $t->same(null, $invalidMeter['meterValueRegion']);
        $t->same(null, $invalidMeter['meterOptimumRegion']);
        $t->same(null, $invalidMeter['meterValueMatchesOptimumRegion']);
        $t->same(['invalid-meter-value'], $invalidMeter['meterIssueCodes']);
        $t->same(false, $invalidMeter['meterValid']);

        $t->contains('<progress id="good-progress" max="4" value="3">75%</progress>', $html);
        $t->contains('<meter high="9" id="bad-meter" low="11" max="2" min="10" optimum="oops" value="12">Bad</meter>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/measurement-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
