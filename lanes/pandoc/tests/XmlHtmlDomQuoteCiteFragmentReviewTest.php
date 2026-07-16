<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html quote cite fragment targets for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="source">Resolved claim</article>'
                . '<a name="legacy-source">Legacy source</a>'
                . '<p><q id="resolved" cite="#source">Resolved quote</q><q id="legacy" cite="#legacy-source">Legacy quote</q><q id="top" cite="#">Top quote</q></p>'
                . '<p><q id="missing" cite="#missing-source">Missing quote</q><q id="invalid" cite="#bad target">Invalid quote</q></p>'
                . '<div id="dup-source">First duplicate</div><section id="dup-source">Second duplicate</section>'
                . '<blockquote id="duplicate-quote" cite="#dup-source"><p>Duplicate quote</p></blockquote>',
            'quote cite fragment target review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/quote-cite-fragment-target-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $resolved = $summary[2]['children'][0];
        $legacy = $summary[2]['children'][1];
        $top = $summary[2]['children'][2];
        $missing = $summary[3]['children'][0];
        $invalid = $summary[3]['children'][1];
        $duplicate = $summary[6];

        $t->same('quote-cite-fragment-target-review', $resolved['quoteCiteFragmentReviewPolicy']);
        $t->same('fragment', $resolved['quoteCiteKind']);
        $t->same(null, $resolved['quoteCiteScheme']);
        $t->same(false, $resolved['quoteCiteUnsafe']);
        $t->same('source', $resolved['quoteCiteFragmentRaw']);
        $t->same('source', $resolved['quoteCiteFragmentTarget']);
        $t->same(true, $resolved['quoteCiteFragmentTargetValid']);
        $t->same(true, $resolved['quoteCiteFragmentTargetFound']);
        $t->same(1, $resolved['quoteCiteFragmentTargetCount']);
        $t->same('id', $resolved['quoteCiteFragmentTargetKind']);
        $t->same('article', $resolved['quoteCiteFragmentTargetElement']['tag']);
        $t->same('Resolved claim', $resolved['quoteCiteFragmentTargetElement']['text']);
        $t->same([], $resolved['quoteCiteFragmentIssueCodes']);
        $t->same([], $resolved['quoteCiteIssueCodes']);

        $t->same('anchor-name', $legacy['quoteCiteFragmentTargetKind']);
        $t->same('a', $legacy['quoteCiteFragmentTargetElement']['tag']);
        $t->same('legacy-source', $legacy['quoteCiteFragmentTargetElement']['nameAttribute']);
        $t->same('Legacy source', $legacy['quoteCiteFragmentTargetElement']['text']);
        $t->same([], $legacy['quoteCiteIssueCodes']);

        $t->same('', $top['quoteCiteFragmentRaw']);
        $t->same(null, $top['quoteCiteFragmentTarget']);
        $t->same(true, $top['quoteCiteFragmentDocumentTop']);
        $t->same(false, $top['quoteCiteFragmentTargetFound']);
        $t->same('document-top', $top['quoteCiteFragmentTargetKind']);
        $t->same([], $top['quoteCiteIssueCodes']);

        $t->same('missing-target', $missing['quoteCiteFragmentTargetKind']);
        $t->same(['missing-quote-cite-fragment-target'], $missing['quoteCiteFragmentIssueCodes']);
        $t->same(['missing-quote-cite-fragment-target'], $missing['quoteCiteIssueCodes']);
        $t->same([[
            'code' => 'missing-quote-cite-fragment-target',
            'fragmentTarget' => 'missing-source',
        ]], $missing['quoteCiteIssues']);

        $t->same(false, $invalid['quoteCiteFragmentTargetValid']);
        $t->same('invalid-reference', $invalid['quoteCiteFragmentTargetKind']);
        $t->same(['invalid-quote-cite-fragment-target'], $invalid['quoteCiteFragmentIssueCodes']);
        $t->same(['invalid-quote-cite-fragment-target'], $invalid['quoteCiteIssueCodes']);

        $t->same('block', $duplicate['quote']);
        $t->same('duplicate-id', $duplicate['quoteCiteFragmentTargetKind']);
        $t->same(2, $duplicate['quoteCiteFragmentTargetCount']);
        $t->same(['duplicate-quote-cite-fragment-target'], $duplicate['quoteCiteFragmentIssueCodes']);
        $t->same(['duplicate-quote-cite-fragment-target'], $duplicate['quoteCiteIssueCodes']);
        $t->same(['div', 'section'], array_map(static fn (array $target): string => (string) $target['tag'], $duplicate['quoteCiteFragmentTargetElements']));
        $t->same([[
            'code' => 'duplicate-quote-cite-fragment-target',
            'fragmentTarget' => 'dup-source',
            'targetType' => 'id',
            'count' => 2,
        ]], $duplicate['quoteCiteIssues']);

        $t->same('<article id="source">Resolved claim</article><a name="legacy-source">Legacy source</a><p><q cite="#source" id="resolved">Resolved quote</q><q cite="#legacy-source" id="legacy">Legacy quote</q><q cite="#" id="top">Top quote</q></p><p><q cite="#missing-source" id="missing">Missing quote</q><q cite="#bad target" id="invalid">Invalid quote</q></p><div id="dup-source">First duplicate</div><section id="dup-source">Second duplicate</section><blockquote cite="#dup-source" id="duplicate-quote"><p>Duplicate quote</p></blockquote>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/quote-cite-fragment-target-review.html', $document->children[0]->attr('part'));
    },
];
