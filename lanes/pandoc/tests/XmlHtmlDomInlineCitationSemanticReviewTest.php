<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html inline citation text semantics for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<p>Read <cite id="manual" data-review="work">Reviewer Manual</cite> says <q id="claim" cite="./claims.html#one">quoted <em>claim</em></q>.</p>',
            'inline citation semantic review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/inline-citation-semantics-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $summary[0];
        $citedWork = $paragraph['children'][1];
        $inlineQuote = $paragraph['children'][3];
        $emphasis = $inlineQuote['children'][1];

        $t->same('p', $paragraph['name']);
        $t->same('Read Reviewer Manual says quoted claim.', $paragraph['text']);

        $t->same('cite', $citedWork['name']);
        $t->same('cited-work', $citedWork['textSemantic']);
        $t->same('cite', $citedWork['semanticTag']);
        $t->same('Reviewer Manual', $citedWork['semanticText']);
        $t->same('cite', $citedWork['citedWork']);
        $t->same('Reviewer Manual', $citedWork['citedWorkText']);
        $t->same('Reviewer Manual', $citedWork['citationText']);
        $t->same(['review' => 'work'], $citedWork['dataset']);

        $t->same('q', $inlineQuote['name']);
        $t->same('inline-quotation', $inlineQuote['textSemantic']);
        $t->same('q', $inlineQuote['semanticTag']);
        $t->same('quoted claim', $inlineQuote['semanticText']);
        $t->same('inline', $inlineQuote['quote']);
        $t->same('./claims.html#one', $inlineQuote['quoteCite']);
        $t->same('./claims.html#one', $inlineQuote['quoteCiteNormalized']);
        $t->same('relative', $inlineQuote['quoteCiteKind']);
        $t->same(false, $inlineQuote['quoteCiteUnsafe']);
        $t->same([], $inlineQuote['quoteCiteIssueCodes']);
        $t->same('quoted claim', $inlineQuote['quoteText']);

        $t->same('em', $emphasis['semanticTag']);
        $t->same('stress-emphasis', $emphasis['textSemantic']);
        $t->same('claim', $emphasis['semanticText']);

        $t->same('<p>Read <cite data-review="work" id="manual">Reviewer Manual</cite> says <q cite="./claims.html#one" id="claim">quoted <em>claim</em></q>.</p>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/inline-citation-semantics-review.html', $document->children[0]->attr('part'));
    },
];
