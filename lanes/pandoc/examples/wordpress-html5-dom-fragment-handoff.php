<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\WordPressBlockWriter;

$srcdoc = htmlspecialchars(
    '<base href="./embedded/"><article><h2>Embedded srcdoc packet</h2><a href="note.html">frame note</a><img src="frame.png" alt="Frame"><script>drop()</script></article>',
    ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
    'UTF-8'
);

$source = <<<HTML
<!-- legacy export marker -->
<html lang="en-US" dir="ltr">
<body lang="es-MX" dir="auto">
<template><base href="https://inactive.example/assets/"><a href="template-note.html">Template fallback note</a></template>
<base href="https://source.example.test/import/posts/post-42.html?draft=1" target="_blank">
<title>Legacy post title &amp; review packet</title>
<meta charset="Windows-1252">
<meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
<meta name="description" content="Legacy import packet for reviewer handoff">
<meta name="author" content="Migration Desk">
<meta name="keywords" content="wordpress, html import">
<meta name="generator" content="Legacy CMS">
<meta name="application-name" content="Legacy CMS Import">
<meta name="theme-color" content="#0a84ff" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="url(javascript:alert(1))">
<meta name="color-scheme" content="light dark only">
<meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src https: data:; report-uri https://tracker.example.test/csp; script-src 'none'">
<meta http-equiv="Content-Security-Policy" content="script-src java&#10;script:alert(1)">
<meta name="referrer" content="strict-origin-when-cross-origin">
<meta name="referrer" content="bad policy">
<meta property="og:title" content="Legacy social title">
<meta property="og:description" content="Legacy social description">
<meta property="article:published_time" content="2026-06-06T10:00:00Z">
<meta property="twitter:title" content="Reviewer social card">
<meta property="og:image" content="./social-cover.png">
<meta name="viewport" content="width=device-width">
<link rel="canonical" href="../canonical/post-42.html" title="Canonical source">
<link rel="alternate" hreflang="es" type="text/html" href="./es/post-42.html" title="Spanish source">
<link rel="shortlink" href="?p=42">
<link rel="author" href="./authors/migration.html">
<link rel="license" type="text/html" href="../license.html" title="Reuse terms">
<link rel="help" href="?help=import">
<link rel="bookmark" href="#chapter-1" title="Chapter anchor">
<link rel="author preload" href="./active-author.html" title="Active author">
<link rel="alternate stylesheet" href="./legacy.css" title="Legacy theme">
<link rel="canonical" href="java&#10;script:alert(1)" title="Bad canonical">
<link rel="license" href="java&#10;script:alert(1)" title="Bad license">
<link rel="preload" as="image" href="./preload-cover.png">
<meta http-equiv="refresh" content="5; url=./refresh-target.html">
<meta http-equiv="refresh" content="0; url=java&#10;script:alert(1)">
<article id="legacy-post-42" data-source="html-export" data-pandoc-link-rel="source-spoof" data-pandoc-meta-name="source-spoof" xmlns="http://www.w3.org/1999/xhtml" itemscope itemtype="https://schema.org/Article ./schema/LegacyPost" itemid="./post-42.html#article" itemref="imported-headline legacy-author">
  <h1 id="imported-headline" itemprop="headline schema:name">Imported source packet</h1>
  <!--review--->
  <p style="color: #222; font-weight: 600; background-image:url(javascript:alert(1))" property="schema:description og:description" typeof="schema:Article" about="#legacy-post-42" resource="./post-42.html#review" vocab="https://schema.org/" prefix="schema: https://schema.org/ og: https://ogp.me/ns# bad: javascript:alert(1)">AT&amp;T &lt;review&gt; text<br>keeps its line break with a <a href=" ../media/source.html#note&#10;" target="_blank" rel="opener" download="source.html">source note</a> and <a href="./policy-source.html" referrerpolicy=" Strict-Origin ">policy source</a>.</p>
  <p contenteditable="plaintext-only" spellcheck="false" draggable="true" data-pandoc-contenteditable-state="source-spoof">Editable migration note</p>
  <section tabindex=" 0002 " accesskey="r R" autofocus data-pandoc-tabindex="source-spoof" data-pandoc-accesskey="source-spoof" data-pandoc-autofocus-state="source-spoof"><a href="./focus/review.html" tabindex="-1" accesskey="f f">Focusable source</a><span tabindex="bad" accesskey="save">Bad focus</span></section>
  <section lang=" ar " dir="RTL" translate="no" data-pandoc-lang="source-spoof" data-pandoc-translate-state="source-spoof"><p xml:lang="sr-Cyrl-rs" dir="auto" translate="yes">Localized migration note</p></section>
  <section role="region bad-role" aria-label="Import status" aria-describedby="imported-headline legacy-author" aria-expanded="true" aria-current="page" aria-busy="maybe" data-pandoc-aria-label="source-spoof"><p>ARIA migration note</p></section>
  <legacy-gallery data-source="legacy-widget" part="card primary card" exportparts="cover: card-cover, title" data-pandoc-custom-element="source-spoof"><h2>Custom gallery</h2><img src="./custom-cover.png" alt="Custom cover"></legacy-gallery>
  <p is="x-review-paragraph" data-pandoc-custom-is="source-spoof">Custom paragraph <legacy-badge part="status">Ready</legacy-badge></p>
  <p>Published <time datetime="2026-06-08 09:30Z" data-pandoc-time-datetime="source-spoof">June 8, 2026</time>; review took <time datetime="PT2H30M">two hours</time>; bad date <time datetime="java&#10;script:alert(1)">legacy date</time>.</p>
  <p>Metric <data value=" SKU-42 " data-pandoc-data-value="source-spoof">Legacy SKU</data>; import quality <meter value="0.75" min="0" max="1" low=".25" high=".9" optimum="1" data-pandoc-meter-value="source-spoof">Quality</meter>; progress <progress value=".5" max="1">half done</progress>; calculated <output for="quality progress" form="legacy-form" name="total-score" data-pandoc-output-name="source-spoof">total due</output>; bad metric <progress value="-1" max="0">invalid metric</progress>.</p>
  <p>Revision <ins cite="./revisions/add-note.html" datetime="2026-06-08 09:40Z" data-pandoc-revision-cite="source-spoof">added reviewer note</ins> and <del cite="java&#10;script:alert(1)" datetime="2026-06-07">removed unsafe note</del>.</p>
  <blockquote cite="./quotes/source.html" data-pandoc-quote-cite="source-spoof"><p>Imported pull quote <q cite="https://review.example.test/inline-quote.html">inline source</q><q cite="java&#10;script:alert(1)">unsafe source</q></p></blockquote>
  <p>Ruby note <ruby data-pandoc-ruby-annotation="source-spoof">&#28450;<rp>(</rp><rt>Kan ji</rt><rp>)</rp><rtc><rt>Han</rt><rt>character</rt></rtc></ruby></p>
  <p>Math source <math data-pandoc-math-source="source-spoof"><semantics><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow><annotation encoding="application/x-tex"><![CDATA[x + y]]></annotation><annotation-xml encoding="application/mathml-content"><apply><plus></plus><ci>x</ci><ci>y</ci></apply></annotation-xml></semantics></math></p>
  <template shadowrootmode="open" shadowrootdelegatesfocus shadowrootclonable shadowrootserializable data-pandoc-shadowroot-mode="source-spoof"><p>Shadow import <slot name="headline" data-pandoc-slot-name="source-spoof">fallback slot <a href="./shadow/source.html">shadow source</a><a href="java&#10;script:alert(1)">bad shadow</a></slot></p><script>drop()</script></template>
  <details><summary>Collapsed migration notes</summary><p>Hidden packet <a href="./details/source.html">details source</a><a href="java&#10;script:alert(1)">bad details</a></p></details>
  <details open><summary>Open import note</summary><p>Visible disclosure text</p></details>
  <section hidden data-pandoc-hidden-state="source-spoof"><h2>Hidden migration note</h2><p>Hidden packet <a href="./hidden/source.html">hidden source</a><a href="java&#10;script:alert(1)">bad hidden</a></p></section>
  <aside hidden="until-found" inert><p>Search reveal import note</p></aside>
  <aside id="popover-note" popover="manual" data-pandoc-popover-state="source-spoof"><p>Popover migration note <a href="./popover/source.html">popover source</a><a href="java&#10;script:alert(1)">bad popover</a></p></aside>
  <a href="./popover/control.html" popovertarget="popover-note" popovertargetaction="show">Popover control source</a>
  <iframe srcdoc="$srcdoc"></iframe>
  <iframe src="./frames/source.html?review=1" title="Embedded frame source" sandbox="allow-scripts allow-same-origin" allow="fullscreen; clipboard-write" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
  <iframe src="java&#10;script:alert(1)" title="Bad frame"></iframe>
  <object data="./docs/source.pdf" title="Embedded PDF source"><param name="movie" value="./media/player.swf" valuetype="ref" type="application/x-shockwave-flash"><param name="FlashVars" value=" autoplay = false ; poster = cover.png " data-pandoc-object-param-name="source-spoof"><param name="src" value="java&#10;script:alert(1)"><p>PDF fallback <a href="./docs/fallback.html">details</a></p></object>
  <object data="java&#10;script:alert(1)" title="Bad object"><p>Unsafe object fallback</p></object>
  <embed src="./media/demo.mp4" title="Embedded media source">
  <embed src="java&#10;script:alert(1)" title="Bad embed">
  <source srcset="./orphan-source.avif 1x, javascript:alert(1) 2x" type="image/avif">
  <portal src="./portal/review.html" referrerpolicy="strict-origin" title="Portal preview"><p>Portal fallback <a href="./portal/fallback.html">portal fallback source</a></p></portal>
  <portal src="java&#10;script:alert(1)" title="Unsafe portal"><p>Unsafe portal fallback</p></portal>
  <caption>Loose imported caption</caption><col span="2"><thead><td>Loose imported head</td></thead><tfoot><td>Loose imported total</td></tfoot>
  <tr><td>Loose imported row</td></tr><td>Loose imported cell</td><th scope="row">Loose imported header</th>
  <svg><desc><![CDATA[Legacy <source> & review notes]]></desc><image href="data:image/png;base64,iVBORw0KGgo="></image><image href="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4="></image><defs><clipPath id="review-clip"><path d="M0 0"></path></clipPath></defs><g clip-path=" url( #review-clip ) " filter="url(javascript:alert(1))" mask="url(./masks/review.svg#mask)" marker-start="url(ja/**/vascript:alert(1))"><path d="M0 0" fill="url(#paint)" stroke="url( java&#10;script:alert(1) )"></path></g></svg>
  <figure align="right" aria-describedby="cover-caption" data-pandoc-figure-caption="source-spoof"><img src=" cover.png&#13;" srcset=" h&#9;ttps://cdn.example.test/cover.png?x=1&amp;y=2 01.00x, cover.png 1x, ../media/cover@2x.png 2x, javascript:alert(1) 3x" loading=" Lazy " decoding="ASYNC" fetchpriority="HIGH" crossorigin="" data-pandoc-image-loading="source-spoof" alt="Cover"><figcaption id="cover-caption">Cover image</figcaption></figure>
  <video autoplay controls loop muted playsinline preload="metadata" crossorigin="" controlslist="nodownload nofullscreen" width="0640" height="0360" poster="./media/trailer.jpg" data-pandoc-media-controls="source-spoof"><source src="./media/trailer.mp4" type="video/mp4">Media trailer fallback</video>
  <audio controls preload="none" crossorigin="use-credentials" src="./media/interview.mp3">Audio interview fallback</audio>
  <figure><img src="./floorplan.png" usemap="#review-map" alt="Review floor plan"><map name="review-map"><area shape="rect" coords="0,0,120,80" href="./map/lead.html" alt="Mapped lead" target="_blank"><area shape="star" coords="1,2,bad" href="./map/metadata.html" alt="Map metadata"><area shape="star" coords="1,2,bad" href="java&#10;script:alert(1)" alt="Bad map region"></map></figure>
  <picture><source srcset="data:image/png;base64,iVBORw0KGgo= 1x, data:text/html;base64,PHNjcmlwdD4= 2x" type="image/png"><source srcset="hero.avif 1x, javascript:alert(1) 2x" media="(min-width: 48em)" type="image/avif"><source srcset="./metadata-only.webp 1x" media="screen and (background:url(javascript:alert(1)))" sizes="(min-width: 40em) calc(50vw + url(javascript:alert(1)))" type="image/webp"><source srcset="mailto:bad@example.test 1x" media="(max-width: 47em)"><img src="fallback.jpg" srcset="fallback.jpg 1x" sizes="(min-width: 30em) calc(100vw + url(javascript:alert(1)))" alt="Responsive cover"></picture>
  <p><img src="data:image/png;base64,iVBORw0KGgo=" alt="Inline raster"><img src="data:text/html;base64,PHNjcmlwdD4=" alt="HTML data"></p>
  <datalist id="review-suggestions" data-pandoc-datalist-id="source-spoof"><option label="Reviewer suggested tag" value="tag-42"></option><option>Legacy taxonomy</option><option value="private-token"></option></datalist>
  <fieldset disabled name=" import-settings " form="legacy-form" data-pandoc-fieldset-name="source-spoof"><legend>Import settings</legend><p>Form group note <input type="submit" value="Save settings"></p></fieldset>
  <form method="post" action="./forms/review?packet=42" target="review-frame" autocomplete="off" name="comment-form" data-pandoc-form-name="source-spoof"><p><input type="submit" value="Send review"><input type="button" value="Preview packet"><input type="image" src="javascript:alert(1)" alt="Image submit"><select name=" publish-status " form="legacy-form" required size="02" data-pandoc-select-name="source-spoof"><option label="Draft review" value="draft-token"></option><option selected>Ready for import</option><option value="private-token"></option></select><input type="text" value="Hidden draft"><button name=" publish " value=" yes " formaction="./forms/publish?packet=42" formmethod="post" formtarget="review-frame" formnovalidate>Publish now</button><button type="reset" disabled value="clear">Clear draft</button><button type="bad" formaction="java&#10;script:alert(1)" name="bad&lt;tag">Bad button</button></p></form>
