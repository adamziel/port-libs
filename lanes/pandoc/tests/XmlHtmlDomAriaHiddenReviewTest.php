<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes aria-hidden subtree provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<section id="decor" aria-hidden="true"><p id="copy">Copy</p><button id="override" aria-hidden="false">Still hidden</button></section>'
                . '<section id="visible" aria-hidden="false"><span id="visible-child">Visible child</span></section>'
                . '<aside id="invalid" aria-hidden="maybe"><em id="invalid-child">Invalid child</em></aside>'
                . '<div id="plain">Plain</div>',
            'aria-hidden subtree review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/aria-hidden-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $decor = $summary[0];
        $copy = $decor['children'][0];
        $override = $decor['children'][1];
        $visible = $summary[1];
        $visibleChild = $visible['children'][0];
        $invalid = $summary[2];
        $invalidChild = $invalid['children'][0];
        $plain = $summary[3];

        $t->same(['aria-hidden' => 'true'], $decor['ariaAttributes']);
        $t->same('aria-hidden-subtree-review', $decor['ariaHiddenReviewPolicy']);
        $t->same('true', $decor['ariaHiddenRaw']);
        $t->same('true', $decor['ariaHiddenKeyword']);
        $t->same('hidden', $decor['ariaHiddenState']);
        $t->same(true, $decor['ariaHidden']);
        $t->same(true, $decor['ariaHiddenValid']);
        $t->same(true, $decor['ariaHiddenHidesSubtree']);
        $t->same(false, $decor['ariaHiddenInvalidValueIgnored']);
        $t->same([], $decor['ariaHiddenIssueCodes']);
        $t->same('metadata-only-no-accessibility-tree', $decor['ariaHiddenReviewHandoffPolicy']);
        $t->same(true, $decor['effectiveAriaHidden']);
        $t->same('hidden', $decor['effectiveAriaHiddenState']);
        $t->same(false, $decor['ariaHiddenInherited']);
        $t->same('self-aria-hidden', $decor['ariaHiddenSource']);
        $t->same('section', $decor['ariaHiddenSourceElement']);
        $t->same('decor', $decor['ariaHiddenSourceElementId']);

        $t->true(!array_key_exists('ariaHiddenReviewPolicy', $copy));
        $t->same(true, $copy['effectiveAriaHidden']);
        $t->same(true, $copy['ariaHiddenInherited']);
        $t->same('ancestor-aria-hidden', $copy['ariaHiddenSource']);
        $t->same('decor', $copy['ariaHiddenSourceElementId']);

        $t->same('false', $override['ariaHiddenRaw']);
        $t->same('false', $override['ariaHiddenKeyword']);
        $t->same('visible', $override['ariaHiddenState']);
        $t->same(false, $override['ariaHidden']);
        $t->same(true, $override['ariaHiddenValid']);
        $t->same(false, $override['ariaHiddenHidesSubtree']);
        $t->same(true, $override['effectiveAriaHidden']);
        $t->same('true', $override['effectiveAriaHiddenKeyword']);
        $t->same('hidden', $override['effectiveAriaHiddenState']);
        $t->same(true, $override['ariaHiddenInherited']);
        $t->same('ancestor-aria-hidden', $override['ariaHiddenSource']);
        $t->same('decor', $override['ariaHiddenSourceElementId']);

        $t->same('false', $visible['ariaHiddenRaw']);
        $t->same(false, $visible['ariaHidden']);
        $t->same(true, $visible['ariaHiddenValid']);
        $t->same(false, $visible['effectiveAriaHidden']);
        $t->same('visible', $visible['effectiveAriaHiddenState']);
        $t->same(false, $visible['ariaHiddenInherited']);
        $t->same('self-aria-hidden', $visible['ariaHiddenSource']);
        $t->true(!array_key_exists('effectiveAriaHidden', $visibleChild));

        $t->same('maybe', $invalid['ariaHiddenRaw']);
        $t->same(null, $invalid['ariaHiddenKeyword']);
        $t->same('invalid', $invalid['ariaHiddenState']);
        $t->same(null, $invalid['ariaHidden']);
        $t->same(false, $invalid['ariaHiddenValid']);
        $t->same(true, $invalid['ariaHiddenInvalidValueIgnored']);
        $t->same([
            ['code' => 'invalid-aria-hidden-token', 'ariaHiddenRaw' => 'maybe'],
        ], $invalid['ariaHiddenIssues']);
        $t->same(['invalid-aria-hidden-token'], $invalid['ariaHiddenIssueCodes']);
        $t->same(false, $invalid['effectiveAriaHidden']);
        $t->same(null, $invalid['effectiveAriaHiddenKeyword']);
        $t->same('visible', $invalid['effectiveAriaHiddenState']);
        $t->same('invalid-aria-hidden-ignored', $invalid['ariaHiddenSource']);
        $t->true(!array_key_exists('effectiveAriaHidden', $invalidChild));
        $t->true(!array_key_exists('ariaHiddenReviewPolicy', $plain));

        $t->contains('aria-hidden="true" id="decor"', $html);
        $t->contains('aria-hidden="false" id="override"', $html);
        $t->contains('aria-hidden="maybe" id="invalid"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/aria-hidden-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
