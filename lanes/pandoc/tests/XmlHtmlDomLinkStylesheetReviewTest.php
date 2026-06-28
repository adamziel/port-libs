<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html link stylesheet selection metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<link id="base" rel="stylesheet" href="/base.css" media="screen" type="text/css">'
                . '<link id="theme" rel="stylesheet" href="/theme.css" title=" Theme  ">'
                . '<link id="contrast" rel="alternate stylesheet" href="/contrast.css" title="High Contrast" disabled>'
                . '<link id="missing-title" rel="alternate stylesheet" href="/missing.css">'
                . '<link id="bad-type" rel="stylesheet" href="/print.css" type="text/x-less">'
                . '<link id="disabled-icon" rel="icon" href="/favicon.ico" disabled>',
            'link stylesheet selection review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/link-stylesheet-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $base = $summary[0];
        $theme = $summary[1];
        $contrast = $summary[2];
        $missingTitle = $summary[3];
        $badType = $summary[4];
        $disabledIcon = $summary[5];

        $t->same('link-stylesheet-selection-review', $base['linkStylesheetReviewPolicy']);
        $t->same(true, $base['linkStylesheetRelPresent']);
        $t->same(false, $base['linkStylesheetAlternate']);
        $t->same(true, $base['linkStylesheetPersistent']);
        $t->same(false, $base['linkStylesheetPreferred']);
        $t->same('persistent', $base['linkStylesheetSetKind']);
        $t->same('enabled', $base['linkStylesheetActivationState']);
        $t->same(true, $base['linkStylesheetDefaultEnabled']);
        $t->same('screen', $base['linkStylesheetMedia']);
        $t->same('text/css', $base['linkStylesheetMimeType']);
        $t->same(true, $base['linkStylesheetTypeSupported']);
        $t->same([], $base['linkStylesheetIssueCodes']);
        $t->same(true, $base['linkStylesheetReviewOnlyNoCssFetch']);

        $t->same('preferred', $theme['linkStylesheetSetKind']);
        $t->same('Theme', $theme['linkStylesheetTitle']);
        $t->same(true, $theme['linkStylesheetPreferred']);
        $t->same(true, $theme['linkStylesheetTitlePresent']);
        $t->same(true, $theme['linkStylesheetDefaultEnabled']);

        $t->same('alternate', $contrast['linkStylesheetSetKind']);
        $t->same('disabled', $contrast['linkStylesheetActivationState']);
        $t->same(true, $contrast['linkStylesheetAlternate']);
        $t->same(true, $contrast['linkStylesheetDisabled']);
        $t->same('disabled', $contrast['linkStylesheetDisabledRaw']);
        $t->same('High Contrast', $contrast['linkStylesheetTitle']);
        $t->same(false, $contrast['linkStylesheetDefaultEnabled']);
        $t->same([], $contrast['linkStylesheetIssueCodes']);

        $t->same('alternate-missing-title', $missingTitle['linkStylesheetSetKind']);
        $t->same(null, $missingTitle['linkStylesheetTitle']);
        $t->same(false, $missingTitle['linkStylesheetTitlePresent']);
        $t->same(['alternate-stylesheet-missing-title'], $missingTitle['linkStylesheetIssueCodes']);
        $t->same([['code' => 'alternate-stylesheet-missing-title']], $missingTitle['linkStylesheetIssues']);
        $t->same(false, $missingTitle['linkStylesheetValid']);
        $t->same([['code' => 'alternate-stylesheet-missing-title']], $missingTitle['linkIssues']);

        $t->same('text/x-less', $badType['linkStylesheetMimeType']);
        $t->same(false, $badType['linkStylesheetTypeSupported']);
        $t->same(['unsupported-link-stylesheet-type'], $badType['linkStylesheetIssueCodes']);
        $t->same(false, $badType['linkStylesheetValid']);

        $t->same('not-stylesheet', $disabledIcon['linkStylesheetSetKind']);
        $t->same(false, $disabledIcon['linkStylesheetRelPresent']);
        $t->same(true, $disabledIcon['linkStylesheetDisabled']);
        $t->same(['link-disabled-without-stylesheet'], $disabledIcon['linkStylesheetIssueCodes']);
        $t->same([['code' => 'link-disabled-without-stylesheet']], $disabledIcon['linkIssues']);
        $t->same(false, $disabledIcon['linkStylesheetValid']);
        $t->same(['icon'], $disabledIcon['linkRelTokens']);

        $t->contains('<link href="/base.css" id="base" media="screen" rel="stylesheet" type="text/css">', $html);
        $t->contains('<link disabled href="/contrast.css" id="contrast" rel="alternate stylesheet" title="High Contrast">', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/link-stylesheet-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
