<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes responsive image media sizes selection provenance for reviewer handoff' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadHtmlFragment(
            '<picture>'
                . '<source media="(min-width: 60em)" sizes="(min-width: 60em) 50vw, 100vw" type="image/avif" srcset="/img/hero.avif 800w">'
                . '<source media="screen and (background: url(javascript:alert(1)))" sizes="(min-width: 40em) calc(50vw + 2rem), url(javascript:bad)" type="image/webp" srcset="/img/hero.webp 800w">'
                . '<img src="/img/hero.jpg" sizes="(min-width: 30em) calc(100vw - 2rem), 100vw" srcset="/img/hero.jpg 1x" alt="Hero">'
                . '</picture>'
                . '<link rel="preload" as="image" href="/img/hero.jpg" media="screen and (min-width: 40em)" imagesrcset="/img/hero.jpg 1x, /img/hero@2x.jpg 2x" imagesizes="(min-width: 70em) 80vw, 100vw">',
            'responsive selection review fragment'
        );
        $summary = XmlHtmlDom::summarizeHtmlFragment($dom);
        $html = XmlHtmlDom::serializeHtmlFragment($dom);
        $document = new AstNode('document', [], [
            new AstNode('raw_html', ['format' => 'html', 'html' => $html, 'part' => '/migration/responsive-selection-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $picture = $summary[0];
        $validSource = $picture['pictureSources'][0];
        $unsafeSource = $picture['pictureSources'][1];
        $image = $picture['image'];
        $link = $summary[1];

        $t->same('html-responsive-media-metadata-review', $validSource['mediaReviewPolicy']);
        $t->same('(min-width: 60em)', $validSource['mediaCondition']);
        $t->same(true, $validSource['mediaValid']);
        $t->same('html-responsive-sizes-metadata-review', $validSource['responsiveSizesReviewPolicy']);
        $t->same(2, $validSource['responsiveSizesCandidateCount']);
        $t->same(['(min-width: 60em)'], $validSource['responsiveSizesConditions']);
        $t->same(['50vw', '100vw'], $validSource['responsiveSizesLengths']);
        $t->same('(min-width: 60em)', $validSource['responsiveSizesCandidateRecords'][0]['condition']);
        $t->same('50vw', $validSource['responsiveSizesCandidateRecords'][0]['length']);
        $t->same([], $validSource['responsiveSizesIssueCodes']);
        $t->same(true, $validSource['responsiveSizesValid']);

        $t->same(['unsafe-responsive-media'], $unsafeSource['mediaIssueCodes']);
        $t->same(false, $unsafeSource['mediaValid']);
        $t->same(['unsafe-responsive-size', 'invalid-responsive-size-length'], $unsafeSource['responsiveSizesIssueCodes']);
        $t->same(false, $unsafeSource['responsiveSizesValid']);
        $t->same('url(javascript:bad)', $unsafeSource['responsiveSizesCandidateRecords'][1]['length']);

        $t->same(['calc(100vw - 2rem)', '100vw'], $image['responsiveSizesLengths']);
        $t->same(true, $image['responsiveSizesCandidateRecords'][0]['lengthValid']);
        $t->same([], $image['responsiveSizesIssueCodes']);

        $t->same('screen and (min-width: 40em)', $link['mediaCondition']);
        $t->same(true, $link['mediaValid']);
        $t->same(['80vw', '100vw'], $link['responsiveImageSizesLengths']);
        $t->same(true, $link['responsiveImageSizesValid']);
        $t->same('preload', $link['linkPrimaryResourceKind']);
        $t->same(true, $link['imageSrcsetValid']);

        $t->contains('imagesizes="(min-width: 70em) 80vw, 100vw"', $html);
        $t->contains('url(javascript:bad)', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/responsive-selection-review.html', $document->children[0]->attr('part'));
    },
];
