<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\Html5Dom;
use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\WordPressBlockWriter;

$sourceHtml = <<<'HTML'
<section class="import-review">
  <h1 id="packet">Imported packet</h1>
  <p onclick="alert(1)">Manual<br>break before reviewer note.</p>
  <p><a href="/wp-admin/post.php?post=42" ping="https://tracker.example.test/ping javascript:alert(1)" data-source="legacy">Tracked source link</a></p>
  <div action="/safe-submit" formaction="javascript:alert(1)" longdesc="https://example.test/longdesc" background="mailto:review@example.test">Extended URL attributes</div>
  <p><img src="https://example.test/preview.png" srcset="https://example.test/preview.png 1x, /uploads/preview@2x.png 02.00x, javascript:alert(1) 3x, /uploads/bad.png 0w" alt="Preview"></p>
  <p><img src="/uploads/legacy-preview.png" dynsrc="javascript:alert(1)" lowsrc="mailto:cover@example.test" usemap="https://tracker.example.test/review-map" alt="Legacy preview"><img src="/uploads/mapped-preview.png" dynsrc="/uploads/clip.avi" lowsrc="https://example.test/preview-low.jpg" usemap="#review-map" alt="Mapped preview"><map name="review-map"><area href="/review" alt="Review map"></map></p>
  <p><a href="mailto:review@example.test">Mail reviewer</a><img src="mailto:review@example.test" alt="Unsafe media link"></p>
  <form action="/submit" onsubmit="alert(1)"><p>Reviewer choice <input name="status" value="draft"><button formaction="javascript:alert(1)">Keep visible label</button></p><p><select><optgroup label="Publication status"><option label="Draft"></option><option>Final</option></optgroup><option label="Needs copyedit">Submission value</option></select></p><textarea>Visible reviewer note</textarea></form>
  <iframe src="javascript:alert(1)">Iframe fallback <b>caption</b><script>drop()</script></iframe>
  <object data="javascript:alert(1)" type="application/x-shockwave-flash"><param name="movie" value="legacy.swf"><p>Object fallback <a href="/review">review</a></p></object>
  <object data="/wp-content/uploads/review.pdf" title="Embedded PDF source"><p>PDF fallback</p></object>
  <embed src="/wp-content/uploads/demo.mp4" title="Embedded media source">
  <applet code="Legacy.class"><span>Applet fallback</span></applet>
  <canvas width="400" height="200"><p>Canvas fallback <a href="/review">review</a><a href="javascript:alert(1)">bad</a></p><script>drop()</script></canvas>
  <noscript><p>Script-disabled fallback <a href="/review">review</a><a href="javascript:alert(1)">bad</a></p><script>drop()</script></noscript>
  <template data-source="legacy-hidden"><p>Template fallback <a href="/review">review</a><a href="javascript:alert(1)">bad</a></p><img src="/uploads/template.png" alt="Template"><script>drop()</script></template>
  <xmp data-source="legacy-raw">Reviewer <script>alert(1)</script> &amp; <b>source</b></xmp>
  <table class="legacy-table"><caption>Review rows</caption><p data-review="loose-table">Loose table note</p><tr><td>Cell A</td></tr>orphan table text<tr><td>Cell B</td></tr></table>
  <details open="open"><summary>Media review</summary><video controls="" muted playsinline loop poster="tel:+15550100"><source src="mailto:review@example.test" type="video/mp4"><source src="/uploads/preview.mp4" type="video/mp4"></video></details>
  <figure class="foreign-content"><svg xmlns:xlink="http://www.w3.org/1999/xlink"><foreignObject><div viewBox="html attr"><linearGradient>HTML child</linearGradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject><title><p viewBox="title html"><textPath>Title fallback</textPath><svg viewBox="0 0 3 3"><linearGradient id="title-nested"></linearGradient></svg></p></title><symbol id="review-icon"><path d="M0 0"></path></symbol><use href="#review-icon"></use><image href="mailto:cover@example.test" xlink:href="https://example.test/review.svg"></image><feImage href="tel:+15550100"></feImage><textPath href="#review-label">Logo</textPath></svg><math><annotation-xml encoding="text/html"><div viewBox="math html"><textPath>HTML text</textPath></div></annotation-xml><mtext><span viewBox="math text"><textPath>Math token HTML</textPath><svg viewBox="0 0 2 2"><linearGradient id="math-nested"></linearGradient></svg></span></mtext><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math></figure>
  <script>alert("legacy embed")</script>
