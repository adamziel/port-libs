<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html translate inheritance and invalid tokens for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<article id="root" translate="no"><p id="child">Child <span id="invalid" translate="maybe">Invalid</span><em id="yes" translate="yes">Yes</em><strong id="empty" translate>Empty</strong></p></article>'
                . '<section id="plain"><span id="plain-child">Plain</span><span id="invalid-plain" translate="later">Invalid Plain</span></section>',
            'translate inheritance review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/translate-inheritance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $byId = [];
        $collect = static function (array $nodes) use (&$collect, &$byId): void {
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                if (($node['type'] ?? null) === 'element' && is_string($node['elementId'] ?? null)) {
                    $byId[$node['elementId']] = $node;
                }
                if (is_array($node['children'] ?? null)) {
                    $collect($node['children']);
                }
            }
        };
        $collect($summary);

        $root = $byId['root'];
        $child = $byId['child'];
        $invalid = $byId['invalid'];
        $yes = $byId['yes'];
        $empty = $byId['empty'];
        $plainChild = $byId['plain-child'];
        $invalidPlain = $byId['invalid-plain'];

        $t->same('html-translate-inheritance-review', $root['translateReviewPolicy']);
        $t->same('no', $root['translateRaw']);
        $t->same(false, $root['translate']);
        $t->same('no', $root['translateKeyword']);
        $t->same(true, $root['translateValid']);
        $t->same(false, $root['translateInvalidValueInherited']);
        $t->same([], $root['translateIssueCodes']);
        $t->same(false, $root['effectiveTranslate']);
        $t->same('no', $root['effectiveTranslateKeyword']);
        $t->same(false, $root['translateInherited']);
        $t->same('self-translate', $root['translateSource']);

        $t->true(!array_key_exists('translateRaw', $child));
        $t->same(false, $child['effectiveTranslate']);
        $t->same(true, $child['translateInherited']);
        $t->same('ancestor-translate', $child['translateSource']);
        $t->same('root', $child['translateSourceElementId']);

        $t->same('maybe', $invalid['translateRaw']);
        $t->same(null, $invalid['translate']);
        $t->same(null, $invalid['translateKeyword']);
        $t->same(false, $invalid['translateValid']);
        $t->same(true, $invalid['translateInvalidValueInherited']);
        $t->same(['invalid-html-translate-token'], $invalid['translateIssueCodes']);
        $t->same(false, $invalid['effectiveTranslate']);
        $t->same('no', $invalid['effectiveTranslateKeyword']);
        $t->same(true, $invalid['translateInherited']);
        $t->same('root', $invalid['translateSourceElementId']);

        $t->same('yes', $yes['translateRaw']);
        $t->same(true, $yes['translate']);
        $t->same('yes', $yes['translateKeyword']);
        $t->same(true, $yes['translateValid']);
        $t->same(false, $yes['translateInvalidValueInherited']);
        $t->same(true, $yes['effectiveTranslate']);
        $t->same('yes', $yes['effectiveTranslateKeyword']);
        $t->same(false, $yes['translateInherited']);

        $t->same('', $empty['translateRaw']);
        $t->same(true, $empty['translate']);
        $t->same('yes', $empty['translateKeyword']);
        $t->same(true, $empty['translateEmptyValueDefaulted']);
        $t->same(true, $empty['effectiveTranslate']);

        $t->true(!array_key_exists('translateRaw', $plainChild));
        $t->true(!array_key_exists('effectiveTranslate', $plainChild));

        $t->same('later', $invalidPlain['translateRaw']);
        $t->same(false, $invalidPlain['translateValid']);
        $t->same(false, $invalidPlain['translateInvalidValueInherited']);
        $t->same(['invalid-html-translate-token'], $invalidPlain['translateIssueCodes']);
        $t->true(!array_key_exists('effectiveTranslate', $invalidPlain));

        $t->contains('<article id="root" translate="no">', $html);
        $t->contains('<span id="invalid" translate="maybe">Invalid</span>', $html);
        $t->contains('<strong id="empty" translate="">Empty</strong>', $html);
        $t->contains('translate="maybe"', $blocks);
    },
];
