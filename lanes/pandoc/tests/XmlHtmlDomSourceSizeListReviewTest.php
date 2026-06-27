<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html source size list provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<picture>'
                . '<source media="(min-width: 60em)" srcset="/img/hero-wide.avif 1200w" sizes="(min-width: 80em) 50vw, calc(100vw - 2rem)">'
                . '<img id="hero" loading="lazy" src="/img/hero.jpg" srcset="/img/hero-small.jpg 400w, /img/hero-large.jpg 1200w" sizes="auto, (max-width: 40em) 100vw, 50vw" alt="Hero">'
                . '</picture>'
                . '<link rel="preload" as="image" href="/img/hero.jpg" imagesrcset="/img/hero-small.jpg 400w, /img/hero-large.jpg 1200w" imagesizes="(max-width: 40em) 100vw, 50vw">'
                . '<img id="bad" src="/img/bad.jpg" sizes="(max-width: 40em) 50%,, bad&lt;value" alt="Bad">',
            'source size list review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/source-size-list-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $picture = $summary[0];
        $source = $picture['pictureSources'][0];
        $image = $picture['image'];
        $link = $summary[1];
        $bad = $summary[2];

        $t->same('html-source-size-list-review', $source['sizesReviewPolicy']);
        $t->same('(min-width: 80em) 50vw, calc(100vw - 2rem)', $source['sizesRaw']);
        $t->same(2, $source['sizesItemCount']);
        $t->same(['length', 'function'], $source['sizesSourceSizeKinds']);
        $t->same('(min-width: 80em)', $source['sizesItems'][0]['mediaConditionRaw']);
        $t->same('50vw', $source['sizesItems'][0]['sourceSizeRaw']);
        $t->same('length', $source['sizesItems'][0]['sourceSizeKind']);
        $t->same('calc(100vw - 2rem)', $source['sizesItems'][1]['sourceSizeRaw']);
        $t->same('function', $source['sizesItems'][1]['sourceSizeKind']);
        $t->same('calc(100vw - 2rem)', $source['sizesDefaultSourceSize']);
        $t->same(false, $source['sizesAutoPresent']);
        $t->same([], $source['sizesIssueCodes']);
        $t->same(true, $source['sizesValid']);

        $t->same('html-source-size-list-review', $image['sizesReviewPolicy']);
        $t->same('auto, (max-width: 40em) 100vw, 50vw', $image['sizesRaw']);
        $t->same(3, $image['sizesItemCount']);
        $t->same(['auto', 'length'], $image['sizesSourceSizeKinds']);
        $t->same(true, $image['sizesAutoPresent']);
        $t->same('auto', $image['sizesItems'][0]['sourceSizeKind']);
        $t->same('(max-width: 40em)', $image['sizesItems'][1]['mediaConditionRaw']);
        $t->same('100vw', $image['sizesItems'][1]['sourceSizeNormalized']);
        $t->same('50vw', $image['sizesDefaultSourceSize']);
        $t->same([], $image['sizesIssueCodes']);
        $t->same(true, $image['sizesValid']);
        $t->same('html-srcset-resource-metadata-review', $image['srcsetResourceReviewPolicy']);

        $t->same('html-source-size-list-review', $link['imageSizesReviewPolicy']);
        $t->same('(max-width: 40em) 100vw, 50vw', $link['imageSizesRaw']);
        $t->same(2, $link['imageSizesItemCount']);
        $t->same('100vw', $link['imageSizesItems'][0]['sourceSizeNormalized']);
        $t->same('50vw', $link['imageSizesDefaultSourceSize']);
        $t->same(false, $link['imageSizesAutoPresent']);
        $t->same([], $link['imageSizesIssueCodes']);
        $t->same(true, $link['imageSizesValid']);
        $t->same('preload', $link['linkPrimaryResourceKind']);

        $t->same('html-source-size-list-review', $bad['sizesReviewPolicy']);
        $t->same('(max-width: 40em) 50%,, bad<value', $bad['sizesRaw']);
        $t->same(3, $bad['sizesItemCount']);
        $t->same('50%', $bad['sizesItems'][0]['sourceSizeRaw']);
        $t->same([
            'source-size-percent-not-allowed',
            'invalid-source-size-value',
        ], $bad['sizesItems'][0]['issueCodes']);
        $t->same('', $bad['sizesItems'][1]['raw']);
        $t->same(['empty-source-size-item', 'missing-source-size-value'], $bad['sizesItems'][1]['issueCodes']);
        $t->same('bad<value', $bad['sizesItems'][2]['sourceSizeRaw']);
        $t->same(['unsafe-source-size-value', 'invalid-source-size-value'], $bad['sizesItems'][2]['issueCodes']);
        $t->same([
            'source-size-percent-not-allowed',
            'invalid-source-size-value',
            'empty-source-size-item',
            'missing-source-size-value',
            'unsafe-source-size-value',
        ], $bad['sizesIssueCodes']);
        $t->same(false, $bad['sizesValid']);

        $t->contains('sizes="auto, (max-width: 40em) 100vw, 50vw"', $html);
        $t->contains('imagesizes="(max-width: 40em) 100vw, 50vw"', $html);
        $t->contains('sizes="(max-width: 40em) 50%,, bad&lt;value"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/source-size-list-review.html', $document->children[0]->attr('part'));
        json_encode($summary, JSON_THROW_ON_ERROR);
    },
];