</section>
<plaintext data-source="legacy-plaintext">Plain reviewer <script>alert(1)</script> &amp; <b>tail</b></plaintext><p>suppressed tail</p>
HTML;

$fragment = Html5DomFragment::fromHtml($sourceHtml);
$controlBaseFragment = Html5DomFragment::fromHtml(
    '<base href="h&#9;ttps://cdn.example.test/root/packet.html">'
    . '<article><a href="./doc.html">doc</a><img src="cover.png" srcset="cover.png 1x, ./cover@2x.png 2x" alt="Cover"></article>',
    'https://source.example.test/import/posts/post.html'
);
$unsafeBaseFragment = Html5DomFragment::fromHtml(
    '<base href="java&#10;script:alert(1)"><a href="./doc.html">doc</a>',
    'https://source.example.test/import/posts/post.html'
);
$duplicateBaseFragment = Html5DomFragment::fromHtml(
    '<base href="https://source.example.test/import/posts/post.html" target="review-frame">'
    . '<base href="https://spoof.example.test/assets/" target="_blank">'
    . '<a href="./doc.html">doc</a><img src="cover.png" alt="Cover">',
);
$semanticMetadataLineFragment = Html5DomFragment::fromHtml(
    "<article itemscope itemtype=\"./types/Local javascript:alert(1)\" itemid=\"./articles/42\" itemref=\"headline bad<tag\">\n"
    . "<h1 itemprop=\"headline bad<tag\">Title</h1>\n"
    . "<a property=\"schema:url bad<>\" about=\" ./article&#10;\" resource=\"java&#10;script:alert(1)\" prefix=\"schema: ./schema bad: javascript:alert(1)\" href=\"./canonical.html\">Canonical</a>\n"
    . '</article>',
    'https://source.example.test/import/posts/post.html'
);
$documentMetadataLineFragment = Html5DomFragment::fromHtml(
    "<section>\n"
    . "<title>Imported packet</title>\n"
    . "<meta charset=\"Windows-1252\">\n"
    . "<meta http-equiv=\"Content-Security-Policy\" content=\"default-src &#039;self&#039;; report-uri https://tracker.example.test/csp; script-src java&#10;script:alert(1)\">\n"
    . "<meta name=\"referrer\" content=\"bad policy\">\n"
    . "<meta name=\"robots\" content=\"index, url=javascript:alert(1), unsupported-policy\">\n"
    . "<meta name=\"theme-color\" content=\"url(javascript:alert(1))\">\n"
    . "<meta name=\"theme-color\" content=\"#123456\" media=\"screen and (background: url(javascript:alert(1)))\">\n"
    . "<meta name=\"color-scheme\" content=\"dark bad-token light\">\n"
    . "<meta property=\"og:title\" content=\"Share title\">\n"
    . "<p>after</p>\n"
    . '</section>'
);
$helperMetadataLineFragment = Html5DomFragment::fromHtml(
    "<article>\n"
    . "<p style=\"background:url(javascript:alert(1)); color:red\">Styled</p>\n"
    . "<track kind=\"transcript\" srclang=\"bad<tag\" src=\"javascript:alert(1)\">\n"
    . "<time datetime=\"2026-13-40\">Bad date</time>\n"
    . "<progress value=\"bad\" max=\"0\">Bad progress</progress>\n"
    . "<output for=\"good bad<tag\" form=\"bad id\" name=\"bad<tag\">Total</output>\n"
    . '</article>'
);
$document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
    $fragment->toRawHtmlAst(['part' => '/migration/review-fragment.html']),
]);
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        '<section class="import-review">',
        '<p>Manual<br>break before reviewer note.</p>',
        '<a href="/wp-admin/post.php?post=42" data-source="legacy">Tracked source link</a>',
        '<div action="/safe-submit" longdesc="https://example.test/longdesc">Extended URL attributes</div>',
        '<img src="https://example.test/preview.png" srcset="https://example.test/preview.png 1x, /uploads/preview@2x.png 2x" alt="Preview">',
        '<img src="/uploads/legacy-preview.png" alt="Legacy preview">',
        '<img src="/uploads/mapped-preview.png" dynsrc="/uploads/clip.avi" lowsrc="https://example.test/preview-low.jpg" usemap="#review-map" alt="Mapped preview">',
        '<a href="/review" data-pandoc-image-map-area="true" data-pandoc-image-map-name="review-map" data-pandoc-image-map-alt="Review map">Review map</a>',
        '<a href="mailto:review@example.test">Mail reviewer</a><span data-pandoc-image-alt-fallback="true">Unsafe media link</span>',
        '<p>Reviewer choice <span data-pandoc-button-type="submit">Keep visible label</span></p><p><span data-pandoc-select-selected="Draft">Select: Draft</span>Publication statusDraftFinalNeeds copyedit</p>Visible reviewer note',
        'Iframe fallback <b>caption</b>',
        '<p>Object fallback <a href="/review">review</a></p>',
        '<a href="/wp-content/uploads/review.pdf" data-pandoc-object-data="true" title="Embedded PDF source">Embedded PDF source</a><p>PDF fallback</p>',
        '<a href="/wp-content/uploads/demo.mp4" data-pandoc-embed-src="true" title="Embedded media source">Embedded media source</a>',
        '<span>Applet fallback</span>',
        '<p>Canvas fallback <a href="/review">review</a><a>bad</a></p>',
        '<p>Script-disabled fallback <a href="/review">review</a><a>bad</a></p>',
        '<p>Template fallback <a href="/review">review</a><a>bad</a></p><img src="/uploads/template.png" alt="Template">',
        'Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;b&gt;source&lt;/b&gt;',
        '<p data-review="loose-table">Loose table note</p>orphan table text<table class="legacy-table"><caption>Review rows</caption><tr><td>Cell A</td></tr><tr><td>Cell B</td></tr></table>',
        '<details open><summary>Media review</summary><video data-pandoc-media-controls="true" data-pandoc-media-muted="true" data-pandoc-media-playsinline="true" data-pandoc-media-loop="true"><source type="video/mp4"><source src="/uploads/preview.mp4" type="video/mp4"></video></details>',
        '<foreignObject><div viewbox="html attr"><lineargradient>HTML child</lineargradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject>',
        '<title><p viewbox="title html"><textpath>Title fallback</textpath><svg viewBox="0 0 3 3"><linearGradient id="title-nested"></linearGradient></svg></p></title>',
        '<use href="#review-icon"></use><image xlink:href="https://example.test/review.svg"></image><feImage></feImage><textPath href="#review-label">Logo</textPath>',
        '<annotation-xml encoding="text/html"><div viewbox="math html"><textpath>HTML text</textpath></div></annotation-xml>',
        '<mtext><span viewbox="math text"><textpath>Math token HTML</textpath><svg viewBox="0 0 2 2"><linearGradient id="math-nested"></linearGradient></svg></span></mtext>',
        'Plain reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;b&gt;tail&lt;/b&gt;&lt;/plaintext&gt;&lt;p&gt;suppressed tail&lt;/p&gt;',
        '<!-- wp:html -->',
    ] as $snippet) {
        if (!str_contains($blocks, $snippet)) {
            throw new RuntimeException('HTML5 DOM handoff self-test missing expected snippet: ' . $snippet);
        }
    }

    foreach (['onclick=', 'ping=', 'formaction=', 'background="mailto:', 'javascript:', 'src="mailto:', 'lowsrc="mailto:', 'poster="tel:', 'href="mailto:cover@example.test"', 'href="tel:+15550100"', 'usemap="https://tracker.example.test/review-map"', '<form', '<input', '<button', '<select', '<optgroup', '<option', '<textarea', '<iframe', '<object', '<embed', '<applet', '<canvas', '<noscript', '<template', '<param', '<xmp', '<plaintext', '<script>', '<p>suppressed tail</p>', 'Submission value', 'open="open"', 'controls=""', 'viewBox="html attr"', 'viewBox="title html"', 'viewBox="math text"', '<textPath>Title fallback</textPath>', '<textPath>HTML text</textPath>', '<textPath>Math token HTML</textPath>'] as $blocked) {
        if (str_contains($blocks, $blocked)) {
            throw new RuntimeException('HTML5 DOM handoff self-test retained blocked content: ' . $blocked);
        }
    }

    $controlBaseHtml = $controlBaseFragment->serialize();
    if ($controlBaseFragment->baseUrl() !== 'https://cdn.example.test/root/packet.html') {
        throw new RuntimeException('HTML5 DOM handoff self-test did not normalize control-separated HTTPS base metadata');
    }
    if (!str_contains($controlBaseHtml, '<a href="https://cdn.example.test/root/doc.html">doc</a>')) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not resolve links from normalized base metadata');
    }
    if (!str_contains($controlBaseHtml, 'srcset="https://cdn.example.test/root/cover.png 1x, https://cdn.example.test/root/cover@2x.png 2x"')) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not resolve srcset candidates from normalized base metadata');
    }
    if (!in_array('normalized-url', $controlBaseFragment->diagnosticCodes(), true)) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not report normalized base href provenance');
    }

    $unsafeBaseHtml = $unsafeBaseFragment->serialize();
    if ($unsafeBaseFragment->baseUrl() !== 'https://source.example.test/import/posts/post.html') {
        throw new RuntimeException('HTML5 DOM handoff self-test trusted an unsafe control-separated base URL');
    }
    if ($unsafeBaseHtml !== '<a href="https://source.example.test/import/posts/doc.html">doc</a>') {
        throw new RuntimeException('HTML5 DOM handoff self-test did not fall back to caller base after unsafe base rejection');
    }
    if (!in_array('unsafe-url', $unsafeBaseFragment->diagnosticCodes(), true) || str_contains($unsafeBaseHtml, 'javascript:')) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not reject unsafe control-separated base URL metadata');
    }

    $duplicateBaseHtml = $duplicateBaseFragment->serialize();
    $duplicateBaseDiagnostics = array_values(array_filter(
        $duplicateBaseFragment->diagnosticCodes(),
        static fn (string $code): bool => $code === 'duplicate-base-ignored'
    ));
    if ($duplicateBaseFragment->baseUrl() !== 'https://source.example.test/import/posts/post.html') {
        throw new RuntimeException('HTML5 DOM handoff self-test let duplicate base href override first base metadata');
    }
    if (!str_contains($duplicateBaseHtml, '<span data-pandoc-meta-name="base-target" data-pandoc-meta-source="base" data-pandoc-meta-content="review-frame">Base target: review-frame</span>')) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not preserve first base target metadata');
    }
    if (!str_contains($duplicateBaseHtml, '<a href="https://source.example.test/import/posts/doc.html">doc</a>')) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not resolve links from the first duplicate-base href');
    }
    if (count($duplicateBaseDiagnostics) !== 2 || str_contains($duplicateBaseHtml, 'spoof.example.test') || str_contains($duplicateBaseHtml, 'target=')) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not keep duplicate base metadata diagnostic-only');
    }

    $semanticLineHtml = $semanticMetadataLineFragment->serialize();
    $semanticLineDiagnostics = array_values(array_filter(
        $semanticMetadataLineFragment->diagnostics(),
        static function (array $diagnostic): bool {
            $code = (string) ($diagnostic['code'] ?? '');
            $attribute = (string) ($diagnostic['attribute'] ?? '');

            return in_array($code, ['semantic-metadata-review', 'unsafe-attribute', 'unsafe-url', 'normalized-url'], true)
                && in_array($attribute, ['itemscope', 'itemtype', 'itemid', 'itemref', 'itemprop', 'property', 'about', 'resource', 'prefix'], true);
        }
    ));
    if (!str_contains($semanticLineHtml, 'data-pandoc-microdata-id="https://source.example.test/import/posts/articles/42"')) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not preserve semantic metadata after source-line diagnostics');
    }
    if (array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $semanticLineDiagnostics) !== [1, 1, 1, 1, 1, 2, 2, 3, 3, 3, 3, 3, 3, 3]) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not carry source lines on semantic metadata diagnostics');
    }
    if (str_contains($semanticLineHtml, 'javascript:') || str_contains($semanticLineHtml, 'bad&lt;')) {
        throw new RuntimeException('HTML5 DOM handoff self-test leaked unsafe semantic metadata source values');
    }

    $documentMetadataLineHtml = $documentMetadataLineFragment->serialize();
    $documentMetadataLineBlocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [
        $documentMetadataLineFragment->toRawHtmlAst(['part' => '/migration/document-metadata-lines-review.html']),
    ]));
    $documentMetadataLineNodes = $documentMetadataLineFragment->nodes();
    $documentMetadataLineChildren = isset($documentMetadataLineNodes[0]['children']) && is_array($documentMetadataLineNodes[0]['children']) ? $documentMetadataLineNodes[0]['children'] : [];
    $documentMetadataLineMetadataNodes = array_values(array_filter(
        $documentMetadataLineChildren,
        static fn (array $node): bool => ($node['type'] ?? '') === 'element'
            && ($node['name'] ?? '') === 'span'
            && str_starts_with((string) array_key_first(is_array($node['attrs'] ?? null) ? $node['attrs'] : []), 'data-pandoc-meta')
    ));
    $documentMetadataLineDiagnostics = array_values(array_filter(
        $documentMetadataLineFragment->diagnostics(),
        static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'unsafe-attribute'
            && ($diagnostic['tag'] ?? '') === 'meta'
            && in_array((string) ($diagnostic['attribute'] ?? ''), ['content', 'media'], true)
    ));
    foreach ([
        'data-pandoc-meta-source="title"',
        'data-pandoc-meta-charset="windows-1252"',
        'data-pandoc-meta-http-equiv="content-security-policy"',
        'data-pandoc-meta-name="robots"',
        'data-pandoc-meta-name="theme-color"',
        'data-pandoc-meta-name="color-scheme"',
        'data-pandoc-meta-property="og:title"',
    ] as $expectedHtml) {
        if (!str_contains($documentMetadataLineHtml, $expectedHtml) || !str_contains($documentMetadataLineBlocks, $expectedHtml)) {
            throw new RuntimeException('HTML5 DOM handoff self-test did not preserve document metadata review node: ' . $expectedHtml);
        }
    }
    if (array_map(static fn (array $node): ?int => $node['line'] ?? null, $documentMetadataLineMetadataNodes) !== [2, 3, 4, 6, 8, 9, 10]) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not carry source lines on document metadata review nodes');
    }
    if (array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $documentMetadataLineDiagnostics) !== [4, 4, 5, 6, 6, 7, 8, 9]) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not carry source lines on document metadata diagnostics');
    }
    foreach (['tracker.example.test', 'javascript:', 'bad policy', 'unsupported-policy', 'bad-token', 'url('] as $blockedMetadata) {
        if (str_contains($documentMetadataLineHtml, $blockedMetadata) || str_contains($documentMetadataLineBlocks, $blockedMetadata)) {
            throw new RuntimeException('HTML5 DOM handoff self-test leaked unsafe document metadata source value: ' . $blockedMetadata);
        }
    }

    $helperMetadataLineHtml = $helperMetadataLineFragment->serialize();
    $helperMetadataLineAst = $helperMetadataLineFragment->toRawHtmlAst(['part' => '/migration/html-helper-metadata-lines.html']);
    $helperMetadataLineBlocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [
        $helperMetadataLineAst,
    ]));
    $helperDiagnosticFilter = static function (array $diagnostic): bool {
        return in_array((string) ($diagnostic['tag'] ?? ''), ['p', 'track', 'time', 'progress', 'output'], true)
            && in_array((string) ($diagnostic['code'] ?? ''), [
                'unsafe-attribute',
                'unsafe-url',
                'style-review-metadata',
                'time-metadata-review',
                'value-metadata-review',
                'output-metadata-review',
            ], true);
    };
    $helperMetadataLineDiagnostics = array_values(array_filter(
        $helperMetadataLineFragment->diagnostics(),
        $helperDiagnosticFilter
    ));
    $helperMetadataLineAstDiagnostics = array_values(array_filter(
        $helperMetadataLineAst->attr('diagnostics'),
        $helperDiagnosticFilter
    ));
    $helperMetadataLineNumbers = array_map(
        static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null,
        $helperMetadataLineDiagnostics
    );
    if (!str_contains($helperMetadataLineHtml, 'data-pandoc-style="color: red"') || !str_contains($helperMetadataLineBlocks, 'data-pandoc-output-for="good"')) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not preserve helper metadata after source-line diagnostics');
    }
    if ($helperMetadataLineNumbers !== [2, 2, 3, 3, 3, 4, 5, 5, 6, 6, 6, 6]) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not carry source lines on helper metadata diagnostics');
    }
    if ($helperMetadataLineNumbers !== array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $helperMetadataLineAstDiagnostics)) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not carry helper metadata source lines into raw HTML AST diagnostics');
    }
    foreach (['javascript:', 'bad&lt;tag', 'bad id', 'transcript'] as $blockedMetadata) {
        if (str_contains($helperMetadataLineHtml, $blockedMetadata) || str_contains($helperMetadataLineBlocks, $blockedMetadata)) {
            throw new RuntimeException('HTML5 DOM handoff self-test leaked unsafe helper metadata source value: ' . $blockedMetadata);
        }
    }

    if ($fragment->summary()['blockedTags'] !== ['applet', 'area', 'button', 'canvas', 'embed', 'form', 'iframe', 'input', 'map', 'noscript', 'object', 'optgroup', 'option', 'param', 'plaintext', 'script', 'select', 'template', 'textarea', 'xmp']) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not report blocked form/embed/noscript/template/script/plaintext tags');
    }
    if (!in_array('srcset', $fragment->summary()['filteredAttributes'], true)) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not report filtered srcset attribute');
    }
    foreach (['ping', 'formaction', 'background', 'dynsrc', 'lowsrc', 'usemap'] as $filteredAttribute) {
        if (!in_array($filteredAttribute, $fragment->summary()['filteredAttributes'], true)) {
            throw new RuntimeException('HTML5 DOM handoff self-test did not report filtered ' . $filteredAttribute . ' attribute');
        }
    }
    $tableFosterDiagnostics = array_values(array_filter(
        $fragment->diagnosticCodes(),
        static fn (string $code): bool => $code === 'table-foster-parented-content'
    ));
    if (count($tableFosterDiagnostics) !== 2) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not report foster-parented table content');
    }
    $documentDom = Html5Dom::parseHtmlDocument('<!doctype html><html><body><article data-source="full-doc"><h1>Full packet</h1><p>Document<br>review</p></article></body></html>');
    $documentBody = $documentDom->getElementsByTagName('body')->item(0);
    if (!$documentBody instanceof DOMElement || !str_contains(Html5Dom::serializeHtmlChildren($documentBody), '<article data-source="full-doc"><h1>Full packet</h1><p>Document<br>review</p></article>')) {
        throw new RuntimeException('HTML5 DOM handoff self-test did not parse a simple doctype-bearing complete HTML document');
    }
    foreach ([
        '<!DOCTYPE html [<!ENTITY reviewer SYSTEM "file:///etc/passwd">]><html><body>&reviewer;</body></html>',
        '<!DOCTYPE html SYSTEM "file:///etc/passwd"><html><body><p>bad</p></body></html>',
        '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "https://example.invalid/xhtml.dtd"><html><body><p>bad</p></body></html>',
        '<!DOCTYPE svg><html><body><p>bad</p></body></html>',
        '<!DOCTYPE html><!DOCTYPE html><html><body><p>bad</p></body></html>',
        '<!ELEMENT html ANY><html><body>bad</body></html>',
        '<?xml-stylesheet href="https://example.invalid/review.xsl"?><html><body>bad</body></html>',
    ] as $unsafeDocument) {
        try {
            Html5Dom::parseHtmlDocument($unsafeDocument);
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException('HTML5 DOM handoff self-test accepted unsafe complete HTML document input');
    }
    foreach ([
        '<!DOCTYPE html><p>legacy doctype</p>',
        '<!ENTITY reviewer SYSTEM "file:///etc/passwd"><p>&reviewer;</p>',
        '<?xml-stylesheet href="https://example.invalid/review.xsl"?><p>stylesheet</p>',
    ] as $unsafeFragment) {
        try {
            Html5DomFragment::fromHtml($unsafeFragment);
        } catch (InvalidArgumentException) {
            continue;
        }

        throw new RuntimeException('HTML5 DOM handoff self-test accepted unsafe fragment declaration');
    }

    echo "wordpress-html5-dom-handoff self-test passed\n";
    return;
}

echo $blocks . "\n";
echo "controlBaseReview:\n" . $controlBaseFragment->serialize() . "\n";
echo "unsafeBaseReview:\n" . $unsafeBaseFragment->serialize() . "\n";
echo "duplicateBaseReview:\n" . $duplicateBaseFragment->serialize() . "\n";
echo "semanticMetadataLineReview:\n" . $semanticMetadataLineFragment->serialize() . "\n";
echo "documentMetadataLineReview:\n" . $documentMetadataLineFragment->serialize() . "\n";
echo "helperMetadataLineReview:\n" . $helperMetadataLineFragment->serialize() . "\n";
