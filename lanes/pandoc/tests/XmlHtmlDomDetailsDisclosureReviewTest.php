<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html details disclosure diagnostics for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<details id="alpha" name="faq" open><summary id="alpha-label">Alpha label</summary><summary id="alpha-extra">Extra label</summary><p>Alpha body</p></details>'
                . '<details id="beta" name=" faq " open><summary>Beta label</summary></details>'
                . '<details id="empty-name" name="   "><summary>Empty name</summary></details>'
                . '<details id="missing-summary"><p>No label</p></details>'
                . '<summary id="loose">Loose label</summary>',
            'details disclosure diagnostics review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/details-disclosure-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $alpha = $summary[0];
        $alphaPrimary = $alpha['children'][0];
        $alphaSecondary = $alpha['children'][1];
        $beta = $summary[1];
        $emptyName = $summary[2];
        $missingSummary = $summary[3];
        $loose = $summary[4];

        $t->same('html-details-disclosure-state-review', $alpha['detailsDisclosureReviewPolicy']);
        $t->same('faq', $alpha['detailsName']);
        $t->same(2, $alpha['detailsGroupOpenCount']);
        $t->same(['alpha', 'beta'], $alpha['detailsGroupOpenIds']);
        $t->same(true, $alpha['detailsGroupOpenConflict']);
        $t->same([
            'multiple-details-summary-elements',
            'multiple-open-details-in-name-group',
        ], $alpha['detailsDisclosureIssueCodes']);
        $t->same(false, $alpha['detailsDisclosureValid']);
        $t->same(2, $alpha['detailsDisclosureIssues'][0]['summaryElementCount'] ?? null);
        $t->same(['alpha', 'beta'], $alpha['detailsDisclosureIssues'][1]['openIds'] ?? null);

        $t->same('html-summary-disclosure-parent-review', $alphaPrimary['summaryDisclosureReviewPolicy']);
        $t->same([], $alphaPrimary['summaryDisclosureIssueCodes']);
        $t->same(true, $alphaPrimary['summaryDisclosureValid']);
        $t->same(['non-primary-details-summary-element'], $alphaSecondary['summaryDisclosureIssueCodes']);
        $t->same(false, $alphaSecondary['summaryDisclosureValid']);

        $t->same('faq', $beta['detailsName']);
        $t->same(2, $beta['detailsGroupOpenCount']);
        $t->same(['multiple-open-details-in-name-group'], $beta['detailsDisclosureIssueCodes']);

        $t->same(null, $emptyName['detailsName']);
        $t->same(['empty-details-name-group'], $emptyName['detailsDisclosureIssueCodes']);
        $t->same(false, $emptyName['detailsDisclosureValid']);

        $t->same(0, $missingSummary['summaryElementCount']);
        $t->same(['missing-details-summary-element'], $missingSummary['detailsDisclosureIssueCodes']);
        $t->same(false, $missingSummary['detailsDisclosureValid']);

        $t->same(null, $loose['summaryForDetailsId']);
        $t->same(['summary-without-details-parent'], $loose['summaryDisclosureIssueCodes']);
        $t->same(false, $loose['summaryDisclosureValid']);

        $t->contains('<details id="alpha" name="faq" open>', $html);
        $t->contains('<details id="beta" name=" faq " open>', $html);
        $t->contains('<summary id="loose">Loose label</summary>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/details-disclosure-review.html', $document->children[0]->attr('part'));
    },
];
