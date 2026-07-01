<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html fieldset packet rollups for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<form id="survey" action="/save">'
                . '<fieldset id="profile" name="profile" disabled>'
                . '<legend>Profile <button id="unlock" name="unlock" value="1">Unlock</button></legend>'
                . '<input id="title" name="title" value="Draft">'
                . '<legend>Extra <input id="second" name="second"></legend>'
                . '<fieldset id="nested" name="nested"><legend>Nested</legend><textarea id="nested-note" name="nested-note">N</textarea></fieldset>'
                . '<button id="save" name="save">Save</button>'
                . '</fieldset>'
                . '<fieldset id="missing" form="survey"><input id="remote" name="remote"></fieldset>'
                . '</form>',
            'fieldset review packet fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $packet = XmlHtmlDom::summarizeHtmlFieldsetReviewPacket($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/fieldset-review-packet.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $profile = $packet['fieldsets'][0];
        $nested = $packet['fieldsets'][1];
        $missing = $packet['fieldsets'][2];

        $t->same('html-fieldset-legend-control-review-packet', $packet['fieldsetReviewPacketPolicy']);
        $t->same('fieldset-legend-disabled-control-review', $packet['fieldsetReviewPolicy']);
        $t->same(false, $packet['directReaderParity']);
        $t->same(['html-fieldset-legend-control-review-only'], $packet['directReaderDiagnosticCodes']);
        $t->same(3, $packet['fieldsetCount']);
        $t->same(1, $packet['disabledFieldsetCount']);
        $t->same(2, $packet['enabledFieldsetCount']);
        $t->same(2, $packet['namedFieldsetCount']);
        $t->same(3, $packet['formOwnedFieldsetCount']);
        $t->same(['profile', 'nested', 'missing'], $packet['fieldsetIds']);
        $t->same(['profile', 'nested'], $packet['fieldsetNames']);
        $t->same(3, $packet['legendCount']);
        $t->same(['Profile Unlock', 'Extra', 'Nested'], $packet['fieldsetLegendTexts']);
        $t->same(7, $packet['controlReferenceCount']);
        $t->same(2, $packet['enabledControlReferenceCount']);
        $t->same(5, $packet['disabledControlReferenceCount']);
        $t->same(['unlock', 'title', 'second', 'nested-note', 'save', 'remote'], $packet['fieldsetControlNames']);
        $t->same(['unlock', 'remote'], $packet['enabledFieldsetControlNames']);
        $t->same(['title', 'second', 'nested-note', 'save'], $packet['disabledFieldsetControlNames']);
        $t->same(1, $packet['nestedFieldsetReferenceCount']);
        $t->same(1, $packet['fieldsetWithMissingLegendCount']);
        $t->same(1, $packet['fieldsetWithMultipleLegendCount']);
        $t->same(1, $packet['fieldsetWithNestedFieldsetCount']);
        $t->same(3, $packet['fieldsetIssueCount']);
        $t->same([
            'multiple-fieldset-legends',
            'nested-fieldset-review',
            'missing-fieldset-legend',
        ], $packet['fieldsetIssueCodes']);
        $t->same(false, $packet['fieldsetReviewValid']);

        $t->same(0, $profile['index']);
        $t->same('profile', $profile['id']);
        $t->same('profile', $profile['fieldsetName']);
        $t->same('survey', $profile['formOwnerId']);
        $t->same('ancestor', $profile['formOwnerSource']);
        $t->same(true, $profile['disabled']);
        $t->same(['Profile Unlock', 'Extra'], $profile['legendTexts']);
        $t->same(5, $profile['controlCount']);
        $t->same(1, $profile['enabledControlCount']);
        $t->same(4, $profile['disabledControlCount']);
        $t->same(['unlock'], $profile['enabledControlNames']);
        $t->same(['title', 'second', 'nested-note', 'save'], $profile['disabledControlNames']);
        $t->same('unlock', $profile['controls'][0]['controlName']);
        $t->same(true, $profile['controls'][0]['inFirstLegend']);
        $t->same(false, $profile['controls'][0]['effectiveDisabled']);
        $t->same('second', $profile['controls'][2]['controlName']);
        $t->same(false, $profile['controls'][2]['inFirstLegend']);
        $t->same(true, $profile['controls'][2]['effectiveDisabled']);
        $t->same(1, $profile['nestedFieldsetCount']);
        $t->same('nested', $profile['nestedFieldsets'][0]['id']);
        $t->same(['multiple-fieldset-legends', 'nested-fieldset-review'], $profile['fieldsetIssueCodes']);

        $t->same(1, $nested['index']);
        $t->same('nested', $nested['id']);
        $t->same('nested', $nested['fieldsetName']);
        $t->same(false, $nested['disabled']);
        $t->same('Nested', $nested['legendText']);
        $t->same(1, $nested['controlCount']);
        $t->same(0, $nested['enabledControlCount']);
        $t->same(1, $nested['disabledControlCount']);
        $t->same([], $nested['fieldsetIssueCodes']);

        $t->same(2, $missing['index']);
        $t->same('missing', $missing['id']);
        $t->same(null, $missing['fieldsetName']);
        $t->same('survey', $missing['formOwnerTargetId']);
        $t->same('form-attribute', $missing['formOwnerSource']);
        $t->same('resolved', $missing['formOwnerResolutionState']);
        $t->same(0, $missing['legendCount']);
        $t->same(['remote'], $missing['enabledControlNames']);
        $t->same(['missing-fieldset-legend'], $missing['fieldsetIssueCodes']);

        $t->same(['multiple-fieldset-legends', 'nested-fieldset-review'], $summary[0]['children'][0]['fieldsetIssueCodes']);
        $t->contains('<fieldset disabled id="profile" name="profile">', $html);
        $t->contains('form="survey"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/fieldset-review-packet.html', $document->children[0]->attr('part'));
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
];
