<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html base href and target review metadata for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<base href="https://example.test/docs/" target="_blank">'
                . '<base href="../assets/" target="review-frame">'
                . '<base href="java&#10;script:alert(1)" target="bad{frame}">'
                . '<base target="side-frame">'
                . '<base target="">'
                . '<base>'
                . '<base target="review&#10;&lt;frame">'
                . '<p>Body</p>',
            'base metadata review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/base-metadata-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $trusted = $summary[0];
        $relative = $summary[1];
        $unsafe = $summary[2];
        $targetOnly = $summary[3];
        $emptyTarget = $summary[4];
        $missing = $summary[5];
        $normalizedTarget = $summary[6];
        $paragraph = $summary[7];

        $t->same('base', $trusted['documentMetadata']);
        $t->same('html-base-url-target-review', $trusted['baseReviewPolicy']);
        $t->same('https://example.test/docs/', $trusted['baseHrefRaw']);
        $t->same('https://example.test/docs/', $trusted['baseHref']);
        $t->same('absolute', $trusted['baseHrefKind']);
        $t->same('https', $trusted['baseHrefScheme']);
        $t->same(false, $trusted['baseHrefUnsafe']);
        $t->same('https://example.test/docs/', $trusted['baseHrefResolvedUrl']);
        $t->same(true, $trusted['baseHrefUsable']);
        $t->same(true, $trusted['baseHrefValid']);
        $t->same('_blank', $trusted['baseTargetRaw']);
        $t->same('_blank', $trusted['baseTargetName']);
        $t->same('_blank', $trusted['baseTargetEffectiveName']);
        $t->same('_blank', $trusted['baseTargetKeyword']);
        $t->same(false, $trusted['baseTargetCustom']);
        $t->same(false, $trusted['baseTargetUnsafe']);
        $t->same(true, $trusted['baseTargetValid']);
        $t->same([], $trusted['baseIssueCodes']);
        $t->same(true, $trusted['baseValid']);

        $t->same('../assets/', $relative['baseHrefRaw']);
        $t->same('relative', $relative['baseHrefKind']);
        $t->same('../assets/', $relative['baseHrefResolvedUrl']);
        $t->same(true, $relative['baseHrefUsable']);
        $t->same('review-frame', $relative['baseTargetName']);
        $t->same('review-frame', $relative['baseTargetEffectiveName']);
        $t->same(null, $relative['baseTargetKeyword']);
        $t->same(true, $relative['baseTargetCustom']);
        $t->same([], $relative['baseIssueCodes']);
        $t->same(true, $relative['baseValid']);

        $t->same('invalid', $unsafe['baseHrefKind']);
        $t->same(true, $unsafe['baseHrefUnsafe']);
        $t->same(null, $unsafe['baseHrefResolvedUrl']);
        $t->same(false, $unsafe['baseHrefUsable']);
        $t->same(false, $unsafe['baseHrefValid']);
        $t->same(['invalid-base-href', 'unsafe-base-href'], $unsafe['baseHrefIssueCodes']);
        $t->same(null, $unsafe['baseTargetName']);
        $t->same(null, $unsafe['baseTargetEffectiveName']);
        $t->same(true, $unsafe['baseTargetUnsafe']);
        $t->same(false, $unsafe['baseTargetValid']);
        $t->same(['unsafe-base-target'], $unsafe['baseTargetIssueCodes']);
        $t->same(['invalid-base-href', 'unsafe-base-href', 'unsafe-base-target'], $unsafe['baseIssueCodes']);
        $t->same(false, $unsafe['baseValid']);

        $t->same(null, $targetOnly['baseHrefRaw']);
        $t->same('missing', $targetOnly['baseHrefKind']);
        $t->same(null, $targetOnly['baseHrefValid']);
        $t->same('side-frame', $targetOnly['baseTargetName']);
        $t->same([], $targetOnly['baseIssueCodes']);
        $t->same(true, $targetOnly['baseValid']);

        $t->same('', $emptyTarget['baseTargetRaw']);
        $t->same(null, $emptyTarget['baseTargetName']);
        $t->same(false, $emptyTarget['baseTargetValid']);
        $t->same(['empty-base-target'], $emptyTarget['baseIssueCodes']);
        $t->same(false, $emptyTarget['baseValid']);

        $t->same(null, $missing['baseHrefRaw']);
        $t->same(null, $missing['baseTargetRaw']);
        $t->same(['missing-base-href-or-target'], $missing['baseIssueCodes']);
        $t->same(false, $missing['baseValid']);

        $t->same(null, $normalizedTarget['baseTargetName']);
        $t->same('_blank', $normalizedTarget['baseTargetFallback']);
        $t->same('_blank', $normalizedTarget['baseTargetEffectiveName']);
        $t->same('_blank', $normalizedTarget['baseTargetKeyword']);
        $t->same(true, $normalizedTarget['baseTargetNormalizedToBlank']);
        $t->same(true, $normalizedTarget['baseTargetUnsafe']);
        $t->same(false, $normalizedTarget['baseTargetValid']);
        $t->same(['base-target-normalized-to-blank'], $normalizedTarget['baseIssueCodes']);

        $t->same('Body', $paragraph['text']);
        $t->contains('<base href="https://example.test/docs/" target="_blank">', $html);
        $t->contains("href=\"java\nscript:alert(1)\"", $html);
        $t->contains('target="bad{frame}"', $html);
        $t->contains("target=\"review\n&lt;frame\"", $html);
        $t->contains($html, $blocks);
        $t->same('/migration/base-metadata-review.html', $document->children[0]->attr('part'));
    },
];
