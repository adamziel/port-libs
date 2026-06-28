<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html hidden state review metadata for raw handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="bare" hidden>Bare</section>'
                . '<section id="keyword" hidden="hidden">Keyword</section>'
                . '<section id="found" hidden="until-found">Found</section>'
                . '<section id="case" hidden="UNTIL-FOUND">Case</section>'
                . '<section id="invalid" hidden="collapse">Invalid</section>'
                . '<section id="plain">Plain</section>',
            'hidden state review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hidden-state-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $bare = $summary[0];
        $keyword = $summary[1];
        $found = $summary[2];
        $case = $summary[3];
        $invalid = $summary[4];
        $plain = $summary[5];

        $t->same('html-hidden-state-review', $bare['hiddenReviewPolicy']);
        $t->same('', $bare['hiddenRaw']);
        $t->same('hidden', $bare['hiddenKeyword']);
        $t->same('hidden', $bare['hiddenState']);
        $t->same(false, $bare['hiddenUntilFound']);
        $t->same(true, $bare['hiddenValid']);
        $t->same([], $bare['hiddenIssueCodes']);

        $t->same('hidden', $keyword['hiddenRaw']);
        $t->same('hidden', $keyword['hiddenKeyword']);
        $t->same('hidden', $keyword['hiddenState']);
        $t->same(false, $keyword['hiddenUntilFound']);
        $t->same(true, $keyword['hiddenValid']);

        $t->same('until-found', $found['hiddenRaw']);
        $t->same('until-found', $found['hiddenKeyword']);
        $t->same('until-found', $found['hiddenState']);
        $t->same(true, $found['hiddenUntilFound']);
        $t->same(true, $found['hiddenValid']);
        $t->same(false, $found['hiddenInvalidValueDefaulted']);

        $t->same('UNTIL-FOUND', $case['hiddenRaw']);
        $t->same('until-found', $case['hiddenKeyword']);
        $t->same('until-found', $case['hiddenState']);
        $t->same(true, $case['hiddenUntilFound']);
        $t->same(true, $case['hiddenValid']);

        $t->same('collapse', $invalid['hiddenRaw']);
        $t->same(null, $invalid['hiddenKeyword']);
        $t->same('hidden', $invalid['hiddenState']);
        $t->same(false, $invalid['hiddenUntilFound']);
        $t->same(false, $invalid['hiddenValid']);
        $t->same(true, $invalid['hiddenInvalidValueDefaulted']);
        $t->same(['invalid-html-hidden-token'], $invalid['hiddenIssueCodes']);
        $t->same('invalid-html-hidden-token', $invalid['hiddenIssues'][0]['code'] ?? null);
        $t->same('collapse', $invalid['hiddenIssues'][0]['hiddenRaw'] ?? null);

        $t->true(!array_key_exists('hiddenReviewPolicy', $plain));
        $t->true(!array_key_exists('hiddenRaw', $plain));
        $t->same(
            '<section hidden id="bare">Bare</section>'
                . '<section hidden id="keyword">Keyword</section>'
                . '<section hidden="until-found" id="found">Found</section>'
                . '<section hidden="UNTIL-FOUND" id="case">Case</section>'
                . '<section hidden="collapse" id="invalid">Invalid</section>'
                . '<section id="plain">Plain</section>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/hidden-state-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
