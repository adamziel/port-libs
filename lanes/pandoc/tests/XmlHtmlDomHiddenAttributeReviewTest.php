<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html hidden attribute states for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="plain" hidden>Plain hidden</section>'
                . '<article id="found" hidden="until-found"><h2>Searchable panel</h2><p>Reveal from find in page</p></article>'
                . '<aside id="bad" hidden="dismissed">Invalid hidden token</aside>'
                . '<p id="visible">Visible</p>',
            'hidden attribute review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hidden-attribute-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $plain = $summary[0];
        $found = $summary[1];
        $bad = $summary[2];
        $visible = $summary[3];

        $t->same('html-hidden-state-review', $plain['hiddenAttributeReviewPolicy']);
        $t->same('', $plain['hiddenRaw']);
        $t->same('hidden', $plain['hiddenKeyword']);
        $t->same('hidden', $plain['hiddenState']);
        $t->same(true, $plain['hidden']);
        $t->same(true, $plain['hiddenValid']);
        $t->same(false, $plain['hiddenInvalidValueDefaulted']);
        $t->same(false, $plain['hiddenUntilFound']);
        $t->same(false, $plain['hiddenFindInPageDiscoverable']);
        $t->same(false, $plain['hiddenBeforeMatchRevealCandidate']);
        $t->same('not-rendered', $plain['hiddenRevealMode']);
        $t->same(false, $plain['hiddenBrowserEventDispatch']);
        $t->same('metadata-only-no-browser-beforematch', $plain['hiddenReviewHandoffPolicy']);
        $t->same('section', $plain['hiddenElement']);
        $t->same('plain', $plain['hiddenElementId']);
        $t->same([], $plain['hiddenIssueCodes']);

        $t->same('until-found', $found['hiddenRaw']);
        $t->same('until-found', $found['hiddenKeyword']);
        $t->same('until-found', $found['hiddenState']);
        $t->same(true, $found['hidden']);
        $t->same(true, $found['hiddenValid']);
        $t->same(false, $found['hiddenInvalidValueDefaulted']);
        $t->same(true, $found['hiddenUntilFound']);
        $t->same(true, $found['hiddenFindInPageDiscoverable']);
        $t->same(true, $found['hiddenBeforeMatchRevealCandidate']);
        $t->same('beforematch-fragment-reveal', $found['hiddenRevealMode']);
        $t->same(false, $found['hiddenBrowserEventDispatch']);
        $t->same('article', $found['hiddenElement']);
        $t->same('found', $found['hiddenElementId']);
        $t->same([], $found['hiddenIssues']);
        $t->same('Searchable panel', $found['children'][0]['text']);

        $t->same('dismissed', $bad['hiddenRaw']);
        $t->same(null, $bad['hiddenKeyword']);
        $t->same('hidden', $bad['hiddenState']);
        $t->same(false, $bad['hiddenValid']);
        $t->same(true, $bad['hiddenInvalidValueDefaulted']);
        $t->same(false, $bad['hiddenUntilFound']);
        $t->same('not-rendered-invalid-default', $bad['hiddenRevealMode']);
        $t->same([['code' => 'invalid-html-hidden-token', 'hiddenRaw' => 'dismissed']], $bad['hiddenIssues']);
        $t->same(['invalid-html-hidden-token'], $bad['hiddenIssueCodes']);

        $t->same('visible', $visible['elementId']);
        $t->true(!array_key_exists('hiddenAttributeReviewPolicy', $visible));
        $t->true(!array_key_exists('hiddenState', $visible));

        $t->contains('<section hidden id="plain">Plain hidden</section>', $html);
        $t->contains('<article hidden="until-found" id="found">', $html);
        $t->contains('<aside hidden="dismissed" id="bad">Invalid hidden token</aside>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/hidden-attribute-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