</article>
</body>
</html>
HTML;

$fragment = Html5DomFragment::fromHtml($source);
$document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
    $fragment->toRawHtmlAst(['part' => '/migration/legacy-post-42.html']),
]);
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach (['Language: en-US', 'Direction: ltr', 'Body language: es-MX', 'Body direction: auto', 'Base target: _blank', 'Template fallback note', 'Title: Legacy post title & review packet', 'Charset: windows-1252', 'Charset: shift_jis', 'Description: Legacy import packet for reviewer handoff', 'Author: Migration Desk', 'Keywords: wordpress, html import', 'Generator: Legacy CMS', 'Application name: Legacy CMS Import', 'Theme color: #0a84ff', 'Color scheme: light dark only', 'Content security policy: default-src \'self\'; img-src https: data:; script-src \'none\'', 'Referrer policy: strict-origin-when-cross-origin', 'Open Graph title: Legacy social title', 'Open Graph description: Legacy social description', 'Article published time: 2026-06-06T10:00:00Z', 'Twitter title: Reviewer social card', 'Open Graph image', 'Canonical source', 'Spanish source', 'Shortlink', 'Author source', 'Reuse terms', 'Help source', 'Chapter anchor', 'Refresh target', 'Imported source packet', 'AT&T <review> text', 'source note', 'policy source', 'Editable migration note', 'Focusable source', 'Bad focus', 'Localized migration note', 'ARIA migration note', 'Custom gallery', 'Custom paragraph', 'Published', 'June 8, 2026', 'two hours', 'legacy date', 'Metric', 'Legacy SKU', 'Quality', 'half done', 'total due', 'invalid metric', 'Revision', 'added reviewer note', 'removed unsafe note', 'Imported pull quote', 'inline source', 'unsafe source', 'Ruby note', '漢(Kan ji)Hancharacter', 'Math source', 'Shadow root: open', 'fallback slot', 'shadow source', 'Collapsed migration notes', 'Hidden packet', 'details source', 'Open import note', 'Visible disclosure text', 'Hidden migration note', 'hidden source', 'Search reveal import note', 'Popover migration note', 'popover source', 'Popover control source', 'Embedded srcdoc packet', 'frame note', 'Embedded frame source', 'Embedded PDF source', 'Object parameter: movie=https://source.example.test/import/posts/media/player.swf', 'Object parameter: flashvars=autoplay = false ; poster = cover.png', 'Object parameter: src', 'PDF fallback', 'details', 'Unsafe object fallback', 'Embedded media source', 'Portal preview', 'Portal fallback', 'portal fallback source', 'Unsafe portal fallback', 'Loose imported caption', 'Loose imported head', 'Loose imported total', 'Loose imported row', 'Loose imported cell', 'Loose imported header', 'Cover image', 'Media trailer fallback', 'Audio interview fallback', 'Mapped lead', 'Map metadata', 'Datalist suggestions: Reviewer suggested tag; Legacy taxonomy', 'Import settings', 'Form group note', 'Save settings', 'Form submission: post', 'Send review', 'Preview packet', 'Image submit', 'Select: Ready for import', 'Draft review', 'Ready for import', 'Publish now', 'Clear draft', 'Bad button'] as $textSnippet) {
        if (!str_contains($fragment->textContent(), $textSnippet)) {
            throw new RuntimeException('HTML5 DOM fragment self-test missing reviewer text: ' . $textSnippet);
        }
    }
    if ($fragment->baseUrl() !== 'https://source.example.test/import/posts/post-42.html?draft=1') {
        throw new RuntimeException('HTML5 DOM fragment self-test missing base URL');
    }
    foreach ([
        '<span data-pandoc-meta-name="language" data-pandoc-meta-source="html" data-pandoc-meta-content="en-US">Language: en-US</span>',
        '<span data-pandoc-meta-name="direction" data-pandoc-meta-source="html" data-pandoc-meta-content="ltr">Direction: ltr</span>',
        '<span data-pandoc-meta-name="body-language" data-pandoc-meta-source="body" data-pandoc-meta-content="es-MX">Body language: es-MX</span>',
        '<span data-pandoc-meta-name="body-direction" data-pandoc-meta-source="body" data-pandoc-meta-content="auto">Body direction: auto</span>',
        '<!-- legacy export marker -->',
        '<span data-pandoc-meta-name="base-target" data-pandoc-meta-source="base" data-pandoc-meta-content="_blank">Base target: _blank</span>',
        '<a href="https://source.example.test/import/posts/template-note.html">Template fallback note</a>',
        '<span data-pandoc-meta-name="title" data-pandoc-meta-source="title" data-pandoc-meta-content="Legacy post title &amp; review packet">Title: Legacy post title &amp; review packet</span>',
        '<span data-pandoc-meta-charset="windows-1252" data-pandoc-meta-source="charset">Charset: windows-1252</span>',
        '<span data-pandoc-meta-charset="shift_jis" data-pandoc-meta-source="content-type">Charset: shift_jis</span>',
        '<span data-pandoc-meta-name="description" data-pandoc-meta-content="Legacy import packet for reviewer handoff">Description: Legacy import packet for reviewer handoff</span>',
        '<span data-pandoc-meta-name="author" data-pandoc-meta-content="Migration Desk">Author: Migration Desk</span>',
        '<span data-pandoc-meta-name="keywords" data-pandoc-meta-content="wordpress, html import">Keywords: wordpress, html import</span>',
        '<span data-pandoc-meta-name="generator" data-pandoc-meta-content="Legacy CMS">Generator: Legacy CMS</span>',
        '<span data-pandoc-meta-name="application-name" data-pandoc-meta-content="Legacy CMS Import">Application name: Legacy CMS Import</span>',
        '<span data-pandoc-meta-name="theme-color" data-pandoc-meta-content="#0a84ff" data-pandoc-meta-media="(prefers-color-scheme: dark)">Theme color: #0a84ff</span>',
        '<span data-pandoc-meta-name="color-scheme" data-pandoc-meta-content="light dark only">Color scheme: light dark only</span>',
        '<span data-pandoc-meta-http-equiv="content-security-policy" data-pandoc-meta-content="default-src &#039;self&#039;; img-src https: data:; script-src &#039;none&#039;">Content security policy: default-src \'self\'; img-src https: data:; script-src \'none\'</span>',
        '<span data-pandoc-meta-name="referrer" data-pandoc-meta-content="strict-origin-when-cross-origin">Referrer policy: strict-origin-when-cross-origin</span>',
        '<span data-pandoc-meta-property="og:title" data-pandoc-meta-content="Legacy social title">Open Graph title: Legacy social title</span>',
        '<span data-pandoc-meta-property="og:description" data-pandoc-meta-content="Legacy social description">Open Graph description: Legacy social description</span>',
        '<span data-pandoc-meta-property="article:published_time" data-pandoc-meta-content="2026-06-06T10:00:00Z">Article published time: 2026-06-06T10:00:00Z</span>',
        '<span data-pandoc-meta-property="twitter:title" data-pandoc-meta-content="Reviewer social card">Twitter title: Reviewer social card</span>',
        '<a href="https://source.example.test/import/posts/social-cover.png" data-pandoc-meta-property="og:image" data-pandoc-meta-content="https://source.example.test/import/posts/social-cover.png" data-pandoc-meta-url="true">Open Graph image</a>',
        '<a href="https://source.example.test/import/canonical/post-42.html" data-pandoc-link-rel="canonical" title="Canonical source">Canonical source</a>',
        '<a href="https://source.example.test/import/posts/es/post-42.html" data-pandoc-link-rel="alternate" hreflang="es" type="text/html" title="Spanish source">Spanish source</a>',
        '<a href="https://source.example.test/import/posts/post-42.html?p=42" data-pandoc-link-rel="shortlink">Shortlink</a>',
        '<a href="https://source.example.test/import/posts/authors/migration.html" data-pandoc-link-rel="author">Author source</a>',
        '<a href="https://source.example.test/import/license.html" data-pandoc-link-rel="license" type="text/html" title="Reuse terms">Reuse terms</a>',
        '<a href="https://source.example.test/import/posts/post-42.html?help=import" data-pandoc-link-rel="help">Help source</a>',
        '<a href="https://source.example.test/import/posts/post-42.html?draft=1#chapter-1" data-pandoc-link-rel="bookmark" title="Chapter anchor">Chapter anchor</a>',
        '<a href="https://source.example.test/import/posts/refresh-target.html" data-pandoc-meta-refresh="true">Refresh target</a>',
        '<article id="legacy-post-42" data-source="html-export" data-pandoc-microdata-scope="true" data-pandoc-microdata-type="https://schema.org/Article https://source.example.test/import/posts/schema/LegacyPost" data-pandoc-microdata-id="https://source.example.test/import/posts/post-42.html#article" data-pandoc-microdata-ref="imported-headline legacy-author" data-pandoc-microdata-ref-count="2" data-pandoc-microdata-ref-resolved="imported-headline" data-pandoc-microdata-ref-resolved-count="1" data-pandoc-microdata-ref-missing="legacy-author" data-pandoc-microdata-ref-missing-count="1" data-pandoc-microdata-properties="headline schema:name" data-pandoc-microdata-property-count="2" data-pandoc-microdata-value-count="2">',
        '<h1 id="imported-headline" data-pandoc-microdata-property="headline schema:name" data-pandoc-microdata-value="Imported source packet">Imported source packet</h1>',
        '<p data-pandoc-style="color: #222; font-weight: 600" data-pandoc-rdfa-property="schema:description og:description" data-pandoc-rdfa-typeof="schema:Article" data-pandoc-rdfa-about="https://source.example.test/import/posts/post-42.html?draft=1#legacy-post-42" data-pandoc-rdfa-resource="https://source.example.test/import/posts/post-42.html#review" data-pandoc-rdfa-vocab="https://schema.org/" data-pandoc-rdfa-prefix="schema: https://schema.org/ og: https://ogp.me/ns#">AT&amp;T &lt;review&gt; text<br>keeps its line break with a <a href="https://source.example.test/import/media/source.html#note" data-pandoc-link-target="_blank" data-pandoc-link-download="source.html">source note</a> and <a href="https://source.example.test/import/posts/policy-source.html" data-pandoc-referrerpolicy="strict-origin">policy source</a>.</p>',
        '<p data-pandoc-contenteditable-state="plaintext-only" data-pandoc-spellcheck-state="false" data-pandoc-draggable-state="true">Editable migration note</p>',
        '<section data-pandoc-tabindex="2" data-pandoc-accesskey="r R" data-pandoc-autofocus-state="true"><a href="https://source.example.test/import/posts/focus/review.html" data-pandoc-tabindex="-1" data-pandoc-accesskey="f">Focusable source</a><span>Bad focus</span></section>',
        '<section data-pandoc-lang="ar" data-pandoc-dir="rtl" data-pandoc-translate-state="no"><p data-pandoc-lang="sr-Cyrl-RS" data-pandoc-dir="auto" data-pandoc-translate-state="yes">Localized migration note</p></section>',
        '<section data-pandoc-aria-role="region" data-pandoc-aria-label="Import status" data-pandoc-aria-describedby="imported-headline legacy-author" data-pandoc-aria-expanded="true" data-pandoc-aria-current="page"><p>ARIA migration note</p></section>',
        '<div data-source="legacy-widget" data-pandoc-custom-part="card primary" data-pandoc-custom-exportparts="cover: card-cover, title" data-pandoc-custom-element="legacy-gallery"><h2>Custom gallery</h2><img src="https://source.example.test/import/posts/custom-cover.png" alt="Custom cover"></div>',
        '<p data-pandoc-custom-is="x-review-paragraph">Custom paragraph <span data-pandoc-custom-part="status" data-pandoc-custom-element="legacy-badge">Ready</span></p>',
        '<p>Published <time data-pandoc-time-datetime="2026-06-08T09:30Z" data-pandoc-time-kind="global-datetime">June 8, 2026</time>; review took <time data-pandoc-time-datetime="PT2H30M" data-pandoc-time-kind="duration">two hours</time>; bad date <time>legacy date</time>.</p>',
        '<p>Metric <data data-pandoc-data-value="SKU-42">Legacy SKU</data>; import quality <meter data-pandoc-meter-value="0.75" data-pandoc-meter-min="0" data-pandoc-meter-max="1" data-pandoc-meter-low="0.25" data-pandoc-meter-high="0.9" data-pandoc-meter-optimum="1">Quality</meter>; progress <progress data-pandoc-progress-value="0.5" data-pandoc-progress-max="1">half done</progress>; calculated <output data-pandoc-output-for="quality progress" data-pandoc-output-form="legacy-form" data-pandoc-output-name="total-score">total due</output>; bad metric <progress>invalid metric</progress>.</p>',
        '<p>Revision <ins data-pandoc-revision-cite="https://source.example.test/import/posts/revisions/add-note.html" data-pandoc-revision-datetime="2026-06-08T09:40Z" data-pandoc-revision-kind="global-datetime">added reviewer note</ins> and <del data-pandoc-revision-datetime="2026-06-07" data-pandoc-revision-kind="date">removed unsafe note</del>.</p>',
        '<blockquote data-pandoc-quote-cite="https://source.example.test/import/posts/quotes/source.html"><p>Imported pull quote <q data-pandoc-quote-cite="https://review.example.test/inline-quote.html">inline source</q><q>unsafe source</q></p></blockquote>',
        '<p>Ruby note <ruby data-pandoc-ruby-base="漢" data-pandoc-ruby-annotation="Kan ji | Han | character" data-pandoc-ruby-fallback="()">漢<rp>(</rp><rt>Kan ji</rt><rp>)</rp><rtc><rt>Han</rt><rt>character</rt></rtc></ruby></p>',
        '<p>Math source <math data-pandoc-math-source-format="application/x-tex" data-pandoc-math-source="x + y" data-pandoc-math-annotation-xml-encoding="application/mathml-content"><semantics><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow><annotation encoding="application/x-tex">x + y</annotation><annotation-xml encoding="application/mathml-content"><apply><plus></plus><ci>x</ci><ci>y</ci></apply></annotation-xml></semantics></math></p>',
        '<span data-pandoc-shadowroot-mode="open" data-pandoc-shadowroot-delegatesfocus="true" data-pandoc-shadowroot-clonable="true" data-pandoc-shadowroot-serializable="true">Shadow root: open</span>',
        '<p>Shadow import <span data-pandoc-slot-fallback="true" data-pandoc-slot-name="headline">fallback slot <a href="https://source.example.test/import/posts/shadow/source.html">shadow source</a><a>bad shadow</a></span></p>',
        '<details data-pandoc-details-state="closed"><summary data-pandoc-details-summary="true">Collapsed migration notes</summary><p>Hidden packet <a href="https://source.example.test/import/posts/details/source.html">details source</a><a>bad details</a></p></details>',
        '<details open><summary>Open import note</summary><p>Visible disclosure text</p></details>',
        '<section data-pandoc-hidden-state="hidden"><h2>Hidden migration note</h2><p>Hidden packet <a href="https://source.example.test/import/posts/hidden/source.html">hidden source</a><a>bad hidden</a></p></section>',
        '<aside data-pandoc-hidden-state="until-found" data-pandoc-inert-state="true"><p>Search reveal import note</p></aside>',
        '<aside id="popover-note" data-pandoc-popover-state="manual"><p>Popover migration note <a href="https://source.example.test/import/posts/popover/source.html">popover source</a><a>bad popover</a></p></aside>',
        '<a href="https://source.example.test/import/posts/popover/control.html">Popover control source</a>',
        '<div data-pandoc-iframe-srcdoc="true" data-pandoc-iframe-srcdoc-base-url="https://source.example.test/import/posts/embedded/"><article><h2>Embedded srcdoc packet</h2><a href="https://source.example.test/import/posts/embedded/note.html">frame note</a><img src="https://source.example.test/import/posts/embedded/frame.png" alt="Frame"></article></div>',
        '<a href="https://source.example.test/import/posts/frames/source.html?review=1" data-pandoc-iframe-src="true" title="Embedded frame source" data-pandoc-iframe-sandbox="allow-scripts allow-same-origin" data-pandoc-iframe-allow="fullscreen; clipboard-write" data-pandoc-iframe-referrerpolicy="strict-origin-when-cross-origin" data-pandoc-iframe-allowfullscreen="true">Embedded frame source</a>',
        '<a href="https://source.example.test/import/posts/docs/source.pdf" data-pandoc-object-data="true" title="Embedded PDF source">Embedded PDF source</a>',
        '<span data-pandoc-object-param-name="movie" data-pandoc-object-param-valuetype="ref" data-pandoc-object-param-type="application/x-shockwave-flash" data-pandoc-object-param-value="https://source.example.test/import/posts/media/player.swf">Object parameter: movie=https://source.example.test/import/posts/media/player.swf</span>',
        '<span data-pandoc-object-param-name="flashvars" data-pandoc-object-param-value="autoplay = false ; poster = cover.png">Object parameter: flashvars=autoplay = false ; poster = cover.png</span>',
        '<span data-pandoc-object-param-name="src">Object parameter: src</span>',
        '<p>PDF fallback <a href="https://source.example.test/import/posts/docs/fallback.html">details</a></p>',
        '<p>Unsafe object fallback</p>',
        '<a href="https://source.example.test/import/posts/media/demo.mp4" data-pandoc-embed-src="true" title="Embedded media source">Embedded media source</a>',
        '<a href="https://source.example.test/import/posts/portal/review.html" data-pandoc-portal-src="true" title="Portal preview" data-pandoc-portal-referrerpolicy="strict-origin">Portal preview</a>',
        '<p>Portal fallback <a href="https://source.example.test/import/posts/portal/fallback.html">portal fallback source</a></p>',
        '<p>Unsafe portal fallback</p>',
        '<table><caption>Loose imported caption</caption><colgroup><col span="2"></colgroup><thead><tr><td>Loose imported head</td></tr></thead><tfoot><tr><td>Loose imported total</td></tr></tfoot></table>',
        '<table><tr><td>Loose imported row</td></tr><tr><td>Loose imported cell</td><th scope="row">Loose imported header</th></tr></table>',
        '<svg><desc>Legacy &lt;source&gt; &amp; review notes</desc><image href="data:image/png;base64,iVBORw0KGgo="></image><image></image><defs><clipPath id="review-clip"><path d="M0 0"></path></clipPath></defs><g clip-path="url(#review-clip)" mask="url(https://source.example.test/import/posts/masks/review.svg#mask)"><path d="M0 0" fill="url(#paint)"></path></g></svg>',
        '<figure data-pandoc-aria-describedby="cover-caption" data-pandoc-figure-align="right" data-pandoc-figure-caption="Cover image" data-pandoc-figure-caption-id="cover-caption"><img src="https://source.example.test/import/posts/cover.png" srcset="https://cdn.example.test/cover.png?x=1&amp;y=2 1x, https://source.example.test/import/posts/cover.png 1x, https://source.example.test/import/media/cover@2x.png 2x" data-pandoc-image-loading="lazy" data-pandoc-image-decoding="async" data-pandoc-image-fetchpriority="high" data-pandoc-image-crossorigin="anonymous" alt="Cover"><figcaption id="cover-caption">Cover image</figcaption></figure>',
        '<video data-pandoc-media-autoplay="true" data-pandoc-media-controls="true" data-pandoc-media-loop="true" data-pandoc-media-muted="true" data-pandoc-media-playsinline="true" data-pandoc-media-preload="metadata" data-pandoc-media-crossorigin="anonymous" data-pandoc-media-controlslist="nodownload nofullscreen" data-pandoc-media-width="640" data-pandoc-media-height="360" poster="https://source.example.test/import/posts/media/trailer.jpg"><source src="https://source.example.test/import/posts/media/trailer.mp4" type="video/mp4">Media trailer fallback</video>',
        '<audio data-pandoc-media-controls="true" data-pandoc-media-preload="none" data-pandoc-media-crossorigin="use-credentials" src="https://source.example.test/import/posts/media/interview.mp3">Audio interview fallback</audio>',
        '<img src="https://source.example.test/import/posts/floorplan.png" usemap="#review-map" alt="Review floor plan"><a href="https://source.example.test/import/posts/map/lead.html" data-pandoc-image-map-area="true" data-pandoc-image-map-name="review-map" data-pandoc-image-map-shape="rect" data-pandoc-image-map-coords="0,0,120,80" data-pandoc-image-map-alt="Mapped lead">Mapped lead</a>',
        '<a href="https://source.example.test/import/posts/map/metadata.html" data-pandoc-image-map-area="true" data-pandoc-image-map-name="review-map" data-pandoc-image-map-alt="Map metadata">Map metadata</a>',
        '<source srcset="data:image/png;base64,iVBORw0KGgo= 1x" type="image/png">',
        '<source srcset="https://source.example.test/import/posts/hero.avif 1x" media="(min-width: 48em)" type="image/avif">',
        '<source srcset="https://source.example.test/import/posts/metadata-only.webp 1x" type="image/webp">',
        '<img src="https://source.example.test/import/posts/fallback.jpg" srcset="https://source.example.test/import/posts/fallback.jpg 1x" alt="Responsive cover">',
        '<img src="data:image/png;base64,iVBORw0KGgo=" alt="Inline raster"><span data-pandoc-image-alt-fallback="true">HTML data</span>',
        '<span data-pandoc-datalist-id="review-suggestions" data-pandoc-datalist-options="Reviewer suggested tag | Legacy taxonomy">Datalist suggestions: Reviewer suggested tag; Legacy taxonomy</span>',
        '<fieldset data-pandoc-fieldset-disabled="true" data-pandoc-fieldset-name="import-settings" data-pandoc-fieldset-form="legacy-form" data-pandoc-fieldset-label="Import settings"><legend data-pandoc-fieldset-legend="true">Import settings</legend><p>Form group note Save settings</p></fieldset>',
        '<span data-pandoc-form-method="post" data-pandoc-form-action="https://source.example.test/import/posts/forms/review?packet=42" data-pandoc-form-target="review-frame" data-pandoc-form-autocomplete="off" data-pandoc-form-name="comment-form">Form submission: post</span>',
        '<p>Send reviewPreview packetImage submit<span data-pandoc-select-name="publish-status" data-pandoc-select-form="legacy-form" data-pandoc-select-required="true" data-pandoc-select-size="2" data-pandoc-select-selected="Ready for import">Select: Ready for import</span>Draft reviewReady for import<span data-pandoc-button-type="submit" data-pandoc-button-name="publish" data-pandoc-button-value="yes" data-pandoc-button-formaction="https://source.example.test/import/posts/forms/publish?packet=42" data-pandoc-button-formmethod="post" data-pandoc-button-formtarget="review-frame" data-pandoc-button-formnovalidate="true">Publish now</span><span data-pandoc-button-type="reset" data-pandoc-button-value="clear" data-pandoc-button-disabled="true">Clear draft</span><span data-pandoc-button-type="submit">Bad button</span></p>',
        '<!--review- -->',
        '<!-- wp:html -->',
    ] as $expected) {
        if (!str_contains($blocks, $expected)) {
            throw new RuntimeException('HTML5 DOM fragment self-test missing expected snippet: ' . $expected);
        }
    }
    $imageResourcePolicyDiagnostics = array_values(array_filter(
        $fragment->diagnostics(),
        static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'image-resource-policy-review'
            && ($diagnostic['tag'] ?? '') === 'img'
    ));
    if ($imageResourcePolicyDiagnostics === []) {
        throw new RuntimeException('Expected image resource policy review diagnostics');
    }
    foreach ($imageResourcePolicyDiagnostics as $diagnostic) {
        if (($diagnostic['line'] ?? 0) <= 0) {
            throw new RuntimeException('Expected image resource policy diagnostics to include source line metadata');
        }
    }
    $mediaResourcePolicyDiagnostics = array_values(array_filter(
        $fragment->diagnostics(),
        static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'media-resource-policy-review'
            && in_array((string) ($diagnostic['tag'] ?? ''), ['audio', 'video'], true)
    ));
    if ($mediaResourcePolicyDiagnostics === []) {
        throw new RuntimeException('Expected media resource policy review diagnostics');
    }
    foreach ($mediaResourcePolicyDiagnostics as $diagnostic) {
        if (($diagnostic['line'] ?? 0) <= 0) {
            throw new RuntimeException('Expected media resource policy diagnostics to include source line metadata');
        }
    }
    $urlRepairDiagnostics = array_values(array_filter(
        $fragment->diagnostics(),
        static function (array $diagnostic): bool {
            $code = (string) ($diagnostic['code'] ?? '');
            $tag = (string) ($diagnostic['tag'] ?? '');

            return in_array($code, ['unsafe-url', 'normalized-url', 'quote-cite-review', 'revision-metadata-review'], true)
                && in_array($tag, ['link', 'meta', 'iframe', 'blockquote', 'q', 'ins', 'del', 'area'], true);
        }
    ));
    if ($urlRepairDiagnostics === []) {
        throw new RuntimeException('Expected URL repair diagnostics for source line metadata review');
    }
    foreach ($urlRepairDiagnostics as $diagnostic) {
        if (($diagnostic['line'] ?? 0) <= 0) {
            throw new RuntimeException('Expected URL repair diagnostics to include source line metadata');
        }
    }
    $referrerPolicyDiagnostics = array_values(array_filter(
        $fragment->diagnostics(),
        static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'referrer-policy-review'
            && in_array((string) ($diagnostic['tag'] ?? ''), ['a', 'iframe', 'portal'], true)
    ));
    if ($referrerPolicyDiagnostics === []) {
        throw new RuntimeException('Expected referrer policy diagnostics for source line metadata review');
    }
    foreach ($referrerPolicyDiagnostics as $diagnostic) {
        if (($diagnostic['line'] ?? 0) <= 0) {
            throw new RuntimeException('Expected referrer policy diagnostics to include source line metadata');
        }
    }
    $srcdocDiagnostics = array_values(array_filter(
        $fragment->diagnostics(),
        static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'iframe-srcdoc-review'
    ));
    if ($srcdocDiagnostics === []) {
        throw new RuntimeException('Expected iframe srcdoc review diagnostics');
    }
    foreach ($srcdocDiagnostics as $diagnostic) {
        if (($diagnostic['line'] ?? 0) <= 0) {
            throw new RuntimeException('Expected iframe srcdoc diagnostics to include source line metadata');
        }
    }
    $embeddedSourceDiagnostics = array_values(array_filter(
        $fragment->diagnostics(),
        static function (array $diagnostic): bool {
            $code = (string) ($diagnostic['code'] ?? '');
            $tag = (string) ($diagnostic['tag'] ?? '');
            $attribute = (string) ($diagnostic['attribute'] ?? '');

            return in_array($code, ['embedded-source-review', 'unsafe-url'], true)
                && (($tag === 'object' && $attribute === 'data') || ($tag === 'embed' && $attribute === 'src'));
        }
    ));
    if (count($embeddedSourceDiagnostics) !== 4) {
        throw new RuntimeException('Expected object/embed source review and unsafe URL diagnostics');
    }
    foreach ($embeddedSourceDiagnostics as $diagnostic) {
        if (($diagnostic['line'] ?? 0) <= 0) {
            throw new RuntimeException('Expected object/embed source diagnostics to include source line metadata');
        }
    }
    $objectParamDiagnostics = array_values(array_filter(
        $fragment->diagnostics(),
        static function (array $diagnostic): bool {
            $code = (string) ($diagnostic['code'] ?? '');

            return ($diagnostic['tag'] ?? '') === 'param'
                && in_array($code, ['object-param-review', 'unsafe-attribute', 'unsafe-url'], true);
        }
    ));
    if (count($objectParamDiagnostics) !== 5) {
        throw new RuntimeException('Expected object param review, spoof, and unsafe URL diagnostics');
    }
    foreach ($objectParamDiagnostics as $diagnostic) {
        if (($diagnostic['line'] ?? 0) <= 0) {
            throw new RuntimeException('Expected object param diagnostics to include source line metadata');
        }
    }
    $imageMapDiagnostics = array_values(array_filter(
        $fragment->diagnostics(),
        static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'unsafe-attribute'
            && ($diagnostic['tag'] ?? '') === 'area'
            && in_array((string) ($diagnostic['attribute'] ?? ''), ['shape', 'coords'], true)
    ));
    if ($imageMapDiagnostics === []) {
        throw new RuntimeException('Expected image map helper diagnostics for source line metadata review');
    }
    foreach ($imageMapDiagnostics as $diagnostic) {
        if (($diagnostic['line'] ?? 0) <= 0) {
            throw new RuntimeException('Expected image map helper diagnostics to include source line metadata');
        }
    }
    $reviewStateDiagnostics = array_values(array_filter(
        $fragment->diagnostics(),
        static fn (array $diagnostic): bool => in_array((string) ($diagnostic['code'] ?? ''), [
            'closed-details-review',
            'hidden-content-review',
            'inert-content-review',
        ], true)
    ));
    if (count($reviewStateDiagnostics) !== 4) {
        throw new RuntimeException('Expected disclosure and hidden-state review diagnostics');
    }
    foreach ($reviewStateDiagnostics as $diagnostic) {
        if (($diagnostic['line'] ?? 0) <= 0) {
            throw new RuntimeException('Expected disclosure and hidden-state diagnostics to include source line metadata');
        }
    }
    $globalMetadataDiagnostics = array_values(array_filter(
        $fragment->diagnostics(),
        static function (array $diagnostic): bool {
            $code = (string) ($diagnostic['code'] ?? '');
            if (in_array($code, [
                'language-direction-review',
                'editing-state-review',
                'translation-state-review',
                'focus-navigation-review',
                'popover-review',
            ], true)) {
                return true;
            }

            return $code === 'unsafe-attribute'
                && in_array((string) ($diagnostic['attribute'] ?? ''), [
                    'accesskey',
                    'contenteditable',
                    'dir',
                    'draggable',
                    'lang',
                    'popover',
                    'spellcheck',
                    'tabindex',
                    'translate',
                    'xml:lang',
                ], true);
        }
    ));
    if ($globalMetadataDiagnostics === []) {
        throw new RuntimeException('Expected global HTML metadata diagnostics for source line metadata review');
    }
    foreach ($globalMetadataDiagnostics as $diagnostic) {
        if (($diagnostic['line'] ?? 0) <= 0) {
            throw new RuntimeException('Expected global HTML metadata diagnostics to include source line metadata');
        }
    }
    foreach (['<html', '<body', '<base', '<title', '<link', '<meta', '<iframe', '<object', '<param', '<embed', '<portal', '<map', '<area', '<template', '<slot', '<legacy-', '<datalist', '<select', '<option', '<iframe srcdoc', '<script', '<input', ' style=', 'background-image', 'background:url', 'calc(50vw + url', 'calc(100vw + url', ' target=', ' download=', 'rel="opener"', ' referrerpolicy=', ' loading=', ' decoding=', ' fetchpriority=', ' crossorigin=', ' autoplay', ' controls', ' loop', ' muted', ' playsinline', ' preload=', ' controlslist=', ' width=', ' height=', ' popover=', 'popovertarget=', 'popovertargetaction=', ' contenteditable=', ' spellcheck=', ' draggable=', ' tabindex=', ' accesskey=', ' autofocus', ' translate=', ' lang=', ' xml:lang=', ' dir=', ' role=', ' aria-label=', ' aria-describedby=', ' aria-expanded=', ' aria-current=', ' aria-busy=', ' is=', ' part=', ' exportparts=', ' align=', ' cite=', ' datetime=', ' value=', ' min=', ' max=', ' low=', ' high=', ' optimum=', ' for=', ' method=', ' action=', ' autocomplete=', ' shadowrootmode=', ' shadowrootdelegatesfocus', ' shadowrootclonable', ' shadowrootserializable', ' selected', ' size=', ' required', ' disabled name=', ' name=" import-settings "', ' name="publish-status"', ' name="total-score"', ' name="comment-form"', ' form="legacy-form"', 'javascript:', 'ja/**/vascript', 'report-uri', 'tracker.example.test', 'bad policy', 'inactive.example', 'legacy.css', 'active-author.html', 'Active author', 'Bad license', 'Bad object', 'Bad embed', 'preload-cover.png', 'orphan-source.avif', 'mailto:bad@example.test', 'data:text/html', 'data:image/svg+xml', '(max-width: 47em)', '<![CDATA[', '--->', 'Hidden draft', 'draft-token', 'private-token', 'Bad frame', 'Bad map region', 'bad-role', 'source-spoof', ' hidden=', ' inert', 'http://www.w3.org/1999/xhtml', ' itemscope', ' itemtype=', ' itemid=', ' itemref=', ' itemprop=', ' property=', ' typeof=', ' about=', ' resource=', ' vocab=', ' prefix='] as $blocked) {
        if (str_contains($blocks, $blocked)) {
            throw new RuntimeException('HTML5 DOM fragment self-test retained blocked content: ' . $blocked);
        }
    }
    $normalizedUrlDiagnostics = array_values(array_filter(
        $fragment->diagnosticCodes(),
        static fn (string $code): bool => $code === 'normalized-url'
    ));
    if (count($normalizedUrlDiagnostics) !== 5) {
        throw new RuntimeException('HTML5 DOM fragment self-test expected five normalized URL diagnostics');
    }
    if (!in_array('empty-source', $fragment->diagnosticCodes(), true)) {
        throw new RuntimeException('HTML5 DOM fragment self-test expected empty picture source diagnostic');
    }
    foreach (['filter=', 'marker-start=', 'stroke='] as $blocked) {
        if (str_contains($blocks, $blocked)) {
            throw new RuntimeException('HTML5 DOM fragment self-test retained unsafe SVG resource attribute: ' . $blocked);
        }
    }

    echo "html5 dom fragment handoff self-test ok\n";
    exit(0);
}

echo "HTML5 DOM fragment handoff for WordPress review:\n";
echo 'text=' . $fragment->textContent() . "\n";
echo 'base=' . ($fragment->baseUrl() ?? '') . "\n";
echo 'summary=' . json_encode($fragment->summary(), JSON_UNESCAPED_SLASHES) . "\n";
echo "blocks:\n" . $blocks . "\n";
