<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes docbook media issue code rollups for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<inlinemediaobject id="bad-media">'
                . '<imageobject><imagedata format="PNG"></imagedata></imageobject>'
                . '<xref linkend="missing-target bad:token"></xref>'
                . '</inlinemediaobject>'
                . '<para id="known-caption">Known caption</para>'
                . '<mediaobject id="ok-media">'
                . '<imageobject><imagedata fileref="assets/figure.png" format="PNG"></imagedata></imageobject>'
                . '<alt>Figure fallback</alt><xref linkend="known-caption"></xref>'
                . '</mediaobject>',
            'docbook media issue code review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/docbook-media-issue-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $bad = $summary[0];
        $association = $bad['docBookLinkendAssociations'][0];
        $ok = $summary[2];

        $t->same('inlinemediaobject', $bad['docBookMediaObject']);
        $t->same('docbook-media-object-issue-review', $bad['docBookMediaReviewPolicy']);
        $t->same(true, $bad['docBookMediaInline']);
        $t->same('bad-media', $bad['docBookMediaId']);
        $t->same(true, $bad['docBookMissingAlt']);
        $t->same([
            'missing-docbook-media-alt',
            'missing-docbook-imagedata-target',
            'invalid-docbook-linkend',
            'missing-docbook-linkend-target',
        ], $bad['docBookMediaIssueCodes']);
        $t->same(4, $bad['docBookMediaIssueCount']);
        $t->same(4, $bad['docBookMediaIssueCodeCount']);
        $t->same(false, $bad['docBookMediaValid']);
        $t->same([
            ['code' => 'missing-docbook-media-alt', 'media' => 'inlinemediaobject', 'imageDataCount' => 1],
            ['code' => 'missing-docbook-imagedata-target', 'imageDataIndex' => 0],
            ['code' => 'invalid-docbook-linkend', 'element' => 'xref', 'linkendId' => 'bad:token'],
            ['code' => 'missing-docbook-linkend-target', 'element' => 'xref', 'linkendId' => 'missing-target'],
        ], $bad['docBookMediaIssues']);
        $t->same(['missing-target', 'bad:token'], $association['linkendIds']);
        $t->same(['missing-target'], $association['missingIds']);
        $t->same(['bad:token'], $association['invalidIds']);
        $t->same(false, $association['valid']);

        $t->same('mediaobject', $ok['docBookMediaObject']);
        $t->same('docbook-media-object-issue-review', $ok['docBookMediaReviewPolicy']);
        $t->same(false, $ok['docBookMediaInline']);
        $t->same('ok-media', $ok['docBookMediaId']);
        $t->same(['Figure fallback'], $ok['docBookAltTexts']);
        $t->same(['figure.png'], $ok['docBookImageTargetBasenames']);
        $t->same(['image/png'], $ok['docBookImageContentTypes']);
        $t->same([], $ok['docBookMediaIssues']);
        $t->same([], $ok['docBookMediaIssueCodes']);
        $t->same(0, $ok['docBookMediaIssueCount']);
        $t->same(0, $ok['docBookMediaIssueCodeCount']);
        $t->same(true, $ok['docBookMediaValid']);
        $t->same(['known-caption'], $ok['docBookLinkendAssociations'][0]['resolvedIds']);

        $t->contains('<imagedata format="PNG"></imagedata>', $html);
        $t->contains('<imagedata fileref="assets/figure.png" format="PNG"></imagedata>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/docbook-media-issue-review.html', $document->children[0]->attr('part'));
        json_encode([$bad, $ok], JSON_THROW_ON_ERROR);
    },
];
