<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html hidden state inheritance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="root" hidden="until-found"><p id="child">Child <span id="grand">Grand</span></p><section id="own" hidden>Own</section><aside id="bad" hidden="collapse"><em id="bad-child">Bad child</em></aside></article>'
                . '<p id="outside">Outside</p>',
            'hidden state inheritance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/hidden-state-inheritance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $root = $summary[0];
        $child = $root['children'][0];
        $own = $root['children'][1];
        $bad = $root['children'][2];
        $badChild = $bad['children'][0];
        $outside = $summary[1];

        $t->same('html-hidden-state-inheritance-review', $root['hiddenReviewPolicy']);
        $t->same('until-found', $root['hiddenRaw']);
        $t->same('until-found', $root['hiddenKeyword']);
        $t->same('until-found', $root['hiddenState']);
        $t->same(true, $root['hiddenValid']);
        $t->same('until-found', $root['effectiveHiddenRaw']);
        $t->same('until-found', $root['effectiveHiddenKeyword']);
        $t->same('until-found', $root['effectiveHiddenState']);
        $t->same(true, $root['effectiveHidden']);
        $t->same(true, $root['effectiveHiddenUntilFound']);
        $t->same(false, $root['effectiveHiddenInvalidValueDefaulted']);
        $t->same(false, $root['hiddenInherited']);
        $t->same('self-hidden', $root['hiddenSource']);
        $t->same('article', $root['hiddenSourceElement']);
        $t->same('root', $root['hiddenSourceElementId']);

        $t->true(!array_key_exists('hiddenRaw', $child));
        $t->same('until-found', $child['effectiveHiddenState']);
        $t->same(true, $child['effectiveHiddenUntilFound']);
        $t->same(true, $child['hiddenInherited']);
        $t->same('ancestor-hidden', $child['hiddenSource']);
        $t->same('root', $child['hiddenSourceElementId']);

        $t->same('', $own['hiddenRaw']);
        $t->same('hidden', $own['hiddenKeyword']);
        $t->same('hidden', $own['effectiveHiddenState']);
        $t->same(false, $own['effectiveHiddenUntilFound']);
        $t->same(false, $own['hiddenInherited']);
        $t->same('self-hidden', $own['hiddenSource']);
        $t->same('own', $own['hiddenSourceElementId']);

        $t->same('collapse', $bad['hiddenRaw']);
        $t->same(null, $bad['hiddenKeyword']);
        $t->same('hidden', $bad['hiddenState']);
        $t->same(false, $bad['hiddenValid']);
        $t->same(true, $bad['hiddenInvalidValueDefaulted']);
        $t->same('hidden', $bad['effectiveHiddenState']);
        $t->same(true, $bad['effectiveHiddenInvalidValueDefaulted']);
        $t->same(false, $bad['hiddenInherited']);
        $t->same('bad', $bad['hiddenSourceElementId']);
        $t->same('collapse', $badChild['effectiveHiddenRaw']);
        $t->same('hidden', $badChild['effectiveHiddenState']);
        $t->same(true, $badChild['effectiveHiddenInvalidValueDefaulted']);
        $t->same(true, $badChild['hiddenInherited']);
        $t->same('bad', $badChild['hiddenSourceElementId']);

        $t->same('outside', $outside['elementId']);
        $t->true(!array_key_exists('hiddenReviewPolicy', $outside));
        $t->true(!array_key_exists('effectiveHiddenState', $outside));
        $t->same(
            '<article hidden="until-found" id="root"><p id="child">Child <span id="grand">Grand</span></p><section hidden id="own">Own</section><aside hidden="collapse" id="bad"><em id="bad-child">Bad child</em></aside></article><p id="outside">Outside</p>',
            $html
        );
        $t->contains($html, $blocks);
        $t->same('/migration/hidden-state-inheritance-review.html', $document->children[0]->attr('part'));
    },
];
