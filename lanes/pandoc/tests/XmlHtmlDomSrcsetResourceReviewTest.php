<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes html srcset resource provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<picture>'
                . '<source type="image/avif" srcset="/img/hero.avif 400w, https://cdn.example.test/hero.avif 800w, data:image/png;base64,AAAA 2x, bad.png 1x 2x">'
                . '<img src="/img/hero.jpg" srcset="/img/hero-small.jpg 1x, /img/hero-large.jpg 2x" sizes="100vw" crossorigin="Anonymous" referrerpolicy="strict-origin" alt="Hero">'
                . '</picture>'
                . '<link rel="preload" as="image" href="/img/hero.jpg" imagesrcset="/img/hero.jpg 1x, https://cdn.example.test/hero@2x.jpg 2x" imagesizes="100vw">',
            'srcset resource review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/srcset-resource-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $picture = $summary[0];
        $source = $picture['pictureSources'][0];
        $image = $picture['image'];
        $link = $summary[1];

        $t->same('html-srcset-resource-metadata-review', $source['srcsetResourceReviewPolicy']);
        $t->same(4, $source['srcsetCandidateCount']);
        $t->same([
            '/img/hero.avif',
            'https://cdn.example.test/hero.avif',
            'data:image/png;base64,AAAA',
            'bad.png',
        ], $source['srcsetCandidateUrls']);
        $t->same('width', $source['srcsetCandidateUrlRecords'][0]['descriptorKind']);
        $t->same(400, $source['srcsetCandidateUrlRecords'][0]['descriptorValue']);
        $t->same('absolute', $source['srcsetCandidateUrlRecords'][1]['urlKind']);
        $t->same('https', $source['srcsetCandidateUrlRecords'][1]['urlScheme']);
        $t->same('pixel-density', $source['srcsetCandidateUrlRecords'][2]['descriptorKind']);
        $t->same(2.0, $source['srcsetCandidateUrlRecords'][2]['descriptorValue']);
        $t->same('data:image/png;base64,AAAA 2x', $source['srcsetCandidateUrlRecords'][2]['raw']);
        $t->same(true, $source['srcsetCandidateUrlRecords'][2]['urlUnsafe']);
        $t->same(false, $source['srcsetCandidateUrlRecords'][3]['descriptorValid']);
        $t->same(['https://cdn.example.test/hero.avif'], $source['srcsetRemoteUrls']);
        $t->same(['data:image/png;base64,AAAA'], $source['srcsetUnsafeUrls']);
        $t->same(['1x 2x'], $source['srcsetInvalidDescriptors']);
        $t->same(['width', 'pixel-density', 'invalid'], $source['srcsetDescriptorKinds']);
        $t->same([
            'unsafe-srcset-url',
            'invalid-srcset-descriptor',
            'mixed-srcset-descriptor-kinds',
        ], $source['srcsetIssueCodes']);
        $t->same(false, $source['srcsetValid']);

        $t->same('html-srcset-resource-metadata-review', $image['srcsetResourceReviewPolicy']);
        $t->same(2, $image['srcsetCandidateCount']);
        $t->same(['pixel-density'], $image['srcsetDescriptorKinds']);
        $t->same([], $image['srcsetIssueCodes']);
        $t->same(true, $image['srcsetValid']);
        $t->same('anonymous', $image['imageCrossoriginState']);
        $t->same('strict-origin', $image['imageReferrerPolicy']);

        $t->same('html-srcset-resource-metadata-review', $link['imageSrcsetResourceReviewPolicy']);
        $t->same(2, $link['imageSrcsetCandidateCount']);
        $t->same(['https://cdn.example.test/hero@2x.jpg'], $link['imageSrcsetRemoteUrls']);
        $t->same([], $link['imageSrcsetIssueCodes']);
        $t->same(true, $link['imageSrcsetValid']);
        $t->same('preload', $link['linkPrimaryResourceKind']);

        $t->contains('data:image/png;base64,AAAA 2x', $html);
        $t->contains('imagesrcset="/img/hero.jpg 1x, https://cdn.example.test/hero@2x.jpg 2x"', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/srcset-resource-review.html', $document->children[0]->attr('part'));
    },
];
