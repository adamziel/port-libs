<?php

declare(strict_types=1);

use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes docbook media role caption stable fields' => static function (TestRunner $t): void {
        $docbook = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article xmlns="http://docbook.org/ns/docbook" version="5.2" xml:id="media-root">
  <title>DocBook Media Stable Fields</title>
  <section xml:id="media-section">
    <title>Media coverage</title>
    <mediaobject xml:id="hero-media" id="legacy-hero" role="screenshot">
      <title>Hero media title</title>
      <caption><para>Hero screenshot import</para></caption>
      <imageobject role="screenshot"><imagedata fileref="images/hero.png" format="PNG"/></imageobject>
      <textobject role="alt"><phrase>Hero screenshot alt text</phrase></textobject>
    </mediaobject>
    <inlinemediaobject xml:id="hero-inline" role="screenshot">
      <imageobject role="screenshot"><imagedata fileref="images/hero.png" format="PNG"/></imageobject>
      <textobject role="alt"><phrase>Inline hero alt text</phrase></textobject>
    </inlinemediaobject>
    <mediaobject xml:id="title-only" role="diagram">
      <title>Title-only fallback caption</title>
      <imageobject role="diagram"><imagedata fileref="images/diagram.svg" format="SVG"/></imageobject>
      <textobject><phrase>Diagram description</phrase></textobject>
    </mediaobject>
    <mediaobject xml:id="poster-media" id="legacy-poster" role="poster">
      <imageobject role="poster"><imagedata fileref="images/poster.png" format="PNG"/></imageobject>
    </mediaobject>
  </section>
</article>
XML, 'DocBook media stable field XML', preserveWhiteSpace: false);

        $packet = XmlHtmlDom::summarizeDocBookReviewPacket($docbook, 'docbook5');

        $t->same(false, $packet['directReaderParity']);
        $t->same('docbook-structural-media-review-only', $packet['reviewPolicy']);
        $t->same(4, $packet['mediaObjectCount']);
        $t->same(['legacy-hero', 'legacy-poster'], $packet['mediaObjectIds']);
        $t->same(['hero-media', 'hero-inline', 'title-only', 'poster-media'], $packet['mediaObjectXmlIds']);
        $t->same(['screenshot', 'alt', 'diagram', 'poster'], $packet['mediaObjectRoles']);
        $t->same(['Hero screenshot import', 'Title-only fallback caption'], $packet['mediaCaptionTexts']);
        $t->same(2, $packet['captionedMediaObjectCount']);
        $t->same(['legacy-hero'], $packet['captionedMediaObjectIds']);
        $t->same(['hero-media', 'title-only'], $packet['captionedMediaObjectXmlIds']);
        $t->same(2, $packet['captionlessMediaObjectCount']);
        $t->same(['legacy-poster'], $packet['captionlessMediaObjectIds']);
        $t->same(['hero-inline', 'poster-media'], $packet['captionlessMediaObjectXmlIds']);
        $t->same(['Hero screenshot alt text', 'Inline hero alt text', 'Diagram description'], $packet['mediaTextAlternativeTexts']);
        $t->same(3, $packet['mediaObjectsWithTextAlternativeCount']);
        $t->same(['legacy-hero'], $packet['mediaObjectsWithTextAlternativeIds']);
        $t->same(['hero-media', 'hero-inline', 'title-only'], $packet['mediaObjectsWithTextAlternativeXmlIds']);
        $t->same(1, $packet['mediaObjectsMissingTextAlternativeCount']);
        $t->same(['legacy-poster'], $packet['mediaObjectsMissingTextAlternativeIds']);
        $t->same(['poster-media'], $packet['mediaObjectsMissingTextAlternativeXmlIds']);
        $t->same(['images/hero.png', 'images/diagram.svg', 'images/poster.png'], $packet['mediaTargetManifestTargets']);
        $t->same(3, $packet['mediaTargetManifestCount']);
        $t->same(2, $packet['mediaTargetManifest'][0]['occurrenceCount'] ?? null);
        $t->same(['hero-media', 'hero-inline'], $packet['mediaTargetManifest'][0]['mediaObjectXmlIds'] ?? null);
        $t->same(['Hero screenshot import'], $packet['mediaTargetManifest'][0]['captionTexts'] ?? null);
        $t->same(['Hero screenshot alt text', 'Inline hero alt text'], $packet['mediaTargetManifest'][0]['textAlternatives'] ?? null);
        $t->same(1, $packet['repeatedMediaRoleTargetPairCount']);
        $t->same('images/hero.png', $packet['repeatedMediaRoleTargetPairs'][0]['target'] ?? null);
        $t->same([
            'docbook-media-missing-caption',
            'docbook-media-missing-caption',
            'docbook-media-missing-alt-text',
            'docbook-media-repeated-role-target',
        ], $packet['mediaDiagnosticCodes']);
        $t->same('poster-media', $packet['mediaDiagnostics'][2]['details']['xmlId'] ?? null);
        json_encode($packet, JSON_THROW_ON_ERROR);
    },
];
