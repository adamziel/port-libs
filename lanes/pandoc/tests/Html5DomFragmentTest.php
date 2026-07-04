<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\Html5DomFragment;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'repairs HTML5 fragments and serializes a deterministic normalized tree' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml('<section><p>One<br>Two<p>Three &amp; four</section>');
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();

        $t->same('<section><p>One<br>Two</p><p>Three &amp; four</p></section>', $fragment->serialize());
        $t->same('OneTwoThree & four', $fragment->textContent());
        $t->same('html', $summary['mode']);
        $t->same(1, $summary['topLevelNodes']);
        $t->same(4, $summary['elements']);
        $t->same(3, $summary['textNodes']);
        $t->same(['br', 'p', 'section'], $summary['elementNames']);
        $t->same('element', $nodes[0]['type']);
        $t->same('section', $nodes[0]['name']);
        $t->same(2, count($nodes[0]['children']));
        $t->same('p', $nodes[0]['children'][0]['name']);
        $t->same('br', $nodes[0]['children'][0]['children'][1]['name']);
        $t->same(['libxml-repair'], $fragment->diagnosticCodes());
    },
    'uses HTMLDocument tree construction for fragment formatting repair' => static function (TestRunner $t): void {
        if (!class_exists('Dom\\HTMLDocument')) {
            $t->true(true, 'Dom\\HTMLDocument is unavailable on this PHP runtime');

            return;
        }

        $formatting = Html5DomFragment::fromHtml('<b><i>one</b> two</i>');
        $table = Html5DomFragment::fromHtml('<p>Alpha<table><tr><td>Cell</td></tr></table>Omega</p>');

        $t->same('<b><i>one</i></b><i> two</i>', $formatting->serialize());
        $t->same(['b', 'i'], $formatting->summary()['elementNames']);
        $t->same('<p>Alpha</p><table><tr><td>Cell</td></tr></table>Omega', $table->serialize());
        $t->same(['p', 'table', 'td', 'tr'], $table->summary()['elementNames']);
    },
    'records fragment source provenance for raw html ast handoff' => static function (TestRunner $t): void {
        $source = "<article>\n"
            . '<p onclick="drop()">One<br>Two<script>alert(1)</script></p>'
            . "\n</article>";
        $fragment = Html5DomFragment::fromHtml($source, 'https://source.example.test/import/post.html');
        $html = $fragment->serialize();
        $provenance = $fragment->provenance();
        $ast = $fragment->toRawHtmlAst(['part' => '/migration/html-fragment-provenance.html']);

        $t->same('html', $provenance['sourceFormat']);
        $t->same(strlen($source), $provenance['sourceBytes']);
        $t->same(hash('sha256', $source), $provenance['sourceSha256']);
        $t->same(strlen($html), $provenance['serializedBytes']);
        $t->same(hash('sha256', $html), $provenance['serializedSha256']);
        $t->same(true, $provenance['serializedChanged']);
        $t->same(count($fragment->diagnostics()), $provenance['diagnosticCount']);
        $t->same($fragment->diagnosticCodes(), $provenance['diagnosticCodes']);
        $t->same('https://source.example.test/import/post.html', $provenance['baseUrl']);
        $t->same($html, $ast->attr('html'));
        $t->same($provenance, $ast->attr('fragmentProvenance'));
        $t->same('/migration/html-fragment-provenance.html', $ast->attr('part'));

        $xml = '<review><item xml:lang="en">Ready</item></review>';
        $xmlFragment = Html5DomFragment::fromXml($xml);
        $xmlHtml = $xmlFragment->serialize();
        $xmlProvenance = $xmlFragment->provenance();
        $xmlAst = $xmlFragment->toRawHtmlAst(['part' => '/migration/xml-fragment-provenance.xml']);

        $t->same('xml', $xmlProvenance['sourceFormat']);
        $t->same(strlen($xml), $xmlProvenance['sourceBytes']);
        $t->same(hash('sha256', $xml), $xmlProvenance['sourceSha256']);
        $t->same(strlen($xmlHtml), $xmlProvenance['serializedBytes']);
        $t->same(hash('sha256', $xmlHtml), $xmlProvenance['serializedSha256']);
        $t->same($xmlProvenance, $xmlAst->attr('fragmentProvenance'));
        $t->same('/migration/xml-fragment-provenance.xml', $xmlAst->attr('part'));
        $t->same('xml', $xmlAst->attr('format'));
    },
    'decodes bounded html5 named character references before sanitized handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article data-source="entities">'
            . '<p title="A&NoBreak;B" data-legacy="&nbsp &copy">A&NoBreak;B&NewLine;C&Tab;D &hopf; &nbsp &copy</p>'
            . '<p data-literal="&NoBreak test">Literal &NoBreak test</p>'
            . '<p data-math="&af;&it;&ic;">f&ApplyFunction;g&InvisibleTimes;h&MediumSpace;comma&InvisibleComma;zero&ZeroWidthSpace;neg&NegativeThinSpace;</p>'
            . '<p data-spacing="&NonBreakingSpace;&ThinSpace;&ThickSpace;&VeryThinSpace;&hairsp;">Spaces: non&NonBreakingSpace;thin&ThinSpace;alias&thinsp;thick&ThickSpace;very&VeryThinSpace;hair&hairsp;neg&NegativeVeryThinSpace;&NegativeMediumSpace;&NegativeThickSpace;</p>'
            . '<textarea>&NoBreak;&Tab;</textarea>'
            . '<script type="application/json">{"ref":"&NoBreak;"}</script>'
            . '</article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/html5-character-references.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article data-source="entities">'
            . '<p title="A' . "\u{2060}" . 'B" data-legacy="' . "\u{00A0}" . ' ©">A' . "\u{2060}" . "B\nC\tD " . "\u{1D559}" . ' ' . "\u{00A0}" . ' ©</p>'
            . '<p data-literal="&amp;NoBreak test">Literal &amp;NoBreak test</p>'
            . '<p data-math="' . "\u{2061}\u{2062}\u{2063}" . '">f' . "\u{2061}" . 'g' . "\u{2062}" . 'h' . "\u{205F}" . 'comma' . "\u{2063}" . 'zero' . "\u{200B}" . 'neg' . "\u{200B}" . '</p>'
            . '<p data-spacing="' . "\u{00A0}\u{2009}\u{205F}\u{200A}\u{200A}\u{200A}" . '">Spaces: non' . "\u{00A0}" . 'thin' . "\u{2009}" . 'alias' . "\u{2009}" . 'thick' . "\u{205F}\u{200A}" . 'very' . "\u{200A}" . 'hair' . "\u{200A}" . 'neg' . "\u{200B}\u{200B}\u{200B}" . '</p>'
            . "\u{2060}\t"
            . '</article>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('html', $summary['mode']);
        $t->same(['article', 'p'], $summary['elementNames']);
        $t->same(['script', 'textarea'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag'], $policyDiagnostics);
        $t->same('article', $nodes[0]['name']);
        $t->same("A\u{2060}B\nC\tD \u{1D559} \u{00A0} ©Literal &NoBreak testf\u{2061}g\u{2062}h\u{205F}comma\u{2063}zero\u{200B}neg\u{200B}Spaces: non\u{00A0}thin\u{2009}alias\u{2009}thick\u{205F}\u{200A}very\u{200A}hair\u{200A}neg\u{200B}\u{200B}\u{200B}\u{2060}\t", $fragment->textContent());
        $t->same("A\u{2060}B", $nodes[0]['children'][0]['attrs']['title']);
        $t->same("\u{00A0} ©", $nodes[0]['children'][0]['attrs']['data-legacy']);
        $t->same(['data-literal' => '&NoBreak test'], $nodes[0]['children'][1]['attrs']);
        $t->same('Literal &NoBreak test', $nodes[0]['children'][1]['children'][0]['text']);
        $t->same(['data-math' => "\u{2061}\u{2062}\u{2063}"], $nodes[0]['children'][2]['attrs']);
        $t->same("f\u{2061}g\u{2062}h\u{205F}comma\u{2063}zero\u{200B}neg\u{200B}", $nodes[0]['children'][2]['children'][0]['text']);
        $t->same(['data-spacing' => "\u{00A0}\u{2009}\u{205F}\u{200A}\u{200A}\u{200A}"], $nodes[0]['children'][3]['attrs']);
        $t->same("Spaces: non\u{00A0}thin\u{2009}alias\u{2009}thick\u{205F}\u{200A}very\u{200A}hair\u{200A}neg\u{200B}\u{200B}\u{200B}", $nodes[0]['children'][3]['children'][0]['text']);
        $t->same("\u{2060}\t", $nodes[0]['children'][4]['text']);
        $t->same('/migration/html5-character-references.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '&amp;NoBreak;'), 'Expected NoBreak to decode before sanitizer serialization');
        $t->true(!str_contains($html, '&amp;hopf;'), 'Expected astral named reference to decode before sanitizer serialization');
        $t->true(!str_contains($html, '&amp;ApplyFunction;'), 'Expected math function reference to decode before sanitizer serialization');
        $t->true(!str_contains($html, '&amp;ZeroWidthSpace;'), 'Expected zero-width reference to decode before sanitizer serialization');
        $t->true(!str_contains($html, '&amp;NonBreakingSpace;'), 'Expected named non-breaking space reference to decode before sanitizer serialization');
        $t->true(!str_contains($html, '&amp;ThickSpace;'), 'Expected multi-codepoint spacing reference to decode before sanitizer serialization');
        $t->true(!str_contains($html, '&amp;NegativeMediumSpace;'), 'Expected negative spacing aliases to decode before sanitizer serialization');
        $t->true(!str_contains($html, '<script'), 'Expected active script wrapper to be dropped before WordPress handoff');
    },
    'normalizes unsafe comment boundaries before sanitized raw html handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<!--review---><p>Imported comment boundary</p><!--source -- boundary--><!--triple---tail--->'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/comment-boundary-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $html = $fragment->serialize();

        $t->same('<!--review- --><p>Imported comment boundary</p><!--source - - boundary--><!--triple- - -tail- -->', $html);
        $t->contains($html, $blocks);
        $t->same(4, $summary['topLevelNodes']);
        $t->same(1, $summary['elements']);
        $t->same(3, $summary['comments']);
        $t->same(['p'], $summary['elementNames']);
        $t->same('comment', $nodes[0]['type']);
        $t->same('review-', $nodes[0]['text']);
        $t->same('p', $nodes[1]['name']);
        $t->same('comment', $nodes[2]['type']);
        $t->same('source -- boundary', $nodes[2]['text']);
        $t->same('triple---tail-', $nodes[3]['text']);
        $t->same('/migration/comment-boundary-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '--->'), 'Expected trailing hyphen comments to be padded before the closing delimiter');
        $t->true(!str_contains($html, 'source -- boundary'), 'Expected interior comment delimiters to be split before serialization');
    },
    'filters active tags unsafe attributes and unsafe URLs before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<p onclick="alert(1)">'
            . '<a href=" javascript:alert(1) " data-source="legacy" onmouseover="x">Bad</a>'
            . '<img src="https://example.test/a.png" alt="A" srcset="javascript:bad">'
            . '<script>alert(1)</script>'
            . '<span style="color:red" data-review="yes">Safe</span>'
            . '</p>'
        );
        $summary = $fragment->summary();
        $ast = $fragment->toRawHtmlAst(['source' => 'wordpress-import']);

        $t->same('<p><a data-source="legacy">Bad</a><img src="https://example.test/a.png" alt="A"><span data-pandoc-style="color: red" data-review="yes">Safe</span></p>', $fragment->serialize());
        $t->same('BadSafe', $fragment->textContent());
        $t->same(['a', 'img', 'p', 'span'], $summary['elementNames']);
        $t->same(['script'], $summary['blockedTags']);
        $t->same(['href', 'onclick', 'onmouseover', 'srcset'], $summary['filteredAttributes']);
        $t->same(6, $summary['diagnostics']);
        $t->same(['unsafe-attribute', 'unsafe-url', 'unsafe-attribute', 'unsafe-url', 'blocked-tag', 'style-review-metadata'], $fragment->diagnosticCodes());
        $t->same('raw_html', $ast->type);
        $t->same('html', $ast->attr('format'));
        $t->same('wordpress-import', $ast->attr('source'));
        $t->same($fragment->serialize(), $ast->attr('html'));
        $t->same(6, count($ast->attr('diagnostics')));
    },
    'converts bounded html style declarations into inert reviewer metadata' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<div>'
            . '<p style=" color : #c00 ; font-weight: 700; background-image:url(javascript:alert(1)); position:fixed; text-align: center ; letter-spacing: \30 .5em ;">Styled</p>'
            . '<span style="background-color: rgb(255,255,255); text-decoration: underline; color: \72 ed;">Safe</span>'
            . '<em style="background:url(./track.png); font-size: 1.25rem; -moz-binding:url(xss.xml#x)">Mixed</em>'
            . '</div>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/style-review-fragment.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<div>'
            . '<p data-pandoc-style="color: #c00; font-weight: 700; text-align: center; letter-spacing: 0.5em">Styled</p>'
            . '<span data-pandoc-style="background-color: rgb(255, 255, 255); text-decoration: underline; color: red">Safe</span>'
            . '<em data-pandoc-style="font-size: 1.25rem">Mixed</em>'
            . '</div>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('StyledSafeMixed', $fragment->textContent());
        $t->same(['div', 'em', 'p', 'span'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['style'], $summary['filteredAttributes']);
        $t->same(7, $summary['diagnostics']);
        $t->same([
            'unsafe-attribute',
            'unsafe-attribute',
            'style-review-metadata',
            'style-review-metadata',
            'unsafe-attribute',
            'unsafe-attribute',
            'style-review-metadata',
        ], $fragment->diagnosticCodes());
        $t->same('color: #c00; font-weight: 700; text-align: center; letter-spacing: 0.5em', $nodes[0]['children'][0]['attrs']['data-pandoc-style']);
        $t->same('background-color: rgb(255, 255, 255); text-decoration: underline; color: red', $nodes[0]['children'][1]['attrs']['data-pandoc-style']);
        $t->same('font-size: 1.25rem', $nodes[0]['children'][2]['attrs']['data-pandoc-style']);
        $t->same('/migration/style-review-fragment.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, ' style='), 'Expected active style attributes to be replaced by inert review metadata');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe CSS URLs to be stripped');
        $t->true(!str_contains($html, 'background-image'), 'Expected unbounded CSS properties to be stripped');
        $t->true(!str_contains($html, '-moz-binding'), 'Expected legacy binding CSS to be stripped');
    },
    'filters non-fetch media URLs while preserving reviewer mail and phone links' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<p>'
            . '<a href="mailto:review@example.test">Mail reviewer</a>'
            . '<a href="tel:+15550100">Call reviewer</a>'
            . '<img src="mailto:cover@example.test" alt="Mail image">'
            . '<img src="/media/cover.png" alt="Safe image">'
            . '<video poster="tel:+15550100"><source src="mailto:video@example.test" type="video/mp4"><source src="https://cdn.example.test/video.mp4" type="video/mp4"></video>'
            . '</p>'
        );
        $summary = $fragment->summary();
        $html = $fragment->serialize();

        $t->contains('<a href="mailto:review@example.test">Mail reviewer</a>', $html);
        $t->contains('<a href="tel:+15550100">Call reviewer</a>', $html);
        $t->contains('<span data-pandoc-image-alt-fallback="true">Mail image</span>', $html);
        $t->contains('<img src="/media/cover.png" alt="Safe image">', $html);
        $t->contains('<video><source type="video/mp4"><source src="https://cdn.example.test/video.mp4" type="video/mp4"></video>', $html);
        $t->same('Mail reviewerCall reviewerMail image', $fragment->textContent());
        $t->same(['poster', 'src'], $summary['filteredAttributes']);
        $diagnosticCodes = $fragment->diagnosticCodes();
        $unsafeUrlDiagnostics = array_values(array_filter($diagnosticCodes, static fn (string $code): bool => $code === 'unsafe-url'));
        $imageAltFallbackDiagnostics = array_values(array_filter($diagnosticCodes, static fn (string $code): bool => $code === 'image-alt-fallback'));
        $t->same(3, count($unsafeUrlDiagnostics));
        $t->same(1, count($imageAltFallbackDiagnostics));
        $t->true(in_array('libxml-repair', $diagnosticCodes, true), 'Expected libxml repair diagnostics for HTML5 media elements');
        $t->true(!str_contains($html, 'src="mailto:'), 'Expected mailto media src URLs to be removed');
        $t->true(!str_contains($html, 'poster="tel:'), 'Expected tel poster URLs to be removed');
    },
    'converts stripped image alt text into visible reviewer fallback' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<figure data-review="image-fallback">'
            . '<img src="javascript:alert(1)" alt="Legacy diagram">'
            . '<img srcset="javascript:alert(1) 1x, data:text/html;base64,PHNjcmlwdD4= 2x" alt="Responsive threat">'
            . '<img src="./safe.png" alt="Safe image">'
            . '<img alt="Decorative placeholder">'
            . '</figure>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/image-alt-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $expected = '<figure data-review="image-fallback">'
            . '<span data-pandoc-image-alt-fallback="true">Legacy diagram</span>'
            . '<span data-pandoc-image-alt-fallback="true">Responsive threat</span>'
            . '<img src="https://source.example.test/import/posts/safe.png" alt="Safe image">'
            . '<img alt="Decorative placeholder">'
            . '</figure>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Legacy diagramResponsive threat', $fragment->textContent());
        $t->same(['figure', 'img', 'span'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['src', 'srcset'], $summary['filteredAttributes']);
        $t->same(6, $summary['diagnostics']);
        $t->same(['unsafe-url', 'image-alt-fallback', 'unsafe-url', 'unsafe-url', 'image-alt-fallback'], $policyDiagnostics);
        $t->same('span', $nodes[0]['children'][0]['name']);
        $t->same(['data-pandoc-image-alt-fallback' => 'true'], $nodes[0]['children'][0]['attrs']);
        $t->same('Legacy diagram', $nodes[0]['children'][0]['children'][0]['text']);
        $t->same('Responsive threat', $nodes[0]['children'][1]['children'][0]['text']);
        $t->same('https://source.example.test/import/posts/safe.png', $nodes[0]['children'][2]['attrs']['src']);
        $t->same(['alt' => 'Decorative placeholder'], $nodes[0]['children'][3]['attrs']);
        $t->same('/migration/image-alt-fallback-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe image resources to be stripped');
        $t->true(!str_contains($html, 'data:text/html'), 'Expected active data image candidate to be stripped');
        $t->true(!str_contains($html, '<img src="javascript:'), 'Expected unsafe image element to become fallback text');
    },
    'filters extended URL attributes and ping side effects before review handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<section>'
            . '<a href="/review" ping="https://tracker.example.test/ping javascript:alert(1)">Tracked review link</a>'
            . '<div action="/safe-submit" formaction="javascript:alert(1)" longdesc="https://example.test/longdesc" background="mailto:review@example.test">URL attrs</div>'
            . '<figure longdesc=" javascript:alert(1) " background="/media/bg.png"><img src="/media/cover.png" longdesc="/media/cover-longdesc.html" alt="Cover"></figure>'
            . '</section>'
        );
        $summary = $fragment->summary();
        $html = $fragment->serialize();
        $nodes = $fragment->nodes();

        $t->contains('<a href="/review">Tracked review link</a>', $html);
        $t->contains('<div action="/safe-submit" longdesc="https://example.test/longdesc">URL attrs</div>', $html);
        $t->contains('<figure background="/media/bg.png"><img src="/media/cover.png" longdesc="/media/cover-longdesc.html" alt="Cover"></figure>', $html);
        $t->same(['background', 'formaction', 'longdesc', 'ping'], $summary['filteredAttributes']);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $t->same(['unsafe-attribute', 'unsafe-url', 'unsafe-url', 'unsafe-url'], $policyDiagnostics);
        $t->same('/safe-submit', $nodes[0]['children'][1]['attrs']['action']);
        $t->same('https://example.test/longdesc', $nodes[0]['children'][1]['attrs']['longdesc']);
        $t->same('/media/bg.png', $nodes[0]['children'][2]['attrs']['background']);
        $t->true(!str_contains($html, 'ping='), 'Expected ping side-effect URLs to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected javascript URLs to be stripped from extended attributes');
        $t->true(!str_contains($html, 'background="mailto:'), 'Expected mailto image-fetch URL to be stripped');
    },
    'converts anchor target and download attributes into inert reviewer metadata' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<p>'
            . '<a href="./source.html" target=" Review_Frame " download=" source-copy.html " ping="https://tracker.example.test/ping">Download source</a>'
            . '<a href="./blank.html" target="_blank" download>Blank download</a>'
            . '<a href="./bad.html" target="review&#10;<frame" download="bad&lt;file">Bad metadata</a>'
            . '<a href="javascript:alert(1)" target="_self" download="bad.html">Bad link</a>'
            . '</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/anchor-browsing-download-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $expected = '<p>'
            . '<a href="https://source.example.test/import/posts/source.html" data-pandoc-link-target="Review_Frame" data-pandoc-link-download="source-copy.html">Download source</a>'
            . '<a href="https://source.example.test/import/posts/blank.html" data-pandoc-link-target="_blank" data-pandoc-link-download="true">Blank download</a>'
            . '<a href="https://source.example.test/import/posts/bad.html">Bad metadata</a>'
            . '<a data-pandoc-link-target="_self" data-pandoc-link-download="bad.html">Bad link</a>'
            . '</p>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Download sourceBlank downloadBad metadataBad link', $fragment->textContent());
        $t->same(['a', 'p'], $summary['elementNames']);
        $t->same(['base'], $summary['blockedTags']);
        $t->same(['download', 'href', 'ping', 'target'], $summary['filteredAttributes']);
        $t->same([
            'blocked-tag',
            'link-browsing-review',
            'link-browsing-review',
            'unsafe-attribute',
            'link-browsing-review',
            'link-browsing-review',
            'unsafe-attribute',
            'unsafe-attribute',
            'unsafe-url',
            'link-browsing-review',
            'link-browsing-review',
        ], $policyDiagnostics);
        $t->same([
            'href' => 'https://source.example.test/import/posts/source.html',
            'data-pandoc-link-target' => 'Review_Frame',
            'data-pandoc-link-download' => 'source-copy.html',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/blank.html',
            'data-pandoc-link-target' => '_blank',
            'data-pandoc-link-download' => 'true',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same(['href' => 'https://source.example.test/import/posts/bad.html'], $nodes[0]['children'][2]['attrs']);
        $t->same([
            'data-pandoc-link-target' => '_self',
            'data-pandoc-link-download' => 'bad.html',
        ], $nodes[0]['children'][3]['attrs']);
        $t->same('/migration/anchor-browsing-download-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach ([' target=', ' download=', ' ping=', 'review&lt;frame', 'bad&lt;file', 'javascript:', 'tracker.example.test'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected anchor side-effect source to stay out of review HTML: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to omit anchor side-effect source: ' . $blocked);
        }
    },
    'normalizes control-separated URL attributes before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href=" https://source.example.test/import/posts/post.html ">'
            . '<p>'
            . '<a href=" h&#9;ttps://example.test/review ">Absolute source</a>'
            . '<a href=" ../media/source.html#note&#10;">Relative source</a>'
            . '<img src=" ./cover.png&#13;" srcset=" ./cover.png 1x, ../media/cover@2x.png 2x " alt="Cover">'
            . '</p>'
            . '<blockquote cite=" ?review=1&#10;">Quoted source</blockquote>'
            . '<a href="java&#10;script:alert(1)">Bad source</a>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/url-normalization-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $html = $fragment->serialize();

        $expected = '<p><a href="https://example.test/review">Absolute source</a><a href="https://source.example.test/import/media/source.html#note">Relative source</a><img src="https://source.example.test/import/posts/cover.png" srcset="https://source.example.test/import/posts/cover.png 1x, https://source.example.test/import/media/cover@2x.png 2x" alt="Cover"></p><blockquote data-pandoc-quote-cite="https://source.example.test/import/posts/post.html?review=1">Quoted source</blockquote><a>Bad source</a>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same(['base'], $summary['blockedTags']);
        $t->same(['cite', 'href'], $summary['filteredAttributes']);
        $t->same(7, $summary['diagnostics']);
        $t->same(['blocked-tag', 'normalized-url', 'normalized-url', 'normalized-url', 'normalized-url', 'quote-cite-review', 'unsafe-url'], $fragment->diagnosticCodes());
        $t->same('https://example.test/review', $nodes[0]['children'][0]['attrs']['href']);
        $t->same('https://source.example.test/import/media/source.html#note', $nodes[0]['children'][1]['attrs']['href']);
        $t->same('https://source.example.test/import/posts/cover.png', $nodes[0]['children'][2]['attrs']['src']);
        $t->same('https://source.example.test/import/posts/post.html?review=1', $nodes[1]['attrs']['data-pandoc-quote-cite']);
        $t->same('/migration/url-normalization-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, "h\t"), 'Expected control-separated safe schemes to be canonicalized');
        $t->true(!str_contains($html, "\n"), 'Expected newline-containing URL attributes to be canonicalized');
        $t->true(!str_contains($html, 'javascript:'), 'Expected control-separated unsafe schemes to be stripped');
    },
    'rejects percent-encoded active URL schemes before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<p>'
            . '<a href="java%0ascript:alert(1)">encoded control</a>'
            . '<a href="jav%61script:alert(1)">encoded scheme</a>'
            . '<img src="data%3Atext/html;base64,PHNjcmlwdD4=" alt="Encoded data">'
            . '<img src="https://cdn.example.test/%63over.png" alt="Cover">'
            . '<a href="https://example.test/%7Ereview/%2e%2e/admin">review path</a>'
            . '<img src="/media/fallback.png" srcset="jav%0dascript:alert(1) 2x, /media/%63over.png 640w" alt="Fallback">'
            . '</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/percent-encoded-url-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<p><a>encoded control</a><a>encoded scheme</a>'
            . '<span data-pandoc-image-alt-fallback="true">Encoded data</span>'
            . '<img src="https://cdn.example.test/%63over.png" alt="Cover">'
            . '<a href="https://example.test/%7Ereview/%2e%2e/admin">review path</a>'
            . '<img src="https://source.example.test/media/fallback.png" srcset="https://source.example.test/media/%63over.png 640w" alt="Fallback"></p>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('encoded controlencoded schemeEncoded datareview path', $fragment->textContent());
        $t->same(['a', 'img', 'p', 'span'], $summary['elementNames']);
        $t->same(['base'], $summary['blockedTags']);
        $t->same(['href', 'src', 'srcset'], $summary['filteredAttributes']);
        $t->same(6, $summary['diagnostics']);
        $t->same(['blocked-tag', 'unsafe-url', 'unsafe-url', 'unsafe-url', 'image-alt-fallback', 'unsafe-url'], $fragment->diagnosticCodes());
        $t->same([], $nodes[0]['children'][0]['attrs']);
        $t->same([], $nodes[0]['children'][1]['attrs']);
        $t->same('Encoded data', $nodes[0]['children'][2]['children'][0]['text']);
        $t->same('https://cdn.example.test/%63over.png', $nodes[0]['children'][3]['attrs']['src']);
        $t->same('https://example.test/%7Ereview/%2e%2e/admin', $nodes[0]['children'][4]['attrs']['href']);
        $t->same('https://source.example.test/media/%63over.png 640w', $nodes[0]['children'][5]['attrs']['srcset']);
        $t->same('/migration/percent-encoded-url-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach (['java%0ascript:', 'jav%61script:', 'data%3Atext/html', 'jav%0dascript:'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected percent-encoded active URL to be stripped: ' . $blocked);
        }
        foreach (['%63over.png', '%7Ereview/%2e%2e/admin'] as $preserved) {
            $t->true(str_contains($html, $preserved), 'Expected ordinary percent-encoded path bytes to stay intact: ' . $preserved);
        }
    },
    'unwraps visible form content while dropping active controls before review handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<form action="/submit" onsubmit="alert(1)">'
            . '<p>Name <input name="name" value="Ada"><button formaction="javascript:alert(1)">Send review</button></p>'
            . '<p><select name="status"><option selected>Draft</option><option>Final</option></select></p>'
            . '<textarea name="notes">Visible reviewer note</textarea>'
            . '</form><p>after</p>'
        );
        $summary = $fragment->summary();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/form-review-fragment.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('<p>Name <span data-pandoc-button-type="submit">Send review</span></p><p><span data-pandoc-select-name="status" data-pandoc-select-selected="Draft">Select: Draft</span>DraftFinal</p>Visible reviewer note<p>after</p>', $html);
        $t->same('Name Send reviewSelect: DraftDraftFinalVisible reviewer noteafter', $fragment->textContent());
        $t->same(['p', 'span'], $summary['elementNames']);
        $t->same(['button', 'form', 'input', 'option', 'select', 'textarea'], $summary['blockedTags']);
        $t->same(['formaction', 'name', 'selected', 'type'], $summary['filteredAttributes']);
        $t->same(11, $summary['diagnostics']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'button-metadata-review', 'unsafe-url', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'select-metadata-review', 'select-metadata-review', 'blocked-tag'], $fragment->diagnosticCodes());
        $t->contains('Visible reviewer note', $blocks);
        $t->same('/migration/form-review-fragment.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<form'), 'Expected form wrapper to be stripped');
        $t->true(!str_contains($html, '<input'), 'Expected input control to be dropped');
        $t->true(!str_contains($html, '<button'), 'Expected button wrapper to be stripped');
        $t->true(!str_contains($html, '<select'), 'Expected select wrapper to be stripped');
        $t->true(!str_contains($html, '<textarea'), 'Expected textarea wrapper to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected form-side javascript URLs to be stripped');
    },
    'converts form submission metadata into inert reviewer nodes before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<form method="POST" action=" ./submit?draft=1 " target=" review-frame " autocomplete="off" name=" comment-form " data-pandoc-form-name="source-spoof">'
            . '<p>Comment <input type="submit" value="Send comment"></p></form>'
            . '<form method="trace" action="java&#10;script:alert(1)" target="bad&lt;target" autocomplete="sometimes" name="bad&lt;tag">'
            . '<p>Bad form<input type="submit" value="Bad send"></p></form>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/form-submission-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_count_values(array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        )));

        $expected = '<span data-pandoc-form-method="post" data-pandoc-form-action="https://source.example.test/import/posts/submit?draft=1" data-pandoc-form-target="review-frame" data-pandoc-form-autocomplete="off" data-pandoc-form-name="comment-form">Form submission: post</span>'
            . '<p>Comment Send comment</p><p>Bad formBad send</p>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Form submission: postComment Send commentBad formBad send', $fragment->textContent());
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same(['p', 'span'], $summary['elementNames']);
        $t->same(['base', 'form', 'input'], $summary['blockedTags']);
        $t->same(['action', 'autocomplete', 'data-pandoc-form-name', 'method', 'name', 'target'], $summary['filteredAttributes']);
        $t->same(16, $summary['diagnostics']);
        $t->same(1, $policyDiagnostics['unsafe-url'] ?? 0);
        $t->same(5, $policyDiagnostics['form-metadata-review'] ?? 0);
        $t->same(5, $policyDiagnostics['unsafe-attribute'] ?? 0);
        $t->same(5, $policyDiagnostics['blocked-tag'] ?? 0);
        $t->same('span', $nodes[0]['name']);
        $t->same([
            'data-pandoc-form-method' => 'post',
            'data-pandoc-form-action' => 'https://source.example.test/import/posts/submit?draft=1',
            'data-pandoc-form-target' => 'review-frame',
            'data-pandoc-form-autocomplete' => 'off',
            'data-pandoc-form-name' => 'comment-form',
        ], $nodes[0]['attrs']);
        $t->same('Form submission: post', $nodes[0]['children'][0]['text']);
        $t->same('p', $nodes[1]['name']);
        $t->same('Comment ', $nodes[1]['children'][0]['text']);
        $t->same('Send comment', $nodes[1]['children'][1]['text']);
        $t->same('p', $nodes[2]['name']);
        $t->same('/migration/form-submission-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach (['<form', '<input', ' method=', ' action=', ' target=', ' autocomplete=', ' name=" comment-form "', 'source-spoof', 'bad&lt;target', 'bad&lt;tag', 'javascript:'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected form metadata handoff to strip live or unsafe source content: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to strip live or unsafe source content: ' . $blocked);
        }
    },
    'turns fieldset grouping controls into inert reviewer metadata' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<form action="/submit">'
            . '<fieldset disabled name=" import-settings " form="legacy-form" data-pandoc-fieldset-name="source-spoof">'
            . '<legend>Import settings</legend><p>Visibility <input type="submit" value="Save settings"></p></fieldset>'
            . '<fieldset name="bad<tag>" form="bad id"><legend>Bad group</legend><p>Bad metadata</p></fieldset>'
            . '</form>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/fieldset-review-fragment.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $fieldsetDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'fieldset-review'
        ));

        $expected = '<fieldset data-pandoc-fieldset-disabled="true" data-pandoc-fieldset-name="import-settings" data-pandoc-fieldset-form="legacy-form" data-pandoc-fieldset-label="Import settings"><legend data-pandoc-fieldset-legend="true">Import settings</legend><p>Visibility Save settings</p></fieldset>'
            . '<fieldset data-pandoc-fieldset-label="Bad group"><legend data-pandoc-fieldset-legend="true">Bad group</legend><p>Bad metadata</p></fieldset>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Import settingsVisibility Save settingsBad groupBad metadata', $fragment->textContent());
        $t->same(['fieldset', 'legend', 'p'], $summary['elementNames']);
        $t->same(['form', 'input'], $summary['blockedTags']);
        $t->same(['data-pandoc-fieldset-name', 'disabled', 'form', 'name'], $summary['filteredAttributes']);
        $t->same(10, $summary['diagnostics']);
        $t->same(5, count($fieldsetDiagnostics));
        $t->same(['disabled', 'name', 'form'], array_column(array_slice($fieldsetDiagnostics, 0, 3), 'attribute'));
        $t->same('fieldset-legend-preserved-as-metadata', $fieldsetDiagnostics[3]['reason']);
        $t->same('fieldset-legend-preserved-as-metadata', $fieldsetDiagnostics[4]['reason']);
        $t->same('fieldset', $nodes[0]['name']);
        $t->same('true', $nodes[0]['attrs']['data-pandoc-fieldset-disabled']);
        $t->same('import-settings', $nodes[0]['attrs']['data-pandoc-fieldset-name']);
        $t->same('legacy-form', $nodes[0]['attrs']['data-pandoc-fieldset-form']);
        $t->same('Import settings', $nodes[0]['attrs']['data-pandoc-fieldset-label']);
        $t->same('true', $nodes[0]['children'][0]['attrs']['data-pandoc-fieldset-legend']);
        $t->same('fieldset', $nodes[1]['name']);
        $t->same(['data-pandoc-fieldset-label' => 'Bad group'], $nodes[1]['attrs']);
        $t->same('/migration/fieldset-review-fragment.html', $document->children[0]->attr('part'));
        foreach ([' disabled', ' name=', ' form=', 'source-spoof', 'bad<tag>', 'bad id', '<form', '<input'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected fieldset handoff to strip live or unsafe source content: ' . $blocked);
        }
    },
    'preserves option and optgroup labels as visible reviewer text while dropping controls' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<form action="/submit"><p><select name="status">'
            . '<optgroup label="Publication status"><option label="Draft review"></option><option selected>Final</option></optgroup>'
            . '<option label="Needs copyedit">Submission value</option><option value="private"></option>'
            . '</select></p></form><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/select-label-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<p><span data-pandoc-select-name="status" data-pandoc-select-selected="Final">Select: Final</span>Publication statusDraft reviewFinalNeeds copyedit</p><p>after</p>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Select: FinalPublication statusDraft reviewFinalNeeds copyeditafter', $fragment->textContent());
        $t->same(['p', 'span'], $summary['elementNames']);
        $t->same(['form', 'optgroup', 'option', 'select'], $summary['blockedTags']);
        $t->same(['name', 'selected'], $summary['filteredAttributes']);
        $t->same(9, $summary['diagnostics']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'select-metadata-review', 'select-metadata-review'], $fragment->diagnosticCodes());
        $t->same('p', $nodes[0]['name']);
        $t->same('span', $nodes[0]['children'][0]['name']);
        $t->same([
            'data-pandoc-select-name' => 'status',
            'data-pandoc-select-selected' => 'Final',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same('Select: Final', $nodes[0]['children'][0]['children'][0]['text']);
        $t->same('Publication status', $nodes[0]['children'][1]['text']);
        $t->same('Draft review', $nodes[0]['children'][2]['text']);
        $t->same('Final', $nodes[0]['children'][3]['text']);
        $t->same('Needs copyedit', $nodes[0]['children'][4]['text']);
        $t->same('p', $nodes[1]['name']);
        $t->same('/migration/select-label-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<select'), 'Expected select wrapper to be stripped');
        $t->true(!str_contains($html, '<optgroup'), 'Expected optgroup wrapper to be stripped');
        $t->true(!str_contains($html, '<option'), 'Expected option wrapper to be stripped');
        $t->true(!str_contains($html, 'Submission value'), 'Expected option label to take precedence over child submission text');
        $t->true(!str_contains($html, 'private'), 'Expected option value to stay hidden from review text');
    },
    'converts select state metadata into inert reviewer spans before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<form action="/submit">'
            . '<p><select name=" publish-status " form="review-form" multiple required disabled size="03" data-pandoc-select-name="source-spoof">'
            . '<option label="Draft review" selected value="draft-token"></option>'
            . '<option selected>Ready for import</option>'
            . '<option value="private-default">Private default</option></select></p>'
            . '<p><select name="category"><option>News</option><option>Updates</option></select></p>'
            . '<p><select name="bad&lt;tag" form="bad id" size="0"><option selected>Bad select</option></select></p>'
            . '</form>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/select-state-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $diagnosticCounts = array_count_values($fragment->diagnosticCodes());

        $expected = '<p><span data-pandoc-select-name="publish-status" data-pandoc-select-form="review-form" data-pandoc-select-multiple="true" data-pandoc-select-required="true" data-pandoc-select-disabled="true" data-pandoc-select-size="3" data-pandoc-select-selected="Draft review | Ready for import">Select: Draft review; Ready for import</span>Draft reviewReady for importPrivate default</p>'
            . '<p><span data-pandoc-select-name="category" data-pandoc-select-selected="News">Select: News</span>NewsUpdates</p>'
            . '<p><span data-pandoc-select-selected="Bad select">Select: Bad select</span>Bad select</p>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Select: Draft review; Ready for importDraft reviewReady for importPrivate defaultSelect: NewsNewsUpdatesSelect: Bad selectBad select', $fragment->textContent());
        $t->same(['p', 'span'], $summary['elementNames']);
        $t->same(['form', 'option', 'select'], $summary['blockedTags']);
        $t->same(['data-pandoc-select-name', 'disabled', 'form', 'multiple', 'name', 'required', 'selected', 'size'], $summary['filteredAttributes']);
        $t->same(24, $summary['diagnostics']);
        $t->same(10, $diagnosticCounts['blocked-tag'] ?? 0);
        $t->same(4, $diagnosticCounts['unsafe-attribute'] ?? 0);
        $t->same(10, $diagnosticCounts['select-metadata-review'] ?? 0);
        $t->same('span', $nodes[0]['children'][0]['name']);
        $t->same([
            'data-pandoc-select-name' => 'publish-status',
            'data-pandoc-select-form' => 'review-form',
            'data-pandoc-select-multiple' => 'true',
            'data-pandoc-select-required' => 'true',
            'data-pandoc-select-disabled' => 'true',
            'data-pandoc-select-size' => '3',
            'data-pandoc-select-selected' => 'Draft review | Ready for import',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same('Select: Draft review; Ready for import', $nodes[0]['children'][0]['children'][0]['text']);
        $t->same([
            'data-pandoc-select-name' => 'category',
            'data-pandoc-select-selected' => 'News',
        ], $nodes[1]['children'][0]['attrs']);
        $t->same(['data-pandoc-select-selected' => 'Bad select'], $nodes[2]['children'][0]['attrs']);
        $t->same('/migration/select-state-review.html', $document->children[0]->attr('part'));
        foreach (['<form', '<select', '<option', ' multiple', ' required', ' disabled', ' size=', ' name=', ' form=', 'value=', 'draft-token', 'private-default', 'source-spoof', 'bad id', 'bad&lt;tag', 'bad<tag'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected select metadata handoff to strip live or unsafe source content: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to strip live or unsafe source content: ' . $blocked);
        }
    },
    'converts datalist suggestions into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<datalist id="topics" data-pandoc-datalist-options="source-spoof">'
            . '<base href="https://inactive.example/assets/">'
            . '<option label="WordPress import" value="wp"></option>'
            . '<option>Legacy CMS</option>'
            . '<option label="bad<tag" value="bad"></option>'
            . '<option value="private-token"></option>'
            . '<option label="WordPress import"></option>'
            . '</datalist>'
            . '<base href="https://source.example.test/import/posts/post.html">'
            . '<a href="./after.html">after</a>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/datalist-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $diagnosticCounts = array_count_values($fragment->diagnosticCodes());

        $expected = '<span data-pandoc-datalist-id="topics" data-pandoc-datalist-options="WordPress import | Legacy CMS">Datalist suggestions: WordPress import; Legacy CMS</span>'
            . '<a href="https://source.example.test/import/posts/after.html">after</a>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Datalist suggestions: WordPress import; Legacy CMSafter', $fragment->textContent());
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same(['a', 'span'], $summary['elementNames']);
        $t->same(['base', 'datalist', 'option'], $summary['blockedTags']);
        $t->same(['data-pandoc-datalist-options', 'id', 'label'], $summary['filteredAttributes']);
        $t->same(13, $summary['diagnostics']);
        $t->same(1, $diagnosticCounts['libxml-repair'] ?? 0);
        $t->same(8, $diagnosticCounts['blocked-tag'] ?? 0);
        $t->same(2, $diagnosticCounts['unsafe-attribute'] ?? 0);
        $t->same(2, $diagnosticCounts['datalist-review'] ?? 0);
        $t->same('span', $nodes[0]['name']);
        $t->same([
            'data-pandoc-datalist-id' => 'topics',
            'data-pandoc-datalist-options' => 'WordPress import | Legacy CMS',
        ], $nodes[0]['attrs']);
        $t->same('Datalist suggestions: WordPress import; Legacy CMS', $nodes[0]['children'][0]['text']);
        $t->same('a', $nodes[1]['name']);
        $t->same('/migration/datalist-review.html', $document->children[0]->attr('part'));
        foreach (['<datalist', '<option', '<base', 'value=', 'private-token', 'source-spoof', 'bad&lt;tag', 'bad<tag', 'inactive.example'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected datalist handoff to strip live or unsafe source content: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to strip live or unsafe source content: ' . $blocked);
        }
    },
    'preserves explicit input button labels as reviewer text while dropping controls' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<form action="/submit">'
            . '<p><input type="submit" value="Send review"><input type="reset" value="Clear form"><input type="button" value="Preview packet"></p>'
            . '<p><input type="image" src="javascript:alert(1)" alt="Image submit"><input type="text" value="Secret draft"><input type="hidden" value="Hidden token"></p>'
            . '<p><input type="checkbox" checked value="yes">Agree</p>'
            . '</form><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/input-label-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<p>Send reviewClear formPreview packet</p><p>Image submit</p><p>Agree</p><p>after</p>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Send reviewClear formPreview packetImage submitAgreeafter', $fragment->textContent());
        $t->same(['form', 'input'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(8, $summary['diagnostics']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag'], $fragment->diagnosticCodes());
        $t->same('p', $nodes[0]['name']);
        $t->same('Send review', $nodes[0]['children'][0]['text']);
        $t->same('Clear form', $nodes[0]['children'][1]['text']);
        $t->same('Preview packet', $nodes[0]['children'][2]['text']);
        $t->same('Image submit', $nodes[1]['children'][0]['text']);
        $t->same('p', $nodes[2]['name']);
        $t->same('Agree', $nodes[2]['children'][0]['text']);
        $t->same('/migration/input-label-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<input'), 'Expected input controls to be stripped');
        $t->true(!str_contains($html, 'Secret draft'), 'Expected text input values to stay hidden from review text');
        $t->true(!str_contains($html, 'Hidden token'), 'Expected hidden input values to stay hidden from review text');
        $t->true(!str_contains($html, 'javascript:'), 'Expected image input src URL to be stripped with the control');
    },
    'preserves label control associations as inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<form id="labels">'
            . '<label for="title" data-pandoc-label-text="source-spoof">Title <span>required</span></label><input id="title" name="title" type="rating" value="Draft">'
            . '<label>Subscribe <input id="subscribe" name="subscribe" type="checkbox" checked value="yes"></label>'
            . '<label for="missing">Missing</label>'
            . '<label for="bad id">Bad</label>'
            . '<label for="note">Not control</label><span id="note">Note text</span>'
            . '<label><input type="hidden" id="secret" name="secret" value="hidden"> Hidden</label>'
            . '</form>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/label-association-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $diagnosticCounts = array_count_values($fragment->diagnosticCodes());

        $expected = '<label data-pandoc-label-text="Title required" data-pandoc-label-for="title" data-pandoc-label-control-source="for-attribute" data-pandoc-label-control="input" data-pandoc-label-control-id="title" data-pandoc-label-control-name="title" data-pandoc-label-control-type="text">Title <span>required</span></label>'
            . '<label data-pandoc-label-text="Subscribe" data-pandoc-label-control-source="descendant" data-pandoc-label-control="input" data-pandoc-label-control-id="subscribe" data-pandoc-label-control-name="subscribe" data-pandoc-label-control-type="checkbox">Subscribe </label>'
            . '<label data-pandoc-label-text="Missing" data-pandoc-label-for="missing" data-pandoc-label-control-source="missing-for-target">Missing</label>'
            . '<label data-pandoc-label-text="Bad">Bad</label>'
            . '<label data-pandoc-label-text="Not control" data-pandoc-label-for="note" data-pandoc-label-control-source="non-labelable-for-target">Not control</label><span id="note">Note text</span>'
            . '<label data-pandoc-label-text="Hidden"> Hidden</label>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Title requiredSubscribe MissingBadNot controlNote text Hidden', $fragment->textContent());
        $t->same(['label', 'span'], $summary['elementNames']);
        $t->same(['form', 'input'], $summary['blockedTags']);
        $t->same(['control', 'data-pandoc-label-text', 'for', 'text'], $summary['filteredAttributes']);
        $t->same(28, $summary['diagnostics']);
        $t->same(21, $diagnosticCounts['label-metadata-review'] ?? 0);
        $t->same(4, $diagnosticCounts['blocked-tag'] ?? 0);
        $t->same(3, $diagnosticCounts['unsafe-attribute'] ?? 0);
        $t->same('label', $nodes[0]['name']);
        $t->same([
            'data-pandoc-label-text' => 'Title required',
            'data-pandoc-label-for' => 'title',
            'data-pandoc-label-control-source' => 'for-attribute',
            'data-pandoc-label-control' => 'input',
            'data-pandoc-label-control-id' => 'title',
            'data-pandoc-label-control-name' => 'title',
            'data-pandoc-label-control-type' => 'text',
        ], $nodes[0]['attrs']);
        $t->same([
            'data-pandoc-label-text' => 'Subscribe',
            'data-pandoc-label-control-source' => 'descendant',
            'data-pandoc-label-control' => 'input',
            'data-pandoc-label-control-id' => 'subscribe',
            'data-pandoc-label-control-name' => 'subscribe',
            'data-pandoc-label-control-type' => 'checkbox',
        ], $nodes[1]['attrs']);
        $t->same([
            'data-pandoc-label-text' => 'Missing',
            'data-pandoc-label-for' => 'missing',
            'data-pandoc-label-control-source' => 'missing-for-target',
        ], $nodes[2]['attrs']);
        $t->same(['data-pandoc-label-text' => 'Bad'], $nodes[3]['attrs']);
        $t->same([
            'data-pandoc-label-text' => 'Not control',
            'data-pandoc-label-for' => 'note',
            'data-pandoc-label-control-source' => 'non-labelable-for-target',
        ], $nodes[4]['attrs']);
        $t->same(['data-pandoc-label-text' => 'Hidden'], $nodes[6]['attrs']);
        $t->same('/migration/label-association-review.html', $document->children[0]->attr('part'));
        foreach (['<form', '<input', ' for=', 'source-spoof', 'value=', 'Draft', 'yes', 'hidden', 'bad id'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected label association handoff to strip live or unsafe source content: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to strip live or unsafe source content: ' . $blocked);
        }
    },
    'converts button submit metadata into inert reviewer spans before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<form action="./default-submit">'
            . '<p><button name=" publish " value=" yes " form="legacy-form" formaction=" ./publish?step=2 " formmethod="POST" formenctype="multipart/form-data" formtarget=" review-frame " formnovalidate data-pandoc-button-type="source-spoof">Publish <strong>now</strong></button></p>'
            . '<p><button type="reset" disabled value="clear">Clear draft</button><button type="button" name="preview">Preview only</button></p>'
            . '<p><button type="bad" formaction="java&#10;script:alert(1)" formmethod="TRACE" formenctype="application/json" formtarget="bad&lt;target" name="bad&lt;name">Bad action</button></p>'
            . '</form>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/button-submit-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_count_values(array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        )));

        $expected = '<p><span data-pandoc-button-type="submit" data-pandoc-button-name="publish" data-pandoc-button-value="yes" data-pandoc-button-form="legacy-form" data-pandoc-button-formaction="https://source.example.test/import/posts/publish?step=2" data-pandoc-button-formmethod="post" data-pandoc-button-formenctype="multipart/form-data" data-pandoc-button-formtarget="review-frame" data-pandoc-button-formnovalidate="true">Publish <strong>now</strong></span></p>'
            . '<p><span data-pandoc-button-type="reset" data-pandoc-button-value="clear" data-pandoc-button-disabled="true">Clear draft</span><span data-pandoc-button-type="button" data-pandoc-button-name="preview">Preview only</span></p>'
            . '<p><span data-pandoc-button-type="submit">Bad action</span></p>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Publish nowClear draftPreview onlyBad action', $fragment->textContent());
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same(['p', 'span', 'strong'], $summary['elementNames']);
        $t->same(['base', 'button', 'form'], $summary['blockedTags']);
        $t->same(['data-pandoc-button-type', 'disabled', 'form', 'formaction', 'formenctype', 'formmethod', 'formnovalidate', 'formtarget', 'name', 'type', 'value'], $summary['filteredAttributes']);
        $t->same(29, $summary['diagnostics']);
        $t->same(1, $policyDiagnostics['unsafe-url'] ?? 0);
        $t->same(6, $policyDiagnostics['unsafe-attribute'] ?? 0);
        $t->same(15, $policyDiagnostics['button-metadata-review'] ?? 0);
        $t->same(6, $policyDiagnostics['blocked-tag'] ?? 0);
        $t->same(1, $policyDiagnostics['normalized-url'] ?? 0);
        $t->same('span', $nodes[0]['children'][0]['name']);
        $t->same([
            'data-pandoc-button-type' => 'submit',
            'data-pandoc-button-name' => 'publish',
            'data-pandoc-button-value' => 'yes',
            'data-pandoc-button-form' => 'legacy-form',
            'data-pandoc-button-formaction' => 'https://source.example.test/import/posts/publish?step=2',
            'data-pandoc-button-formmethod' => 'post',
            'data-pandoc-button-formenctype' => 'multipart/form-data',
            'data-pandoc-button-formtarget' => 'review-frame',
            'data-pandoc-button-formnovalidate' => 'true',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same('Publish ', $nodes[0]['children'][0]['children'][0]['text']);
        $t->same('strong', $nodes[0]['children'][0]['children'][1]['name']);
        $t->same([
            'data-pandoc-button-type' => 'reset',
            'data-pandoc-button-value' => 'clear',
            'data-pandoc-button-disabled' => 'true',
        ], $nodes[1]['children'][0]['attrs']);
        $t->same([
            'data-pandoc-button-type' => 'button',
            'data-pandoc-button-name' => 'preview',
        ], $nodes[1]['children'][1]['attrs']);
        $t->same([
            'data-pandoc-button-type' => 'submit',
        ], $nodes[2]['children'][0]['attrs']);
        $t->same('/migration/button-submit-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach (['<button', ' formaction=', ' formmethod=', ' formenctype=', ' formtarget=', ' formnovalidate', ' disabled', 'source-spoof', 'bad&lt;target', 'bad&lt;name', 'javascript:', 'TRACE', 'application/json'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected button metadata handoff to strip live or unsafe source content: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to strip live or unsafe source content: ' . $blocked);
        }
    },
    'converts button command target metadata into inert reviewer spans before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<dialog id="confirm" open>Confirm body</dialog>'
            . '<div id="menu" popover="manual">Menu</div>'
            . '<section id="card">Card</section>'
            . '<p>'
            . '<button commandfor="menu" command="show-popover" data-pandoc-button-command="source-spoof">Show menu</button>'
            . '<button commandfor="confirm" command="request-close">Close dialog</button>'
            . '<button type="button" commandfor="card" command="--mark-reviewed">Mark reviewed</button>'
            . '<button commandfor="missing" command="toggle-popover">Missing target</button>'
            . '<button commandfor="bad target" command="close">Bad target</button>'
            . '<button commandfor="card" command="rotate">Bad command</button>'
            . '</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/button-command-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_count_values(array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        )));

        $expected = '<div id="confirm" data-pandoc-dialog-state="open">Confirm body</div>'
            . '<div id="menu" data-pandoc-popover-state="manual">Menu</div>'
            . '<section id="card">Card</section>'
            . '<p>'
            . '<span data-pandoc-button-type="submit" data-pandoc-button-command-state="show-popover" data-pandoc-button-command="show-popover" data-pandoc-button-command-family="popover" data-pandoc-button-commandfor="menu" data-pandoc-button-command-target="popover" data-pandoc-button-command-target-tag="div" data-pandoc-button-command-target-id="menu" data-pandoc-button-command-target-popover="manual">Show menu</span>'
            . '<span data-pandoc-button-type="submit" data-pandoc-button-command-state="request-close" data-pandoc-button-command="request-close" data-pandoc-button-command-family="dialog" data-pandoc-button-commandfor="confirm" data-pandoc-button-command-target="dialog" data-pandoc-button-command-target-tag="dialog" data-pandoc-button-command-target-id="confirm" data-pandoc-button-command-target-dialog-state="open">Close dialog</span>'
            . '<span data-pandoc-button-type="button" data-pandoc-button-command-state="custom" data-pandoc-button-command="--mark-reviewed" data-pandoc-button-command-family="custom" data-pandoc-button-commandfor="card" data-pandoc-button-command-target="element" data-pandoc-button-command-target-tag="section" data-pandoc-button-command-target-id="card">Mark reviewed</span>'
            . '<span data-pandoc-button-type="submit" data-pandoc-button-command-state="toggle-popover" data-pandoc-button-command="toggle-popover" data-pandoc-button-command-family="popover" data-pandoc-button-commandfor="missing" data-pandoc-button-command-target="missing-target" data-pandoc-button-command-issues="missing-button-command-target">Missing target</span>'
            . '<span data-pandoc-button-type="submit" data-pandoc-button-command-state="close" data-pandoc-button-command="close" data-pandoc-button-command-family="dialog" data-pandoc-button-command-target="invalid-reference" data-pandoc-button-command-issues="invalid-button-commandfor-target">Bad target</span>'
            . '<span data-pandoc-button-type="submit" data-pandoc-button-command-state="unknown" data-pandoc-button-commandfor="card" data-pandoc-button-command-target="element" data-pandoc-button-command-target-tag="section" data-pandoc-button-command-target-id="card" data-pandoc-button-command-issues="unknown-button-command">Bad command</span>'
            . '</p>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Confirm bodyMenuCardShow menuClose dialogMark reviewedMissing targetBad targetBad command', $fragment->textContent());
        $t->same(['div', 'p', 'section', 'span'], $summary['elementNames']);
        $t->same(['button'], $summary['blockedTags']);
        $t->same(['command', 'commandfor', 'data-pandoc-button-command', 'open', 'popover', 'type'], $summary['filteredAttributes']);
        $t->same(6, $policyDiagnostics['blocked-tag'] ?? 0);
        $t->same(46, $policyDiagnostics['button-metadata-review'] ?? 0);
        $t->same(3, $policyDiagnostics['unsafe-attribute'] ?? 0);
        $t->same(1, $policyDiagnostics['dialog-review'] ?? 0);
        $t->same(1, $policyDiagnostics['popover-review'] ?? 0);
        $t->same('span', $nodes[3]['children'][0]['name']);
        $t->same('popover', $nodes[3]['children'][0]['attrs']['data-pandoc-button-command-target']);
        $t->same('manual', $nodes[3]['children'][0]['attrs']['data-pandoc-button-command-target-popover']);
        $t->same('dialog', $nodes[3]['children'][1]['attrs']['data-pandoc-button-command-target']);
        $t->same('open', $nodes[3]['children'][1]['attrs']['data-pandoc-button-command-target-dialog-state']);
        $t->same('custom', $nodes[3]['children'][2]['attrs']['data-pandoc-button-command-family']);
        $t->same('missing-button-command-target', $nodes[3]['children'][3]['attrs']['data-pandoc-button-command-issues']);
        $t->same('invalid-reference', $nodes[3]['children'][4]['attrs']['data-pandoc-button-command-target']);
        $t->same('unknown-button-command', $nodes[3]['children'][5]['attrs']['data-pandoc-button-command-issues']);
        $t->same('/migration/button-command-review.html', $document->children[0]->attr('part'));
        foreach (['<button', ' command=', ' commandfor=', 'source-spoof', 'bad target', 'rotate'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected command button handoff to strip live or unsafe source content: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to strip live or unsafe source content: ' . $blocked);
        }
    },
    'unwraps active embed fallback content while dropping unsafe containers' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<iframe src="javascript:alert(1)">Fallback <b>caption</b><script>drop()</script></iframe>'
            . '<object data="javascript:alert(1)" type="application/x-shockwave-flash"><param name="movie" value="legacy.swf"><p>Object fallback <a href="/review">review</a></p></object>'
            . '<applet code="Legacy.class"><span>Applet fallback</span></applet><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/embed-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = 'Fallback <b>caption</b><p>Object fallback <a href="/review">review</a></p><span>Applet fallback</span><p>after</p>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Fallback captionObject fallback reviewApplet fallbackafter', $fragment->textContent());
        $t->same(['a', 'b', 'p', 'span'], $summary['elementNames']);
        $t->same(['applet', 'iframe', 'object', 'param', 'script'], $summary['blockedTags']);
        $t->same(['data'], $summary['filteredAttributes']);
        $t->same(6, $summary['diagnostics']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'unsafe-url', 'blocked-tag'], $fragment->diagnosticCodes());
        $t->same('text', $nodes[0]['type']);
        $t->same('Fallback ', $nodes[0]['text']);
        $t->same('b', $nodes[1]['name']);
        $t->same('p', $nodes[2]['name']);
        $t->same('span', $nodes[3]['name']);
        $t->same('p', $nodes[4]['name']);
        $t->same('/migration/embed-fallback-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<iframe'), 'Expected iframe wrapper to be stripped');
        $t->true(!str_contains($html, '<object'), 'Expected object wrapper to be stripped');
        $t->true(!str_contains($html, '<applet'), 'Expected applet wrapper to be stripped');
        $t->true(!str_contains($html, '<param'), 'Expected object param metadata to be dropped');
        $t->true(!str_contains($html, '<script'), 'Expected active fallback script to be dropped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe embed URLs to be stripped with their wrappers');
        $t->true(!str_contains($blocks, '<iframe'), 'Expected WordPress blocks to omit iframe wrapper');
        $t->true(!str_contains($blocks, '<object'), 'Expected WordPress blocks to omit object wrapper');
        $t->true(!str_contains($blocks, '<applet'), 'Expected WordPress blocks to omit applet wrapper');
    },
    'unwraps canvas fallback content while ignoring inactive base metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<canvas width="400" height="200"><base href="https://inactive.example.test/assets/">'
            . '<p>Canvas fallback <a href="fallback.html">review</a><a href="javascript:alert(1)">bad</a></p><script>drop()</script></canvas>'
            . '<base href="https://source.example.test/import/posts/post.html"><p><a href="./after.html">after</a></p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/canvas-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<p>Canvas fallback <a href="https://source.example.test/import/posts/fallback.html">review</a><a>bad</a></p>'
            . '<p><a href="https://source.example.test/import/posts/after.html">after</a></p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Canvas fallback reviewbadafter', $fragment->textContent());
        $t->same(['a', 'p'], $summary['elementNames']);
        $t->same(['base', 'canvas', 'script'], $summary['blockedTags']);
        $t->same(['href'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'unsafe-url', 'blocked-tag', 'blocked-tag'], $policyDiagnostics);
        $t->same('p', $nodes[0]['name']);
        $t->same('https://source.example.test/import/posts/fallback.html', $nodes[0]['children'][1]['attrs']['href']);
        $t->same([], $nodes[0]['children'][2]['attrs']);
        $t->same('p', $nodes[1]['name']);
        $t->same('https://source.example.test/import/posts/after.html', $nodes[1]['children'][0]['attrs']['href']);
        $t->same('/migration/canvas-fallback-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<canvas'), 'Expected canvas wrapper to be stripped');
        $t->true(!str_contains($html, 'width='), 'Expected canvas drawing dimensions to stay hidden');
        $t->true(!str_contains($html, 'height='), 'Expected canvas drawing dimensions to stay hidden');
        $t->true(!str_contains($html, 'inactive.example.test'), 'Expected canvas-local base metadata to stay inactive');
        $t->true(!str_contains($html, '<script'), 'Expected active canvas fallback script to be dropped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe canvas fallback URL to stay hidden');
        $t->true(!str_contains($blocks, '<canvas'), 'Expected WordPress blocks to omit canvas wrapper');
    },
    'converts safe object and embed sources into reviewer links before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<object data="./docs/source.pdf" title="Embedded PDF source"><p>PDF fallback <a href="./docs/fallback.html">details</a></p></object>'
            . '<embed src=" h&#9;ttps://cdn.example.test/media/demo.mp4 " title="Embedded media source">'
            . '<embed src="javascript:alert(1)" title="Bad embed">'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/embed-source-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<a href="https://source.example.test/import/posts/docs/source.pdf" data-pandoc-object-data="true" title="Embedded PDF source">Embedded PDF source</a>'
            . '<p>PDF fallback <a href="https://source.example.test/import/posts/docs/fallback.html">details</a></p>'
            . '<a href="https://cdn.example.test/media/demo.mp4" data-pandoc-embed-src="true" title="Embedded media source">Embedded media source</a>'
            . '<p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Embedded PDF sourcePDF fallback detailsEmbedded media sourceafter', $fragment->textContent());
        $t->same(['a', 'p'], $summary['elementNames']);
        $t->same(['base', 'embed', 'object'], $summary['blockedTags']);
        $t->same(['data', 'src'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'embedded-source-review', 'blocked-tag', 'blocked-tag', 'unsafe-url', 'normalized-url', 'embedded-source-review'], $policyDiagnostics);
        $t->same('a', $nodes[0]['name']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/docs/source.pdf',
            'data-pandoc-object-data' => 'true',
            'title' => 'Embedded PDF source',
        ], $nodes[0]['attrs']);
        $t->same('p', $nodes[1]['name']);
        $t->same('https://source.example.test/import/posts/docs/fallback.html', $nodes[1]['children'][1]['attrs']['href']);
        $t->same('a', $nodes[2]['name']);
        $t->same([
            'href' => 'https://cdn.example.test/media/demo.mp4',
            'data-pandoc-embed-src' => 'true',
            'title' => 'Embedded media source',
        ], $nodes[2]['attrs']);
        $t->same('/migration/embed-source-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<object'), 'Expected object wrapper to be stripped');
        $t->true(!str_contains($html, '<embed'), 'Expected embed wrapper to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe embed source URL to stay hidden');
        $t->true(!str_contains($html, 'Bad embed'), 'Expected unsafe embed title to stay hidden with its source');
        $t->true(!str_contains($blocks, '<object'), 'Expected WordPress blocks to omit object wrapper');
        $t->true(!str_contains($blocks, '<embed'), 'Expected WordPress blocks to omit embed wrapper');
    },
    'converts safe object params into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . "<article>\n"
            . '<object data="./docs/source.pdf" title="Embedded PDF source">'
            . '<param name="movie" value="./media/player.swf" valuetype="ref" type="application/x-shockwave-flash">'
            . '<param name="FlashVars" value=" autoplay = false ; poster = cover.png " data-pandoc-object-param-name="spoof">'
            . '<param name="src" value="java&#10;script:alert(1)">'
            . '<param name="bad name" value="drop">'
            . '<p>PDF fallback <a href="./docs/fallback.html">details</a></p>'
            . '</object>'
            . "\n</article>",
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/object-param-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $paramDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static function (array $diagnostic): bool {
                $code = (string) ($diagnostic['code'] ?? '');

                return ($diagnostic['tag'] ?? '') === 'param'
                    && in_array($code, ['object-param-review', 'unsafe-attribute', 'unsafe-url'], true);
            }
        ));
        $astParamDiagnostics = array_values(array_filter(
            $document->children[0]->attr('diagnostics'),
            static function (array $diagnostic): bool {
                $code = (string) ($diagnostic['code'] ?? '');

                return ($diagnostic['tag'] ?? '') === 'param'
                    && in_array($code, ['object-param-review', 'unsafe-attribute', 'unsafe-url'], true);
            }
        ));

        $t->contains('<a href="https://source.example.test/import/posts/docs/source.pdf" data-pandoc-object-data="true" title="Embedded PDF source">Embedded PDF source</a>', $html);
        $t->contains('<span data-pandoc-object-param-name="movie" data-pandoc-object-param-valuetype="ref" data-pandoc-object-param-type="application/x-shockwave-flash" data-pandoc-object-param-value="https://source.example.test/import/posts/media/player.swf">Object parameter: movie=https://source.example.test/import/posts/media/player.swf</span>', $html);
        $t->contains('<span data-pandoc-object-param-name="flashvars" data-pandoc-object-param-value="autoplay = false ; poster = cover.png">Object parameter: flashvars=autoplay = false ; poster = cover.png</span>', $html);
        $t->contains('<span data-pandoc-object-param-name="src">Object parameter: src</span>', $html);
        $t->contains('<p>PDF fallback <a href="https://source.example.test/import/posts/docs/fallback.html">details</a></p>', $html);
        $t->contains($html, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same(['a', 'article', 'p', 'span'], $summary['elementNames']);
        $t->same(['base', 'object', 'param'], $summary['blockedTags']);
        $t->same(['data', 'data-pandoc-object-param-name', 'name', 'value'], $summary['filteredAttributes']);
        $t->same('/migration/object-param-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->same(6, count($paramDiagnostics));
        $t->same(
            ['object-param-review', 'unsafe-attribute', 'object-param-review', 'unsafe-url', 'object-param-review', 'unsafe-attribute'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $paramDiagnostics)
        );
        $t->same(
            ['value', 'data-pandoc-object-param-name', 'value', 'value', 'name', 'name'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['attribute'] ?? ''), $paramDiagnostics)
        );
        $t->same([2, 2, 2, 2, 2, 2], array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $paramDiagnostics));
        $t->same(
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $paramDiagnostics),
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $astParamDiagnostics)
        );
        $t->true(!str_contains($html, '<object'), 'Expected object wrapper to be stripped');
        $t->true(!str_contains($html, '<param'), 'Expected live param elements to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe param values to stay diagnostic-only');
        $t->true(!str_contains($html, 'bad name'), 'Expected invalid param names to stay diagnostic-only');
        $t->true(!str_contains($html, 'spoof'), 'Expected source-authored reserved object-param metadata to be stripped');
        $t->true(!str_contains($blocks, '<param'), 'Expected WordPress blocks to omit live param elements');
    },
    'adds source line metadata to object and embed source diagnostics before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            "<article>\n"
            . "<object data=\"./docs/review.pdf\" title=\"Review PDF\"></object>\n"
            . "<object data=\"java&#10;script:alert(1)\" title=\"Bad object\"><p>Unsafe fallback</p></object>\n"
            . "<embed src=\" h&#9;ttps://cdn.example.test/media/demo.mp4 \" title=\"Demo\">\n"
            . "<embed src=\"java&#10;script:alert(1)\" title=\"Bad embed\">\n"
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/object-embed-diagnostic-lines-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $embeddedDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static function (array $diagnostic): bool {
                $code = (string) ($diagnostic['code'] ?? '');
                $tag = (string) ($diagnostic['tag'] ?? '');

                return in_array($code, ['embedded-source-review', 'normalized-url', 'unsafe-url'], true)
                    && in_array($tag, ['object', 'embed'], true);
            }
        ));
        $astEmbeddedDiagnostics = array_values(array_filter(
            $document->children[0]->attr('diagnostics'),
            static function (array $diagnostic): bool {
                $code = (string) ($diagnostic['code'] ?? '');
                $tag = (string) ($diagnostic['tag'] ?? '');

                return in_array($code, ['embedded-source-review', 'normalized-url', 'unsafe-url'], true)
                    && in_array($tag, ['object', 'embed'], true);
            }
        ));

        $expected = '<article>' . "\n"
            . '<a href="https://source.example.test/import/posts/docs/review.pdf" data-pandoc-object-data="true" title="Review PDF">Review PDF</a>' . "\n"
            . '<p>Unsafe fallback</p>' . "\n"
            . '<a href="https://cdn.example.test/media/demo.mp4" data-pandoc-embed-src="true" title="Demo">Demo</a>' . "\n\n"
            . '</article>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('/migration/object-embed-diagnostic-lines-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->same(5, count($embeddedDiagnostics));
        $t->same(
            ['embedded-source-review', 'unsafe-url', 'unsafe-url', 'normalized-url', 'embedded-source-review'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $embeddedDiagnostics)
        );
        $t->same(
            ['object', 'object', 'embed', 'embed', 'embed'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['tag'] ?? ''), $embeddedDiagnostics)
        );
        $t->same(
            ['data', 'data', 'src', 'src', 'src'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['attribute'] ?? ''), $embeddedDiagnostics)
        );
        $t->same([2, 3, 5, 4, 4], array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $embeddedDiagnostics));
        $t->same(
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $embeddedDiagnostics),
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $astEmbeddedDiagnostics)
        );
        $t->true(!str_contains($html, '<object'), 'Expected object wrappers to be stripped');
        $t->true(!str_contains($html, '<embed'), 'Expected embed wrappers to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe object/embed URLs to stay diagnostic-only');
        $t->true(!str_contains($html, 'Bad object'), 'Expected unsafe object title to stay hidden with its source');
        $t->true(!str_contains($html, 'Bad embed'), 'Expected unsafe embed title to stay hidden with its source');
    },
    'wraps iframe srcdoc content in inert reviewer provenance before WordPress handoff' => static function (TestRunner $t): void {
        $srcdoc = htmlspecialchars(
            '<base href="./frames/"><article><h2>Embedded packet</h2><a href="note.html">note</a><img src="cover.png" alt="Cover"><a href="javascript:alert(1)">bad</a><script>drop()</script></article>',
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8'
        );
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<iframe srcdoc="' . $srcdoc . '" title="Embedded srcdoc frame" sandbox="allow-scripts allow-same-origin" allow="fullscreen; clipboard-write" referrerpolicy="strict-origin" allowfullscreen></iframe><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/iframe-srcdoc-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<div data-pandoc-iframe-srcdoc="true" title="Embedded srcdoc frame" data-pandoc-iframe-srcdoc-base-url="https://source.example.test/import/posts/frames/" data-pandoc-iframe-sandbox="allow-scripts allow-same-origin" data-pandoc-iframe-allow="fullscreen; clipboard-write" data-pandoc-iframe-referrerpolicy="strict-origin" data-pandoc-iframe-allowfullscreen="true"><article><h2>Embedded packet</h2><a href="https://source.example.test/import/posts/frames/note.html">note</a><img src="https://source.example.test/import/posts/frames/cover.png" alt="Cover"><a>bad</a></article></div><p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Embedded packetnotebadafter', $fragment->textContent());
        $t->same(['a', 'article', 'div', 'h2', 'img', 'p'], $summary['elementNames']);
        $t->same(['base', 'iframe', 'script'], $summary['blockedTags']);
        $t->same(['href', 'srcdoc'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'unsafe-url', 'blocked-tag', 'iframe-srcdoc-review'], $policyDiagnostics);
        $t->same('div', $nodes[0]['name']);
        $t->same([
            'data-pandoc-iframe-srcdoc' => 'true',
            'title' => 'Embedded srcdoc frame',
            'data-pandoc-iframe-srcdoc-base-url' => 'https://source.example.test/import/posts/frames/',
            'data-pandoc-iframe-sandbox' => 'allow-scripts allow-same-origin',
            'data-pandoc-iframe-allow' => 'fullscreen; clipboard-write',
            'data-pandoc-iframe-referrerpolicy' => 'strict-origin',
            'data-pandoc-iframe-allowfullscreen' => 'true',
        ], $nodes[0]['attrs']);
        $t->same('article', $nodes[0]['children'][0]['name']);
        $t->same('https://source.example.test/import/posts/frames/note.html', $nodes[0]['children'][0]['children'][1]['attrs']['href']);
        $t->same('https://source.example.test/import/posts/frames/cover.png', $nodes[0]['children'][0]['children'][2]['attrs']['src']);
        $t->same([], $nodes[0]['children'][0]['children'][3]['attrs']);
        $t->same('p', $nodes[1]['name']);
        $t->same('/migration/iframe-srcdoc-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<iframe'), 'Expected iframe wrapper to be stripped');
        $t->true(!str_contains($html, '<iframe srcdoc'), 'Expected live iframe srcdoc attribute to be stripped');
        $t->true(!str_contains($html, '<base'), 'Expected nested srcdoc base element to be stripped');
        $t->true(!str_contains($html, '<script'), 'Expected nested srcdoc script to be dropped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected nested unsafe srcdoc URL to be stripped');
    },
    'keeps valid empty iframe srcdoc provenance instead of falling back to iframe src' => static function (TestRunner $t): void {
        $srcdoc = htmlspecialchars(
            '<base href="./empty-frame/"><script>drop()</script>',
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8'
        );
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<iframe srcdoc="' . $srcdoc . '" src="./frames/fallback.html" title="Empty srcdoc" sandbox="allow-forms bad-token" referrerpolicy="bad-policy"><p>fallback child</p></iframe><p>after</p>'
            . '<iframe srcdoc="" src="./frames/literal-fallback.html" title="Literal empty srcdoc" sandbox="allow-popups"><p>literal fallback child</p></iframe>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/iframe-empty-srcdoc-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $expected = '<div data-pandoc-iframe-srcdoc="true" title="Empty srcdoc" data-pandoc-iframe-srcdoc-base-url="https://source.example.test/import/posts/empty-frame/" data-pandoc-iframe-sandbox="allow-forms"></div><p>after</p><div data-pandoc-iframe-srcdoc="true" title="Literal empty srcdoc" data-pandoc-iframe-srcdoc-base-url="https://source.example.test/import/posts/post.html" data-pandoc-iframe-sandbox="allow-popups"></div>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('after', $fragment->textContent());
        $t->same(['div', 'p'], $summary['elementNames']);
        $t->same(['base', 'iframe', 'script'], $summary['blockedTags']);
        $t->same(['referrerpolicy', 'sandbox', 'srcdoc'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'unsafe-attribute', 'unsafe-attribute', 'iframe-srcdoc-review', 'blocked-tag', 'iframe-srcdoc-review'], $policyDiagnostics);
        $t->same('div', $nodes[0]['name']);
        $t->same([
            'data-pandoc-iframe-srcdoc' => 'true',
            'title' => 'Empty srcdoc',
            'data-pandoc-iframe-srcdoc-base-url' => 'https://source.example.test/import/posts/empty-frame/',
            'data-pandoc-iframe-sandbox' => 'allow-forms',
        ], $nodes[0]['attrs']);
        $t->same([], $nodes[0]['children']);
        $t->same('p', $nodes[1]['name']);
        $t->same('div', $nodes[2]['name']);
        $t->same([
            'data-pandoc-iframe-srcdoc' => 'true',
            'title' => 'Literal empty srcdoc',
            'data-pandoc-iframe-srcdoc-base-url' => 'https://source.example.test/import/posts/post.html',
            'data-pandoc-iframe-sandbox' => 'allow-popups',
        ], $nodes[2]['attrs']);
        $t->same([], $nodes[2]['children']);
        $t->same('/migration/iframe-empty-srcdoc-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach (['<iframe', '<script', '<base', 'fallback.html', 'literal-fallback.html', 'fallback child', 'literal fallback child', 'bad-policy', 'bad-token', 'data-pandoc-iframe-src='] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected valid iframe srcdoc to suppress fallback or unsafe source content: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to suppress fallback or unsafe source content: ' . $blocked);
        }
    },
    'converts safe iframe sources into reviewer links before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<iframe src="./frames/review.html#packet" title="Source frame"></iframe>'
            . '<iframe src=" h&#9;ttps://frames.example.test/embed?id=42 " title="External frame"></iframe>'
            . '<iframe src="java&#10;script:alert(1)" title="Bad frame"></iframe>'
            . '<iframe title="No source"></iframe>'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/iframe-src-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<a href="https://source.example.test/import/posts/frames/review.html#packet" data-pandoc-iframe-src="true" title="Source frame">Source frame</a>'
            . '<a href="https://frames.example.test/embed?id=42" data-pandoc-iframe-src="true" title="External frame">External frame</a>'
            . '<p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Source frameExternal frameafter', $fragment->textContent());
        $t->same(['a', 'p'], $summary['elementNames']);
        $t->same(['base', 'iframe'], $summary['blockedTags']);
        $t->same(['src'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'normalized-url', 'blocked-tag', 'unsafe-url', 'blocked-tag'], $policyDiagnostics);
        $t->same('a', $nodes[0]['name']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/frames/review.html#packet',
            'data-pandoc-iframe-src' => 'true',
            'title' => 'Source frame',
        ], $nodes[0]['attrs']);
        $t->same('Source frame', $nodes[0]['children'][0]['text']);
        $t->same([
            'href' => 'https://frames.example.test/embed?id=42',
            'data-pandoc-iframe-src' => 'true',
            'title' => 'External frame',
        ], $nodes[1]['attrs']);
        $t->same('External frame', $nodes[1]['children'][0]['text']);
        $t->same('p', $nodes[2]['name']);
        $t->same('/migration/iframe-src-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<iframe'), 'Expected iframe wrappers to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe iframe source URL to be stripped');
        $t->true(!str_contains($html, 'Bad frame'), 'Expected unsafe iframe title to stay hidden with its source');
        $t->true(!str_contains($html, 'No source'), 'Expected sourceless iframe title to stay hidden');
        $t->true(!str_contains($blocks, '<iframe'), 'Expected WordPress blocks to omit iframe wrappers');
    },
    'preserves iframe policy metadata on reviewer links before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<iframe src="./frames/review.html" title="Sandboxed frame" sandbox="allow-scripts allow-same-origin allow-popups allow-scripts" allow="fullscreen *; clipboard-write \'self\'; geolocation https://maps.example.test" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen=""></iframe>'
            . '<iframe src="./frames/bad-policy.html" title="Bad policy" sandbox="allow-forms bad-token" referrerpolicy="bad-policy"></iframe>'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/iframe-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<a href="https://source.example.test/import/posts/frames/review.html" data-pandoc-iframe-src="true" title="Sandboxed frame" data-pandoc-iframe-sandbox="allow-scripts allow-same-origin allow-popups" data-pandoc-iframe-allow="fullscreen *; clipboard-write &#039;self&#039;; geolocation https://maps.example.test" data-pandoc-iframe-referrerpolicy="strict-origin-when-cross-origin" data-pandoc-iframe-allowfullscreen="true">Sandboxed frame</a>'
            . '<a href="https://source.example.test/import/posts/frames/bad-policy.html" data-pandoc-iframe-src="true" title="Bad policy" data-pandoc-iframe-sandbox="allow-forms">Bad policy</a>'
            . '<p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Sandboxed frameBad policyafter', $fragment->textContent());
        $t->same(['a', 'p'], $summary['elementNames']);
        $t->same(['base', 'iframe'], $summary['blockedTags']);
        $t->same(['referrerpolicy', 'sandbox'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'unsafe-attribute', 'unsafe-attribute'], $policyDiagnostics);
        $t->same([
            'href' => 'https://source.example.test/import/posts/frames/review.html',
            'data-pandoc-iframe-src' => 'true',
            'title' => 'Sandboxed frame',
            'data-pandoc-iframe-sandbox' => 'allow-scripts allow-same-origin allow-popups',
            'data-pandoc-iframe-allow' => "fullscreen *; clipboard-write 'self'; geolocation https://maps.example.test",
            'data-pandoc-iframe-referrerpolicy' => 'strict-origin-when-cross-origin',
            'data-pandoc-iframe-allowfullscreen' => 'true',
        ], $nodes[0]['attrs']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/frames/bad-policy.html',
            'data-pandoc-iframe-src' => 'true',
            'title' => 'Bad policy',
            'data-pandoc-iframe-sandbox' => 'allow-forms',
        ], $nodes[1]['attrs']);
        $t->same('/migration/iframe-policy-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<iframe'), 'Expected iframe wrappers to be stripped');
        $t->true(!str_contains($html, 'bad-token'), 'Expected unknown sandbox tokens to be omitted');
        $t->true(!str_contains($html, 'data-pandoc-iframe-referrerpolicy="bad-policy"'), 'Expected unknown referrer policies to be omitted');
        $t->true(!str_contains($blocks, '<iframe'), 'Expected WordPress blocks to omit iframe wrappers');
    },
    'unwraps noscript fallback content while dropping unsafe container before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<noscript><p>Script-disabled fallback <a href="/review">review</a><a href="javascript:alert(1)">bad</a></p>'
            . '<img src="/uploads/fallback.png" alt="Fallback"><script>drop()</script></noscript><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/noscript-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<p>Script-disabled fallback <a href="/review">review</a><a>bad</a></p><img src="/uploads/fallback.png" alt="Fallback"><p>after</p>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Script-disabled fallback reviewbadafter', $fragment->textContent());
        $t->same(['a', 'img', 'p'], $summary['elementNames']);
        $t->same(['noscript', 'script'], $summary['blockedTags']);
        $t->same(['href'], $summary['filteredAttributes']);
        $t->same(3, $summary['diagnostics']);
        $t->same(['blocked-tag', 'unsafe-url', 'blocked-tag'], $fragment->diagnosticCodes());
        $t->same('p', $nodes[0]['name']);
        $t->same('img', $nodes[1]['name']);
        $t->same('p', $nodes[2]['name']);
        $t->same('/migration/noscript-fallback-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<noscript'), 'Expected noscript wrapper to be stripped');
        $t->true(!str_contains($html, '<script'), 'Expected nested active script to be dropped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe noscript fallback URL to be stripped');
        $t->true(!str_contains($blocks, '<noscript'), 'Expected WordPress blocks to omit noscript wrapper');
    },
    'unwraps template inert content while keeping reviewer-visible children' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<template data-source="legacy-hidden"><p>Template fallback <a href="/review">review</a><a href="javascript:alert(1)">bad</a></p>'
            . '<img src="/uploads/template.png" alt="Template"><script>drop()</script></template><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/template-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<p>Template fallback <a href="/review">review</a><a>bad</a></p><img src="/uploads/template.png" alt="Template"><p>after</p>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Template fallback reviewbadafter', $fragment->textContent());
        $t->same(['a', 'img', 'p'], $summary['elementNames']);
        $t->same(['script', 'template'], $summary['blockedTags']);
        $t->same(['href'], $summary['filteredAttributes']);
        $t->same(4, $summary['diagnostics']);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $t->same(['blocked-tag', 'unsafe-url', 'blocked-tag'], $policyDiagnostics);
        $t->same('p', $nodes[0]['name']);
        $t->same('img', $nodes[1]['name']);
        $t->same('p', $nodes[2]['name']);
        $t->same('/migration/template-fallback-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<template'), 'Expected template wrapper to be stripped');
        $t->true(!str_contains($html, '<script'), 'Expected nested active script to be dropped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe template fallback URL to be stripped');
        $t->true(!str_contains($blocks, '<template'), 'Expected WordPress blocks to omit template wrapper');
    },
    'converts declarative shadow templates and slot fallback metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article><template shadowrootmode="open" shadowrootdelegatesfocus shadowrootclonable shadowrootserializable data-pandoc-shadowroot-mode="source-spoof">'
            . '<style>drop</style><p>Shadow <slot name="headline" data-pandoc-slot-name="source-spoof">fallback <a href="./shadow.html">link</a><a href="javascript:alert(1)">bad</a></slot></p></template>'
            . '<template shadowrootmode="closed"><slot>Default fallback</slot></template>'
            . '<template shadowrootmode="bad state"><p>Invalid shadow metadata <slot name="bad&lt;slot">bad slot</slot></p></template></article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/template-shadow-slot-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article><span data-pandoc-shadowroot-mode="open" data-pandoc-shadowroot-delegatesfocus="true" data-pandoc-shadowroot-clonable="true" data-pandoc-shadowroot-serializable="true">Shadow root: open</span>'
            . '<p>Shadow <span data-pandoc-slot-fallback="true" data-pandoc-slot-name="headline">fallback <a href="https://source.example.test/import/posts/shadow.html">link</a><a>bad</a></span></p>'
            . '<span data-pandoc-shadowroot-mode="closed">Shadow root: closed</span><span data-pandoc-slot-fallback="true">Default fallback</span>'
            . '<p>Invalid shadow metadata <span data-pandoc-slot-fallback="true">bad slot</span></p></article>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Shadow root: openShadow fallback linkbadShadow root: closedDefault fallbackInvalid shadow metadata bad slot', $fragment->textContent());
        $t->same(['a', 'article', 'p', 'span'], $summary['elementNames']);
        $t->same(['style', 'template'], $summary['blockedTags']);
        $t->same(['data-pandoc-slot-name', 'href', 'name', 'shadowrootclonable', 'shadowrootdelegatesfocus', 'shadowrootmode', 'shadowrootserializable'], $summary['filteredAttributes']);

        $policyDiagnostics = array_count_values(array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        )));
        $t->same(17, array_sum($policyDiagnostics));
        $t->same(4, $policyDiagnostics['blocked-tag'] ?? 0);
        $t->same(5, $policyDiagnostics['shadowroot-template-review'] ?? 0);
        $t->same(4, $policyDiagnostics['slot-review'] ?? 0);
        $t->same(3, $policyDiagnostics['unsafe-attribute'] ?? 0);
        $t->same(1, $policyDiagnostics['unsafe-url'] ?? 0);
        $t->same('article', $nodes[0]['name']);
        $t->same('span', $nodes[0]['children'][0]['name']);
        $t->same('open', $nodes[0]['children'][0]['attrs']['data-pandoc-shadowroot-mode']);
        $t->same('true', $nodes[0]['children'][0]['attrs']['data-pandoc-shadowroot-delegatesfocus']);
        $t->same('true', $nodes[0]['children'][0]['attrs']['data-pandoc-shadowroot-clonable']);
        $t->same('true', $nodes[0]['children'][0]['attrs']['data-pandoc-shadowroot-serializable']);
        $t->same('span', $nodes[0]['children'][1]['children'][1]['name']);
        $t->same('headline', $nodes[0]['children'][1]['children'][1]['attrs']['data-pandoc-slot-name']);
        $t->same('/migration/template-shadow-slot-review.html', $document->children[0]->attr('part'));
        foreach (['<template', '<slot', '<style', 'source-spoof', 'javascript:', ' shadowrootmode=', ' shadowrootdelegatesfocus', ' shadowrootclonable', ' shadowrootserializable', 'name="bad&lt;slot"', 'data-pandoc-slot-name="source-spoof"'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected shadow template sanitizer to remove blocked source content: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to remove blocked source content: ' . $blocked);
        }
    },
    'converts slot assignment attributes into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article><section slot="main" data-pandoc-slot-assignment="source-spoof">Assigned</section>'
            . '<p slot="bad slot">Invalid</p><span slot="">Empty</span>'
            . '<x-review slot="panel"><b>Custom</b></x-review>'
            . '<slot name="headline" slot="shadow">Fallback</slot></article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/slot-assignment-handoff.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article><section data-pandoc-slot-assignment="main">Assigned</section>'
            . '<p>Invalid</p><span>Empty</span>'
            . '<span data-pandoc-slot-assignment="panel" data-pandoc-custom-element="x-review"><b>Custom</b></span>'
            . '<span data-pandoc-slot-assignment="shadow" data-pandoc-slot-fallback="true" data-pandoc-slot-name="headline">Fallback</span></article>';
        $policyDiagnostics = array_count_values(array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        )));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('AssignedInvalidEmptyCustomFallback', $fragment->textContent());
        $t->same(['article', 'b', 'p', 'section', 'span'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([
            'data-pandoc-slot-assignment',
            'name',
            'slot',
        ], $summary['filteredAttributes']);
        $t->same(5, $policyDiagnostics['slot-review'] ?? 0);
        $t->same(3, $policyDiagnostics['unsafe-attribute'] ?? 0);
        $t->same(1, $policyDiagnostics['custom-element-review'] ?? 0);
        $t->same(['data-pandoc-slot-assignment' => 'main'], $nodes[0]['children'][0]['attrs']);
        $t->same([], $nodes[0]['children'][1]['attrs']);
        $t->same([], $nodes[0]['children'][2]['attrs']);
        $t->same([
            'data-pandoc-slot-assignment' => 'panel',
            'data-pandoc-custom-element' => 'x-review',
        ], $nodes[0]['children'][3]['attrs']);
        $t->same([
            'data-pandoc-slot-assignment' => 'shadow',
            'data-pandoc-slot-fallback' => 'true',
            'data-pandoc-slot-name' => 'headline',
        ], $nodes[0]['children'][4]['attrs']);
        $t->same('/migration/slot-assignment-handoff.html', $document->children[0]->attr('part'));
        foreach ([' slot=', '<slot', '<x-review', 'source-spoof', 'bad slot', 'data-pandoc-slot-assignment="source-spoof"'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected live slot assignment metadata to be stripped or converted: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to omit live slot assignment metadata: ' . $blocked);
        }
    },
    'preserves declarative shadow root accessibility metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article>'
            . '<template shadowrootmode="open" aria-label=" Review card " aria-describedby="caption shadow-note" aria-description=" Hidden panel " data-pandoc-shadowroot-aria-label="source-spoof">'
            . '<p id="caption">Shadow fallback</p><p id="shadow-note">Notes</p></template>'
            . '<template shadowrootmode="closed" aria-labelledby="headline headline"><h2 id="headline">Headline</h2></template>'
            . '<template shadowrootmode="open" aria-label="bad&lt;tag" aria-describedby="bad&lt;id"><p>Invalid shadow label</p></template>'
            . '</article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/template-shadow-accessibility-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article>'
            . '<span data-pandoc-shadowroot-mode="open" data-pandoc-shadowroot-aria-label="Review card" data-pandoc-shadowroot-aria-description="Hidden panel" data-pandoc-shadowroot-aria-describedby="caption shadow-note">Shadow root: open</span>'
            . '<p id="caption">Shadow fallback</p><p id="shadow-note">Notes</p>'
            . '<span data-pandoc-shadowroot-mode="closed" data-pandoc-shadowroot-aria-labelledby="headline">Shadow root: closed</span><h2 id="headline">Headline</h2>'
            . '<span data-pandoc-shadowroot-mode="open">Shadow root: open</span><p>Invalid shadow label</p>'
            . '</article>';
        $policyDiagnostics = array_count_values(array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        )));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Shadow root: openShadow fallbackNotesShadow root: closedHeadlineShadow root: openInvalid shadow label', $fragment->textContent());
        $t->same(['article', 'h2', 'p', 'span'], $summary['elementNames']);
        $t->same(['template'], $summary['blockedTags']);
        $t->same(['aria-describedby', 'aria-description', 'aria-label', 'aria-labelledby', 'shadowrootmode'], $summary['filteredAttributes']);
        $t->same(12, array_sum($policyDiagnostics));
        $t->same(3, $policyDiagnostics['blocked-tag'] ?? 0);
        $t->same(7, $policyDiagnostics['shadowroot-template-review'] ?? 0);
        $t->same(2, $policyDiagnostics['unsafe-attribute'] ?? 0);
        $t->same('article', $nodes[0]['name']);
        $t->same([
            'data-pandoc-shadowroot-mode' => 'open',
            'data-pandoc-shadowroot-aria-label' => 'Review card',
            'data-pandoc-shadowroot-aria-description' => 'Hidden panel',
            'data-pandoc-shadowroot-aria-describedby' => 'caption shadow-note',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same(['id' => 'caption'], $nodes[0]['children'][1]['attrs']);
        $t->same([
            'data-pandoc-shadowroot-mode' => 'closed',
            'data-pandoc-shadowroot-aria-labelledby' => 'headline',
        ], $nodes[0]['children'][3]['attrs']);
        $t->same(['data-pandoc-shadowroot-mode' => 'open'], $nodes[0]['children'][5]['attrs']);
        $t->same('/migration/template-shadow-accessibility-review.html', $document->children[0]->attr('part'));
        foreach (['<template', ' aria-label=', ' aria-describedby=', ' aria-labelledby=', ' aria-description=', 'source-spoof', 'bad&lt;id', 'bad&lt;tag'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected shadow accessibility sanitizer to remove blocked source content: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to remove blocked source content: ' . $blocked);
        }
    },
    'filters mixed unsafe srcset candidates before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<p>'
            . '<img src="https://cdn.example.test/cover.png" srcset="https://cdn.example.test/cover.png 1x, /media/cover@2x.png 2x, ./cover-wide.webp 640w" alt="Safe">'
            . '<img src="https://cdn.example.test/mixed.png" srcset="https://cdn.example.test/mixed.png 1x, javascript:alert(1) 2x" alt="Mixed">'
            . '<img src="/media/fallback.png" srcset="/media/hero.webp 1x, mailto:review@example.test 2x" alt="Fallback">'
            . '</p>'
        );
        $summary = $fragment->summary();
        $html = $fragment->serialize();

        $t->contains('<img src="https://cdn.example.test/cover.png" srcset="https://cdn.example.test/cover.png 1x, /media/cover@2x.png 2x, ./cover-wide.webp 640w" alt="Safe">', $html);
        $t->contains('<img src="https://cdn.example.test/mixed.png" srcset="https://cdn.example.test/mixed.png 1x" alt="Mixed">', $html);
        $t->contains('<img src="/media/fallback.png" srcset="/media/hero.webp 1x" alt="Fallback">', $html);
        $t->same(['srcset'], $summary['filteredAttributes']);
        $t->same(['unsafe-url', 'unsafe-url'], $fragment->diagnosticCodes());
    },
    'normalizes srcset width and density descriptors while dropping invalid candidates' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<p>'
            . '<img src="/media/hero.png" srcset=" ./hero-0640.webp 0640w, https://cdn.example.test/hero@2x.webp 02.00x, /media/fallback.jpg, javascript:alert(1) 4x, /media/zero.webp 0w, /media/mixed.webp 1x 640w, /media/uppercase.webp 1.50X " alt="Hero">'
            . '</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();

        $expectedSrcset = './hero-0640.webp 640w, https://cdn.example.test/hero@2x.webp 2x, /media/fallback.jpg, /media/uppercase.webp 1.5x';
        $t->contains('<img src="/media/hero.png" srcset="' . $expectedSrcset . '" alt="Hero">', $html);
        $t->same($expectedSrcset, $nodes[0]['children'][0]['attrs']['srcset']);
        $t->same(['srcset'], $summary['filteredAttributes']);
        $t->same(3, $summary['diagnostics']);
        $t->same(['unsafe-url', 'invalid-srcset-descriptor', 'invalid-srcset-descriptor'], $fragment->diagnosticCodes());
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe srcset URL candidate to be removed');
        $t->true(!str_contains($html, 'zero.webp'), 'Expected zero-width srcset candidate to be removed');
        $t->true(!str_contains($html, 'mixed.webp'), 'Expected mixed descriptor srcset candidate to be removed');
    },
    'normalizes control-separated srcset candidate URLs before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<picture data-review="legacy-responsive">'
            . '<source srcset=" h&#10;ttps://cdn.example.test/hero.webp?x=1&amp;y=2 02.00x, java&#10;script:alert(1) 3x, ./hero&#13;-wide.webp 0640w" type="image/webp">'
            . '<img src="cover.jpg" srcset="h&#9;ttps://cdn.example.test/cover.jpg 1x, /media/fallback.jpg" alt="Cover">'
            . '</picture>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/control-srcset-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<picture data-review="legacy-responsive">'
            . '<source srcset="https://cdn.example.test/hero.webp?x=1&amp;y=2 2x, https://source.example.test/import/posts/hero-wide.webp 640w" type="image/webp">'
            . '<img src="https://source.example.test/import/posts/cover.jpg" srcset="https://cdn.example.test/cover.jpg 1x, https://source.example.test/media/fallback.jpg" alt="Cover">'
            . '</picture>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same(['img', 'picture', 'source'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['srcset'], $summary['filteredAttributes']);
        $t->same(['normalized-url', 'unsafe-url', 'normalized-url', 'normalized-url'], $policyDiagnostics);
        $t->same('https://cdn.example.test/hero.webp?x=1&y=2 2x, https://source.example.test/import/posts/hero-wide.webp 640w', $nodes[0]['children'][0]['attrs']['srcset']);
        $t->same('https://cdn.example.test/cover.jpg 1x, https://source.example.test/media/fallback.jpg', $nodes[0]['children'][1]['attrs']['srcset']);
        $t->same('/migration/control-srcset-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, "h\n"), 'Expected control-separated source scheme to be canonicalized');
        $t->true(!str_contains($html, "h\t"), 'Expected tab-separated source scheme to be canonicalized');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe srcset candidate to be stripped');
        $t->true(!str_contains($html, '&#13;'), 'Expected encoded control characters to be removed from candidate URLs');
    },
    'preserves safe data image srcset candidates without splitting payload commas' => static function (TestRunner $t): void {
        $pngData = 'data:image/png;base64,iVBORw0KGgo=';
        $webpData = 'data:image/webp;base64,UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAQAcJaQAA3AA/vuUAAA=';
        $fragment = Html5DomFragment::fromHtml(
            '<picture data-review="inline-responsive">'
            . '<source srcset="' . $pngData . ' 1x, data:text/html;base64,PHNjcmlwdD4= 2x, ./fallback.webp 640w" type="image/png">'
            . '<img src="/media/fallback.png" srcset="' . $webpData . ' 2x, data:image/svg+xml;base64,PHN2Zz48L3N2Zz4= 3x" alt="Inline">'
            . '</picture>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/data-srcset-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<picture data-review="inline-responsive">'
            . '<source srcset="' . $pngData . ' 1x, https://source.example.test/import/posts/fallback.webp 640w" type="image/png">'
            . '<img src="https://source.example.test/media/fallback.png" srcset="' . $webpData . ' 2x" alt="Inline">'
            . '</picture>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $source = $nodes[0]['children'][0];
        $image = $nodes[0]['children'][1];

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same(['img', 'picture', 'source'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['srcset'], $summary['filteredAttributes']);
        $t->same(['unsafe-url', 'unsafe-url'], $policyDiagnostics);
        $t->same($pngData . ' 1x, https://source.example.test/import/posts/fallback.webp 640w', $source['attrs']['srcset']);
        $t->same($webpData . ' 2x', $image['attrs']['srcset']);
        $t->same('/migration/data-srcset-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, 'data:text/html'), 'Expected non-image data URL candidates to be stripped');
        $t->true(!str_contains($html, 'data:image/svg+xml'), 'Expected SVG data URL candidates to be stripped');
        $t->true(str_contains($html, $pngData . ' 1x'), 'Expected data image payload to stay attached to its data URL header');
    },
    'preserves safe raster data image src attributes before WordPress handoff' => static function (TestRunner $t): void {
        $pngData = 'data:image/png;base64,iVBORw0KGgo=';
        $fragment = Html5DomFragment::fromHtml(
            '<figure data-review="inline-image">'
            . '<img src="' . $pngData . '" alt="Inline raster">'
            . '<img src="data:text/html;base64,PHNjcmlwdD4=" alt="HTML data">'
            . '<img src="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=" alt="SVG data">'
            . '<a href="' . $pngData . '">linked data image</a>'
            . '<img src="/media/fallback.png" alt="Fallback">'
            . '</figure>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/data-image-src-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<figure data-review="inline-image">'
            . '<img src="' . $pngData . '" alt="Inline raster">'
            . '<span data-pandoc-image-alt-fallback="true">HTML data</span>'
            . '<span data-pandoc-image-alt-fallback="true">SVG data</span><a>linked data image</a>'
            . '<img src="https://source.example.test/media/fallback.png" alt="Fallback">'
            . '</figure>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same(['a', 'figure', 'img', 'span'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['href', 'src'], $summary['filteredAttributes']);
        $t->same(['unsafe-url', 'image-alt-fallback', 'unsafe-url', 'image-alt-fallback', 'unsafe-url'], $policyDiagnostics);
        $t->same($pngData, $nodes[0]['children'][0]['attrs']['src']);
        $t->same('HTML data', $nodes[0]['children'][1]['children'][0]['text']);
        $t->same('SVG data', $nodes[0]['children'][2]['children'][0]['text']);
        $t->same([], $nodes[0]['children'][3]['attrs']);
        $t->same('https://source.example.test/media/fallback.png', $nodes[0]['children'][4]['attrs']['src']);
        $t->same('/migration/data-image-src-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(str_contains($html, $pngData), 'Expected safe raster data image source to survive');
        $t->true(!str_contains($html, 'data:text/html'), 'Expected active data payloads to be stripped from img src');
        $t->true(!str_contains($html, 'data:image/svg+xml'), 'Expected SVG data images to be stripped from img src');
        $t->true(!str_contains($html, '<a href="data:'), 'Expected data URLs to remain blocked for navigational links');
    },
    'converts html5 media booleans into inert reviewer metadata' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<details open="open"><summary>Review packet</summary>'
            . '<video controls="" muted playsinline loop poster="/media/cover.jpg"><source src="/media/review.mp4" type="video/mp4"></video>'
            . '</details>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/media-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<details open><summary>Review packet</summary><video data-pandoc-media-controls="true" data-pandoc-media-muted="true" data-pandoc-media-playsinline="true" data-pandoc-media-loop="true" poster="/media/cover.jpg"><source src="/media/review.mp4" type="video/mp4"></video></details>';
        $t->same($expected, $fragment->serialize());
        $t->contains($expected, $blocks);
        $t->same(['details', 'source', 'summary', 'video'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['controls', 'loop', 'muted', 'playsinline'], $summary['filteredAttributes']);
        $t->same(['open' => 'open'], $nodes[0]['attrs']);
        $t->same([
            'data-pandoc-media-controls' => 'true',
            'data-pandoc-media-muted' => 'true',
            'data-pandoc-media-playsinline' => 'true',
            'data-pandoc-media-loop' => 'true',
            'poster' => '/media/cover.jpg',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same('/migration/media-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($fragment->serialize(), 'open="open"'), 'Expected open to serialize as an HTML5 boolean attribute');
        $t->true(!str_contains($fragment->serialize(), ' controls'), 'Expected live media controls to move into inert metadata');
        $t->true(!str_contains($fragment->serialize(), ' muted'), 'Expected live media muted state to move into inert metadata');
    },
    'normalizes media track caption metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<video controls poster="./cover.jpg">'
            . '<source src="./review.mp4" type="video/mp4">'
            . '<track src="./captions/en.vtt" kind="CAPTIONS" srclang="EN-us" label=" English captions " default="default">'
            . '<track src="java&#10;script:alert(1)" kind="metadata" srclang="x-review" label="Bad source">'
            . '<track src="./captions/bad.vtt" kind="transcript" srclang="bad<tag>" label="Bad metadata">'
            . '</video><audio controls><track src="./audio/es.vtt" kind="subtitles" srclang="es-419" label="Spanish subtitles"></audio>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/media-track-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<video data-pandoc-media-controls="true" poster="https://source.example.test/import/posts/cover.jpg">'
            . '<source src="https://source.example.test/import/posts/review.mp4" type="video/mp4">'
            . '<track src="https://source.example.test/import/posts/captions/en.vtt" kind="captions" srclang="en-US" label="English captions" default>'
            . '<track kind="metadata" srclang="x-review" label="Bad source">'
            . '<track src="https://source.example.test/import/posts/captions/bad.vtt" label="Bad metadata">'
            . '</video><audio data-pandoc-media-controls="true"><track src="https://source.example.test/import/posts/audio/es.vtt" kind="subtitles" srclang="es-419" label="Spanish subtitles"></audio>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('', $fragment->textContent());
        $t->same(['audio', 'source', 'track', 'video'], $summary['elementNames']);
        $t->same(['base'], $summary['blockedTags']);
        $t->same(['controls', 'kind', 'src', 'srclang'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'media-resource-policy-review', 'unsafe-url', 'unsafe-attribute', 'unsafe-attribute', 'media-resource-policy-review'], $policyDiagnostics);
        $t->same('video', $nodes[0]['name']);
        $t->same([
            'data-pandoc-media-controls' => 'true',
            'poster' => 'https://source.example.test/import/posts/cover.jpg',
        ], $nodes[0]['attrs']);
        $t->same([
            'src' => 'https://source.example.test/import/posts/captions/en.vtt',
            'kind' => 'captions',
            'srclang' => 'en-US',
            'label' => 'English captions',
            'default' => '',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same([
            'kind' => 'metadata',
            'srclang' => 'x-review',
            'label' => 'Bad source',
        ], $nodes[0]['children'][2]['attrs']);
        $t->same([
            'src' => 'https://source.example.test/import/posts/captions/bad.vtt',
            'label' => 'Bad metadata',
        ], $nodes[0]['children'][3]['attrs']);
        $t->same('audio', $nodes[1]['name']);
        $t->same(['data-pandoc-media-controls' => 'true'], $nodes[1]['attrs']);
        $t->same([
            'src' => 'https://source.example.test/import/posts/audio/es.vtt',
            'kind' => 'subtitles',
            'srclang' => 'es-419',
            'label' => 'Spanish subtitles',
        ], $nodes[1]['children'][0]['attrs']);
        $t->same('/migration/media-track-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, 'default="default"'), 'Expected default to serialize as an HTML5 boolean attribute');
        $t->true(!str_contains($html, 'CAPTIONS'), 'Expected track kind to normalize to the HTML token form');
        $t->true(!str_contains($html, 'EN-us'), 'Expected track language tags to be canonicalized');
        $t->true(!str_contains($html, 'transcript'), 'Expected non-HTML track kind values to be stripped');
        $t->true(!str_contains($html, 'bad&lt;tag'), 'Expected malformed track language tags to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe caption source URL to be stripped');
    },
    'converts audio and video resource policy into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<article>'
            . '<video autoplay controls loop muted playsinline preload=" Metadata " crossorigin="" controlslist="nodownload nofullscreen nodownload" disablepictureinpicture disableremoteplayback width="0640" height="0360" poster="./poster.jpg" data-pandoc-media-controls="source-spoof"><source src="./intro.mp4" type="video/mp4">Trailer fallback</video>'
            . '<audio controls preload="none" crossorigin="use-credentials" src="./audio.mp3">Audio fallback</audio>'
            . '<video preload="soon" crossorigin="credentialed" controlslist="nodownload bad-token" width="-1" height="bad" src="./bad.mp4">Bad metadata</video>'
            . '</article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/media-resource-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $mediaDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static function (array $diagnostic): bool {
                $code = (string) ($diagnostic['code'] ?? '');
                $reason = (string) ($diagnostic['reason'] ?? '');

                return $code === 'media-resource-policy-review'
                    || ($code === 'unsafe-attribute' && $reason === 'invalid-media-resource-policy-metadata');
            }
        ));

        $expected = '<article>'
            . '<video data-pandoc-media-autoplay="true" data-pandoc-media-controls="true" data-pandoc-media-loop="true" data-pandoc-media-muted="true" data-pandoc-media-playsinline="true" data-pandoc-media-preload="metadata" data-pandoc-media-crossorigin="anonymous" data-pandoc-media-controlslist="nodownload nofullscreen" data-pandoc-media-disablepictureinpicture="true" data-pandoc-media-disableremoteplayback="true" data-pandoc-media-width="640" data-pandoc-media-height="360" poster="https://source.example.test/import/posts/poster.jpg"><source src="https://source.example.test/import/posts/intro.mp4" type="video/mp4">Trailer fallback</video>'
            . '<audio data-pandoc-media-controls="true" data-pandoc-media-preload="none" data-pandoc-media-crossorigin="use-credentials" src="https://source.example.test/import/posts/audio.mp3">Audio fallback</audio>'
            . '<video src="https://source.example.test/import/posts/bad.mp4">Bad metadata</video>'
            . '</article>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Trailer fallbackAudio fallbackBad metadata', $fragment->textContent());
        $t->same(['article', 'audio', 'source', 'video'], $summary['elementNames']);
        $t->same(['base'], $summary['blockedTags']);
        $t->same([
            'autoplay',
            'controls',
            'controlslist',
            'crossorigin',
            'data-pandoc-media-controls',
            'disablepictureinpicture',
            'disableremoteplayback',
            'height',
            'loop',
            'muted',
            'playsinline',
            'preload',
            'width',
        ], $summary['filteredAttributes']);
        $t->same(27, $summary['diagnostics']);
        $t->same(22, count($policyDiagnostics));
        $t->same(20, count($mediaDiagnostics));
        $t->same('article', $nodes[0]['name']);
        $t->same([
            'data-pandoc-media-autoplay' => 'true',
            'data-pandoc-media-controls' => 'true',
            'data-pandoc-media-loop' => 'true',
            'data-pandoc-media-muted' => 'true',
            'data-pandoc-media-playsinline' => 'true',
            'data-pandoc-media-preload' => 'metadata',
            'data-pandoc-media-crossorigin' => 'anonymous',
            'data-pandoc-media-controlslist' => 'nodownload nofullscreen',
            'data-pandoc-media-disablepictureinpicture' => 'true',
            'data-pandoc-media-disableremoteplayback' => 'true',
            'data-pandoc-media-width' => '640',
            'data-pandoc-media-height' => '360',
            'poster' => 'https://source.example.test/import/posts/poster.jpg',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same([
            'src' => 'https://source.example.test/import/posts/intro.mp4',
            'type' => 'video/mp4',
        ], $nodes[0]['children'][0]['children'][0]['attrs']);
        $t->same([
            'data-pandoc-media-controls' => 'true',
            'data-pandoc-media-preload' => 'none',
            'data-pandoc-media-crossorigin' => 'use-credentials',
            'src' => 'https://source.example.test/import/posts/audio.mp3',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same(['src' => 'https://source.example.test/import/posts/bad.mp4'], $nodes[0]['children'][2]['attrs']);
        $t->same('/migration/media-resource-policy-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->same(
            ['autoplay', 'controls', 'loop', 'muted', 'playsinline', 'preload', 'crossorigin', 'controlslist', 'disablepictureinpicture', 'disableremoteplayback', 'width', 'height', 'controls', 'preload', 'crossorigin', 'preload', 'crossorigin', 'controlslist', 'width', 'height'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['attribute'] ?? ''), $mediaDiagnostics)
        );
        $t->same(
            [
                'media-resource-policy-review',
                'media-resource-policy-review',
                'media-resource-policy-review',
                'media-resource-policy-review',
                'media-resource-policy-review',
                'media-resource-policy-review',
                'media-resource-policy-review',
                'media-resource-policy-review',
                'media-resource-policy-review',
                'media-resource-policy-review',
                'media-resource-policy-review',
                'media-resource-policy-review',
                'media-resource-policy-review',
                'media-resource-policy-review',
                'media-resource-policy-review',
                'unsafe-attribute',
                'unsafe-attribute',
                'unsafe-attribute',
                'unsafe-attribute',
                'unsafe-attribute',
            ],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $mediaDiagnostics)
        );
        foreach ($mediaDiagnostics as $diagnostic) {
            $t->true(($diagnostic['line'] ?? 0) > 0, 'Expected media resource policy diagnostics to include source line metadata');
        }
        foreach ([' autoplay', ' controls', ' loop', ' muted', ' playsinline', ' preload=', ' crossorigin=', ' controlslist=', ' disablepictureinpicture', ' disableremoteplayback', ' width=', ' height=', 'source-spoof', 'credentialed', 'bad-token', 'soon'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected live or unsafe media policy source content to stay out of review HTML: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected live or unsafe media policy source content to stay out of WordPress blocks: ' . $blocked);
        }
    },
    'converts live editing attributes into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article contenteditable="" data-pandoc-contenteditable-state="source-spoof">'
            . '<p contenteditable="plaintext-only" spellcheck="false" draggable="true">Editable <a href="./note.html" draggable="maybe">note</a></p>'
            . '<section contenteditable="false" spellcheck="default" draggable="auto">Locked</section>'
            . '<aside contenteditable="inherit" spellcheck="maybe" draggable="bad">Invalid</aside>'
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/editing-state-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article data-pandoc-contenteditable-state="true">'
            . '<p data-pandoc-contenteditable-state="plaintext-only" data-pandoc-spellcheck-state="false" data-pandoc-draggable-state="true">Editable <a href="https://source.example.test/import/posts/note.html">note</a></p>'
            . '<section data-pandoc-contenteditable-state="false" data-pandoc-spellcheck-state="default" data-pandoc-draggable-state="auto">Locked</section>'
            . '<aside>Invalid</aside>'
            . '</article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Editable noteLockedInvalid', $fragment->textContent());
        $t->same(['a', 'article', 'aside', 'p', 'section'], $summary['elementNames']);
        $t->same(['contenteditable', 'data-pandoc-contenteditable-state', 'draggable', 'spellcheck'], $summary['filteredAttributes']);
        $t->same([
            'editing-state-review',
            'unsafe-attribute',
            'editing-state-review',
            'editing-state-review',
            'editing-state-review',
            'unsafe-attribute',
            'editing-state-review',
            'editing-state-review',
            'editing-state-review',
            'unsafe-attribute',
            'unsafe-attribute',
            'unsafe-attribute',
        ], $policyDiagnostics);
        $t->same(['data-pandoc-contenteditable-state' => 'true'], $nodes[0]['attrs']);
        $t->same([
            'data-pandoc-contenteditable-state' => 'plaintext-only',
            'data-pandoc-spellcheck-state' => 'false',
            'data-pandoc-draggable-state' => 'true',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same('https://source.example.test/import/posts/note.html', $nodes[0]['children'][0]['children'][1]['attrs']['href']);
        $t->same([
            'data-pandoc-contenteditable-state' => 'false',
            'data-pandoc-spellcheck-state' => 'default',
            'data-pandoc-draggable-state' => 'auto',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same([], $nodes[0]['children'][2]['attrs']);
        $t->same('/migration/editing-state-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach ([' contenteditable', ' spellcheck', ' draggable', 'source-spoof', 'maybe', 'inherit'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected live editing state to be stripped or converted: ' . $blocked);
        }
    },
    'marks closed details disclosure content for WordPress review handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<details data-pandoc-details-state="source-spoof"><summary onclick="alert(1)">Migration notes</summary>'
            . '<p>Hidden <a href="./packet.html">packet</a><a href="java&#10;script:alert(1)">bad</a></p></details>'
            . '<details open><summary>Open notes</summary><p>Visible</p></details>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/details-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<details data-pandoc-details-state="closed"><summary data-pandoc-details-summary="true">Migration notes</summary>'
            . '<p>Hidden <a href="https://source.example.test/import/posts/packet.html">packet</a><a>bad</a></p></details>'
            . '<details open><summary>Open notes</summary><p>Visible</p></details>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Migration notesHidden packetbadOpen notesVisible', $fragment->textContent());
        $t->same(['a', 'details', 'p', 'summary'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['data-pandoc-details-state', 'href', 'onclick'], $summary['filteredAttributes']);
        $t->same(['unsafe-attribute', 'unsafe-attribute', 'unsafe-url', 'closed-details-review'], $policyDiagnostics);
        $t->same('details', $nodes[0]['name']);
        $t->same(['data-pandoc-details-state' => 'closed'], $nodes[0]['attrs']);
        $t->same('summary', $nodes[0]['children'][0]['name']);
        $t->same(['data-pandoc-details-summary' => 'true'], $nodes[0]['children'][0]['attrs']);
        $t->same('https://source.example.test/import/posts/packet.html', $nodes[0]['children'][1]['children'][1]['attrs']['href']);
        $t->same([], $nodes[0]['children'][1]['children'][2]['attrs']);
        $t->same(['open' => ''], $nodes[1]['attrs']);
        $t->same([], $nodes[1]['children'][0]['attrs']);
        $t->same('/migration/details-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, 'source-spoof'), 'Expected source-owned data-pandoc disclosure state to be stripped');
        $t->true(!str_contains($html, 'onclick='), 'Expected active summary handlers to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe links inside closed details to be stripped');
    },
    'converts dialog states into inert reviewer containers before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<dialog data-pandoc-dialog-state="source-spoof" aria-label="Migration notice"><p>Closed <a href="./closed.html">packet</a><a href="java&#10;script:alert(1)">bad</a></p></dialog>'
            . '<dialog open class="modal" onclick="alert(1)"><h2>Open review</h2><p>Visible overlay content</p></dialog>'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/dialog-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<div data-pandoc-aria-label="Migration notice" data-pandoc-dialog-state="closed"><p>Closed <a href="https://source.example.test/import/posts/closed.html">packet</a><a>bad</a></p></div>'
            . '<div class="modal" data-pandoc-dialog-state="open"><h2>Open review</h2><p>Visible overlay content</p></div><p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Closed packetbadOpen reviewVisible overlay contentafter', $fragment->textContent());
        $t->same(['a', 'div', 'h2', 'p'], $summary['elementNames']);
        $t->same(['base'], $summary['blockedTags']);
        $t->same(['aria-label', 'data-pandoc-dialog-state', 'href', 'onclick', 'open'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'unsafe-attribute', 'aria-metadata-review', 'unsafe-url', 'dialog-review', 'unsafe-attribute', 'dialog-review'], $policyDiagnostics);
        $t->same('div', $nodes[0]['name']);
        $t->same([
            'data-pandoc-aria-label' => 'Migration notice',
            'data-pandoc-dialog-state' => 'closed',
        ], $nodes[0]['attrs']);
        $t->same('https://source.example.test/import/posts/closed.html', $nodes[0]['children'][0]['children'][1]['attrs']['href']);
        $t->same([], $nodes[0]['children'][0]['children'][2]['attrs']);
        $t->same('div', $nodes[1]['name']);
        $t->same([
            'class' => 'modal',
            'data-pandoc-dialog-state' => 'open',
        ], $nodes[1]['attrs']);
        $t->same('p', $nodes[2]['name']);
        $t->same('/migration/dialog-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<dialog'), 'Expected dialog wrappers to become inert reviewer divs');
        $t->true(!str_contains($html, ' open'), 'Expected dialog open state to move into inert metadata');
        $t->true(!str_contains($html, 'source-spoof'), 'Expected source-owned dialog metadata to be stripped');
        $t->true(!str_contains($html, 'onclick='), 'Expected dialog event handlers to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe links inside dialog content to be stripped');
        $t->true(!str_contains($blocks, '<dialog'), 'Expected WordPress blocks to omit live dialog elements');
    },
    'marks hidden and inert content as visible reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<section hidden data-pandoc-hidden-state="source-spoof"><h2>Hidden note</h2>'
            . '<p>Source <a href="./hidden.html">packet</a><a href="java&#10;script:alert(1)">bad</a></p></section>'
            . '<aside hidden="until-found" inert><p>Search reveal note</p></aside><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/hidden-inert-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<section data-pandoc-hidden-state="hidden"><h2>Hidden note</h2>'
            . '<p>Source <a href="https://source.example.test/import/posts/hidden.html">packet</a><a>bad</a></p></section>'
            . '<aside data-pandoc-hidden-state="until-found" data-pandoc-inert-state="true"><p>Search reveal note</p></aside><p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Hidden noteSource packetbadSearch reveal noteafter', $fragment->textContent());
        $t->same(['a', 'aside', 'h2', 'p', 'section'], $summary['elementNames']);
        $t->same(['base'], $summary['blockedTags']);
        $t->same(['data-pandoc-hidden-state', 'hidden', 'href', 'inert'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'unsafe-attribute', 'unsafe-url', 'hidden-content-review', 'hidden-content-review', 'inert-content-review'], $policyDiagnostics);
        $t->same('section', $nodes[0]['name']);
        $t->same(['data-pandoc-hidden-state' => 'hidden'], $nodes[0]['attrs']);
        $t->same('https://source.example.test/import/posts/hidden.html', $nodes[0]['children'][1]['children'][1]['attrs']['href']);
        $t->same([], $nodes[0]['children'][1]['children'][2]['attrs']);
        $t->same('aside', $nodes[1]['name']);
        $t->same([
            'data-pandoc-hidden-state' => 'until-found',
            'data-pandoc-inert-state' => 'true',
        ], $nodes[1]['attrs']);
        $t->same('p', $nodes[2]['name']);
        $t->same('/migration/hidden-inert-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, ' hidden'), 'Expected hidden source attributes to be replaced with visible review metadata');
        $t->true(!str_contains($html, ' inert'), 'Expected inert source attributes to be replaced with visible review metadata');
        $t->true(!str_contains($html, 'source-spoof'), 'Expected source-owned data-pandoc hidden metadata to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe links inside hidden content to be stripped');
    },
    'adds source line metadata to html review state diagnostics' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            "<article>\n"
            . "<section hidden><p>Hidden packet</p></section>\n"
            . "<aside inert><p>Inactive packet</p></aside>\n"
            . "<details><summary>Closed notes</summary><p>Review body</p></details>\n"
            . "<dialog open><p>Dialog note</p></dialog>\n"
            . '</article>'
        );
        $diagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => in_array((string) ($diagnostic['code'] ?? ''), [
                'hidden-content-review',
                'inert-content-review',
                'closed-details-review',
                'dialog-review',
            ], true)
        ));

        $t->same([
            'hidden-content-review',
            'inert-content-review',
            'closed-details-review',
            'dialog-review',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $diagnostics));
        $t->same([2, 3, 4, 5], array_map(static fn (array $diagnostic): int => (int) ($diagnostic['line'] ?? 0), $diagnostics));
        $t->same(['section', 'aside', 'details', 'dialog'], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['tag'] ?? ''), $diagnostics));
        $t->same('closed', $fragment->nodes()[0]['children'][5]['attrs']['data-pandoc-details-state'] ?? null);
        $t->same('open', $fragment->nodes()[0]['children'][7]['attrs']['data-pandoc-dialog-state'] ?? null);
    },
    'adds source line metadata to global html metadata diagnostics before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            "<article lang=\"en-US\" dir=\"rtl\">\n"
            . "<section contenteditable=\"bad state\" draggable=\"true\" spellcheck=\"false\" translate=\"no\" tabindex=\"not-int\" accesskey=\"a bad<tag>\" autofocus>Focus</section>\n"
            . "<aside popover=\"bad state\" dir=\"sideways\">Popover</aside>\n"
            . "<p lang=\"bad<tag>\" translate=\"maybe\" tabindex=\"5\" accesskey=\"z\">Text</p>\n"
            . '</article>'
        );
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/global-html-metadata-lines-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $globalMetadataDiagnostic = static fn (array $diagnostic): bool => in_array((string) ($diagnostic['code'] ?? ''), [
            'language-direction-review',
            'editing-state-review',
            'translation-state-review',
            'focus-navigation-review',
            'popover-review',
            'unsafe-attribute',
        ], true);
        $diagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            $globalMetadataDiagnostic
        ));
        $astDiagnostics = array_values(array_filter(
            $document->children[0]->attr('diagnostics'),
            $globalMetadataDiagnostic
        ));

        $expected = '<article data-pandoc-lang="en-US" data-pandoc-dir="rtl">' . "\n"
            . '<section data-pandoc-draggable-state="true" data-pandoc-spellcheck-state="false" data-pandoc-translate-state="no" data-pandoc-accesskey="a" data-pandoc-autofocus-state="true">Focus</section>' . "\n"
            . '<aside data-pandoc-popover-state="manual">Popover</aside>' . "\n"
            . '<p data-pandoc-tabindex="5" data-pandoc-accesskey="z">Text</p>' . "\n"
            . '</article>';

        $t->same($expected, $fragment->serialize());
        $t->contains($expected, $blocks);
        $t->same('/migration/global-html-metadata-lines-review.html', $document->children[0]->attr('part'));
        $t->same([
            'language-direction-review',
            'language-direction-review',
            'unsafe-attribute',
            'editing-state-review',
            'editing-state-review',
            'translation-state-review',
            'unsafe-attribute',
            'unsafe-attribute',
            'focus-navigation-review',
            'focus-navigation-review',
            'unsafe-attribute',
            'popover-review',
            'unsafe-attribute',
            'unsafe-attribute',
            'unsafe-attribute',
            'focus-navigation-review',
            'focus-navigation-review',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $diagnostics));
        $t->same([
            'lang',
            'dir',
            'contenteditable',
            'draggable',
            'spellcheck',
            'translate',
            'tabindex',
            'accesskey',
            'accesskey',
            'autofocus',
            'popover',
            'popover',
            'dir',
            'lang',
            'translate',
            'tabindex',
            'accesskey',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['attribute'] ?? ''), $diagnostics));
        $t->same([1, 1, 2, 2, 2, 2, 2, 2, 2, 2, 3, 3, 3, 4, 4, 4, 4], array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $diagnostics));
        $t->same(
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $diagnostics),
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $astDiagnostics)
        );
        $t->same('bad<tag>', $diagnostics[7]['token'] ?? null);
        $t->true(!str_contains($fragment->serialize(), 'bad state'), 'Expected invalid editing and popover state to stay diagnostic-only');
        $t->true(!str_contains($fragment->serialize(), 'sideways'), 'Expected invalid direction to stay diagnostic-only');
        $t->true(!str_contains($fragment->serialize(), '<tag'), 'Expected invalid metadata values to stay diagnostic-only');
    },
    'converts popover states into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<button popovertarget="review-pop" popovertargetaction="show">Open note</button>'
            . '<aside id="review-pop" popover data-pandoc-popover-state="source-spoof"><p>Auto <a href="./auto.html">note</a><a href="java&#10;script:alert(1)">bad</a></p></aside>'
            . '<section id="manual-pop" popover="manual"><p>Manual note</p></section>'
            . '<div id="hint-pop" popover="hint"><p>Hint note</p></div>'
            . '<div id="invalid-pop" popover="bad state"><p>Invalid note</p></div>'
            . '<a href="./control.html" popovertarget="manual-pop" popovertargetaction="hide">Control link</a>'
            . '<button popovertarget="missing-pop">Missing target</button>'
            . '<button popovertarget="bad target" popovertargetaction="dismiss">Bad target</button>'
            . '<a href="./plain.html" popovertarget="plain-pop">Plain target</a>'
            . '<section id="plain-pop">Plain target body</section>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/popover-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<span data-pandoc-button-type="submit" data-pandoc-popover-action="show" data-pandoc-popover-action-defaulted="false" data-pandoc-popover-target-kind="popover" data-pandoc-popover-target="review-pop" data-pandoc-popover-target-tag="aside" data-pandoc-popover-target-id="review-pop" data-pandoc-popover-target-state="auto">Open note</span>'
            . '<aside id="review-pop" data-pandoc-popover-state="auto"><p>Auto <a href="https://source.example.test/import/posts/auto.html">note</a><a>bad</a></p></aside>'
            . '<section id="manual-pop" data-pandoc-popover-state="manual"><p>Manual note</p></section>'
            . '<div id="hint-pop" data-pandoc-popover-state="hint"><p>Hint note</p></div>'
            . '<div id="invalid-pop" data-pandoc-popover-state="manual"><p>Invalid note</p></div>'
            . '<a href="https://source.example.test/import/posts/control.html" data-pandoc-popover-action="hide" data-pandoc-popover-action-defaulted="false" data-pandoc-popover-target-kind="popover" data-pandoc-popover-target="manual-pop" data-pandoc-popover-target-tag="section" data-pandoc-popover-target-id="manual-pop" data-pandoc-popover-target-state="manual">Control link</a>'
            . '<span data-pandoc-button-type="submit" data-pandoc-popover-action="toggle" data-pandoc-popover-action-defaulted="true" data-pandoc-popover-target-kind="missing-target" data-pandoc-popover-target="missing-pop" data-pandoc-popover-target-issues="missing-popover-target">Missing target</span>'
            . '<span data-pandoc-button-type="submit" data-pandoc-popover-action="invalid" data-pandoc-popover-action-defaulted="false" data-pandoc-popover-target-kind="invalid-reference" data-pandoc-popover-target-issues="invalid-popover-target-reference invalid-popover-target-action">Bad target</span>'
            . '<a href="https://source.example.test/import/posts/plain.html" data-pandoc-popover-action="toggle" data-pandoc-popover-action-defaulted="true" data-pandoc-popover-target-kind="element" data-pandoc-popover-target="plain-pop" data-pandoc-popover-target-tag="section" data-pandoc-popover-target-id="plain-pop" data-pandoc-popover-target-issues="non-popover-target">Plain target</a>'
            . '<section id="plain-pop">Plain target body</section>';
        $policyDiagnostics = array_count_values(array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        )));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Open noteAuto notebadManual noteHint noteInvalid noteControl linkMissing targetBad targetPlain targetPlain target body', $fragment->textContent());
        $t->same(['a', 'aside', 'div', 'p', 'section', 'span'], $summary['elementNames']);
        $t->same(['base', 'button'], $summary['blockedTags']);
        $t->same(['data-pandoc-popover-state', 'href', 'popover', 'popovertarget', 'popovertargetaction', 'type'], $summary['filteredAttributes']);
        $t->same(4, $policyDiagnostics['blocked-tag'] ?? 0);
        $t->same(3, $policyDiagnostics['button-metadata-review'] ?? 0);
        $t->same(34, $policyDiagnostics['popover-review'] ?? 0);
        $t->same(4, $policyDiagnostics['unsafe-attribute'] ?? 0);
        $t->same(1, $policyDiagnostics['unsafe-url'] ?? 0);
        $t->same('span', $nodes[0]['name']);
        $t->same('submit', $nodes[0]['attrs']['data-pandoc-button-type']);
        $t->same('show', $nodes[0]['attrs']['data-pandoc-popover-action']);
        $t->same('popover', $nodes[0]['attrs']['data-pandoc-popover-target-kind']);
        $t->same('auto', $nodes[0]['attrs']['data-pandoc-popover-target-state']);
        $t->same('Open note', $nodes[0]['children'][0]['text']);
        $t->same('aside', $nodes[1]['name']);
        $t->same([
            'id' => 'review-pop',
            'data-pandoc-popover-state' => 'auto',
        ], $nodes[1]['attrs']);
        $t->same('https://source.example.test/import/posts/auto.html', $nodes[1]['children'][0]['children'][1]['attrs']['href']);
        $t->same([], $nodes[1]['children'][0]['children'][2]['attrs']);
        $t->same([
            'id' => 'manual-pop',
            'data-pandoc-popover-state' => 'manual',
        ], $nodes[2]['attrs']);
        $t->same([
            'id' => 'hint-pop',
            'data-pandoc-popover-state' => 'hint',
        ], $nodes[3]['attrs']);
        $t->same([
            'id' => 'invalid-pop',
            'data-pandoc-popover-state' => 'manual',
        ], $nodes[4]['attrs']);
        $t->same('hide', $nodes[5]['attrs']['data-pandoc-popover-action']);
        $t->same('manual', $nodes[5]['attrs']['data-pandoc-popover-target-state']);
        $t->same('missing-popover-target', $nodes[6]['attrs']['data-pandoc-popover-target-issues']);
        $t->same('invalid-reference', $nodes[7]['attrs']['data-pandoc-popover-target-kind']);
        $t->same('invalid-popover-target-reference invalid-popover-target-action', $nodes[7]['attrs']['data-pandoc-popover-target-issues']);
        $t->same('element', $nodes[8]['attrs']['data-pandoc-popover-target-kind']);
        $t->same('non-popover-target', $nodes[8]['attrs']['data-pandoc-popover-target-issues']);
        $t->same('/migration/popover-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, ' popover'), 'Expected live popover attributes to be replaced with inert metadata');
        $t->true(!str_contains($html, 'popovertarget'), 'Expected popover invoker attributes to be stripped');
        $t->true(!str_contains($html, 'source-spoof'), 'Expected source-owned popover metadata to be stripped');
        $t->true(!str_contains($html, 'bad state'), 'Expected invalid popover token to stay diagnostic-only');
        $t->true(!str_contains($html, 'dismiss'), 'Expected invalid popover action to stay diagnostic-only');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe popover links to be stripped');
        $t->true(!str_contains($blocks, ' popover'), 'Expected WordPress blocks to omit live popover attributes');
    },
    'converts microdata and rdfa attributes into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article itemscope itemtype="https://schema.org/Article ./types/Local" itemid="./articles/42" itemref="headline author bad<tag">'
            . '<h1 itemprop="headline schema:name bad<tag">Title</h1>'
            . '<a property="schema:url og:url" typeof="schema:Article https://schema.org/NewsArticle" about="#article" resource="./canonical.html" vocab="https://schema.org/" prefix="schema: https://schema.org/ og: https://ogp.me/ns# bad: javascript:alert(1)" href="./canonical.html">Canonical</a>'
            . '<span itemtype="ftp://bad.example/Type" property="og:title bad<>">Social</span>'
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/semantic-metadata-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article data-pandoc-microdata-scope="true" data-pandoc-microdata-type="https://schema.org/Article https://source.example.test/import/posts/types/Local" data-pandoc-microdata-id="https://source.example.test/import/posts/articles/42" data-pandoc-microdata-ref="headline author" data-pandoc-microdata-ref-count="2" data-pandoc-microdata-ref-missing="headline author" data-pandoc-microdata-ref-missing-count="2" data-pandoc-microdata-properties="headline schema:name" data-pandoc-microdata-property-count="2" data-pandoc-microdata-value-count="2">'
            . '<h1 data-pandoc-microdata-property="headline schema:name" data-pandoc-microdata-value="Title">Title</h1>'
            . '<a data-pandoc-rdfa-property="schema:url og:url" data-pandoc-rdfa-typeof="schema:Article https://schema.org/NewsArticle" data-pandoc-rdfa-about="https://source.example.test/import/posts/post.html#article" data-pandoc-rdfa-resource="https://source.example.test/import/posts/canonical.html" data-pandoc-rdfa-vocab="https://schema.org/" data-pandoc-rdfa-prefix="schema: https://schema.org/ og: https://ogp.me/ns#" href="https://source.example.test/import/posts/canonical.html">Canonical</a>'
            . '<span data-pandoc-rdfa-property="og:title">Social</span></article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('TitleCanonicalSocial', $fragment->textContent());
        $t->same(['a', 'article', 'h1', 'span'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['itemprop', 'itemref', 'itemtype', 'prefix', 'property'], $summary['filteredAttributes']);
        $t->same([
            'semantic-metadata-review',
            'semantic-metadata-review',
            'semantic-metadata-review',
            'unsafe-attribute',
            'semantic-metadata-review',
            'unsafe-attribute',
            'semantic-metadata-review',
            'microdata-value-review',
            'semantic-metadata-review',
            'semantic-metadata-review',
            'semantic-metadata-review',
            'semantic-metadata-review',
            'semantic-metadata-review',
            'unsafe-url',
            'semantic-metadata-review',
            'unsafe-url',
            'unsafe-attribute',
            'semantic-metadata-review',
            'microdata-itemref-review',
            'microdata-item-review',
        ], $policyDiagnostics);
        $t->same('article', $nodes[0]['name']);
        $t->same([
            'data-pandoc-microdata-scope' => 'true',
            'data-pandoc-microdata-type' => 'https://schema.org/Article https://source.example.test/import/posts/types/Local',
            'data-pandoc-microdata-id' => 'https://source.example.test/import/posts/articles/42',
            'data-pandoc-microdata-ref' => 'headline author',
            'data-pandoc-microdata-ref-count' => '2',
            'data-pandoc-microdata-ref-missing' => 'headline author',
            'data-pandoc-microdata-ref-missing-count' => '2',
            'data-pandoc-microdata-properties' => 'headline schema:name',
            'data-pandoc-microdata-property-count' => '2',
            'data-pandoc-microdata-value-count' => '2',
        ], $nodes[0]['attrs']);
        $t->same([
            'data-pandoc-microdata-property' => 'headline schema:name',
            'data-pandoc-microdata-value' => 'Title',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same([
            'data-pandoc-rdfa-property' => 'schema:url og:url',
            'data-pandoc-rdfa-typeof' => 'schema:Article https://schema.org/NewsArticle',
            'data-pandoc-rdfa-about' => 'https://source.example.test/import/posts/post.html#article',
            'data-pandoc-rdfa-resource' => 'https://source.example.test/import/posts/canonical.html',
            'data-pandoc-rdfa-vocab' => 'https://schema.org/',
            'data-pandoc-rdfa-prefix' => 'schema: https://schema.org/ og: https://ogp.me/ns#',
            'href' => 'https://source.example.test/import/posts/canonical.html',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same(['data-pandoc-rdfa-property' => 'og:title'], $nodes[0]['children'][2]['attrs']);
        $t->same('/migration/semantic-metadata-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach ([' itemscope', ' itemtype=', ' itemid=', ' itemref=', ' itemprop=', ' property=', ' typeof=', ' about=', ' resource=', ' vocab=', ' prefix='] as $sourceAttribute) {
            $t->true(!str_contains($html, $sourceAttribute), 'Expected source semantic attribute to be replaced: ' . $sourceAttribute);
        }
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe semantic metadata URLs to be stripped');
        $t->true(!str_contains($html, 'bad&lt;tag'), 'Expected malformed semantic term tokens to be stripped');
    },
    'derives microdata item values into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article itemscope itemtype="https://schema.org/Article">'
            . '<h1 itemprop="headline"> Review title </h1>'
            . '<a itemprop="url" href="./posts/42.html">Permalink</a>'
            . '<img itemprop="image" src="./cover.jpg" alt="Cover">'
            . '<time itemprop="datePublished" datetime="2026-06-09 16:57Z">June 9</time>'
            . '<data itemprop="wordCount" value=" 1250 ">1,250 words</data>'
            . '<meter itemprop="ratingValue" value=".75" min="0" max="1">Rating</meter>'
            . '<span itemprop="description"> Summary <em>with emphasis</em> </span>'
            . '<section itemprop="author" itemscope itemtype="https://schema.org/Person"><span itemprop="name">Ada Lovelace</span></section>'
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/microdata-value-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $expected = '<article data-pandoc-microdata-scope="true" data-pandoc-microdata-type="https://schema.org/Article" data-pandoc-microdata-properties="headline url image datePublished wordCount ratingValue description author" data-pandoc-microdata-property-count="8" data-pandoc-microdata-value-count="7" data-pandoc-microdata-nested-item-count="1">'
            . '<h1 data-pandoc-microdata-property="headline" data-pandoc-microdata-value="Review title"> Review title </h1>'
            . '<a data-pandoc-microdata-property="url" href="https://source.example.test/import/posts/posts/42.html" data-pandoc-microdata-value="https://source.example.test/import/posts/posts/42.html">Permalink</a>'
            . '<img data-pandoc-microdata-property="image" src="https://source.example.test/import/posts/cover.jpg" alt="Cover" data-pandoc-microdata-value="https://source.example.test/import/posts/cover.jpg">'
            . '<time data-pandoc-microdata-property="datePublished" data-pandoc-time-datetime="2026-06-09T16:57Z" data-pandoc-time-kind="global-datetime" data-pandoc-microdata-value="2026-06-09T16:57Z">June 9</time>'
            . '<data data-pandoc-microdata-property="wordCount" data-pandoc-data-value="1250" data-pandoc-microdata-value="1250">1,250 words</data>'
            . '<meter data-pandoc-microdata-property="ratingValue" data-pandoc-meter-value="0.75" data-pandoc-meter-min="0" data-pandoc-meter-max="1" data-pandoc-microdata-value="0.75">Rating</meter>'
            . '<span data-pandoc-microdata-property="description" data-pandoc-microdata-value="Summary with emphasis"> Summary <em>with emphasis</em> </span>'
            . '<section data-pandoc-microdata-property="author" data-pandoc-microdata-scope="true" data-pandoc-microdata-type="https://schema.org/Person" data-pandoc-microdata-properties="name" data-pandoc-microdata-property-count="1" data-pandoc-microdata-value-count="1"><span data-pandoc-microdata-property="name" data-pandoc-microdata-value="Ada Lovelace">Ada Lovelace</span></section>'
            . '</article>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same(' Review title PermalinkJune 91,250 wordsRating Summary with emphasis Ada Lovelace', $fragment->textContent());
        $t->same(['a', 'article', 'data', 'em', 'h1', 'img', 'meter', 'section', 'span', 'time'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(28, count($policyDiagnostics));
        $t->same(8, count(array_filter(
            $policyDiagnostics,
            static fn (string $code): bool => $code === 'microdata-value-review'
        )));
        $t->same([
            'data-pandoc-microdata-scope' => 'true',
            'data-pandoc-microdata-type' => 'https://schema.org/Article',
            'data-pandoc-microdata-properties' => 'headline url image datePublished wordCount ratingValue description author',
            'data-pandoc-microdata-property-count' => '8',
            'data-pandoc-microdata-value-count' => '7',
            'data-pandoc-microdata-nested-item-count' => '1',
        ], $nodes[0]['attrs']);
        $t->same('Review title', $nodes[0]['children'][0]['attrs']['data-pandoc-microdata-value']);
        $t->same('https://source.example.test/import/posts/posts/42.html', $nodes[0]['children'][1]['attrs']['data-pandoc-microdata-value']);
        $t->same('https://source.example.test/import/posts/cover.jpg', $nodes[0]['children'][2]['attrs']['data-pandoc-microdata-value']);
        $t->same('2026-06-09T16:57Z', $nodes[0]['children'][3]['attrs']['data-pandoc-microdata-value']);
        $t->same('1250', $nodes[0]['children'][4]['attrs']['data-pandoc-microdata-value']);
        $t->same('0.75', $nodes[0]['children'][5]['attrs']['data-pandoc-microdata-value']);
        $t->same('Summary with emphasis', $nodes[0]['children'][6]['attrs']['data-pandoc-microdata-value']);
        $t->true(!array_key_exists('data-pandoc-microdata-value', $nodes[0]['children'][7]['attrs']));
        $t->same('name', $nodes[0]['children'][7]['attrs']['data-pandoc-microdata-properties']);
        $t->same('1', $nodes[0]['children'][7]['attrs']['data-pandoc-microdata-property-count']);
        $t->same('1', $nodes[0]['children'][7]['attrs']['data-pandoc-microdata-value-count']);
        $t->same('Ada Lovelace', $nodes[0]['children'][7]['children'][0]['attrs']['data-pandoc-microdata-value']);
        $t->same('/migration/microdata-value-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach ([' itemprop=', ' itemscope', ' itemtype=', ' datetime=', ' value=', ' min=', ' max='] as $sourceAttribute) {
            $t->true(!str_contains($html, $sourceAttribute), 'Expected source microdata/value attribute to be replaced: ' . $sourceAttribute);
            $t->true(!str_contains($blocks, $sourceAttribute), 'Expected WordPress blocks to omit source microdata/value attribute: ' . $sourceAttribute);
        }
    },
    'preserves meta itemprop scalar values in microdata summaries before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article itemscope itemtype="https://schema.org/Article">'
            . '<meta itemprop="dateModified schema:dateModified" content=" 2026-06-09T17:30:00Z ">'
            . '<meta itemprop="bad<tag" content="lost scalar">'
            . '<meta itemprop="empty" content="   ">'
            . '<h1 itemprop="headline">Review title</h1>'
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/microdata-meta-value-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $microdataValueDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'microdata-value-review'
        ));

        $expected = '<article data-pandoc-microdata-scope="true" data-pandoc-microdata-type="https://schema.org/Article" data-pandoc-microdata-properties="dateModified schema:dateModified headline" data-pandoc-microdata-property-count="3" data-pandoc-microdata-value-count="3">'
            . '<span data-pandoc-microdata-property="dateModified schema:dateModified" data-pandoc-microdata-value="2026-06-09T17:30:00Z" data-pandoc-microdata-source="meta">Microdata dateModified schema:dateModified: 2026-06-09T17:30:00Z</span>'
            . '<h1 data-pandoc-microdata-property="headline" data-pandoc-microdata-value="Review title">Review title</h1></article>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Microdata dateModified schema:dateModified: 2026-06-09T17:30:00ZReview title', $fragment->textContent());
        $t->same(['article', 'h1', 'span'], $summary['elementNames']);
        $t->same(['meta'], $summary['blockedTags']);
        $t->same(['content', 'itemprop'], $summary['filteredAttributes']);
        $t->same(2, count($microdataValueDiagnostics));
        $t->same('meta', $microdataValueDiagnostics[0]['tag']);
        $t->same('content', $microdataValueDiagnostics[0]['attribute']);
        $t->same('data-pandoc-microdata-value', $microdataValueDiagnostics[0]['metadataAttribute']);
        $t->same('h1', $microdataValueDiagnostics[1]['tag']);
        $t->same([
            'data-pandoc-microdata-scope' => 'true',
            'data-pandoc-microdata-type' => 'https://schema.org/Article',
            'data-pandoc-microdata-properties' => 'dateModified schema:dateModified headline',
            'data-pandoc-microdata-property-count' => '3',
            'data-pandoc-microdata-value-count' => '3',
        ], $nodes[0]['attrs']);
        $t->same([
            'data-pandoc-microdata-property' => 'dateModified schema:dateModified',
            'data-pandoc-microdata-value' => '2026-06-09T17:30:00Z',
            'data-pandoc-microdata-source' => 'meta',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same('/migration/microdata-meta-value-review.html', $document->children[0]->attr('part'));
        foreach (['<meta', ' itemprop=', ' content=', 'bad&lt;tag', 'lost scalar'] as $sourceContent) {
            $t->true(!str_contains($html, $sourceContent), 'Expected source meta microdata content to be replaced: ' . $sourceContent);
            $t->true(!str_contains($blocks, $sourceContent), 'Expected WordPress blocks to omit source meta microdata content: ' . $sourceContent);
        }
    },
    'keeps passive meta conversions while preserving itemprop microdata values' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article itemscope itemtype="https://schema.org/Article">'
            . '<meta property="og:image" itemprop="image" content="./cover.png">'
            . '<meta name="description" itemprop="description" content=" Review summary ">'
            . '<p>body</p>'
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/microdata-meta-passive-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $microdataDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'microdata-value-review'
        ));

        $expected = '<article data-pandoc-microdata-scope="true" data-pandoc-microdata-type="https://schema.org/Article" data-pandoc-microdata-properties="image description" data-pandoc-microdata-property-count="2" data-pandoc-microdata-value-count="2">'
            . '<a href="https://source.example.test/import/posts/cover.png" data-pandoc-meta-property="og:image" data-pandoc-meta-content="https://source.example.test/import/posts/cover.png" data-pandoc-meta-url="true" data-pandoc-microdata-property="image" data-pandoc-microdata-value="https://source.example.test/import/posts/cover.png" data-pandoc-microdata-source="meta">Open Graph image</a>'
            . '<span data-pandoc-meta-name="description" data-pandoc-meta-content="Review summary" data-pandoc-microdata-property="description" data-pandoc-microdata-value="Review summary" data-pandoc-microdata-source="meta">Description: Review summary</span>'
            . '<p>body</p></article>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Open Graph imageDescription: Review summarybody', $fragment->textContent());
        $t->same(['a', 'article', 'p', 'span'], $summary['elementNames']);
        $t->same(['meta'], $summary['blockedTags']);
        $t->same(2, count($microdataDiagnostics));
        $t->same([
            'data-pandoc-microdata-scope' => 'true',
            'data-pandoc-microdata-type' => 'https://schema.org/Article',
            'data-pandoc-microdata-properties' => 'image description',
            'data-pandoc-microdata-property-count' => '2',
            'data-pandoc-microdata-value-count' => '2',
        ], $nodes[0]['attrs']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/cover.png',
            'data-pandoc-meta-property' => 'og:image',
            'data-pandoc-meta-content' => 'https://source.example.test/import/posts/cover.png',
            'data-pandoc-meta-url' => 'true',
            'data-pandoc-microdata-property' => 'image',
            'data-pandoc-microdata-value' => 'https://source.example.test/import/posts/cover.png',
            'data-pandoc-microdata-source' => 'meta',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same([
            'data-pandoc-meta-name' => 'description',
            'data-pandoc-meta-content' => 'Review summary',
            'data-pandoc-microdata-property' => 'description',
            'data-pandoc-microdata-value' => 'Review summary',
            'data-pandoc-microdata-source' => 'meta',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same('/migration/microdata-meta-passive-review.html', $document->children[0]->attr('part'));
        foreach (['<meta', ' itemprop=', ' content='] as $sourceAttribute) {
            $t->true(!str_contains($html, $sourceAttribute), 'Expected source meta microdata attribute to be replaced: ' . $sourceAttribute);
            $t->true(!str_contains($blocks, $sourceAttribute), 'Expected WordPress blocks to omit source meta microdata attribute: ' . $sourceAttribute);
        }
    },
    'summarizes scoped microdata properties without crossing nested item boundaries' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article itemscope itemtype="https://schema.org/Event">'
            . '<h1 itemprop="name alternateName">Launch review</h1>'
            . '<section itemprop="location" itemscope itemtype="https://schema.org/Place">'
            . '<span itemprop="name">Main Hall</span><span itemprop="address">1 Review Way</span></section>'
            . '<p><span itemprop="startDate">2026-06-09</span><span itemprop="startDate">2026-06-10</span></p>'
            . '</article>',
            'https://source.example.test/import/events/launch.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/microdata-item-summary-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $itemDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'microdata-item-review'
        ));
        $repeatedPropertyDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'microdata-repeated-property-review'
        ));

        $expected = '<article data-pandoc-microdata-scope="true" data-pandoc-microdata-type="https://schema.org/Event" data-pandoc-microdata-properties="name alternateName location startDate" data-pandoc-microdata-property-count="5" data-pandoc-microdata-value-count="4" data-pandoc-microdata-nested-item-count="1" data-pandoc-microdata-repeated-properties="startDate" data-pandoc-microdata-repeated-property-count="1">'
            . '<h1 data-pandoc-microdata-property="name alternateName" data-pandoc-microdata-value="Launch review">Launch review</h1>'
            . '<section data-pandoc-microdata-property="location" data-pandoc-microdata-scope="true" data-pandoc-microdata-type="https://schema.org/Place" data-pandoc-microdata-properties="name address" data-pandoc-microdata-property-count="2" data-pandoc-microdata-value-count="2">'
            . '<span data-pandoc-microdata-property="name" data-pandoc-microdata-value="Main Hall">Main Hall</span><span data-pandoc-microdata-property="address" data-pandoc-microdata-value="1 Review Way">1 Review Way</span></section>'
            . '<p><span data-pandoc-microdata-property="startDate" data-pandoc-microdata-value="2026-06-09">2026-06-09</span><span data-pandoc-microdata-property="startDate" data-pandoc-microdata-value="2026-06-10">2026-06-10</span></p>'
            . '</article>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Launch reviewMain Hall1 Review Way2026-06-092026-06-10', $fragment->textContent());
        $t->same(['article', 'h1', 'p', 'section', 'span'], $summary['elementNames']);
        $t->same(2, count($itemDiagnostics));
        $t->same([
            'data-pandoc-microdata-scope' => 'true',
            'data-pandoc-microdata-type' => 'https://schema.org/Event',
            'data-pandoc-microdata-properties' => 'name alternateName location startDate',
            'data-pandoc-microdata-property-count' => '5',
            'data-pandoc-microdata-value-count' => '4',
            'data-pandoc-microdata-nested-item-count' => '1',
            'data-pandoc-microdata-repeated-properties' => 'startDate',
            'data-pandoc-microdata-repeated-property-count' => '1',
        ], $nodes[0]['attrs']);
        $t->same([
            'data-pandoc-microdata-property' => 'location',
            'data-pandoc-microdata-scope' => 'true',
            'data-pandoc-microdata-type' => 'https://schema.org/Place',
            'data-pandoc-microdata-properties' => 'name address',
            'data-pandoc-microdata-property-count' => '2',
            'data-pandoc-microdata-value-count' => '2',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same(1, count($repeatedPropertyDiagnostics));
        $t->same('itemscope', $repeatedPropertyDiagnostics[0]['attribute'] ?? null);
        $t->same(1, $repeatedPropertyDiagnostics[0]['repeatedPropertyCount'] ?? null);
        $t->same('/migration/microdata-item-summary-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/events/launch.html', $document->children[0]->attr('baseUrl'));
        foreach ([' itemscope', ' itemtype=', ' itemprop='] as $sourceAttribute) {
            $t->true(!str_contains($html, $sourceAttribute), 'Expected source microdata attribute to be replaced: ' . $sourceAttribute);
            $t->true(!str_contains($blocks, $sourceAttribute), 'Expected WordPress blocks to omit source microdata attribute: ' . $sourceAttribute);
        }
    },
    'preserves microdata itemref inventories as inert reviewer metadata' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article itemscope itemtype="https://schema.org/Article" itemref="headline author missing-id">'
            . '<h1 id="headline" itemprop="headline">Referenced title</h1>'
            . '<p id="author" itemprop="author">Reference author</p>'
            . '</article>',
            'https://source.example.test/import/posts/ref-review.html'
        );
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/microdata-itemref-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $itemRefDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'microdata-itemref-review'
        ));

        $expected = '<article data-pandoc-microdata-scope="true" data-pandoc-microdata-type="https://schema.org/Article" data-pandoc-microdata-ref="headline author missing-id" data-pandoc-microdata-ref-count="3" data-pandoc-microdata-ref-resolved="headline author" data-pandoc-microdata-ref-resolved-count="2" data-pandoc-microdata-ref-missing="missing-id" data-pandoc-microdata-ref-missing-count="1" data-pandoc-microdata-properties="headline author" data-pandoc-microdata-property-count="2" data-pandoc-microdata-value-count="2">'
            . '<h1 id="headline" data-pandoc-microdata-property="headline" data-pandoc-microdata-value="Referenced title">Referenced title</h1>'
            . '<p id="author" data-pandoc-microdata-property="author" data-pandoc-microdata-value="Reference author">Reference author</p></article>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same([
            'data-pandoc-microdata-scope' => 'true',
            'data-pandoc-microdata-type' => 'https://schema.org/Article',
            'data-pandoc-microdata-ref' => 'headline author missing-id',
            'data-pandoc-microdata-ref-count' => '3',
            'data-pandoc-microdata-ref-resolved' => 'headline author',
            'data-pandoc-microdata-ref-resolved-count' => '2',
            'data-pandoc-microdata-ref-missing' => 'missing-id',
            'data-pandoc-microdata-ref-missing-count' => '1',
            'data-pandoc-microdata-properties' => 'headline author',
            'data-pandoc-microdata-property-count' => '2',
            'data-pandoc-microdata-value-count' => '2',
        ], $nodes[0]['attrs']);
        $t->same(1, count($itemRefDiagnostics));
        $t->same(3, $itemRefDiagnostics[0]['referenceCount']);
        $t->same(2, $itemRefDiagnostics[0]['resolvedCount']);
        $t->same(1, $itemRefDiagnostics[0]['missingCount']);
        $t->same('/migration/microdata-itemref-review.html', $document->children[0]->attr('part'));
        foreach ([' itemscope', ' itemtype=', ' itemref=', ' itemprop='] as $sourceAttribute) {
            $t->true(!str_contains($html, $sourceAttribute), 'Expected source microdata attribute to be replaced: ' . $sourceAttribute);
            $t->true(!str_contains($blocks, $sourceAttribute), 'Expected WordPress blocks to omit source microdata attribute: ' . $sourceAttribute);
        }
    },
    'marks repeated microdata properties introduced by itemref summary merge' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article itemscope itemtype="https://schema.org/Event" itemref="event-alias">'
            . '<h1 itemprop="name">Launch review</h1>'
            . '</article>'
            . '<p id="event-alias" itemprop="name">Launch alias</p>',
            'https://source.example.test/import/events/repeated.html'
        );
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/microdata-repeated-property-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $repeatedPropertyDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'microdata-repeated-property-review'
        ));

        $expected = '<article data-pandoc-microdata-scope="true" data-pandoc-microdata-type="https://schema.org/Event" data-pandoc-microdata-ref="event-alias" data-pandoc-microdata-ref-count="1" data-pandoc-microdata-ref-resolved="event-alias" data-pandoc-microdata-ref-resolved-count="1" data-pandoc-microdata-properties="name" data-pandoc-microdata-property-count="2" data-pandoc-microdata-value-count="2" data-pandoc-microdata-repeated-properties="name" data-pandoc-microdata-repeated-property-count="1">'
            . '<h1 data-pandoc-microdata-property="name" data-pandoc-microdata-value="Launch review">Launch review</h1></article>'
            . '<p id="event-alias" data-pandoc-microdata-property="name" data-pandoc-microdata-value="Launch alias">Launch alias</p>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same([
            'data-pandoc-microdata-scope' => 'true',
            'data-pandoc-microdata-type' => 'https://schema.org/Event',
            'data-pandoc-microdata-ref' => 'event-alias',
            'data-pandoc-microdata-ref-count' => '1',
            'data-pandoc-microdata-ref-resolved' => 'event-alias',
            'data-pandoc-microdata-ref-resolved-count' => '1',
            'data-pandoc-microdata-properties' => 'name',
            'data-pandoc-microdata-property-count' => '2',
            'data-pandoc-microdata-value-count' => '2',
            'data-pandoc-microdata-repeated-properties' => 'name',
            'data-pandoc-microdata-repeated-property-count' => '1',
        ], $nodes[0]['attrs']);
        $t->same(1, count($repeatedPropertyDiagnostics));
        $t->same('itemref', $repeatedPropertyDiagnostics[0]['attribute'] ?? null);
        $t->same(1, $repeatedPropertyDiagnostics[0]['repeatedPropertyCount'] ?? null);
        $t->same('/migration/microdata-repeated-property-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/events/repeated.html', $document->children[0]->attr('baseUrl'));
        foreach ([' itemscope', ' itemtype=', ' itemref=', ' itemprop='] as $sourceAttribute) {
            $t->true(!str_contains($html, $sourceAttribute), 'Expected source microdata attribute to be replaced: ' . $sourceAttribute);
            $t->true(!str_contains($blocks, $sourceAttribute), 'Expected WordPress blocks to omit source microdata attribute: ' . $sourceAttribute);
        }
    },
    'merges resolved microdata itemref properties into scoped item summaries before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article itemscope itemtype="https://schema.org/Event" itemref="venue sponsor embedded missing-id">'
            . '<h1 itemprop="name">Launch review</h1>'
            . '<section id="embedded" itemprop="performer" itemscope itemtype="https://schema.org/Person"><span itemprop="name">In-tree performer</span></section>'
            . '</article>'
            . '<p id="venue" itemprop="location"><span itemprop="name">Town Hall</span><span itemprop="address">1 Review Way</span></p>'
            . '<aside id="sponsor" itemprop="sponsor" itemscope itemtype="https://schema.org/Organization"><span itemprop="name">Open Source Guild</span></aside>',
            'https://source.example.test/import/events/launch.html'
        );
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/microdata-itemref-properties-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $itemRefPropertyDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'microdata-itemref-property-review'
        ));
        $repeatedPropertyDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'microdata-repeated-property-review'
        ));

        $expected = '<article data-pandoc-microdata-scope="true" data-pandoc-microdata-type="https://schema.org/Event" data-pandoc-microdata-ref="venue sponsor embedded missing-id" data-pandoc-microdata-ref-count="4" data-pandoc-microdata-ref-resolved="venue sponsor embedded" data-pandoc-microdata-ref-resolved-count="3" data-pandoc-microdata-ref-missing="missing-id" data-pandoc-microdata-ref-missing-count="1" data-pandoc-microdata-properties="name performer location address sponsor" data-pandoc-microdata-property-count="6" data-pandoc-microdata-value-count="4" data-pandoc-microdata-nested-item-count="2" data-pandoc-microdata-repeated-properties="name" data-pandoc-microdata-repeated-property-count="1">'
            . '<h1 data-pandoc-microdata-property="name" data-pandoc-microdata-value="Launch review">Launch review</h1>'
            . '<section id="embedded" data-pandoc-microdata-property="performer" data-pandoc-microdata-scope="true" data-pandoc-microdata-type="https://schema.org/Person" data-pandoc-microdata-properties="name" data-pandoc-microdata-property-count="1" data-pandoc-microdata-value-count="1"><span data-pandoc-microdata-property="name" data-pandoc-microdata-value="In-tree performer">In-tree performer</span></section>'
            . '</article>'
            . '<p id="venue" data-pandoc-microdata-property="location" data-pandoc-microdata-value="Town Hall1 Review Way"><span data-pandoc-microdata-property="name" data-pandoc-microdata-value="Town Hall">Town Hall</span><span data-pandoc-microdata-property="address" data-pandoc-microdata-value="1 Review Way">1 Review Way</span></p>'
            . '<aside id="sponsor" data-pandoc-microdata-property="sponsor" data-pandoc-microdata-scope="true" data-pandoc-microdata-type="https://schema.org/Organization" data-pandoc-microdata-properties="name" data-pandoc-microdata-property-count="1" data-pandoc-microdata-value-count="1"><span data-pandoc-microdata-property="name" data-pandoc-microdata-value="Open Source Guild">Open Source Guild</span></aside>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Launch reviewIn-tree performerTown Hall1 Review WayOpen Source Guild', $fragment->textContent());
        $t->same([
            'data-pandoc-microdata-scope' => 'true',
            'data-pandoc-microdata-type' => 'https://schema.org/Event',
            'data-pandoc-microdata-ref' => 'venue sponsor embedded missing-id',
            'data-pandoc-microdata-ref-count' => '4',
            'data-pandoc-microdata-ref-resolved' => 'venue sponsor embedded',
            'data-pandoc-microdata-ref-resolved-count' => '3',
            'data-pandoc-microdata-ref-missing' => 'missing-id',
            'data-pandoc-microdata-ref-missing-count' => '1',
            'data-pandoc-microdata-properties' => 'name performer location address sponsor',
            'data-pandoc-microdata-property-count' => '6',
            'data-pandoc-microdata-value-count' => '4',
            'data-pandoc-microdata-nested-item-count' => '2',
            'data-pandoc-microdata-repeated-properties' => 'name',
            'data-pandoc-microdata-repeated-property-count' => '1',
        ], $nodes[0]['attrs']);
        $t->same(1, count($itemRefPropertyDiagnostics));
        $t->same(4, $itemRefPropertyDiagnostics[0]['referenceCount']);
        $t->same(2, $itemRefPropertyDiagnostics[0]['mergedReferenceCount']);
        $t->same(4, $itemRefPropertyDiagnostics[0]['propertyCount']);
        $t->same(3, $itemRefPropertyDiagnostics[0]['valueCount']);
        $t->same(1, $itemRefPropertyDiagnostics[0]['nestedItemCount']);
        $t->same(1, count($repeatedPropertyDiagnostics));
        $t->same('itemref', $repeatedPropertyDiagnostics[0]['attribute'] ?? null);
        $t->same(1, $repeatedPropertyDiagnostics[0]['repeatedPropertyCount'] ?? null);
        $t->same('/migration/microdata-itemref-properties-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/events/launch.html', $document->children[0]->attr('baseUrl'));
        foreach ([' itemscope', ' itemtype=', ' itemref=', ' itemprop='] as $sourceAttribute) {
            $t->true(!str_contains($html, $sourceAttribute), 'Expected source microdata attribute to be replaced: ' . $sourceAttribute);
            $t->true(!str_contains($blocks, $sourceAttribute), 'Expected WordPress blocks to omit source microdata attribute: ' . $sourceAttribute);
        }
    },
    'adds source line metadata to semantic metadata diagnostics before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            "<article itemscope itemtype=\"./types/Local javascript:alert(1)\" itemid=\"./articles/42\" itemref=\"headline bad<tag\">\n"
            . "<h1 itemprop=\"headline bad<tag\">Title</h1>\n"
            . "<a property=\"schema:url bad<>\" about=\" ./article&#10;\" resource=\"java&#10;script:alert(1)\" prefix=\"schema: ./schema bad: javascript:alert(1)\" href=\"./canonical.html\">Canonical</a>\n"
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/semantic-metadata-lines-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $semanticAttributes = [
            'about' => true,
            'itemid' => true,
            'itemprop' => true,
            'itemref' => true,
            'itemscope' => true,
            'itemtype' => true,
            'prefix' => true,
            'property' => true,
            'resource' => true,
        ];
        $semanticDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static function (array $diagnostic) use ($semanticAttributes): bool {
                $code = (string) ($diagnostic['code'] ?? '');
                $attribute = (string) ($diagnostic['attribute'] ?? '');

                return in_array($code, ['semantic-metadata-review', 'unsafe-attribute', 'unsafe-url', 'normalized-url'], true)
                    && isset($semanticAttributes[$attribute]);
            }
        ));
        $astSemanticDiagnostics = array_values(array_filter(
            $document->children[0]->attr('diagnostics'),
            static function (array $diagnostic) use ($semanticAttributes): bool {
                $code = (string) ($diagnostic['code'] ?? '');
                $attribute = (string) ($diagnostic['attribute'] ?? '');

                return in_array($code, ['semantic-metadata-review', 'unsafe-attribute', 'unsafe-url', 'normalized-url'], true)
                    && isset($semanticAttributes[$attribute]);
            }
        ));

        $expected = '<article data-pandoc-microdata-scope="true" data-pandoc-microdata-id="https://source.example.test/import/posts/articles/42" data-pandoc-microdata-ref="headline" data-pandoc-microdata-ref-count="1" data-pandoc-microdata-ref-missing="headline" data-pandoc-microdata-ref-missing-count="1" data-pandoc-microdata-properties="headline" data-pandoc-microdata-property-count="1" data-pandoc-microdata-value-count="1">' . "\n"
            . '<h1 data-pandoc-microdata-property="headline" data-pandoc-microdata-value="Title">Title</h1>' . "\n"
            . '<a data-pandoc-rdfa-property="schema:url" data-pandoc-rdfa-about="https://source.example.test/import/posts/article" data-pandoc-rdfa-prefix="schema: https://source.example.test/import/posts/schema" href="https://source.example.test/import/posts/canonical.html">Canonical</a>' . "\n"
            . '</article>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('/migration/semantic-metadata-lines-review.html', $document->children[0]->attr('part'));
        $t->same(14, count($semanticDiagnostics));
        $t->same(
            ['itemscope', 'itemtype', 'itemid', 'itemref', 'itemref', 'itemprop', 'itemprop', 'property', 'property', 'about', 'about', 'resource', 'prefix', 'prefix'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['attribute'] ?? ''), $semanticDiagnostics)
        );
        $t->same(
            ['semantic-metadata-review', 'unsafe-url', 'semantic-metadata-review', 'unsafe-attribute', 'semantic-metadata-review', 'unsafe-attribute', 'semantic-metadata-review', 'unsafe-attribute', 'semantic-metadata-review', 'normalized-url', 'semantic-metadata-review', 'unsafe-url', 'unsafe-url', 'semantic-metadata-review'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $semanticDiagnostics)
        );
        $t->same([1, 1, 1, 1, 1, 2, 2, 3, 3, 3, 3, 3, 3, 3], array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $semanticDiagnostics));
        $t->same(
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $semanticDiagnostics),
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $astSemanticDiagnostics)
        );
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe semantic URLs to stay diagnostic-only');
        $t->true(!str_contains($html, 'bad&lt;'), 'Expected malformed semantic terms to stay diagnostic-only');
    },
    'converts time datetime attributes into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article><p>'
            . '<time datetime=" 2026-06-08 ">June 8</time>'
            . '<time datetime="2026-06-08 09:30:05.120Z" data-pandoc-time-datetime="source-spoof">Published</time>'
            . '<time datetime="2026-06-08T09:30-0400">Offset</time>'
            . '<time datetime="2026-W23">Week 23</time>'
            . '<time datetime="PT2H30M">Two hours</time>'
            . '<time datetime="2026-06-08T09:30">Local time</time>'
            . '<time datetime="2026-13-40">Bad date</time>'
            . '<time datetime="java&#10;script:alert(1)">Bad scheme</time>'
            . '</p></article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/time-metadata-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article><p>'
            . '<time data-pandoc-time-datetime="2026-06-08" data-pandoc-time-kind="date">June 8</time>'
            . '<time data-pandoc-time-datetime="2026-06-08T09:30:05.120Z" data-pandoc-time-kind="global-datetime">Published</time>'
            . '<time data-pandoc-time-datetime="2026-06-08T09:30-04:00" data-pandoc-time-kind="global-datetime">Offset</time>'
            . '<time data-pandoc-time-datetime="2026-W23" data-pandoc-time-kind="week">Week 23</time>'
            . '<time data-pandoc-time-datetime="PT2H30M" data-pandoc-time-kind="duration">Two hours</time>'
            . '<time data-pandoc-time-datetime="2026-06-08T09:30" data-pandoc-time-kind="local-datetime">Local time</time>'
            . '<time>Bad date</time><time>Bad scheme</time></p></article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('June 8PublishedOffsetWeek 23Two hoursLocal timeBad dateBad scheme', $fragment->textContent());
        $t->same(['article', 'p', 'time'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['data-pandoc-time-datetime', 'datetime'], $summary['filteredAttributes']);
        $t->same(9, count($policyDiagnostics));
        $t->same([
            'time-metadata-review',
            'time-metadata-review',
            'unsafe-attribute',
            'time-metadata-review',
            'time-metadata-review',
            'time-metadata-review',
            'time-metadata-review',
            'unsafe-attribute',
            'unsafe-attribute',
        ], $policyDiagnostics);
        $times = $nodes[0]['children'][0]['children'];
        $t->same([
            'data-pandoc-time-datetime' => '2026-06-08',
            'data-pandoc-time-kind' => 'date',
        ], $times[0]['attrs']);
        $t->same([
            'data-pandoc-time-datetime' => '2026-06-08T09:30:05.120Z',
            'data-pandoc-time-kind' => 'global-datetime',
        ], $times[1]['attrs']);
        $t->same([
            'data-pandoc-time-datetime' => '2026-06-08T09:30-04:00',
            'data-pandoc-time-kind' => 'global-datetime',
        ], $times[2]['attrs']);
        $t->same([
            'data-pandoc-time-datetime' => '2026-W23',
            'data-pandoc-time-kind' => 'week',
        ], $times[3]['attrs']);
        $t->same([
            'data-pandoc-time-datetime' => 'PT2H30M',
            'data-pandoc-time-kind' => 'duration',
        ], $times[4]['attrs']);
        $t->same([
            'data-pandoc-time-datetime' => '2026-06-08T09:30',
            'data-pandoc-time-kind' => 'local-datetime',
        ], $times[5]['attrs']);
        $t->same([], $times[6]['attrs']);
        $t->same([], $times[7]['attrs']);
        $t->same('/migration/time-metadata-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, ' datetime='), 'Expected source datetime attributes to be replaced by inert metadata');
        $t->true(!str_contains($html, 'source-spoof'), 'Expected source-owned Pandoc time metadata to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected malformed datetime schemes to stay diagnostic-only');
        $t->true(!str_contains($blocks, ' datetime='), 'Expected WordPress blocks to omit source datetime attributes');
    },
    'converts data meter and progress values into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article><p>'
            . '<data value=" SKU-42 " data-pandoc-data-value="source-spoof">Legacy SKU</data>'
            . '<data value="bad<tag">Bad data</data>'
            . '<meter value=" 000.750 " min="0" max="1.500" low=".25" high="1.25" optimum="1.0" data-pandoc-meter-value="source-spoof">Quality</meter>'
            . '<meter value="NaN" min="bad" max="10">Bad meter</meter>'
            . '<progress value=".5" max="02.00" data-pandoc-progress-max="source-spoof">Half done</progress>'
            . '<progress value="-1" max="0">Bad progress</progress>'
            . '</p></article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/value-metadata-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article><p>'
            . '<data data-pandoc-data-value="SKU-42">Legacy SKU</data>'
            . '<data>Bad data</data>'
            . '<meter data-pandoc-meter-value="0.75" data-pandoc-meter-min="0" data-pandoc-meter-max="1.5" data-pandoc-meter-low="0.25" data-pandoc-meter-high="1.25" data-pandoc-meter-optimum="1">Quality</meter>'
            . '<meter data-pandoc-meter-max="10">Bad meter</meter>'
            . '<progress data-pandoc-progress-value="0.5" data-pandoc-progress-max="2">Half done</progress>'
            . '<progress>Bad progress</progress>'
            . '</p></article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Legacy SKUBad dataQualityBad meterHalf doneBad progress', $fragment->textContent());
        $t->same(['article', 'data', 'meter', 'p', 'progress'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['data-pandoc-data-value', 'data-pandoc-meter-value', 'data-pandoc-progress-max', 'high', 'low', 'max', 'min', 'optimum', 'value'], $summary['filteredAttributes']);
        $t->same(18, count($policyDiagnostics));
        $t->same([
            'value-metadata-review',
            'unsafe-attribute',
            'unsafe-attribute',
            'value-metadata-review',
            'value-metadata-review',
            'value-metadata-review',
            'value-metadata-review',
            'value-metadata-review',
            'value-metadata-review',
            'unsafe-attribute',
            'unsafe-attribute',
            'unsafe-attribute',
            'value-metadata-review',
            'value-metadata-review',
            'value-metadata-review',
            'unsafe-attribute',
            'unsafe-attribute',
            'unsafe-attribute',
        ], $policyDiagnostics);
        $children = $nodes[0]['children'][0]['children'];
        $t->same(['data-pandoc-data-value' => 'SKU-42'], $children[0]['attrs']);
        $t->same([], $children[1]['attrs']);
        $t->same([
            'data-pandoc-meter-value' => '0.75',
            'data-pandoc-meter-min' => '0',
            'data-pandoc-meter-max' => '1.5',
            'data-pandoc-meter-low' => '0.25',
            'data-pandoc-meter-high' => '1.25',
            'data-pandoc-meter-optimum' => '1',
        ], $children[2]['attrs']);
        $t->same(['data-pandoc-meter-max' => '10'], $children[3]['attrs']);
        $t->same([
            'data-pandoc-progress-value' => '0.5',
            'data-pandoc-progress-max' => '2',
        ], $children[4]['attrs']);
        $t->same([], $children[5]['attrs']);
        $t->same('/migration/value-metadata-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach ([' value=', ' min=', ' max=', ' low=', ' high=', ' optimum='] as $sourceAttribute) {
            $t->true(!str_contains($html, $sourceAttribute), 'Expected source value attribute to be replaced: ' . $sourceAttribute);
        }
        $t->true(!str_contains($html, 'source-spoof'), 'Expected source-owned Pandoc value metadata to be stripped');
        $t->true(!str_contains($html, 'bad&lt;tag'), 'Expected malformed data values to stay diagnostic-only');
        $t->true(!str_contains($html, 'NaN'), 'Expected non-finite meter values to stay diagnostic-only');
        $t->true(!str_contains($blocks, ' value='), 'Expected WordPress blocks to omit source value attributes');
    },
    'converts output calculation metadata into inert reviewer attributes before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article><form id="calc"><label>Subtotal <input id="subtotal"></label>'
            . '<output for=" subtotal tax bad<tag subtotal " form=" calc " name=" total " data-pandoc-output-name="source-spoof">Total due</output>'
            . '<output for=" missing " form="bad id" name="bad<tag">Bad output</output>'
            . '</form></article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/output-metadata-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article><label data-pandoc-label-text="Subtotal" data-pandoc-label-control-source="descendant" data-pandoc-label-control="input" data-pandoc-label-control-id="subtotal" data-pandoc-label-control-type="text">Subtotal </label>'
            . '<output data-pandoc-output-for="subtotal tax" data-pandoc-output-form="calc" data-pandoc-output-name="total">Total due</output>'
            . '<output data-pandoc-output-for="missing">Bad output</output>'
            . '</article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Subtotal Total dueBad output', $fragment->textContent());
        $t->same(['article', 'label', 'output'], $summary['elementNames']);
        $t->same(['form', 'input'], $summary['blockedTags']);
        $t->same(['control', 'data-pandoc-output-name', 'for', 'form', 'name', 'text'], $summary['filteredAttributes']);
        $t->same([
            'blocked-tag',
            'blocked-tag',
            'label-metadata-review',
            'label-metadata-review',
            'label-metadata-review',
            'label-metadata-review',
            'label-metadata-review',
            'unsafe-attribute',
            'output-metadata-review',
            'output-metadata-review',
            'output-metadata-review',
            'unsafe-attribute',
            'output-metadata-review',
            'unsafe-attribute',
            'unsafe-attribute',
        ], $policyDiagnostics);
        $children = $nodes[0]['children'];
        $t->same('label', $children[0]['name']);
        $t->same([
            'data-pandoc-label-text' => 'Subtotal',
            'data-pandoc-label-control-source' => 'descendant',
            'data-pandoc-label-control' => 'input',
            'data-pandoc-label-control-id' => 'subtotal',
            'data-pandoc-label-control-type' => 'text',
        ], $children[0]['attrs']);
        $t->same([
            'data-pandoc-output-for' => 'subtotal tax',
            'data-pandoc-output-form' => 'calc',
            'data-pandoc-output-name' => 'total',
        ], $children[1]['attrs']);
        $t->same(['data-pandoc-output-for' => 'missing'], $children[2]['attrs']);
        $t->same('/migration/output-metadata-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach ([' for=', ' form=', ' name=', 'source-spoof', 'bad&lt;tag'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected live output association metadata to be stripped or converted: ' . $blocked);
        }
        $t->true(!str_contains($blocks, ' for='), 'Expected WordPress blocks to omit source output for attributes');
        $t->true(!str_contains($blocks, ' form='), 'Expected WordPress blocks to omit source output form attributes');
    },
    'adds source line metadata to html helper metadata diagnostics before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            "<article>\n"
            . "<p style=\"background:url(javascript:alert(1)); color:red\">Styled</p>\n"
            . "<track kind=\"transcript\" srclang=\"bad<tag\" src=\"javascript:alert(1)\">\n"
            . "<time datetime=\"2026-13-40\">Bad date</time>\n"
            . "<progress value=\"bad\" max=\"0\">Bad progress</progress>\n"
            . "<output for=\"good bad<tag\" form=\"bad id\" name=\"bad<tag\">Total</output>\n"
            . '</article>'
        );
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/html-helper-metadata-lines.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $diagnosticFilter = static function (array $diagnostic): bool {
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
        $metadataDiagnostics = array_values(array_filter($fragment->diagnostics(), $diagnosticFilter));
        $astMetadataDiagnostics = array_values(array_filter($document->children[0]->attr('diagnostics'), $diagnosticFilter));

        $t->same("<article>\n<p data-pandoc-style=\"color: red\">Styled</p>\n<track>\n<time>Bad date</time>\n<progress>Bad progress</progress>\n<output data-pandoc-output-for=\"good\">Total</output>\n</article>", $html);
        $t->contains($html, $blocks);
        $t->same('/migration/html-helper-metadata-lines.html', $document->children[0]->attr('part'));
        $t->same([
            'unsafe-attribute',
            'style-review-metadata',
            'unsafe-attribute',
            'unsafe-attribute',
            'unsafe-url',
            'unsafe-attribute',
            'unsafe-attribute',
            'unsafe-attribute',
            'unsafe-attribute',
            'output-metadata-review',
            'unsafe-attribute',
            'unsafe-attribute',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $metadataDiagnostics));
        $t->same(
            ['p', 'p', 'track', 'track', 'track', 'time', 'progress', 'progress', 'output', 'output', 'output', 'output'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['tag'] ?? ''), $metadataDiagnostics)
        );
        $t->same(
            ['style', 'style', 'kind', 'srclang', 'src', 'datetime', 'value', 'max', 'for', 'for', 'form', 'name'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['attribute'] ?? ''), $metadataDiagnostics)
        );
        $t->same([2, 2, 3, 3, 3, 4, 5, 5, 6, 6, 6, 6], array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $metadataDiagnostics));
        $t->same(
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $metadataDiagnostics),
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $astMetadataDiagnostics)
        );
        $t->same('background', $metadataDiagnostics[0]['property'] ?? null);
        $t->same('transcript', $metadataDiagnostics[2]['value'] ?? null);
        $t->same('good', $fragment->nodes()[0]['children'][9]['attrs']['data-pandoc-output-for'] ?? null);
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe helper metadata URLs to stay diagnostic-only');
        $t->true(!str_contains($html, 'bad&lt;tag'), 'Expected malformed helper metadata values to stay diagnostic-only');
    },
    'converts ins and del revision metadata into inert reviewer attributes before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article><p>'
            . '<ins cite="./revisions/add-note.html" datetime=" 2026-06-08 09:30Z " data-pandoc-revision-cite="source-spoof">Added copy</ins>'
            . '<del cite=" h&#9;ttps://review.example.test/revisions/remove.html#old " datetime="2026-06-07">Removed copy</del>'
            . '</p><p>'
            . '<ins cite="java&#10;script:alert(1)" datetime="PT2H">Bad cite</ins>'
            . '<del datetime="2026-13-40" cite="./safe-delete.html">Bad date</del>'
            . '</p></article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/revision-metadata-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article><p>'
            . '<ins data-pandoc-revision-cite="https://source.example.test/import/posts/revisions/add-note.html" data-pandoc-revision-datetime="2026-06-08T09:30Z" data-pandoc-revision-kind="global-datetime">Added copy</ins>'
            . '<del data-pandoc-revision-cite="https://review.example.test/revisions/remove.html#old" data-pandoc-revision-datetime="2026-06-07" data-pandoc-revision-kind="date">Removed copy</del>'
            . '</p><p><ins>Bad cite</ins><del data-pandoc-revision-cite="https://source.example.test/import/posts/safe-delete.html">Bad date</del></p></article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Added copyRemoved copyBad citeBad date', $fragment->textContent());
        $t->same(['article', 'del', 'ins', 'p'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['cite', 'data-pandoc-revision-cite', 'datetime'], $summary['filteredAttributes']);
        $t->same([
            'revision-metadata-review',
            'revision-metadata-review',
            'unsafe-attribute',
            'normalized-url',
            'revision-metadata-review',
            'revision-metadata-review',
            'unsafe-url',
            'unsafe-attribute',
            'unsafe-attribute',
            'revision-metadata-review',
        ], $policyDiagnostics);
        $revisions = [
            $nodes[0]['children'][0]['children'][0],
            $nodes[0]['children'][0]['children'][1],
            $nodes[0]['children'][1]['children'][0],
            $nodes[0]['children'][1]['children'][1],
        ];
        $t->same([
            'data-pandoc-revision-cite' => 'https://source.example.test/import/posts/revisions/add-note.html',
            'data-pandoc-revision-datetime' => '2026-06-08T09:30Z',
            'data-pandoc-revision-kind' => 'global-datetime',
        ], $revisions[0]['attrs']);
        $t->same([
            'data-pandoc-revision-cite' => 'https://review.example.test/revisions/remove.html#old',
            'data-pandoc-revision-datetime' => '2026-06-07',
            'data-pandoc-revision-kind' => 'date',
        ], $revisions[1]['attrs']);
        $t->same([], $revisions[2]['attrs']);
        $t->same([
            'data-pandoc-revision-cite' => 'https://source.example.test/import/posts/safe-delete.html',
        ], $revisions[3]['attrs']);
        $t->same('/migration/revision-metadata-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, ' cite='), 'Expected source revision cite attributes to be replaced by inert metadata');
        $t->true(!str_contains($html, ' datetime='), 'Expected source revision datetime attributes to be replaced by inert metadata');
        $t->true(!str_contains($html, 'source-spoof'), 'Expected source-owned revision metadata to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe revision cite URL to stay diagnostic-only');
        $t->true(!str_contains($blocks, ' datetime='), 'Expected WordPress blocks to omit source revision datetime attributes');
    },
    'converts quote citation sources into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<blockquote cite=" ./quotes/source.html#quote " data-pandoc-quote-cite="source-spoof"><p>Quoted <q cite=" h&#9;ttps://review.example.test/inline.html ">inline</q><q cite="java&#10;script:alert(1)">bad cite</q></p></blockquote>'
            . '<blockquote cite="mailto:editor@example.test">Mail quote source</blockquote>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/quote-cite-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $expected = '<blockquote data-pandoc-quote-cite="https://source.example.test/import/posts/quotes/source.html#quote"><p>Quoted <q data-pandoc-quote-cite="https://review.example.test/inline.html">inline</q><q>bad cite</q></p></blockquote><blockquote>Mail quote source</blockquote>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Quoted inlinebad citeMail quote source', $fragment->textContent());
        $t->same(['base'], $summary['blockedTags']);
        $t->same(['cite', 'data-pandoc-quote-cite'], $summary['filteredAttributes']);
        $t->same([
            'blocked-tag',
            'normalized-url',
            'quote-cite-review',
            'unsafe-attribute',
            'normalized-url',
            'quote-cite-review',
            'unsafe-url',
            'unsafe-url',
        ], $policyDiagnostics);
        $t->same([
            'data-pandoc-quote-cite' => 'https://source.example.test/import/posts/quotes/source.html#quote',
        ], $nodes[0]['attrs']);
        $t->same([
            'data-pandoc-quote-cite' => 'https://review.example.test/inline.html',
        ], $nodes[0]['children'][0]['children'][1]['attrs']);
        $t->same([], $nodes[0]['children'][0]['children'][2]['attrs']);
        $t->same([], $nodes[1]['attrs']);
        $t->same('/migration/quote-cite-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, ' cite='), 'Expected source quote cite attributes to be replaced by inert metadata');
        $t->true(!str_contains($html, 'source-spoof'), 'Expected source-owned quote metadata to be stripped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe quote cite URL to stay diagnostic-only');
        $t->true(!str_contains($html, 'mailto:editor@example.test'), 'Expected non-fetch quote cite URL to stay diagnostic-only');
        $t->true(!str_contains($blocks, ' cite='), 'Expected WordPress blocks to omit source quote cite attributes');
    },
    'parses XML fragments strictly and rejects DTD entity expansion inputs' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromXml('<root xml:lang="en"><br/><custom data-id="42">A &amp; B</custom></root><note/>');
        $summary = $fragment->summary();

        $t->same('<root xml:lang="en"><br/><custom data-id="42">A &amp; B</custom></root><note/>', $fragment->serialize());
        $t->same('A & B', $fragment->textContent());
        $t->same('xml', $summary['mode']);
        $t->same(2, $summary['topLevelNodes']);
        $t->same(4, $summary['elements']);
        $t->same(['br', 'custom', 'note', 'root'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same([], $fragment->diagnosticCodes());
        $t->throws(\InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromXml('<root><unclosed></root>'));
        $t->throws(\InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromXml('<!DOCTYPE root [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><root>&xxe;</root>'));
    },
    'preserves XML namespace bindings for prefixed fragment serialization' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromXml(
            '<root xmlns="urn:packet"><w:p xmlns:w="urn:word" w:rsidR="001">'
            . '<w:t xml:space="preserve"> Review </w:t>'
            . '<r:link xmlns:r="urn:rel" r:id="rId1">media</r:link>'
            . '<plain xmlns="">fallback</plain>'
            . '</w:p></root>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $serialized = $fragment->serialize();
        $roundTrip = Html5DomFragment::fromXml($serialized);

        $expected = '<root xmlns="urn:packet"><w:p xmlns:w="urn:word" w:rsidR="001">'
            . '<w:t xmlns:w="urn:word" xml:space="preserve"> Review </w:t>'
            . '<r:link xmlns:r="urn:rel" r:id="rId1">media</r:link>'
            . '<plain xmlns="">fallback</plain>'
            . '</w:p></root>';

        $t->same($expected, $serialized);
        $t->same($expected, $roundTrip->serialize());
        $t->same('xml', $summary['mode']);
        $t->same(['plain', 'r:link', 'root', 'w:p', 'w:t'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same([], $fragment->diagnosticCodes());
        $t->same(['xmlns' => 'urn:packet'], $nodes[0]['attrs']);
        $t->same(['xmlns:w' => 'urn:word', 'w:rsidR' => '001'], $nodes[0]['children'][0]['attrs']);
        $t->same(['xmlns:w' => 'urn:word', 'xml:space' => 'preserve'], $nodes[0]['children'][0]['children'][0]['attrs']);
        $t->same(['xmlns:r' => 'urn:rel', 'r:id' => 'rId1'], $nodes[0]['children'][0]['children'][1]['attrs']);
        $t->same(['xmlns' => ''], $nodes[0]['children'][0]['children'][2]['attrs']);
        $t->contains('xmlns:w="urn:word"', $serialized);
        $t->contains('xmlns:r="urn:rel"', $serialized);
        $t->contains('<plain xmlns="">fallback</plain>', $serialized);
    },
    'preserves XML elements and attributes that overlap HTML sanitizer policy' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromXml(
            '<packet>'
            . '<link href="rId1" onload="review-source">media</link>'
            . '<meta name="review" content="ok" style="source-style"/>'
            . '<script type="text/source">if (a &lt; b) { source(); }</script>'
            . '<style data-pandoc-fragment-root="source">.source &gt; note { color: red; }</style>'
            . '</packet>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $serialized = $fragment->serialize();
        $roundTrip = Html5DomFragment::fromXml($serialized);

        $expected = '<packet>'
            . '<link href="rId1" onload="review-source">media</link>'
            . '<meta name="review" content="ok" style="source-style"/>'
            . '<script type="text/source">if (a &lt; b) { source(); }</script>'
            . '<style data-pandoc-fragment-root="source">.source &gt; note { color: red; }</style>'
            . '</packet>';

        $t->same($expected, $serialized);
        $t->same($expected, $roundTrip->serialize());
        $t->same('xml', $summary['mode']);
        $t->same(['link', 'meta', 'packet', 'script', 'style'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same([], $fragment->diagnosticCodes());
        $t->same('link', $nodes[0]['children'][0]['name']);
        $t->same(['href' => 'rId1', 'onload' => 'review-source'], $nodes[0]['children'][0]['attrs']);
        $t->same(['name' => 'review', 'content' => 'ok', 'style' => 'source-style'], $nodes[0]['children'][1]['attrs']);
        $t->same('script', $nodes[0]['children'][2]['name']);
        $t->same('if (a < b) { source(); }', $nodes[0]['children'][2]['children'][0]['text']);
        $t->same(['data-pandoc-fragment-root' => 'source'], $nodes[0]['children'][3]['attrs']);
        $t->true(str_contains($serialized, '<script type="text/source">'), 'Expected XML script-named element to survive as package markup');
        $t->true(str_contains($serialized, 'onload="review-source"'), 'Expected XML onload-named attribute to remain package metadata');
    },
    'normalizes svg and mathml foreign content for raw html review packets' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<aside><svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg>'
                . '<math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math></aside>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();

        $t->contains('<svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg>', $fragment->serialize());
        $t->contains('<math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>', $fragment->serialize());
        $t->same('Logoxx', $fragment->textContent());
        $t->true(in_array('linearGradient', $summary['elementNames'], true), 'Expected adjusted SVG linearGradient element in summary');
        $t->true(in_array('textPath', $summary['elementNames'], true), 'Expected adjusted SVG textPath element in summary');
        $t->same('svg', $nodes[0]['children'][0]['name']);
        $t->same([
            'viewBox' => '0 0 10 10',
            'preserveAspectRatio' => 'xMidYMid meet',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same('linearGradient', $nodes[0]['children'][0]['children'][0]['name']);
        $t->same('definitionURL', array_key_first($nodes[0]['children'][1]['children'][0]['attrs']));
        $t->same([], $summary['blockedTags']);
    },
    'filters svg resource URLs while preserving local references before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/post.html">'
            . '<figure><svg xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<symbol id="icon"><path d="M0 0"></path></symbol>'
            . '<use href="#icon"></use>'
            . '<image href="mailto:cover@example.test" xlink:href="https://cdn.example.test/cover.svg"></image>'
            . '<feImage href="tel:+15550100"></feImage>'
            . '<textPath href="#label">Logo</textPath>'
            . '<a href="mailto:review@example.test">mail</a>'
            . '</svg></figure>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/svg-resource-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $svg = $nodes[0]['children'][0];

        $t->same('https://source.example.test/import/post.html', $fragment->baseUrl());
        $t->same('<figure><svg xmlns:xlink="http://www.w3.org/1999/xlink"><symbol id="icon"><path d="M0 0"></path></symbol><use href="#icon"></use><image xlink:href="https://cdn.example.test/cover.svg"></image><feImage></feImage><textPath href="#label">Logo</textPath><a href="mailto:review@example.test">mail</a></svg></figure>', $html);
        $t->contains($html, $blocks);
        $t->same(['href' => '#icon'], $svg['children'][1]['attrs']);
        $t->same(['xlink:href' => 'https://cdn.example.test/cover.svg'], $svg['children'][2]['attrs']);
        $t->same([], $svg['children'][3]['attrs']);
        $t->same(['href' => '#label'], $svg['children'][4]['attrs']);
        $t->same(['href' => 'mailto:review@example.test'], $svg['children'][5]['attrs']);
        $t->same(['base'], $summary['blockedTags']);
        $t->same(['href'], $summary['filteredAttributes']);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $t->same(['blocked-tag', 'unsafe-url', 'unsafe-url'], $policyDiagnostics);
        $t->same('/migration/svg-resource-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, 'mailto:cover@example.test'), 'Expected mailto SVG image resource URL to be removed');
        $t->true(!str_contains($html, 'tel:+15550100'), 'Expected tel SVG filter resource URL to be removed');
        $t->true(!str_contains($html, 'https://source.example.test/import/post.html#icon'), 'Expected SVG use reference to stay local under base URL metadata');
        $t->true(!str_contains($html, 'https://source.example.test/import/post.html#label'), 'Expected SVG textPath reference to stay local under base URL metadata');
    },
    'preserves safe raster data svg image resources before WordPress handoff' => static function (TestRunner $t): void {
        $pngData = 'data:image/png;base64,iVBORw0KGgo=';
        $gifData = 'data:image/gif;base64,R0lGODlhAQABAAAAACw=';
        $webpData = 'data:image/webp;base64,UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAQAcJaQAA3AA/vuUAAA=';
        $fragment = Html5DomFragment::fromHtml(
            '<figure><svg xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<image href="' . $pngData . '"></image>'
            . '<image xlink:href="' . $webpData . '"></image>'
            . '<feImage href="' . $gifData . '"></feImage>'
            . '<image href="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4="></image>'
            . '<image href="data:text/html;base64,PHNjcmlwdD4="></image>'
            . '<a href="' . $pngData . '">linked data image</a>'
            . '</svg></figure>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/svg-data-image-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $svg = $nodes[0]['children'][0];

        $expected = '<figure><svg xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<image href="' . $pngData . '"></image>'
            . '<image xlink:href="' . $webpData . '"></image>'
            . '<feImage href="' . $gifData . '"></feImage>'
            . '<image></image><image></image><a>linked data image</a></svg></figure>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same(['a', 'feImage', 'figure', 'image', 'svg'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['href'], $summary['filteredAttributes']);
        $t->same(['unsafe-url', 'unsafe-url', 'unsafe-url'], $policyDiagnostics);
        $t->same(['href' => $pngData], $svg['children'][0]['attrs']);
        $t->same(['xlink:href' => $webpData], $svg['children'][1]['attrs']);
        $t->same(['href' => $gifData], $svg['children'][2]['attrs']);
        $t->same([], $svg['children'][3]['attrs']);
        $t->same([], $svg['children'][4]['attrs']);
        $t->same([], $svg['children'][5]['attrs']);
        $t->same('/migration/svg-data-image-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(str_contains($html, $pngData), 'Expected safe raster SVG image href data to survive');
        $t->true(str_contains($html, $webpData), 'Expected safe raster SVG image xlink:href data to survive');
        $t->true(str_contains($html, $gifData), 'Expected safe raster SVG feImage data to survive');
        $t->true(!str_contains($html, 'data:image/svg+xml'), 'Expected script-capable SVG image data to be stripped');
        $t->true(!str_contains($html, 'data:text/html'), 'Expected active HTML data payloads to be stripped');
        $t->true(!str_contains($html, '<a href="data:'), 'Expected data URLs to remain blocked for SVG navigational links');
    },
    'filters svg presentation resource attributes before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<figure><svg>'
            . '<defs><clipPath id="clip"><path d="M0 0"></path></clipPath><mask id="local-mask"></mask></defs>'
            . '<g clip-path=" url( #clip ) " filter="url(&quot;javascript:alert(1)&quot;)" mask="url(./mask.svg#review-mask)" marker-start="url(\'mailto:bad@example.test\')" marker-mid="url(#mid)" marker-end="#end">'
            . '<path d="M0 0" fill="url(#paint)" stroke="url( java&#10;script:alert(1) )"></path>'
            . '</g></svg></figure>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/svg-presentation-resource-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $svg = $nodes[0]['children'][0];
        $group = $svg['children'][1];
        $path = $group['children'][0];

        $expected = '<figure><svg><defs><clipPath id="clip"><path d="M0 0"></path></clipPath><mask id="local-mask"></mask></defs>'
            . '<g clip-path="url(#clip)" mask="url(https://source.example.test/import/posts/mask.svg#review-mask)" marker-mid="url(#mid)" marker-end="#end">'
            . '<path d="M0 0" fill="url(#paint)"></path></g></svg></figure>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same(['filter', 'marker-start', 'stroke'], $summary['filteredAttributes']);
        $t->same(['normalized-url', 'unsafe-url', 'normalized-url', 'unsafe-url', 'unsafe-url'], $policyDiagnostics);
        $t->same('clipPath', $svg['children'][0]['children'][0]['name']);
        $t->same('url(#clip)', $group['attrs']['clip-path']);
        $t->same('url(https://source.example.test/import/posts/mask.svg#review-mask)', $group['attrs']['mask']);
        $t->same('url(#mid)', $group['attrs']['marker-mid']);
        $t->same('#end', $group['attrs']['marker-end']);
        $t->same(['d' => 'M0 0', 'fill' => 'url(#paint)'], $path['attrs']);
        $t->same('/migration/svg-presentation-resource-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe SVG presentation URLs to be stripped');
        $t->true(!str_contains($html, 'mailto:bad@example.test'), 'Expected mailto marker resource to be stripped');
        $t->true(!str_contains($html, 'filter='), 'Expected unsafe filter resource attribute to be stripped');
        $t->true(!str_contains($html, 'stroke='), 'Expected unsafe stroke resource attribute to be stripped');
        $t->true(!str_contains($html, 'https://source.example.test/import/posts/post.html#clip'), 'Expected local clip-path reference to avoid base URL expansion');
    },
    'filters css-escaped svg presentation resource URLs before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<figure><svg><g'
            . ' clip-path="url(\23 clip)"'
            . ' mask="url(\2e/mask.svg#review-mask)"'
            . ' filter="url(\00006a\000061vascript:alert(1))"'
            . ' marker-start="url(ja/**/vascript:alert(1))"'
            . ' marker-mid="url( \6d ailto:bad@example.test )">'
            . '<path d="M0 0" fill="url( \0023 paint)" stroke="url(https://cdn.example.test/stroke.svg#stroke)"></path>'
            . '</g></svg></figure>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/svg-css-escaped-resource-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $group = $nodes[0]['children'][0]['children'][0];
        $path = $group['children'][0];

        $expected = '<figure><svg><g clip-path="url(#clip)" mask="url(https://source.example.test/import/posts/mask.svg#review-mask)">'
            . '<path d="M0 0" fill="url(#paint)" stroke="url(https://cdn.example.test/stroke.svg#stroke)"></path></g></svg></figure>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same(['filter', 'marker-mid', 'marker-start'], $summary['filteredAttributes']);
        $t->same(['normalized-url', 'normalized-url', 'unsafe-url', 'unsafe-url', 'unsafe-url', 'normalized-url'], $policyDiagnostics);
        $t->same('url(#clip)', $group['attrs']['clip-path']);
        $t->same('url(https://source.example.test/import/posts/mask.svg#review-mask)', $group['attrs']['mask']);
        $t->same(['d' => 'M0 0', 'fill' => 'url(#paint)', 'stroke' => 'url(https://cdn.example.test/stroke.svg#stroke)'], $path['attrs']);
        $t->same('/migration/svg-css-escaped-resource-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, 'javascript:'), 'Expected CSS-escaped javascript scheme to be stripped');
        $t->true(!str_contains($html, 'ja/**/vascript'), 'Expected CSS comment-obfuscated resource URL to be stripped');
        $t->true(!str_contains($html, 'mailto:bad@example.test'), 'Expected CSS-escaped mailto resource URL to be stripped');
        $t->true(!str_contains($html, 'https://source.example.test/import/posts/post.html#clip'), 'Expected CSS-escaped local clip reference to avoid base URL expansion');
    },
    'keeps html integration point descendants lowercase in sanitized fragments' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article><svg><foreignObject><div viewBox="html attr"><linearGradient>HTML child</linearGradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject></svg>'
                . '<math><annotation-xml encoding="text/html"><div viewBox="math html"><textPath>HTML text</textPath></div></annotation-xml><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math></article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $article = $nodes[0];
        $foreignObject = $article['children'][0]['children'][0];
        $foreignDiv = $foreignObject['children'][0];
        $mathHtmlAnnotation = $article['children'][1]['children'][0];
        $mathHtmlDiv = $mathHtmlAnnotation['children'][0];
        $mathContentAnnotation = $article['children'][1]['children'][1];
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/foreign-content-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('foreignObject', $foreignObject['name']);
        $t->same(['viewbox' => 'html attr'], $foreignDiv['attrs']);
        $t->same('lineargradient', $foreignDiv['children'][0]['name']);
        $t->same('linearGradient', $foreignDiv['children'][1]['children'][0]['name']);
        $t->same(['viewbox' => 'math html'], $mathHtmlDiv['attrs']);
        $t->same('textpath', $mathHtmlDiv['children'][0]['name']);
        $t->same(['definitionURL' => '#x'], $mathContentAnnotation['children'][0]['attrs']);
        $t->contains('<foreignObject><div viewbox="html attr"><lineargradient>HTML child</lineargradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject>', $html);
        $t->contains('<annotation-xml encoding="text/html"><div viewbox="math html"><textpath>HTML text</textpath></div></annotation-xml>', $blocks);
        $t->same('/migration/foreign-content-review.html', $document->children[0]->attr('part'));
        $t->same([], $summary['blockedTags']);
    },
    'keeps mathml token text integration descendants lowercase in sanitized fragments' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article><math><mtext><span viewBox="html attr"><textPath>HTML text</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></span></mtext>'
                . '<mi><a href="/review">link</a></mi><mo><mglyph src="glyph.png"></mglyph></mo></math></article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $article = $nodes[0];
        $math = $article['children'][0];
        $mtext = $math['children'][0];
        $span = $mtext['children'][0];
        $nestedSvg = $span['children'][1];
        $mi = $math['children'][1];
        $mo = $math['children'][2];
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/mathml-text-integration-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article><math><mtext><span viewbox="html attr"><textpath>HTML text</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></span></mtext><mi><a href="/review">link</a></mi><mo><mglyph src="glyph.png"></mglyph></mo></math></article>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('HTML textlink', $fragment->textContent());
        $t->true(in_array('textpath', $summary['elementNames'], true), 'Expected HTML descendant in mtext to stay lowercase');
        $t->true(in_array('linearGradient', $summary['elementNames'], true), 'Expected nested SVG in mtext to retain foreign-content casing');
        $t->same('mtext', $mtext['name']);
        $t->same(['viewbox' => 'html attr'], $span['attrs']);
        $t->same('textpath', $span['children'][0]['name']);
        $t->same('svg', $nestedSvg['name']);
        $t->same(['viewBox' => '0 0 1 1'], $nestedSvg['attrs']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->same(['href' => '/review'], $mi['children'][0]['attrs']);
        $t->same('mglyph', $mo['children'][0]['name']);
        $t->same('/migration/mathml-text-integration-review.html', $document->children[0]->attr('part'));
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
    },
    'keeps mathml mglyph and malignmark exception children in foreign casing in sanitized fragments' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article><math><mi><malignmark definitionURL="#mark"><svg viewBox="0 0 1 1"><linearGradient id="g"></linearGradient></svg></malignmark><mglyph definitionURL="#glyph"></mglyph><span definitionURL="#html">HTML</span></mi></math></article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $mi = $nodes[0]['children'][0]['children'][0];
        $malignmark = $mi['children'][0];
        $mglyph = $mi['children'][1];
        $span = $mi['children'][2];
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/mathml-text-exception-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article><math><mi><malignmark definitionURL="#mark"><svg viewBox="0 0 1 1"><linearGradient id="g"></linearGradient></svg></malignmark><mglyph definitionURL="#glyph"></mglyph><span definitionurl="#html">HTML</span></mi></math></article>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same(['definitionURL' => '#mark'], $malignmark['attrs']);
        $t->same('linearGradient', $malignmark['children'][0]['children'][0]['name']);
        $t->same(['definitionURL' => '#glyph'], $mglyph['attrs']);
        $t->same(['definitionurl' => '#html'], $span['attrs']);
        $t->true(in_array('linearGradient', $summary['elementNames'], true), 'Expected nested SVG under MathML exception to retain foreign-content casing');
        $t->same('/migration/mathml-text-exception-review.html', $document->children[0]->attr('part'));
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
    },
    'preserves foreign-content cdata text before sanitized WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<figure><svg><desc><![CDATA[Reviewer <source> & notes]]></desc><text><![CDATA[A < B & C]]></text></svg>'
                . '<math><annotation encoding="application/x-tex"><![CDATA[x < y & z]]></annotation></math></figure>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/foreign-cdata-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<figure><svg><desc>Reviewer &lt;source&gt; &amp; notes</desc><text>A &lt; B &amp; C</text></svg><math><annotation encoding="application/x-tex">x &lt; y &amp; z</annotation></math></figure>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Reviewer <source> & notesA < B & Cx < y & z', $fragment->textContent());
        $t->same(['annotation', 'desc', 'figure', 'math', 'svg', 'text'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $t->same([], $policyDiagnostics);
        $t->same('figure', $nodes[0]['name']);
        $t->same('Reviewer <source> & notes', $nodes[0]['children'][0]['children'][0]['children'][0]['text']);
        $t->same('A < B & C', $nodes[0]['children'][0]['children'][1]['children'][0]['text']);
        $t->same('x < y & z', $nodes[0]['children'][1]['children'][0]['children'][0]['text']);
        $t->same('/migration/foreign-cdata-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<![CDATA['), 'Expected CDATA delimiters to be stripped before WordPress handoff');
        $t->true(!str_contains($html, '<source>'), 'Expected CDATA tag-looking source text to remain escaped');
        $t->true(!str_contains($blocks, '<source>'), 'Expected WordPress blocks to avoid parsed source-looking CDATA text');
    },
    'adds mathml annotation source metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<math data-pandoc-math-source="source-spoof"><semantics>'
            . '<mrow><mi>x</mi><mo>&lt;</mo><mi>y</mi></mrow>'
            . '<annotation encoding="Application/X-TeX"> x &lt; y &amp; z </annotation>'
            . '<annotation encoding="text/plain">duplicate source text</annotation>'
            . '<annotation-xml encoding="Application/MathML-Content"><ci>x</ci></annotation-xml>'
            . '</semantics></math>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/mathml-source-annotation-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $expected = '<math data-pandoc-math-source-format="application/x-tex" data-pandoc-math-source="x &lt; y &amp; z" data-pandoc-math-annotation-xml-encoding="application/mathml-content"><semantics>'
            . '<mrow><mi>x</mi><mo>&lt;</mo><mi>y</mi></mrow>'
            . '<annotation encoding="Application/X-TeX"> x &lt; y &amp; z </annotation>'
            . '<annotation encoding="text/plain">duplicate source text</annotation>'
            . '<annotation-xml encoding="Application/MathML-Content"><ci>x</ci></annotation-xml>'
            . '</semantics></math>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('x<y x < y & z duplicate source textx', $fragment->textContent());
        $t->same(['annotation', 'annotation-xml', 'ci', 'math', 'mi', 'mo', 'mrow', 'semantics'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['annotation', 'data-pandoc-math-source'], $summary['filteredAttributes']);
        $t->same(['unsafe-attribute', 'math-annotation-review', 'math-annotation-review'], $policyDiagnostics);
        $t->same('math', $nodes[0]['name']);
        $t->same([
            'data-pandoc-math-source-format' => 'application/x-tex',
            'data-pandoc-math-source' => 'x < y & z',
            'data-pandoc-math-annotation-xml-encoding' => 'application/mathml-content',
        ], $nodes[0]['attrs']);
        $t->same('semantics', $nodes[0]['children'][0]['name']);
        $t->same('annotation', $nodes[0]['children'][0]['children'][1]['name']);
        $t->same(' x < y & z ', $nodes[0]['children'][0]['children'][1]['children'][0]['text']);
        $t->same('annotation-xml', $nodes[0]['children'][0]['children'][3]['name']);
        $t->same('/migration/mathml-source-annotation-review.html', $document->children[0]->attr('part'));
        foreach (['source-spoof', 'data-pandoc-math-source="source-spoof"'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected source-owned MathML metadata to be stripped: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to strip source-owned MathML metadata: ' . $blocked);
        }
    },
    'hands normalized HTML fragments to WordPress raw HTML blocks without browser or Pandoc execution' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml('<h1 id="review">Import</h1><p>Manual<br>break &amp; reviewer note</p>');
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/review-fragment.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:html -->', $blocks);
        $t->contains('<h1 id="review">Import</h1><p>Manual<br>break &amp; reviewer note</p>', $blocks);
        $t->contains('<!-- /wp:html -->', $blocks);
        $t->same('<h1 id="review">Import</h1><p>Manual<br>break &amp; reviewer note</p>', $document->children[0]->attr('html'));
        $t->same('/migration/review-fragment.html', $document->children[0]->attr('part'));
        $t->same([], $document->children[0]->attr('diagnostics'));
    },
    'preserves textarea rcdata as escaped visible reviewer text during sanitizer unwrap' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<textarea data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></textarea><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/textarea-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;<p>after</p>', $fragment->serialize());
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>after', $fragment->textContent());
        $t->same(['p'], $summary['elementNames']);
        $t->same(['textarea'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['blocked-tag'], $fragment->diagnosticCodes());
        $t->same('text', $nodes[0]['type']);
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>', $nodes[0]['text']);
        $t->contains('Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;', $blocks);
        $t->true(!str_contains($fragment->serialize(), '<script>'), 'Expected textarea-like source tags to remain escaped text');
        $t->true(!str_contains($fragment->serialize(), '<b>note</b>'), 'Expected textarea-like inline tags to remain escaped text');
    },
    'unwraps obsolete raw text fallback containers as escaped reviewer text' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<xmp data-source="legacy">Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea></xmp>'
                . '<noembed>Fallback <img src=x> & source</noembed>'
                . '<noframes>Frame fallback <a href="/edit">edit</a></noframes><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/raw-text-fallback-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = 'Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;textarea&gt;&lt;b&gt;note&lt;/b&gt;&lt;/textarea&gt;'
            . 'Fallback &lt;img src=x&gt; &amp; source'
            . 'Frame fallback &lt;a href="/edit"&gt;edit&lt;/a&gt;<p>after</p>';
        $t->same($expected, $fragment->serialize());
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>Fallback <img src=x> & sourceFrame fallback <a href="/edit">edit</a>after', $fragment->textContent());
        $t->same(['p'], $summary['elementNames']);
        $t->same(['noembed', 'noframes', 'xmp'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag'], $policyDiagnostics);
        $t->same('text', $nodes[0]['type']);
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>', $nodes[0]['text']);
        $t->same('text', $nodes[1]['type']);
        $t->same('Fallback <img src=x> & source', $nodes[1]['text']);
        $t->same('/migration/raw-text-fallback-review.html', $document->children[0]->attr('part'));
        $t->contains('Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;textarea&gt;&lt;b&gt;note&lt;/b&gt;&lt;/textarea&gt;', $blocks);
        $t->true(!str_contains($fragment->serialize(), '<xmp'), 'Expected obsolete raw text xmp wrapper to be stripped');
        $t->true(!str_contains($fragment->serialize(), '<noembed'), 'Expected noembed wrapper to be stripped');
        $t->true(!str_contains($fragment->serialize(), '<noframes'), 'Expected noframes wrapper to be stripped');
        $t->true(!str_contains($fragment->serialize(), '<textarea>'), 'Expected raw text textarea-looking source to stay escaped');
        $t->true(!str_contains($fragment->serialize(), '<script>alert(1)</script>'), 'Expected raw text script-looking source to stay escaped');
        $t->true(!str_contains($fragment->serialize(), '<img src=x>'), 'Expected fallback image-looking source to stay escaped');
    },
    'unwraps html plaintext as escaped reviewer text through fragment end' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<plaintext data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $diagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') !== 'libxml-repair'
        ));
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/plaintext-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expectedText = 'Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>after</p>';
        $expectedHtml = 'Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;b&gt;note&lt;/b&gt;&lt;/plaintext&gt;&lt;p&gt;after&lt;/p&gt;';

        $t->same($expectedHtml, $fragment->serialize());
        $t->same($expectedText, $fragment->textContent());
        $t->same('text', $nodes[0]['type']);
        $t->same($expectedText, $nodes[0]['text']);
        $t->same([], $summary['elementNames']);
        $t->same(['plaintext'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $t->same(['plaintext-boundary', 'blocked-tag'], $policyDiagnostics);
        $t->same('plaintext-boundary', $diagnostics[0]['code'] ?? null);
        $t->same('plaintext', $diagnostics[0]['tag'] ?? null);
        $t->same('plaintext-consumes-fragment-tail', $diagnostics[0]['reason'] ?? null);
        $t->same('fragment-eof', $diagnostics[0]['closedBy'] ?? null);
        $t->same(true, $diagnostics[0]['ignoredEndTag'] ?? null);
        $t->same('/migration/plaintext-review.html', $document->children[0]->attr('part'));
        $t->contains($expectedHtml, $blocks);
        $t->true(!str_contains($fragment->serialize(), '<plaintext'), 'Expected plaintext wrapper to be stripped from sanitized output');
        $t->true(!str_contains($fragment->serialize(), '<script>alert(1)</script>'), 'Expected plaintext script-looking source to stay escaped');
        $t->true(!str_contains($fragment->serialize(), '<p>after</p>'), 'Expected following paragraph source to stay plaintext text');
    },
    'diagnoses unterminated html raw text before sanitized handoff' => static function (TestRunner $t): void {
        $source = "<article>before<script>if (a < b) { alert(1); }\n<p>after</p>";
        $fragment = Html5DomFragment::fromHtml($source);
        $diagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') !== 'libxml-repair'
        ));
        $ast = $fragment->toRawHtmlAst(['part' => '/migration/unterminated-raw-text.html']);
        $astDiagnostics = array_values(array_filter(
            $ast->attr('diagnostics'),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') !== 'libxml-repair'
        ));

        $t->same('<article>before</article>', $fragment->serialize());
        $t->same('before', $fragment->textContent());
        $t->same(['raw-text-boundary', 'blocked-tag'], array_map(
            static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''),
            $diagnostics
        ));
        $t->same('script', $diagnostics[0]['tag'] ?? null);
        $t->same('raw-text', $diagnostics[0]['kind'] ?? null);
        $t->same('missing-end-tag-synthesized', $diagnostics[0]['reason'] ?? null);
        $t->same('synthetic-eof', $diagnostics[0]['closedBy'] ?? null);
        $t->same('</script>', $diagnostics[0]['syntheticEndTag'] ?? null);
        $t->same(1, $diagnostics[0]['line'] ?? null);
        $t->same(16, $diagnostics[0]['column'] ?? null);
        $t->same($diagnostics, $astDiagnostics);
        $t->true(!str_contains($fragment->serialize(), '<p>after</p>'), 'Expected source after unterminated script to stay raw diagnostic-only text');
        $t->true(!str_contains($fragment->serialize(), '<script'), 'Expected blocked script wrapper to stay out of sanitized output');
    },
    'foster-parents invalid table children before sanitized WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<table class="legacy"><caption>Review rows</caption><p data-review="loose">Loose note</p><tr><td>A</td></tr>orphan text<tr><td>B</td></tr></table><p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/table-foster-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<p data-review="loose">Loose note</p>orphan text<table class="legacy"><caption>Review rows</caption><tr><td>A</td></tr><tr><td>B</td></tr></table><p>after</p>';
        $t->same($expected, $fragment->serialize());
        $t->contains($expected, $blocks);
        $t->same('p', $nodes[0]['name']);
        $t->same('text', $nodes[1]['type']);
        $t->same('table', $nodes[2]['name']);
        $t->same('caption', $nodes[2]['children'][0]['name']);
        $t->same('tr', $nodes[2]['children'][1]['name']);
        $t->same('tr', $nodes[2]['children'][2]['name']);
        $t->same(2, $summary['diagnostics']);
        $t->same(['table-foster-parented-content', 'table-foster-parented-content'], $fragment->diagnosticCodes());
        $t->same('/migration/table-foster-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($fragment->serialize(), '</caption><p data-review="loose">'), 'Expected paragraph to move outside table');
        $t->true(!str_contains($fragment->serialize(), '</tr>orphan text<tr>'), 'Expected loose text to move outside table rows');
    },
    'wraps orphan table rows and cells before sanitized WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article>'
            . '<tr><td>Loose row</td></tr><td data-review="cell">Loose cell</td><th scope="row">Loose head</th>'
            . '<table class="legacy"><caption>Source table</caption><td>Direct A</td><td>Direct B</td><tr><td>Kept row</td></tr></table>'
            . '<p>after</p>'
            . '</article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/orphan-table-row-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $expected = '<article>'
            . '<table><tr><td>Loose row</td></tr><tr><td data-review="cell">Loose cell</td><th scope="row">Loose head</th></tr></table>'
            . '<table class="legacy"><caption>Source table</caption><tr><td>Direct A</td><td>Direct B</td></tr><tr><td>Kept row</td></tr></table>'
            . '<p>after</p>'
            . '</article>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Loose rowLoose cellLoose headSource tableDirect ADirect BKept rowafter', $fragment->textContent());
        $t->same(['article', 'caption', 'p', 'table', 'td', 'th', 'tr'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same([
            'table-orphan-cell-repaired',
            'table-orphan-cell-repaired',
            'table-orphan-row-repaired',
            'table-orphan-cell-repaired',
            'table-orphan-cell-repaired',
        ], $policyDiagnostics);
        $t->same('article', $nodes[0]['name']);
        $t->same('table', $nodes[0]['children'][0]['name']);
        $t->same('tr', $nodes[0]['children'][0]['children'][0]['name']);
        $t->same('Loose row', $nodes[0]['children'][0]['children'][0]['children'][0]['children'][0]['text']);
        $t->same('tr', $nodes[0]['children'][0]['children'][1]['name']);
        $t->same(['data-review' => 'cell'], $nodes[0]['children'][0]['children'][1]['children'][0]['attrs']);
        $t->same(['scope' => 'row'], $nodes[0]['children'][0]['children'][1]['children'][1]['attrs']);
        $t->same('table', $nodes[0]['children'][1]['name']);
        $t->same('caption', $nodes[0]['children'][1]['children'][0]['name']);
        $t->same('tr', $nodes[0]['children'][1]['children'][1]['name']);
        $t->same('Direct A', $nodes[0]['children'][1]['children'][1]['children'][0]['children'][0]['text']);
        $t->same('Direct B', $nodes[0]['children'][1]['children'][1]['children'][1]['children'][0]['text']);
        $t->same('/migration/orphan-table-row-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<article><tr>'), 'Expected orphan rows to be wrapped before WordPress handoff');
        $t->true(!str_contains($html, '</tr><td data-review="cell">'), 'Expected orphan sibling cells to be wrapped in a generated row');
        $t->true(!str_contains($html, '</caption><td>'), 'Expected direct table cells to be wrapped in a generated row');
    },
    'wraps orphan table sections and columns before sanitized WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article>'
            . '<caption>Loose summary</caption><col span="2"><colgroup><col class="narrow"></colgroup>'
            . '<thead><td>Loose head A</td><th>Loose head B</th></thead>'
            . '<tbody><tr><td>Loose body</td></tr></tbody><tfoot><td>Loose total</td></tfoot>'
            . '<table class="legacy"><caption>Source columns</caption><col span="3"><tbody><td>Direct body</td></tbody></table>'
            . '<p>after</p>'
            . '</article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/orphan-table-section-column-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $diagnosticCounts = array_count_values($policyDiagnostics);

        $expected = '<article>'
            . '<table><caption>Loose summary</caption><colgroup><col span="2"></colgroup><colgroup><col class="narrow"></colgroup>'
            . '<thead><tr><td>Loose head A</td><th>Loose head B</th></tr></thead>'
            . '<tbody><tr><td>Loose body</td></tr></tbody><tfoot><tr><td>Loose total</td></tr></tfoot></table>'
            . '<table class="legacy"><caption>Source columns</caption><colgroup><col span="3"></colgroup><tbody><tr><td>Direct body</td></tr></tbody></table>'
            . '<p>after</p>'
            . '</article>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Loose summaryLoose head ALoose head BLoose bodyLoose totalSource columnsDirect bodyafter', $fragment->textContent());
        $t->same(['article', 'caption', 'col', 'colgroup', 'p', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(2, $diagnosticCounts['table-orphan-column-repaired'] ?? 0);
        $t->same(1, $diagnosticCounts['table-orphan-column-group-repaired'] ?? 0);
        $t->same(1, $diagnosticCounts['table-orphan-caption-repaired'] ?? 0);
        $t->same(3, $diagnosticCounts['table-orphan-section-repaired'] ?? 0);
        $t->same(4, $diagnosticCounts['table-orphan-cell-repaired'] ?? 0);
        $t->same('article', $nodes[0]['name']);
        $t->same('table', $nodes[0]['children'][0]['name']);
        $t->same('caption', $nodes[0]['children'][0]['children'][0]['name']);
        $t->same('colgroup', $nodes[0]['children'][0]['children'][1]['name']);
        $t->same(['span' => '2'], $nodes[0]['children'][0]['children'][1]['children'][0]['attrs']);
        $t->same(['class' => 'narrow'], $nodes[0]['children'][0]['children'][2]['children'][0]['attrs']);
        $t->same('thead', $nodes[0]['children'][0]['children'][3]['name']);
        $t->same('tr', $nodes[0]['children'][0]['children'][3]['children'][0]['name']);
        $t->same('tfoot', $nodes[0]['children'][0]['children'][5]['name']);
        $t->same('tr', $nodes[0]['children'][0]['children'][5]['children'][0]['name']);
        $t->same('table', $nodes[0]['children'][1]['name']);
        $t->same('colgroup', $nodes[0]['children'][1]['children'][1]['name']);
        $t->same('tbody', $nodes[0]['children'][1]['children'][2]['name']);
        $t->same('tr', $nodes[0]['children'][1]['children'][2]['children'][0]['name']);
        $t->same('/migration/orphan-table-section-column-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<article><caption>'), 'Expected orphan caption to be wrapped in a generated table');
        $t->true(!str_contains($html, '<article><col'), 'Expected orphan col to be wrapped in a generated table colgroup');
        $t->true(!str_contains($html, '</caption><col span="3">'), 'Expected direct table col to be wrapped in a generated colgroup');
        $t->true(!str_contains($html, '<thead><td>'), 'Expected direct section cells to be wrapped in generated rows');
    },
    'resolves safe relative URLs from trusted HTML base metadata' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://example.test/import/posts/source.html?draft=1">'
            . '<article><a href="../media/doc.html#section">doc</a>'
            . '<img src="./cover.png" srcset="./cover.png 1x, ../media/cover@2x.png 2x, javascript:alert(1) 3x" alt="Cover">'
            . '<a href="#note">note</a><blockquote cite="?review=1">quoted</blockquote>'
            . '<a href="mailto:review@example.test">mail</a></article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $ast = $fragment->toRawHtmlAst(['part' => '/migration/base-url-review.html']);
        $html = $fragment->serialize();

        $t->same('https://example.test/import/posts/source.html?draft=1', $fragment->baseUrl());
        $t->same('https://example.test/import/posts/source.html?draft=1', $summary['baseUrl']);
        $t->same(['base'], $summary['blockedTags']);
        $t->same(['cite', 'srcset'], $summary['filteredAttributes']);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $t->same(['blocked-tag', 'unsafe-url', 'quote-cite-review'], $policyDiagnostics);
        $t->contains('<a href="https://example.test/import/media/doc.html#section">doc</a>', $html);
        $t->contains('<img src="https://example.test/import/posts/cover.png" srcset="https://example.test/import/posts/cover.png 1x, https://example.test/import/media/cover@2x.png 2x" alt="Cover">', $html);
        $t->contains('<a href="https://example.test/import/posts/source.html?draft=1#note">note</a>', $html);
        $t->contains('<blockquote data-pandoc-quote-cite="https://example.test/import/posts/source.html?review=1">quoted</blockquote>', $html);
        $t->contains('<a href="mailto:review@example.test">mail</a>', $html);
        $t->same('https://example.test/import/media/doc.html#section', $nodes[0]['children'][0]['attrs']['href']);
        $t->same('https://example.test/import/posts/cover.png', $nodes[0]['children'][1]['attrs']['src']);
        $t->same('https://example.test/import/posts/cover.png 1x, https://example.test/import/media/cover@2x.png 2x', $nodes[0]['children'][1]['attrs']['srcset']);
        $t->same('https://example.test/import/posts/source.html?draft=1#note', $nodes[0]['children'][2]['attrs']['href']);
        $t->same('https://example.test/import/posts/source.html?review=1', $nodes[0]['children'][3]['attrs']['data-pandoc-quote-cite']);
        $t->same('mailto:review@example.test', $nodes[0]['children'][4]['attrs']['href']);
        $t->same('/migration/base-url-review.html', $ast->attr('part'));
        $t->same('https://example.test/import/posts/source.html?draft=1', $ast->attr('baseUrl'));
        $t->true(!str_contains($html, '<base'), 'Expected base element to be dropped from sanitized output');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe srcset candidate to be dropped');

        $relativeBase = Html5DomFragment::fromHtml(
            '<base href="../assets/"><img src="cover.png" srcset="cover.png 1x, ./cover@2x.png 2x" alt="Cover">',
            'https://example.test/import/posts/source.html'
        );
        $relativeHtml = $relativeBase->serialize();

        $t->same('https://example.test/import/assets/', $relativeBase->baseUrl());
        $t->same(['blocked-tag'], $relativeBase->diagnosticCodes());
        $t->same('<img src="https://example.test/import/assets/cover.png" srcset="https://example.test/import/assets/cover.png 1x, https://example.test/import/assets/cover@2x.png 2x" alt="Cover">', $relativeHtml);
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml('<a href="/review">review</a>', 'file:///tmp/source.html'));
    },
    'normalizes control-separated base href before resolving reviewer URLs' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="h&#9;ttps://cdn.example.test/root/packet.html">'
            . '<article><a href="./doc.html">doc</a><img src="cover.png" srcset="cover.png 1x, ./cover@2x.png 2x" alt="Cover"></article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/control-base-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article><a href="https://cdn.example.test/root/doc.html">doc</a>'
            . '<img src="https://cdn.example.test/root/cover.png" srcset="https://cdn.example.test/root/cover.png 1x, https://cdn.example.test/root/cover@2x.png 2x" alt="Cover"></article>';

        $t->same('https://cdn.example.test/root/packet.html', $fragment->baseUrl());
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('doc', $fragment->textContent());
        $t->same(['a', 'article', 'img'], $summary['elementNames']);
        $t->same(['base'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['normalized-url', 'blocked-tag'], $policyDiagnostics);
        $t->same('article', $nodes[0]['name']);
        $t->same('https://cdn.example.test/root/doc.html', $nodes[0]['children'][0]['attrs']['href']);
        $t->same('https://cdn.example.test/root/cover.png', $nodes[0]['children'][1]['attrs']['src']);
        $t->same('https://cdn.example.test/root/cover.png 1x, https://cdn.example.test/root/cover@2x.png 2x', $nodes[0]['children'][1]['attrs']['srcset']);
        $t->same('/migration/control-base-review.html', $document->children[0]->attr('part'));
        $t->same('https://cdn.example.test/root/packet.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, "h\t"), 'Expected control-separated base scheme to be canonicalized');
        $t->true(!str_contains($html, '<base'), 'Expected base element to be stripped from sanitized output');

        $unsafeBase = Html5DomFragment::fromHtml(
            '<base href="java&#10;script:alert(1)"><a href="./doc.html">doc</a>',
            'https://source.example.test/import/posts/post.html'
        );
        $unsafeHtml = $unsafeBase->serialize();

        $t->same('https://source.example.test/import/posts/post.html', $unsafeBase->baseUrl());
        $t->same('<a href="https://source.example.test/import/posts/doc.html">doc</a>', $unsafeHtml);
        $t->same(['normalized-url', 'unsafe-url', 'blocked-tag'], $unsafeBase->diagnosticCodes());
        $t->true(!str_contains($unsafeHtml, 'javascript:'), 'Expected control-separated unsafe base scheme to be rejected');
    },
    'ignores duplicate active base href and target metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html" target="review-frame">'
            . '<base href="https://spoof.example.test/assets/" target="_blank">'
            . '<base href="../ignored/" target="side-frame">'
            . '<article><a href="./doc.html">doc</a><img src="cover.png" alt="Cover"></article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/duplicate-base-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') !== 'libxml-repair'
        ));

        $expected = '<span data-pandoc-meta-name="base-target" data-pandoc-meta-source="base" data-pandoc-meta-content="review-frame">Base target: review-frame</span>'
            . '<article><a href="https://source.example.test/import/posts/doc.html">doc</a><img src="https://source.example.test/import/posts/cover.png" alt="Cover"></article>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Base target: review-framedoc', $fragment->textContent());
        $t->same(['a', 'article', 'img', 'span'], $summary['elementNames']);
        $t->same(['base'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same([
            'duplicate-base-ignored',
            'duplicate-base-ignored',
            'base-target-review',
            'duplicate-base-ignored',
            'duplicate-base-ignored',
            'blocked-tag',
            'blocked-tag',
            'blocked-tag',
        ], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $policyDiagnostics));
        $t->same(['href', 'href', 'target', 'target'], array_column(array_slice($policyDiagnostics, 0, 4), 'attribute'));
        $t->same([1, 1, 1, 1], array_map(static fn (array $diagnostic): int => (int) ($diagnostic['line'] ?? 0), array_slice($policyDiagnostics, 0, 4)));
        $t->same('span', $nodes[0]['name']);
        $t->same([
            'data-pandoc-meta-name' => 'base-target',
            'data-pandoc-meta-source' => 'base',
            'data-pandoc-meta-content' => 'review-frame',
        ], $nodes[0]['attrs']);
        $t->same('article', $nodes[1]['name']);
        $t->same('https://source.example.test/import/posts/doc.html', $nodes[1]['children'][0]['attrs']['href']);
        $t->same('https://source.example.test/import/posts/cover.png', $nodes[1]['children'][1]['attrs']['src']);
        $t->same('/migration/duplicate-base-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach (['spoof.example.test', 'side-frame', '../ignored/', '_blank', '<base', 'target='] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected duplicate base metadata to stay diagnostic-only: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to omit duplicate base metadata: ' . $blocked);
        }
    },
    'converts safe meta refresh targets into reviewer links before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<meta http-equiv="refresh" content="5; url= ./next.html?draft=1&#10;">'
            . '<meta http-equiv="Refresh" content="0; URL=java&#10;script:alert(1)">'
            . '<meta name="viewport" content="width=device-width">'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/meta-refresh-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<a href="https://source.example.test/import/posts/next.html?draft=1" data-pandoc-meta-refresh="true">Refresh target</a><p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Refresh targetafter', $fragment->textContent());
        $t->same(['a', 'p'], $summary['elementNames']);
        $t->same(['base', 'meta'], $summary['blockedTags']);
        $t->same(['content'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'unsafe-url', 'blocked-tag'], $policyDiagnostics);
        $t->same('a', $nodes[0]['name']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/next.html?draft=1',
            'data-pandoc-meta-refresh' => 'true',
        ], $nodes[0]['attrs']);
        $t->same('Refresh target', $nodes[0]['children'][0]['text']);
        $t->same('p', $nodes[1]['name']);
        $t->same('/migration/meta-refresh-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<meta'), 'Expected meta refresh elements to be stripped from sanitized output');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe meta refresh URL to be stripped');
        $t->true(!str_contains($html, 'width=device-width'), 'Expected passive viewport metadata to stay out of review HTML');
    },
    'converts document title elements into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<title>Legacy &amp; review <b>title</b></title>'
            . '<title>   </title>'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/document-title-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<span data-pandoc-meta-name="title" data-pandoc-meta-source="title" data-pandoc-meta-content="Legacy &amp; review &lt;b&gt;title&lt;/b&gt;">Title: Legacy &amp; review &lt;b&gt;title&lt;/b&gt;</span><p>after</p>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Title: Legacy & review <b>title</b>after', $fragment->textContent());
        $t->same(['p', 'span'], $summary['elementNames']);
        $t->same(['title'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag'], $fragment->diagnosticCodes());
        $t->same([
            'data-pandoc-meta-name' => 'title',
            'data-pandoc-meta-source' => 'title',
            'data-pandoc-meta-content' => 'Legacy & review <b>title</b>',
        ], $nodes[0]['attrs']);
        $t->same('Title: Legacy & review <b>title</b>', $nodes[0]['children'][0]['text']);
        $t->same('p', $nodes[1]['name']);
        $t->same('/migration/document-title-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<title'), 'Expected original title elements to be stripped from sanitized output');
        $t->true(!str_contains($html, '<b>title</b>'), 'Expected tag-looking title text to stay escaped');
        $t->true(!str_contains($blocks, '<title'), 'Expected WordPress blocks to omit active title elements');
    },
    'converts html document language and direction into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<html lang=" PT-br " dir="RTL" data-pandoc-meta-name="source-spoof"><head><title>Localized packet</title></head><body><p>Review copy</p></body></html>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/document-language-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<span data-pandoc-meta-name="language" data-pandoc-meta-source="html" data-pandoc-meta-content="pt-BR">Language: pt-BR</span>'
            . '<span data-pandoc-meta-name="direction" data-pandoc-meta-source="html" data-pandoc-meta-content="rtl">Direction: rtl</span>'
            . '<span data-pandoc-meta-name="title" data-pandoc-meta-source="title" data-pandoc-meta-content="Localized packet">Title: Localized packet</span>'
            . '<p>Review copy</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Language: pt-BRDirection: rtlTitle: Localized packetReview copy', $fragment->textContent());
        $t->same(['p', 'span'], $summary['elementNames']);
        $t->same(['title'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['document-metadata-review', 'document-metadata-review', 'blocked-tag'], $policyDiagnostics);
        $t->same([
            'data-pandoc-meta-name' => 'language',
            'data-pandoc-meta-source' => 'html',
            'data-pandoc-meta-content' => 'pt-BR',
        ], $nodes[0]['attrs']);
        $t->same('Language: pt-BR', $nodes[0]['children'][0]['text']);
        $t->same([
            'data-pandoc-meta-name' => 'direction',
            'data-pandoc-meta-source' => 'html',
            'data-pandoc-meta-content' => 'rtl',
        ], $nodes[1]['attrs']);
        $t->same('Direction: rtl', $nodes[1]['children'][0]['text']);
        $t->same('title', $nodes[2]['attrs']['data-pandoc-meta-name']);
        $t->same('p', $nodes[3]['name']);
        $t->same('/migration/document-language-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<html'), 'Expected original html wrapper to be stripped from sanitized output');
        $t->true(!str_contains($html, '<body'), 'Expected original body wrapper to be stripped from sanitized output');
        $t->true(!str_contains($html, 'source-spoof'), 'Expected source-owned Pandoc metadata on html to stay hidden');
        $t->true(!str_contains($blocks, '<html'), 'Expected WordPress blocks to omit document wrapper elements');

        $invalid = Html5DomFragment::fromHtml('<html lang="bad lang" dir="sideways"><body><p>after</p></body></html>');
        $invalidHtml = $invalid->serialize();
        $invalidPolicyDiagnostics = array_values(array_filter(
            $invalid->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same('<p>after</p>', $invalidHtml);
        $t->same(['unsafe-attribute', 'unsafe-attribute'], $invalidPolicyDiagnostics);
        $t->true(!str_contains($invalidHtml, 'Language:'), 'Expected invalid language metadata to stay hidden');
        $t->true(!str_contains($invalidHtml, 'Direction:'), 'Expected invalid direction metadata to stay hidden');
    },
    'converts html body language and direction wrappers into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            "<html lang=\"en-US\" dir=\"ltr\">\n"
            . "<head><title>Body localized packet</title></head>\n"
            . "<body lang=\" sr-cyrl-rs \" dir=\"RTL\" data-pandoc-meta-source=\"source-spoof\"><article><p>Localized body copy</p></article></body>\n"
            . '</html>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/body-language-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') !== 'libxml-repair'
        ));

        $expected = '<span data-pandoc-meta-name="language" data-pandoc-meta-source="html" data-pandoc-meta-content="en-US">Language: en-US</span>'
            . '<span data-pandoc-meta-name="direction" data-pandoc-meta-source="html" data-pandoc-meta-content="ltr">Direction: ltr</span>'
            . '<span data-pandoc-meta-name="body-language" data-pandoc-meta-source="body" data-pandoc-meta-content="sr-Cyrl-RS">Body language: sr-Cyrl-RS</span>'
            . '<span data-pandoc-meta-name="body-direction" data-pandoc-meta-source="body" data-pandoc-meta-content="rtl">Body direction: rtl</span>'
            . "\n"
            . '<span data-pandoc-meta-name="title" data-pandoc-meta-source="title" data-pandoc-meta-content="Body localized packet">Title: Body localized packet</span>'
            . "\n"
            . '<article><p>Localized body copy</p></article>'
            . "\n";

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same("Language: en-USDirection: ltrBody language: sr-Cyrl-RSBody direction: rtl\nTitle: Body localized packet\nLocalized body copy\n", $fragment->textContent());
        $t->same(['article', 'p', 'span'], $summary['elementNames']);
        $t->same(['title'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['document-metadata-review', 'document-metadata-review', 'document-metadata-review', 'document-metadata-review', 'blocked-tag'], array_map(
            static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''),
            $policyDiagnostics
        ));
        $t->same(['html', 'html', 'body', 'body', 'title'], array_map(
            static fn (array $diagnostic): string => (string) ($diagnostic['tag'] ?? ''),
            $policyDiagnostics
        ));
        $t->same(['language', 'direction', 'body-language', 'body-direction', ''], array_map(
            static fn (array $diagnostic): string => (string) ($diagnostic['name'] ?? ''),
            $policyDiagnostics
        ));
        $t->same(3, $nodes[2]['line'] ?? null);
        $t->same(3, $nodes[3]['line'] ?? null);
        $t->same([
            'data-pandoc-meta-name' => 'body-language',
            'data-pandoc-meta-source' => 'body',
            'data-pandoc-meta-content' => 'sr-Cyrl-RS',
        ], $nodes[2]['attrs']);
        $t->same('Body language: sr-Cyrl-RS', $nodes[2]['children'][0]['text']);
        $t->same([
            'data-pandoc-meta-name' => 'body-direction',
            'data-pandoc-meta-source' => 'body',
            'data-pandoc-meta-content' => 'rtl',
        ], $nodes[3]['attrs']);
        $t->same('Body direction: rtl', $nodes[3]['children'][0]['text']);
        $t->same("\n", $nodes[4]['text']);
        $t->same('title', $nodes[5]['attrs']['data-pandoc-meta-name']);
        $t->same("\n", $nodes[6]['text']);
        $t->same('article', $nodes[7]['name']);
        $t->same('/migration/body-language-review.html', $document->children[0]->attr('part'));
        foreach (['<html', '<body', ' lang=', ' dir=', 'source-spoof'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected document wrapper metadata to stay inert: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to omit live document wrapper metadata: ' . $blocked);
        }

        $invalid = Html5DomFragment::fromHtml('<html><body lang="bad lang" dir="sideways"><p>after</p></body></html>');
        $invalidDiagnostics = array_values(array_filter(
            $invalid->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') !== 'libxml-repair'
        ));

        $t->same('<p>after</p>', $invalid->serialize());
        $t->same(['unsafe-attribute', 'unsafe-attribute'], array_map(
            static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''),
            $invalidDiagnostics
        ));
        $t->same(['body', 'body'], array_map(static fn (array $diagnostic): string => (string) ($diagnostic['tag'] ?? ''), $invalidDiagnostics));
        $t->true(!str_contains($invalid->serialize(), 'Body language:'), 'Expected invalid body language metadata to stay hidden');
        $t->true(!str_contains($invalid->serialize(), 'Body direction:'), 'Expected invalid body direction metadata to stay hidden');
    },
    'preserves html document metadata after leading comments before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            "\n<!-- exported by legacy CMS -->\n<html lang=\" fr-ca \" dir=\"AUTO\"><head><title>Commented packet</title></head><body><p>Salut</p></body></html>"
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/commented-document-language-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $expected = '<span data-pandoc-meta-name="language" data-pandoc-meta-source="html" data-pandoc-meta-content="fr-CA">Language: fr-CA</span>'
            . '<span data-pandoc-meta-name="direction" data-pandoc-meta-source="html" data-pandoc-meta-content="auto">Direction: auto</span>'
            . "\n"
            . '<!-- exported by legacy CMS -->' . "\n"
            . '<span data-pandoc-meta-name="title" data-pandoc-meta-source="title" data-pandoc-meta-content="Commented packet">Title: Commented packet</span>'
            . '<p>Salut</p>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same("Language: fr-CADirection: auto\n\nTitle: Commented packetSalut", $fragment->textContent());
        $t->same(7, $summary['topLevelNodes']);
        $t->same(4, $summary['elements']);
        $t->same(1, $summary['comments']);
        $t->same(['p', 'span'], $summary['elementNames']);
        $t->same(['title'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['document-metadata-review', 'document-metadata-review', 'blocked-tag'], $policyDiagnostics);
        $t->same('span', $nodes[0]['name']);
        $t->same([
            'data-pandoc-meta-name' => 'language',
            'data-pandoc-meta-source' => 'html',
            'data-pandoc-meta-content' => 'fr-CA',
        ], $nodes[0]['attrs']);
        $t->same('span', $nodes[1]['name']);
        $t->same([
            'data-pandoc-meta-name' => 'direction',
            'data-pandoc-meta-source' => 'html',
            'data-pandoc-meta-content' => 'auto',
        ], $nodes[1]['attrs']);
        $t->same('text', $nodes[2]['type']);
        $t->same("\n", $nodes[2]['text']);
        $t->same('comment', $nodes[3]['type']);
        $t->same(' exported by legacy CMS ', $nodes[3]['text']);
        $t->same('text', $nodes[4]['type']);
        $t->same("\n", $nodes[4]['text']);
        $t->same('title', $nodes[5]['attrs']['data-pandoc-meta-name']);
        $t->same('p', $nodes[6]['name']);
        $t->same('/migration/commented-document-language-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<html'), 'Expected original html wrapper to be stripped from commented document output');
        $t->true(!str_contains($html, '<body'), 'Expected original body wrapper to be stripped from commented document output');
        $t->true(!str_contains($blocks, '<html'), 'Expected WordPress blocks to omit document wrapper elements');

        $unterminated = Html5DomFragment::fromHtml('<!-- missing close <html lang="es"><p>Hola</p>');
        $t->true(!str_contains($unterminated->serialize(), 'Language:'), 'Expected unterminated leading comment not to unlock html document metadata');
    },
    'converts element language and direction attributes into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article lang=" EN-us " dir="RTL" data-pandoc-lang="source-spoof">'
            . '<p xml:lang="sr-Cyrl-rs" dir="auto">Cyrillic <b lang="x-private-review" dir="ltr">custom</b></p>'
            . '<blockquote lang="bad lang" xml:lang="fr-ca" dir="sideways">Quote</blockquote>'
            . '<span xml:lang="x" dir="">Invalid private tag</span>'
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/element-language-direction-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article data-pandoc-lang="en-US" data-pandoc-dir="rtl">'
            . '<p data-pandoc-lang="sr-Cyrl-RS" data-pandoc-dir="auto">Cyrillic <b data-pandoc-lang="x-private-review" data-pandoc-dir="ltr">custom</b></p>'
            . '<blockquote data-pandoc-lang="fr-CA">Quote</blockquote>'
            . '<span>Invalid private tag</span></article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Cyrillic customQuoteInvalid private tag', $fragment->textContent());
        $t->same(['article', 'b', 'blockquote', 'p', 'span'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['data-pandoc-lang', 'dir', 'lang', 'xml:lang'], $summary['filteredAttributes']);
        $t->same([
            'language-direction-review',
            'language-direction-review',
            'unsafe-attribute',
            'language-direction-review',
            'language-direction-review',
            'language-direction-review',
            'language-direction-review',
            'unsafe-attribute',
            'language-direction-review',
            'unsafe-attribute',
            'unsafe-attribute',
        ], $policyDiagnostics);
        $t->same([
            'data-pandoc-lang' => 'en-US',
            'data-pandoc-dir' => 'rtl',
        ], $nodes[0]['attrs']);
        $t->same([
            'data-pandoc-lang' => 'sr-Cyrl-RS',
            'data-pandoc-dir' => 'auto',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same([
            'data-pandoc-lang' => 'x-private-review',
            'data-pandoc-dir' => 'ltr',
        ], $nodes[0]['children'][0]['children'][1]['attrs']);
        $t->same(['data-pandoc-lang' => 'fr-CA'], $nodes[0]['children'][1]['attrs']);
        $t->same([], $nodes[0]['children'][2]['attrs']);
        $t->same('/migration/element-language-direction-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach ([' lang=', ' xml:lang=', ' dir=', 'source-spoof', 'bad lang', 'sideways'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected source language/direction attribute to be stripped or converted: ' . $blocked);
        }
        $t->true(!str_contains($blocks, ' lang='), 'Expected WordPress blocks to omit source lang attributes');
        $t->true(!str_contains($blocks, ' dir='), 'Expected WordPress blocks to omit source dir attributes');
    },
    'converts translate attributes into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article translate="no" data-pandoc-translate-state="source-spoof">'
            . '<p translate="">Reviewer source</p>'
            . '<span translate=" YES ">Machine text</span>'
            . '<em translate="maybe">Invalid state</em>'
            . '</article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/translation-state-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article data-pandoc-translate-state="no">'
            . '<p data-pandoc-translate-state="yes">Reviewer source</p>'
            . '<span data-pandoc-translate-state="yes">Machine text</span>'
            . '<em>Invalid state</em></article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Reviewer sourceMachine textInvalid state', $fragment->textContent());
        $t->same(['article', 'em', 'p', 'span'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['data-pandoc-translate-state', 'translate'], $summary['filteredAttributes']);
        $t->same([
            'translation-state-review',
            'unsafe-attribute',
            'translation-state-review',
            'translation-state-review',
            'unsafe-attribute',
        ], $policyDiagnostics);
        $t->same(['data-pandoc-translate-state' => 'no'], $nodes[0]['attrs']);
        $t->same(['data-pandoc-translate-state' => 'yes'], $nodes[0]['children'][0]['attrs']);
        $t->same(['data-pandoc-translate-state' => 'yes'], $nodes[0]['children'][1]['attrs']);
        $t->same([], $nodes[0]['children'][2]['attrs']);
        $t->same('/migration/translation-state-review.html', $document->children[0]->attr('part'));
        foreach ([' translate=', 'source-spoof', 'maybe'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected source translate state to be stripped or converted: ' . $blocked);
        }
        $t->true(!str_contains($blocks, ' translate='), 'Expected WordPress blocks to omit source translate attributes');
    },
    'converts text input hint attributes into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article inputmode=" numeric " enterkeyhint="NEXT" autocapitalize="words" data-pandoc-inputmode="source-spoof" data-pandoc-enterkeyhint="source-spoof" data-pandoc-autocapitalize="source-spoof">'
            . '<p inputmode="email" enterkeyhint="send" autocapitalize="characters">Contact</p>'
            . '<span inputmode="url" enterkeyhint="go" autocapitalize="none">Link hint</span>'
            . '<em inputmode="bad mode" enterkeyhint="launch" autocapitalize="bad&lt;token">Invalid hints</em>'
            . '</article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/text-input-hint-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article data-pandoc-inputmode="numeric" data-pandoc-enterkeyhint="next" data-pandoc-autocapitalize="words">'
            . '<p data-pandoc-inputmode="email" data-pandoc-enterkeyhint="send" data-pandoc-autocapitalize="characters">Contact</p>'
            . '<span data-pandoc-inputmode="url" data-pandoc-enterkeyhint="go" data-pandoc-autocapitalize="off">Link hint</span>'
            . '<em>Invalid hints</em></article>';
        $policyDiagnostics = array_count_values(array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        )));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('ContactLink hintInvalid hints', $fragment->textContent());
        $t->same(['article', 'em', 'p', 'span'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([
            'autocapitalize',
            'data-pandoc-autocapitalize',
            'data-pandoc-enterkeyhint',
            'data-pandoc-inputmode',
            'enterkeyhint',
            'inputmode',
        ], $summary['filteredAttributes']);
        $t->same(9, $policyDiagnostics['text-input-hint-review'] ?? 0);
        $t->same(6, $policyDiagnostics['unsafe-attribute'] ?? 0);
        $t->same([
            'data-pandoc-inputmode' => 'numeric',
            'data-pandoc-enterkeyhint' => 'next',
            'data-pandoc-autocapitalize' => 'words',
        ], $nodes[0]['attrs']);
        $t->same([
            'data-pandoc-inputmode' => 'email',
            'data-pandoc-enterkeyhint' => 'send',
            'data-pandoc-autocapitalize' => 'characters',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same([
            'data-pandoc-inputmode' => 'url',
            'data-pandoc-enterkeyhint' => 'go',
            'data-pandoc-autocapitalize' => 'off',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same([], $nodes[0]['children'][2]['attrs']);
        $t->same('/migration/text-input-hint-review.html', $document->children[0]->attr('part'));
        foreach ([' inputmode=', ' enterkeyhint=', ' autocapitalize=', 'source-spoof', 'bad mode', 'launch', 'bad&lt;token'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected live text input hint metadata to be stripped or converted: ' . $blocked);
        }
        $t->true(!str_contains($blocks, ' inputmode='), 'Expected WordPress blocks to omit live inputmode attributes');
        $t->true(!str_contains($blocks, ' enterkeyhint='), 'Expected WordPress blocks to omit live enterkeyhint attributes');
        $t->true(!str_contains($blocks, ' autocapitalize='), 'Expected WordPress blocks to omit live autocapitalize attributes');
    },
    'converts writing assistance attributes into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article autocorrect="off" writingsuggestions="false" virtualkeyboardpolicy="manual" data-pandoc-autocorrect="source-spoof" data-pandoc-writingsuggestions="source-spoof" data-pandoc-virtualkeyboardpolicy="source-spoof">'
            . '<p autocorrect writingsuggestions virtualkeyboardpolicy>Defaults</p>'
            . '<span autocorrect="ON" writingsuggestions="TRUE" virtualkeyboardpolicy="AUTO">Explicit</span>'
            . '<em autocorrect="maybe" writingsuggestions="maybe" virtualkeyboardpolicy="onscreen">Invalid</em>'
            . '</article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/writing-assistance-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article data-pandoc-autocorrect="off" data-pandoc-writingsuggestions="false" data-pandoc-virtualkeyboardpolicy="manual">'
            . '<p data-pandoc-autocorrect="on" data-pandoc-writingsuggestions="true" data-pandoc-virtualkeyboardpolicy="auto">Defaults</p>'
            . '<span data-pandoc-autocorrect="on" data-pandoc-writingsuggestions="true" data-pandoc-virtualkeyboardpolicy="auto">Explicit</span>'
            . '<em>Invalid</em></article>';
        $policyDiagnostics = array_count_values(array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        )));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('DefaultsExplicitInvalid', $fragment->textContent());
        $t->same(['article', 'em', 'p', 'span'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([
            'autocorrect',
            'data-pandoc-autocorrect',
            'data-pandoc-virtualkeyboardpolicy',
            'data-pandoc-writingsuggestions',
            'virtualkeyboardpolicy',
            'writingsuggestions',
        ], $summary['filteredAttributes']);
        $t->same(9, $policyDiagnostics['writing-assistance-review'] ?? 0);
        $t->same(6, $policyDiagnostics['unsafe-attribute'] ?? 0);
        $t->same([
            'data-pandoc-autocorrect' => 'off',
            'data-pandoc-writingsuggestions' => 'false',
            'data-pandoc-virtualkeyboardpolicy' => 'manual',
        ], $nodes[0]['attrs']);
        $t->same([
            'data-pandoc-autocorrect' => 'on',
            'data-pandoc-writingsuggestions' => 'true',
            'data-pandoc-virtualkeyboardpolicy' => 'auto',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same([
            'data-pandoc-autocorrect' => 'on',
            'data-pandoc-writingsuggestions' => 'true',
            'data-pandoc-virtualkeyboardpolicy' => 'auto',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same([], $nodes[0]['children'][2]['attrs']);
        $t->same('/migration/writing-assistance-review.html', $document->children[0]->attr('part'));
        foreach ([' autocorrect', ' writingsuggestions', ' virtualkeyboardpolicy', 'source-spoof', 'maybe', 'onscreen'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected live writing assistance metadata to be stripped or converted: ' . $blocked);
        }
        $t->true(!str_contains($blocks, ' autocorrect'), 'Expected WordPress blocks to omit live autocorrect attributes');
        $t->true(!str_contains($blocks, ' writingsuggestions'), 'Expected WordPress blocks to omit live writingsuggestions attributes');
        $t->true(!str_contains($blocks, ' virtualkeyboardpolicy'), 'Expected WordPress blocks to omit live virtualkeyboardpolicy attributes');
    },
    'converts focus and keyboard shortcut attributes into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article tabindex=" 0003 " accesskey="s S ?" autofocus data-pandoc-tabindex="source-spoof" data-pandoc-accesskey="source-spoof" data-pandoc-autofocus-state="source-spoof">'
            . '<h2 tabindex="-01">Focusable heading</h2>'
            . '<a href="./packet.html" accesskey="k k" tabindex="+5">packet</a>'
            . '<p tabindex="bad" accesskey="save bad&lt;tag">bad controls</p>'
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/focus-keyboard-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article data-pandoc-tabindex="3" data-pandoc-accesskey="s S ?" data-pandoc-autofocus-state="true">'
            . '<h2 data-pandoc-tabindex="-1">Focusable heading</h2>'
            . '<a href="https://source.example.test/import/posts/packet.html" data-pandoc-accesskey="k" data-pandoc-tabindex="5">packet</a>'
            . '<p>bad controls</p></article>';
        $policyDiagnostics = array_count_values(array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        )));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Focusable headingpacketbad controls', $fragment->textContent());
        $t->same(['a', 'article', 'h2', 'p'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([
            'accesskey',
            'autofocus',
            'data-pandoc-accesskey',
            'data-pandoc-autofocus-state',
            'data-pandoc-tabindex',
            'tabindex',
        ], $summary['filteredAttributes']);
        $t->same(6, $policyDiagnostics['focus-navigation-review'] ?? 0);
        $t->same(6, $policyDiagnostics['unsafe-attribute'] ?? 0);
        $t->same([
            'data-pandoc-tabindex' => '3',
            'data-pandoc-accesskey' => 's S ?',
            'data-pandoc-autofocus-state' => 'true',
        ], $nodes[0]['attrs']);
        $t->same(['data-pandoc-tabindex' => '-1'], $nodes[0]['children'][0]['attrs']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/packet.html',
            'data-pandoc-accesskey' => 'k',
            'data-pandoc-tabindex' => '5',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same([], $nodes[0]['children'][2]['attrs']);
        $t->same('/migration/focus-keyboard-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach ([' tabindex=', ' accesskey=', ' autofocus', 'source-spoof', 'save', 'bad&lt;tag'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected live focus or shortcut metadata to be stripped or converted: ' . $blocked);
        }
        $t->true(!str_contains($blocks, ' tabindex='), 'Expected WordPress blocks to omit live tabindex attributes');
        $t->true(!str_contains($blocks, ' accesskey='), 'Expected WordPress blocks to omit live accesskey attributes');
        $t->true(!str_contains($blocks, ' autofocus'), 'Expected WordPress blocks to omit live autofocus attributes');
    },
    'converts aria roles and states into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<section role="region bad-role button" aria-label=" Import status " aria-describedby="status-note other_note bad&lt;id" aria-expanded="true" aria-current="PAGE" aria-sort="descending" aria-level="2" aria-valuenow=" 42.500 " aria-busy="maybe" aria-unsupported="source" data-pandoc-aria-label="source-spoof">'
            . '<h2 id="status-note">Import status</h2><p role="presentation none" aria-hidden="true">Ready</p></section>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/aria-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<section data-pandoc-aria-role="region button" data-pandoc-aria-label="Import status" data-pandoc-aria-describedby="status-note other_note" data-pandoc-aria-expanded="true" data-pandoc-aria-current="page" data-pandoc-aria-sort="descending" data-pandoc-aria-level="2" data-pandoc-aria-valuenow="42.5">'
            . '<h2 id="status-note">Import status</h2><p data-pandoc-aria-role="presentation none" data-pandoc-aria-hidden="true">Ready</p></section>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Import statusReady', $fragment->textContent());
        $t->same(['h2', 'p', 'section'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([
            'aria-busy',
            'aria-current',
            'aria-describedby',
            'aria-expanded',
            'aria-hidden',
            'aria-label',
            'aria-level',
            'aria-sort',
            'aria-unsupported',
            'aria-valuenow',
            'data-pandoc-aria-label',
            'role',
        ], $summary['filteredAttributes']);
        $t->same([
            'unsafe-attribute',
            'aria-metadata-review',
            'aria-metadata-review',
            'unsafe-attribute',
            'aria-metadata-review',
            'aria-metadata-review',
            'aria-metadata-review',
            'aria-metadata-review',
            'aria-metadata-review',
            'aria-metadata-review',
            'unsafe-attribute',
            'unsafe-attribute',
            'unsafe-attribute',
            'aria-metadata-review',
            'aria-metadata-review',
        ], $policyDiagnostics);
        $t->same([
            'data-pandoc-aria-role' => 'region button',
            'data-pandoc-aria-label' => 'Import status',
            'data-pandoc-aria-describedby' => 'status-note other_note',
            'data-pandoc-aria-expanded' => 'true',
            'data-pandoc-aria-current' => 'page',
            'data-pandoc-aria-sort' => 'descending',
            'data-pandoc-aria-level' => '2',
            'data-pandoc-aria-valuenow' => '42.5',
        ], $nodes[0]['attrs']);
        $t->same(['id' => 'status-note'], $nodes[0]['children'][0]['attrs']);
        $t->same([
            'data-pandoc-aria-role' => 'presentation none',
            'data-pandoc-aria-hidden' => 'true',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same('/migration/aria-review.html', $document->children[0]->attr('part'));
        foreach ([' role=', ' aria-', 'bad-role', 'bad&lt;id', 'source-spoof', 'aria-unsupported', 'maybe'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected source ARIA attribute to be stripped or converted: ' . $blocked);
        }
        $t->true(!str_contains($blocks, ' aria-'), 'Expected WordPress blocks to omit source ARIA attributes');
    },
    'converts custom element hooks into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article>'
            . '<legacy-gallery data-source="legacy" part="card primary card" exportparts="cover: card-cover, title" data-pandoc-custom-element="source-spoof"><h2>Gallery</h2><img src="./cover.png" alt="Cover"></legacy-gallery>'
            . '<p is="x-review-paragraph" data-pandoc-custom-is="source-spoof">Review paragraph <legacy-badge part="status">Ready</legacy-badge></p>'
            . '<p is="bad">Bad customized built-in</p>'
            . '<svg><foreignObject><legacy-card>HTML fallback</legacy-card></foreignObject></svg>'
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/custom-element-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article>'
            . '<div data-source="legacy" data-pandoc-custom-part="card primary" data-pandoc-custom-exportparts="cover: card-cover, title" data-pandoc-custom-element="legacy-gallery"><h2>Gallery</h2><img src="https://source.example.test/import/posts/cover.png" alt="Cover"></div>'
            . '<p data-pandoc-custom-is="x-review-paragraph">Review paragraph <span data-pandoc-custom-part="status" data-pandoc-custom-element="legacy-badge">Ready</span></p>'
            . '<p>Bad customized built-in</p>'
            . '<svg><foreignObject><span data-pandoc-custom-element="legacy-card">HTML fallback</span></foreignObject></svg>'
            . '</article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('GalleryReview paragraph ReadyBad customized built-inHTML fallback', $fragment->textContent());
        $t->same(['article', 'div', 'foreignObject', 'h2', 'img', 'p', 'span', 'svg'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([
            'data-pandoc-custom-element',
            'data-pandoc-custom-is',
            'exportparts',
            'is',
            'part',
        ], $summary['filteredAttributes']);
        $t->same([
            'custom-element-review',
            'custom-element-review',
            'unsafe-attribute',
            'custom-element-review',
            'custom-element-review',
            'unsafe-attribute',
            'custom-element-review',
            'custom-element-review',
            'unsafe-attribute',
            'custom-element-review',
        ], $policyDiagnostics);
        $t->same('article', $nodes[0]['name']);
        $t->same([
            'data-source' => 'legacy',
            'data-pandoc-custom-part' => 'card primary',
            'data-pandoc-custom-exportparts' => 'cover: card-cover, title',
            'data-pandoc-custom-element' => 'legacy-gallery',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same('div', $nodes[0]['children'][0]['name']);
        $t->same('p', $nodes[0]['children'][1]['name']);
        $t->same(['data-pandoc-custom-is' => 'x-review-paragraph'], $nodes[0]['children'][1]['attrs']);
        $t->same('span', $nodes[0]['children'][1]['children'][1]['name']);
        $t->same([
            'data-pandoc-custom-part' => 'status',
            'data-pandoc-custom-element' => 'legacy-badge',
        ], $nodes[0]['children'][1]['children'][1]['attrs']);
        $t->same([], $nodes[0]['children'][2]['attrs']);
        $t->same('span', $nodes[0]['children'][3]['children'][0]['children'][0]['name']);
        $t->same(['data-pandoc-custom-element' => 'legacy-card'], $nodes[0]['children'][3]['children'][0]['children'][0]['attrs']);
        $t->same('/migration/custom-element-review.html', $document->children[0]->attr('part'));
        foreach (['<legacy-', ' is=', ' part=', ' exportparts=', 'source-spoof', 'bad">Bad customized'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected custom element hook to be stripped or converted: ' . $blocked);
        }
        foreach (['<legacy-', ' is=', ' part=', ' exportparts='] as $blocked) {
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to omit live custom element hooks: ' . $blocked);
        }
    },
    'adds ruby annotation metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article><p>'
            . '<ruby data-pandoc-ruby-annotation="source-spoof">&#28450;<rp>(</rp><rt>Kan ji</rt><rp>)</rp><rtc><rt>Han</rt><rt>character</rt></rtc></ruby>'
            . '<ruby><rb>&#23383;</rb><rt>ji</rt></ruby>'
            . '<ruby><span>&#28304;</span><rt>source<script>drop()</script></rt></ruby>'
            . '</p></article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/ruby-annotation-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article><p>'
            . '<ruby data-pandoc-ruby-base="漢" data-pandoc-ruby-annotation="Kan ji | Han | character" data-pandoc-ruby-fallback="()">漢<rp>(</rp><rt>Kan ji</rt><rp>)</rp><rtc><rt>Han</rt><rt>character</rt></rtc></ruby>'
            . '<ruby data-pandoc-ruby-base="字" data-pandoc-ruby-annotation="ji"><rb>字</rb><rt>ji</rt></ruby>'
            . '<ruby data-pandoc-ruby-base="源" data-pandoc-ruby-annotation="source"><span>源</span><rt>source</rt></ruby>'
            . '</p></article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('漢(Kan ji)Hancharacter字ji源source', $fragment->textContent());
        $t->same(['article', 'p', 'rb', 'rp', 'rt', 'rtc', 'ruby', 'span'], $summary['elementNames']);
        $t->same(['script'], $summary['blockedTags']);
        $t->same(['data-pandoc-ruby-annotation'], $summary['filteredAttributes']);
        $t->same([
            'unsafe-attribute',
            'ruby-annotation-review',
            'ruby-annotation-review',
            'blocked-tag',
            'ruby-annotation-review',
        ], $policyDiagnostics);
        $rubies = $nodes[0]['children'][0]['children'];
        $t->same([
            'data-pandoc-ruby-base' => '漢',
            'data-pandoc-ruby-annotation' => 'Kan ji | Han | character',
            'data-pandoc-ruby-fallback' => '()',
        ], $rubies[0]['attrs']);
        $t->same([
            'data-pandoc-ruby-base' => '字',
            'data-pandoc-ruby-annotation' => 'ji',
        ], $rubies[1]['attrs']);
        $t->same([
            'data-pandoc-ruby-base' => '源',
            'data-pandoc-ruby-annotation' => 'source',
        ], $rubies[2]['attrs']);
        $t->same('/migration/ruby-annotation-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, 'source-spoof'), 'Expected source-owned ruby metadata to be stripped');
        $t->true(!str_contains($html, '<script'), 'Expected active script in ruby annotation to be dropped');
        $t->true(str_contains($blocks, 'data-pandoc-ruby-annotation="Kan ji | Han | character"'), 'Expected WordPress blocks to carry ruby annotation metadata');
    },
    'converts passive named metadata into reviewer spans before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<meta name="description" content="Legacy &amp; import&#10; summary">'
            . '<meta name="Author" content=" Migration Desk ">'
            . '<meta name="keywords" content="wordpress, migration, html">'
            . '<meta name="generator" content="&lt;Legacy CMS&gt;">'
            . '<meta name="viewport" content="width=device-width">'
            . '<meta property="og:image" content="Open graph image">'
            . '<meta name="description" content="   ">'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/meta-name-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<span data-pandoc-meta-name="description" data-pandoc-meta-content="Legacy &amp; import summary">Description: Legacy &amp; import summary</span>'
            . '<span data-pandoc-meta-name="author" data-pandoc-meta-content="Migration Desk">Author: Migration Desk</span>'
            . '<span data-pandoc-meta-name="keywords" data-pandoc-meta-content="wordpress, migration, html">Keywords: wordpress, migration, html</span>'
            . '<span data-pandoc-meta-name="generator" data-pandoc-meta-content="&lt;Legacy CMS&gt;">Generator: &lt;Legacy CMS&gt;</span>'
            . '<p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Description: Legacy & import summaryAuthor: Migration DeskKeywords: wordpress, migration, htmlGenerator: <Legacy CMS>after', $fragment->textContent());
        $t->same(['p', 'span'], $summary['elementNames']);
        $t->same(['base', 'meta'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag'], $policyDiagnostics);
        $t->same('span', $nodes[0]['name']);
        $t->same([
            'data-pandoc-meta-name' => 'description',
            'data-pandoc-meta-content' => 'Legacy & import summary',
        ], $nodes[0]['attrs']);
        $t->same('Description: Legacy & import summary', $nodes[0]['children'][0]['text']);
        $t->same('author', $nodes[1]['attrs']['data-pandoc-meta-name']);
        $t->same('Migration Desk', $nodes[1]['attrs']['data-pandoc-meta-content']);
        $t->same('keywords', $nodes[2]['attrs']['data-pandoc-meta-name']);
        $t->same('generator', $nodes[3]['attrs']['data-pandoc-meta-name']);
        $t->same('<Legacy CMS>', $nodes[3]['attrs']['data-pandoc-meta-content']);
        $t->same('p', $nodes[4]['name']);
        $t->same('/migration/meta-name-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<meta'), 'Expected original meta elements to be stripped from sanitized output');
        $t->true(!str_contains($html, 'width=device-width'), 'Expected viewport metadata to stay out of review HTML');
        $t->true(!str_contains($html, 'Open graph image'), 'Expected unsupported property metadata to remain hidden');
        $t->true(!str_contains($html, '<Legacy CMS>'), 'Expected tag-looking metadata text to remain escaped');
    },
    'converts standard application color metadata into reviewer spans before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<meta name="application-name" content=" Legacy App ">'
            . '<meta name="theme-color" content=" #0A84FF " media=" (prefers-color-scheme: dark) ">'
            . '<meta name="theme-color" content="rgb(255,255,255)">'
            . '<meta name="theme-color" content="url(javascript:alert(1))">'
            . '<meta name="theme-color" content="#123456" media="screen and (background: url(javascript:alert(1)))">'
            . '<meta name="color-scheme" content=" Light Dark only Light ">'
            . '<meta name="color-scheme" content="dark bad-token">'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/meta-standard-color-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<span data-pandoc-meta-name="application-name" data-pandoc-meta-content="Legacy App">Application name: Legacy App</span>'
            . '<span data-pandoc-meta-name="theme-color" data-pandoc-meta-content="#0A84FF" data-pandoc-meta-media="(prefers-color-scheme: dark)">Theme color: #0A84FF</span>'
            . '<span data-pandoc-meta-name="theme-color" data-pandoc-meta-content="rgb(255, 255, 255)">Theme color: rgb(255, 255, 255)</span>'
            . '<span data-pandoc-meta-name="theme-color" data-pandoc-meta-content="#123456">Theme color: #123456</span>'
            . '<span data-pandoc-meta-name="color-scheme" data-pandoc-meta-content="light dark only">Color scheme: light dark only</span>'
            . '<span data-pandoc-meta-name="color-scheme" data-pandoc-meta-content="dark">Color scheme: dark</span>'
            . '<p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Application name: Legacy AppTheme color: #0A84FFTheme color: rgb(255, 255, 255)Theme color: #123456Color scheme: light dark onlyColor scheme: darkafter', $fragment->textContent());
        $t->same(['p', 'span'], $summary['elementNames']);
        $t->same(['base', 'meta'], $summary['blockedTags']);
        $t->same(['content', 'media'], $summary['filteredAttributes']);
        $t->same([
            'blocked-tag',
            'blocked-tag',
            'blocked-tag',
            'blocked-tag',
            'blocked-tag',
            'unsafe-attribute',
            'blocked-tag',
            'unsafe-attribute',
            'blocked-tag',
            'blocked-tag',
            'unsafe-attribute',
        ], $policyDiagnostics);
        $t->same([
            'data-pandoc-meta-name' => 'application-name',
            'data-pandoc-meta-content' => 'Legacy App',
        ], $nodes[0]['attrs']);
        $t->same([
            'data-pandoc-meta-name' => 'theme-color',
            'data-pandoc-meta-content' => '#0A84FF',
            'data-pandoc-meta-media' => '(prefers-color-scheme: dark)',
        ], $nodes[1]['attrs']);
        $t->same('rgb(255, 255, 255)', $nodes[2]['attrs']['data-pandoc-meta-content']);
        $t->same([
            'data-pandoc-meta-name' => 'theme-color',
            'data-pandoc-meta-content' => '#123456',
        ], $nodes[3]['attrs']);
        $t->same('color-scheme', $nodes[4]['attrs']['data-pandoc-meta-name']);
        $t->same('light dark only', $nodes[4]['attrs']['data-pandoc-meta-content']);
        $t->same('dark', $nodes[5]['attrs']['data-pandoc-meta-content']);
        $t->same('/migration/meta-standard-color-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<meta'), 'Expected original standard metadata elements to be stripped from sanitized output');
        $t->true(!str_contains($html, 'url('), 'Expected unsafe theme-color content and media queries to stay hidden');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe metadata URLs to stay hidden');
        $t->true(!str_contains($html, 'bad-token'), 'Expected unsupported color-scheme tokens to stay hidden');
    },
    'converts html charset metadata into reviewer spans before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<meta charset=" Windows-1252 ">'
            . '<meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">'
            . '<meta http-equiv="content-type" content="text/html; charset=&quot;ISO-8859-1&quot;">'
            . '<meta charset="bad charset value">'
            . '<meta http-equiv="content-type" content="text/html">'
            . '<meta http-equiv="x-content-type-options" content="nosniff">'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/meta-charset-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<span data-pandoc-meta-charset="windows-1252" data-pandoc-meta-source="charset">Charset: windows-1252</span>'
            . '<span data-pandoc-meta-charset="shift_jis" data-pandoc-meta-source="content-type">Charset: shift_jis</span>'
            . '<span data-pandoc-meta-charset="iso-8859-1" data-pandoc-meta-source="content-type">Charset: iso-8859-1</span>'
            . '<p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Charset: windows-1252Charset: shift_jisCharset: iso-8859-1after', $fragment->textContent());
        $t->same(['p', 'span'], $summary['elementNames']);
        $t->same(['base', 'meta'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag'], $policyDiagnostics);
        $t->same([
            'data-pandoc-meta-charset' => 'windows-1252',
            'data-pandoc-meta-source' => 'charset',
        ], $nodes[0]['attrs']);
        $t->same('Charset: windows-1252', $nodes[0]['children'][0]['text']);
        $t->same('shift_jis', $nodes[1]['attrs']['data-pandoc-meta-charset']);
        $t->same('content-type', $nodes[1]['attrs']['data-pandoc-meta-source']);
        $t->same('iso-8859-1', $nodes[2]['attrs']['data-pandoc-meta-charset']);
        $t->same('p', $nodes[3]['name']);
        $t->same('/migration/meta-charset-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<meta'), 'Expected original meta charset elements to be stripped from sanitized output');
        $t->true(!str_contains($html, 'bad charset value'), 'Expected invalid charset labels to remain hidden');
        $t->true(!str_contains($html, 'nosniff'), 'Expected unrelated http-equiv metadata to remain hidden');
    },
    'converts document policy metadata into inert reviewer spans before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<meta http-equiv="Content-Security-Policy" content=" default-src &#039;self&#039; ; img-src https: data: ; report-uri https://tracker.example.test/csp ; script-src &#039;none&#039; ">'
            . '<meta http-equiv="content-security-policy" content="script-src java&#10;script:alert(1)">'
            . '<meta name="referrer" content=" Strict-Origin-When-Cross-Origin ">'
            . '<meta name="referrer" content="bad policy">'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/meta-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<span data-pandoc-meta-http-equiv="content-security-policy" data-pandoc-meta-content="default-src &#039;self&#039;; img-src https: data:; script-src &#039;none&#039;">Content security policy: default-src \'self\'; img-src https: data:; script-src \'none\'</span>'
            . '<span data-pandoc-meta-name="referrer" data-pandoc-meta-content="strict-origin-when-cross-origin">Referrer policy: strict-origin-when-cross-origin</span>'
            . '<p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Content security policy: default-src \'self\'; img-src https: data:; script-src \'none\'Referrer policy: strict-origin-when-cross-originafter', $fragment->textContent());
        $t->same(['p', 'span'], $summary['elementNames']);
        $t->same(['meta'], $summary['blockedTags']);
        $t->same(['content'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'unsafe-attribute', 'blocked-tag', 'unsafe-attribute', 'blocked-tag', 'blocked-tag', 'unsafe-attribute'], $policyDiagnostics);
        $t->same([
            'data-pandoc-meta-http-equiv' => 'content-security-policy',
            'data-pandoc-meta-content' => 'default-src \'self\'; img-src https: data:; script-src \'none\'',
        ], $nodes[0]['attrs']);
        $t->same('Content security policy: default-src \'self\'; img-src https: data:; script-src \'none\'', $nodes[0]['children'][0]['text']);
        $t->same([
            'data-pandoc-meta-name' => 'referrer',
            'data-pandoc-meta-content' => 'strict-origin-when-cross-origin',
        ], $nodes[1]['attrs']);
        $t->same('p', $nodes[2]['name']);
        $t->same('/migration/meta-policy-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<meta'), 'Expected original policy meta elements to be stripped from sanitized output');
        $t->true(!str_contains($html, 'report-uri'), 'Expected CSP report endpoints to stay out of inert review metadata');
        $t->true(!str_contains($html, 'tracker.example.test'), 'Expected CSP report target URL to stay hidden');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe CSP source tokens to be stripped');
        $t->true(!str_contains($html, 'bad policy'), 'Expected invalid referrer policy to stay hidden');
    },
    'converts crawler meta directives into inert reviewer spans before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<meta name="robots" content=" noindex, nofollow, max-snippet:-1, max-image-preview:large, url=javascript:alert(1), bad&lt;token&gt; ">'
            . '<meta name="GoogleBot" content="index, follow, max-video-preview:30, max-image-preview:standard">'
            . '<meta name="bingbot" content="noarchive, nocache, unknown-policy">'
            . '<meta name="robots" content="   ">'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/meta-crawler-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<span data-pandoc-meta-name="robots" data-pandoc-meta-content="noindex, nofollow, max-snippet:-1, max-image-preview:large">Robots: noindex, nofollow, max-snippet:-1, max-image-preview:large</span>'
            . '<span data-pandoc-meta-name="googlebot" data-pandoc-meta-content="index, follow, max-video-preview:30, max-image-preview:standard">Googlebot: index, follow, max-video-preview:30, max-image-preview:standard</span>'
            . '<span data-pandoc-meta-name="bingbot" data-pandoc-meta-content="noarchive, nocache">Bingbot: noarchive, nocache</span>'
            . '<p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Robots: noindex, nofollow, max-snippet:-1, max-image-preview:largeGooglebot: index, follow, max-video-preview:30, max-image-preview:standardBingbot: noarchive, nocacheafter', $fragment->textContent());
        $t->same(['p', 'span'], $summary['elementNames']);
        $t->same(['meta'], $summary['blockedTags']);
        $t->same(['content'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'unsafe-attribute', 'unsafe-attribute', 'blocked-tag', 'blocked-tag', 'unsafe-attribute', 'blocked-tag'], $policyDiagnostics);
        $t->same([
            'data-pandoc-meta-name' => 'robots',
            'data-pandoc-meta-content' => 'noindex, nofollow, max-snippet:-1, max-image-preview:large',
        ], $nodes[0]['attrs']);
        $t->same('Robots: noindex, nofollow, max-snippet:-1, max-image-preview:large', $nodes[0]['children'][0]['text']);
        $t->same([
            'data-pandoc-meta-name' => 'googlebot',
            'data-pandoc-meta-content' => 'index, follow, max-video-preview:30, max-image-preview:standard',
        ], $nodes[1]['attrs']);
        $t->same('bingbot', $nodes[2]['attrs']['data-pandoc-meta-name']);
        $t->same('noarchive, nocache', $nodes[2]['attrs']['data-pandoc-meta-content']);
        $t->same('p', $nodes[3]['name']);
        $t->same('/migration/meta-crawler-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, '<meta'), 'Expected crawler meta elements to be stripped from sanitized output');
        $t->true(!str_contains($html, 'javascript:'), 'Expected active crawler directives to stay hidden');
        $t->true(!str_contains($html, 'bad<token>'), 'Expected tag-looking crawler directives to stay hidden');
        $t->true(!str_contains($html, 'unknown-policy'), 'Expected unsupported crawler directives to stay hidden');
    },
    'adds source line metadata to document metadata review nodes and diagnostics before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
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
        $nodes = $fragment->nodes();
        $children = isset($nodes[0]['children']) && is_array($nodes[0]['children']) ? $nodes[0]['children'] : [];
        $metadataNodes = array_values(array_filter(
            $children,
            static fn (array $node): bool => ($node['type'] ?? '') === 'element'
                && ($node['name'] ?? '') === 'span'
                && str_starts_with((string) array_key_first(is_array($node['attrs'] ?? null) ? $node['attrs'] : []), 'data-pandoc-meta')
        ));
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/document-metadata-lines-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $metadataDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'unsafe-attribute'
                && ($diagnostic['tag'] ?? '') === 'meta'
                && in_array((string) ($diagnostic['attribute'] ?? ''), ['content', 'media'], true)
        ));
        $astMetadataDiagnostics = array_values(array_filter(
            $document->children[0]->attr('diagnostics'),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'unsafe-attribute'
                && ($diagnostic['tag'] ?? '') === 'meta'
                && in_array((string) ($diagnostic['attribute'] ?? ''), ['content', 'media'], true)
        ));

        $t->contains('<span data-pandoc-meta-name="title" data-pandoc-meta-source="title" data-pandoc-meta-content="Imported packet">Title: Imported packet</span>', $html);
        $t->contains('<span data-pandoc-meta-charset="windows-1252" data-pandoc-meta-source="charset">Charset: windows-1252</span>', $html);
        $t->contains('<span data-pandoc-meta-http-equiv="content-security-policy" data-pandoc-meta-content="default-src &#039;self&#039;">Content security policy: default-src \'self\'</span>', $html);
        $t->contains('<span data-pandoc-meta-name="robots" data-pandoc-meta-content="index">Robots: index</span>', $html);
        $t->contains('<span data-pandoc-meta-name="theme-color" data-pandoc-meta-content="#123456">Theme color: #123456</span>', $html);
        $t->contains('<span data-pandoc-meta-name="color-scheme" data-pandoc-meta-content="dark light">Color scheme: dark light</span>', $html);
        $t->contains('<span data-pandoc-meta-property="og:title" data-pandoc-meta-content="Share title">Open Graph title: Share title</span>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/document-metadata-lines-review.html', $document->children[0]->attr('part'));
        $t->same([2, 3, 4, 6, 8, 9, 10], array_map(static fn (array $node): ?int => $node['line'] ?? null, $metadataNodes));
        $t->same(['span', 'span', 'span', 'span', 'span', 'span', 'span'], array_map(static fn (array $node): string => (string) ($node['name'] ?? ''), $metadataNodes));
        $t->same(8, count($metadataDiagnostics));
        $t->same(
            ['content', 'content', 'content', 'content', 'content', 'content', 'media', 'content'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['attribute'] ?? ''), $metadataDiagnostics)
        );
        $t->same([4, 4, 5, 6, 6, 7, 8, 9], array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $metadataDiagnostics));
        $t->same(
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $metadataDiagnostics),
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $astMetadataDiagnostics)
        );
        foreach (['tracker.example.test', 'javascript:', 'bad policy', 'unsupported-policy', 'bad-token', 'url('] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected unsafe document metadata to stay diagnostic-only: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to omit unsafe document metadata: ' . $blocked);
        }
    },
    'converts passive property metadata into reviewer spans and links before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<meta property="og:title" content="Legacy &amp; share&#10; title">'
            . '<meta property="OG:Description" content="&lt;Legacy excerpt&gt;">'
            . '<meta property="article:published_time" content="2026-06-06T10:00:00Z">'
            . '<meta property="twitter:title" content="Social card title">'
            . '<meta property="og:image" content="https://cdn.example.test/cover.png">'
            . '<meta property="twitter:image" content="https://cdn.example.test/social.png">'
            . '<meta property="og:title" content="   ">'
            . '<meta name="description" content="Named metadata still survives">'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/meta-property-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<span data-pandoc-meta-property="og:title" data-pandoc-meta-content="Legacy &amp; share title">Open Graph title: Legacy &amp; share title</span>'
            . '<span data-pandoc-meta-property="og:description" data-pandoc-meta-content="&lt;Legacy excerpt&gt;">Open Graph description: &lt;Legacy excerpt&gt;</span>'
            . '<span data-pandoc-meta-property="article:published_time" data-pandoc-meta-content="2026-06-06T10:00:00Z">Article published time: 2026-06-06T10:00:00Z</span>'
            . '<span data-pandoc-meta-property="twitter:title" data-pandoc-meta-content="Social card title">Twitter title: Social card title</span>'
            . '<a href="https://cdn.example.test/cover.png" data-pandoc-meta-property="og:image" data-pandoc-meta-content="https://cdn.example.test/cover.png" data-pandoc-meta-url="true">Open Graph image</a>'
            . '<a href="https://cdn.example.test/social.png" data-pandoc-meta-property="twitter:image" data-pandoc-meta-content="https://cdn.example.test/social.png" data-pandoc-meta-url="true">Twitter image</a>'
            . '<span data-pandoc-meta-name="description" data-pandoc-meta-content="Named metadata still survives">Description: Named metadata still survives</span>'
            . '<p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Open Graph title: Legacy & share titleOpen Graph description: <Legacy excerpt>Article published time: 2026-06-06T10:00:00ZTwitter title: Social card titleOpen Graph imageTwitter imageDescription: Named metadata still survivesafter', $fragment->textContent());
        $t->same(['a', 'p', 'span'], $summary['elementNames']);
        $t->same(['base', 'meta'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag'], $policyDiagnostics);
        $t->same([
            'data-pandoc-meta-property' => 'og:title',
            'data-pandoc-meta-content' => 'Legacy & share title',
        ], $nodes[0]['attrs']);
        $t->same('Open Graph title: Legacy & share title', $nodes[0]['children'][0]['text']);
        $t->same('og:description', $nodes[1]['attrs']['data-pandoc-meta-property']);
        $t->same('<Legacy excerpt>', $nodes[1]['attrs']['data-pandoc-meta-content']);
        $t->same('article:published_time', $nodes[2]['attrs']['data-pandoc-meta-property']);
        $t->same('twitter:title', $nodes[3]['attrs']['data-pandoc-meta-property']);
        $t->same([
            'href' => 'https://cdn.example.test/cover.png',
            'data-pandoc-meta-property' => 'og:image',
            'data-pandoc-meta-content' => 'https://cdn.example.test/cover.png',
            'data-pandoc-meta-url' => 'true',
        ], $nodes[4]['attrs']);
        $t->same('Open Graph image', $nodes[4]['children'][0]['text']);
        $t->same([
            'href' => 'https://cdn.example.test/social.png',
            'data-pandoc-meta-property' => 'twitter:image',
            'data-pandoc-meta-content' => 'https://cdn.example.test/social.png',
            'data-pandoc-meta-url' => 'true',
        ], $nodes[5]['attrs']);
        $t->same('Twitter image', $nodes[5]['children'][0]['text']);
        $t->same('description', $nodes[6]['attrs']['data-pandoc-meta-name']);
        $t->same('p', $nodes[7]['name']);
        $t->same('/migration/meta-property-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<meta'), 'Expected original meta property elements to be stripped from sanitized output');
        $t->true(!str_contains($html, '<Legacy excerpt>'), 'Expected tag-looking property metadata text to remain escaped');
    },
    'normalizes social image meta URLs into inert reviewer links before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<meta property="og:image" content=" ./social-cover.png&#10;">'
            . '<meta property="og:image:secure_url" content="https://cdn.example.test/secure-cover.jpg">'
            . '<meta name="twitter:image" content="../media/twitter-card.png">'
            . '<meta property="twitter:image:src" content="java&#10;script:alert(1)">'
            . '<meta property="og:image" content="data:image/png;base64,iVBORw0KGgo=">'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/social-image-meta-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<a href="https://source.example.test/import/posts/social-cover.png" data-pandoc-meta-property="og:image" data-pandoc-meta-content="https://source.example.test/import/posts/social-cover.png" data-pandoc-meta-url="true">Open Graph image</a>'
            . '<a href="https://cdn.example.test/secure-cover.jpg" data-pandoc-meta-property="og:image:secure_url" data-pandoc-meta-content="https://cdn.example.test/secure-cover.jpg" data-pandoc-meta-url="true">Open Graph secure image</a>'
            . '<a href="https://source.example.test/import/media/twitter-card.png" data-pandoc-meta-name="twitter:image" data-pandoc-meta-content="https://source.example.test/import/media/twitter-card.png" data-pandoc-meta-url="true">Twitter image</a>'
            . '<p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Open Graph imageOpen Graph secure imageTwitter imageafter', $fragment->textContent());
        $t->same(['a', 'p'], $summary['elementNames']);
        $t->same(['base', 'meta'], $summary['blockedTags']);
        $t->same(['content'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'normalized-url', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'unsafe-url', 'blocked-tag', 'unsafe-url'], $policyDiagnostics);
        $t->same([
            'href' => 'https://source.example.test/import/posts/social-cover.png',
            'data-pandoc-meta-property' => 'og:image',
            'data-pandoc-meta-content' => 'https://source.example.test/import/posts/social-cover.png',
            'data-pandoc-meta-url' => 'true',
        ], $nodes[0]['attrs']);
        $t->same('Open Graph image', $nodes[0]['children'][0]['text']);
        $t->same('https://cdn.example.test/secure-cover.jpg', $nodes[1]['attrs']['href']);
        $t->same('og:image:secure_url', $nodes[1]['attrs']['data-pandoc-meta-property']);
        $t->same('Open Graph secure image', $nodes[1]['children'][0]['text']);
        $t->same([
            'href' => 'https://source.example.test/import/media/twitter-card.png',
            'data-pandoc-meta-name' => 'twitter:image',
            'data-pandoc-meta-content' => 'https://source.example.test/import/media/twitter-card.png',
            'data-pandoc-meta-url' => 'true',
        ], $nodes[2]['attrs']);
        $t->same('p', $nodes[3]['name']);
        $t->same('/migration/social-image-meta-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<meta'), 'Expected original social image meta elements to be stripped from sanitized output');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe social image URL metadata to be stripped');
        $t->true(!str_contains($html, 'data:image/png'), 'Expected data image metadata to stay hidden until explicit media import');
        $t->true(!str_contains($blocks, '<img'), 'Expected social image metadata to remain reviewer links, not active image loads');
    },
    'converts passive link relations into reviewer links while dropping active resources' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<link rel="canonical" href="../canonical/post.html" title="Canonical source">'
            . '<link rel="alternate" hreflang="fr" type="text/html" href="./fr/post.html" title="Version francaise">'
            . '<link rel="shortlink" href="?p=42">'
            . '<link rel="alternate stylesheet" href="./legacy.css" title="Legacy theme">'
            . '<link rel="canonical" href="java&#10;script:alert(1)" title="Bad canonical">'
            . '<link rel="preload" as="image" href="./cover.png">'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/link-relation-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<a href="https://source.example.test/import/canonical/post.html" data-pandoc-link-rel="canonical" title="Canonical source">Canonical source</a>'
            . '<a href="https://source.example.test/import/posts/fr/post.html" data-pandoc-link-rel="alternate" hreflang="fr" type="text/html" title="Version francaise">Version francaise</a>'
            . '<a href="https://source.example.test/import/posts/post.html?p=42" data-pandoc-link-rel="shortlink">Shortlink</a>'
            . '<p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Canonical sourceVersion francaiseShortlinkafter', $fragment->textContent());
        $t->same(['a', 'p'], $summary['elementNames']);
        $t->same(['base', 'link'], $summary['blockedTags']);
        $t->same(['href'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'unsafe-url', 'blocked-tag'], $policyDiagnostics);
        $t->same('a', $nodes[0]['name']);
        $t->same([
            'href' => 'https://source.example.test/import/canonical/post.html',
            'data-pandoc-link-rel' => 'canonical',
            'title' => 'Canonical source',
        ], $nodes[0]['attrs']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/fr/post.html',
            'data-pandoc-link-rel' => 'alternate',
            'hreflang' => 'fr',
            'type' => 'text/html',
            'title' => 'Version francaise',
        ], $nodes[1]['attrs']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/post.html?p=42',
            'data-pandoc-link-rel' => 'shortlink',
        ], $nodes[2]['attrs']);
        $t->same('/migration/link-relation-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<link'), 'Expected link elements to be stripped from sanitized output');
        $t->true(!str_contains($html, 'legacy.css'), 'Expected alternate stylesheet resource to be dropped');
        $t->true(!str_contains($html, 'cover.png'), 'Expected preload resource link to be dropped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe link relation URL to be stripped');
    },
    'converts editorial passive link relations into reviewer links before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<link rel="author" href="./authors/migration.html">'
            . '<link rel="license" type="text/html" href="../license.html" title="Reuse terms">'
            . '<link rel="help" href="?help=import">'
            . '<link rel="bookmark" href="#chapter-1" title="Chapter anchor">'
            . '<link rel="author preload" href="./active-author.html" title="Active author">'
            . '<link rel="license" href="java&#10;script:alert(1)" title="Bad license">'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/editorial-link-relation-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<a href="https://source.example.test/import/posts/authors/migration.html" data-pandoc-link-rel="author">Author source</a>'
            . '<a href="https://source.example.test/import/license.html" data-pandoc-link-rel="license" type="text/html" title="Reuse terms">Reuse terms</a>'
            . '<a href="https://source.example.test/import/posts/post.html?help=import" data-pandoc-link-rel="help">Help source</a>'
            . '<a href="https://source.example.test/import/posts/post.html#chapter-1" data-pandoc-link-rel="bookmark" title="Chapter anchor">Chapter anchor</a>'
            . '<p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Author sourceReuse termsHelp sourceChapter anchorafter', $fragment->textContent());
        $t->same(['a', 'p'], $summary['elementNames']);
        $t->same(['base', 'link'], $summary['blockedTags']);
        $t->same(['href'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'unsafe-url'], $policyDiagnostics);
        $t->same([
            'href' => 'https://source.example.test/import/posts/authors/migration.html',
            'data-pandoc-link-rel' => 'author',
        ], $nodes[0]['attrs']);
        $t->same('Author source', $nodes[0]['children'][0]['text']);
        $t->same([
            'href' => 'https://source.example.test/import/license.html',
            'data-pandoc-link-rel' => 'license',
            'type' => 'text/html',
            'title' => 'Reuse terms',
        ], $nodes[1]['attrs']);
        $t->same('help', $nodes[2]['attrs']['data-pandoc-link-rel']);
        $t->same('bookmark', $nodes[3]['attrs']['data-pandoc-link-rel']);
        $t->same('/migration/editorial-link-relation-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<link'), 'Expected editorial link elements to be stripped from sanitized output');
        $t->true(!str_contains($html, 'active-author.html'), 'Expected mixed active author/preload relation to be dropped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe passive link target to be stripped');
        $t->true(!str_contains($html, 'Bad license'), 'Expected unsafe passive link title to remain hidden');
    },
    'converts navigational passive link relations into reviewer links before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/chapter-02.html">'
            . '<link rel="prev" href="./chapter-01.html">'
            . '<link rel="next" href="./chapter-03.html" title="Next chapter">'
            . '<link rel="contents index" href="../toc.html">'
            . '<link rel="search" type="application/opensearchdescription+xml" href="./search.xml">'
            . '<link rel="up preload" href="../book.html" title="Dropped parent preload">'
            . '<link rel="previous" href="java&#10;script:alert(1)" title="Bad previous">'
            . '<p>after</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/navigation-link-relation-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<a href="https://source.example.test/import/posts/chapter-01.html" data-pandoc-link-rel="prev">Previous source</a>'
            . '<a href="https://source.example.test/import/posts/chapter-03.html" data-pandoc-link-rel="next" title="Next chapter">Next chapter</a>'
            . '<a href="https://source.example.test/import/toc.html" data-pandoc-link-rel="contents index">Contents source</a>'
            . '<a href="https://source.example.test/import/posts/search.xml" data-pandoc-link-rel="search" type="application/opensearchdescription+xml">Search source</a>'
            . '<p>after</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/chapter-02.html', $fragment->baseUrl());
        $t->same('Previous sourceNext chapterContents sourceSearch sourceafter', $fragment->textContent());
        $t->same(['a', 'p'], $summary['elementNames']);
        $t->same(['base', 'link'], $summary['blockedTags']);
        $t->same(['href'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'unsafe-url'], $policyDiagnostics);
        $t->same([
            'href' => 'https://source.example.test/import/posts/chapter-01.html',
            'data-pandoc-link-rel' => 'prev',
        ], $nodes[0]['attrs']);
        $t->same('Previous source', $nodes[0]['children'][0]['text']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/chapter-03.html',
            'data-pandoc-link-rel' => 'next',
            'title' => 'Next chapter',
        ], $nodes[1]['attrs']);
        $t->same([
            'href' => 'https://source.example.test/import/toc.html',
            'data-pandoc-link-rel' => 'contents index',
        ], $nodes[2]['attrs']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/search.xml',
            'data-pandoc-link-rel' => 'search',
            'type' => 'application/opensearchdescription+xml',
        ], $nodes[3]['attrs']);
        $t->same('/migration/navigation-link-relation-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/chapter-02.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<link'), 'Expected navigation link elements to be stripped from sanitized output');
        $t->true(!str_contains($html, 'Dropped parent preload'), 'Expected mixed active up/preload relation to be dropped');
        $t->true(!str_contains($html, 'book.html'), 'Expected active preload relation target to be dropped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe navigation relation URL to be stripped');
        $t->true(!str_contains($html, 'Bad previous'), 'Expected unsafe navigation relation title to remain hidden');
    },
    'filters active navigation target download and opener rel side effects before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<p>'
            . '<a href="./packet.html" target="_blank" rel="noopener opener noreferrer opener" download="packet.html">packet</a>'
            . '<a href="./safe.html" rel="Author TAG">safe</a>'
            . '<map name="review-map"><area href="./map.html" target="review-frame" rel="opener nofollow" alt="map"></map>'
            . '</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/navigation-side-effect-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<p>'
            . '<a href="https://source.example.test/import/posts/packet.html" data-pandoc-link-target="_blank" rel="noopener noreferrer" data-pandoc-link-download="packet.html">packet</a>'
            . '<a href="https://source.example.test/import/posts/safe.html" rel="author tag">safe</a>'
            . '<a href="https://source.example.test/import/posts/map.html" data-pandoc-image-map-area="true" data-pandoc-image-map-name="review-map" data-pandoc-image-map-alt="map" rel="nofollow">map</a>'
            . '</p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('packetsafemap', $fragment->textContent());
        $t->same(['a', 'p'], $summary['elementNames']);
        $t->same(['area', 'base', 'map'], $summary['blockedTags']);
        $t->same(['download', 'rel', 'target'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'link-browsing-review', 'unsafe-attribute', 'link-browsing-review', 'blocked-tag', 'blocked-tag', 'unsafe-attribute', 'unsafe-attribute'], $policyDiagnostics);
        $t->same('p', $nodes[0]['name']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/packet.html',
            'data-pandoc-link-target' => '_blank',
            'rel' => 'noopener noreferrer',
            'data-pandoc-link-download' => 'packet.html',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/safe.html',
            'rel' => 'author tag',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/map.html',
            'data-pandoc-image-map-area' => 'true',
            'data-pandoc-image-map-name' => 'review-map',
            'data-pandoc-image-map-alt' => 'map',
            'rel' => 'nofollow',
        ], $nodes[0]['children'][2]['attrs']);
        $t->same('/migration/navigation-side-effect-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, ' target='), 'Expected live browsing-context targets to be stripped');
        $t->true(!str_contains($html, ' download='), 'Expected live download side effects to be stripped');
        $t->true(!str_contains($html, '<map'), 'Expected live image map wrapper to be stripped');
        $t->true(!str_contains($html, '<area'), 'Expected live image map area to be converted into an inert reviewer link');
        $t->same(0, preg_match('/(?:^|[\s"])opener(?:[\s"]|$)/', $html), 'Expected opener rel tokens to be stripped');
        $t->true(!str_contains($blocks, ' target='), 'Expected WordPress blocks to omit live target attributes');
        $t->true(!str_contains($blocks, ' download='), 'Expected WordPress blocks to omit live download attributes');
    },
    'converts element referrer policies into inert reviewer metadata' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<link rel="canonical" href="./canonical.html" referrerpolicy="origin-when-cross-origin" title="Canonical policy">'
            . '<p>'
            . '<a href="./packet.html" referrerpolicy=" Strict-Origin ">packet</a>'
            . '<img src="./cover.png" referrerpolicy="no-referrer" alt="Cover">'
            . '<a href="./bad.html" referrerpolicy="bad policy">bad</a>'
            . '<map name="review-map"><area href="./map.html" alt="map" referrerpolicy="unsafe-url"></map>'
            . '</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/referrer-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<a href="https://source.example.test/import/posts/canonical.html" data-pandoc-link-rel="canonical" title="Canonical policy" data-pandoc-referrerpolicy="origin-when-cross-origin">Canonical policy</a>'
            . '<p><a href="https://source.example.test/import/posts/packet.html" data-pandoc-referrerpolicy="strict-origin">packet</a>'
            . '<img src="https://source.example.test/import/posts/cover.png" data-pandoc-referrerpolicy="no-referrer" alt="Cover">'
            . '<a href="https://source.example.test/import/posts/bad.html">bad</a>'
            . '<a href="https://source.example.test/import/posts/map.html" data-pandoc-image-map-area="true" data-pandoc-image-map-name="review-map" data-pandoc-image-map-alt="map" data-pandoc-referrerpolicy="unsafe-url">map</a></p>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Canonical policypacketbadmap', $fragment->textContent());
        $t->same(['a', 'img', 'p'], $summary['elementNames']);
        $t->same(['area', 'base', 'link', 'map'], $summary['blockedTags']);
        $t->same(['referrerpolicy'], $summary['filteredAttributes']);
        $t->same([
            'blocked-tag',
            'blocked-tag',
            'referrer-policy-review',
            'referrer-policy-review',
            'referrer-policy-review',
            'unsafe-attribute',
            'blocked-tag',
            'blocked-tag',
            'referrer-policy-review',
        ], $policyDiagnostics);
        $t->same('a', $nodes[0]['name']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/canonical.html',
            'data-pandoc-link-rel' => 'canonical',
            'title' => 'Canonical policy',
            'data-pandoc-referrerpolicy' => 'origin-when-cross-origin',
        ], $nodes[0]['attrs']);
        $t->same('strict-origin', $nodes[1]['children'][0]['attrs']['data-pandoc-referrerpolicy']);
        $t->same('no-referrer', $nodes[1]['children'][1]['attrs']['data-pandoc-referrerpolicy']);
        $t->same('unsafe-url', $nodes[1]['children'][3]['attrs']['data-pandoc-referrerpolicy']);
        $t->same('/migration/referrer-policy-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, ' referrerpolicy='), 'Expected live referrerpolicy attributes to be replaced by inert metadata');
        $t->true(!str_contains($html, 'bad policy'), 'Expected invalid referrer policy values to stay hidden');
        $t->true(!str_contains($blocks, ' referrerpolicy='), 'Expected WordPress blocks to omit live referrerpolicy attributes');
    },
    'converts image resource policy hints into inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<figure>'
            . '<img src="./hero.jpg" loading=" Lazy " decoding="ASYNC" fetchpriority="HIGH" crossorigin="" data-pandoc-image-loading="source-spoof" alt="Hero">'
            . '<img src="./eager.jpg" loading="eager" decoding="sync" fetchpriority="low" crossorigin="use-credentials" alt="Eager">'
            . '<img src="./auto.jpg" decoding="auto" fetchpriority="auto" crossorigin="anonymous" alt="Auto">'
            . '<img src="./bad.jpg" loading="soon" decoding="fast" fetchpriority="urgent" crossorigin="credentialed" alt="Bad">'
            . '</figure>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/image-resource-policy-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<figure>'
            . '<img src="https://source.example.test/import/posts/hero.jpg" data-pandoc-image-loading="lazy" data-pandoc-image-decoding="async" data-pandoc-image-fetchpriority="high" data-pandoc-image-crossorigin="anonymous" alt="Hero">'
            . '<img src="https://source.example.test/import/posts/eager.jpg" data-pandoc-image-loading="eager" data-pandoc-image-decoding="sync" data-pandoc-image-fetchpriority="low" data-pandoc-image-crossorigin="use-credentials" alt="Eager">'
            . '<img src="https://source.example.test/import/posts/auto.jpg" data-pandoc-image-decoding="auto" data-pandoc-image-fetchpriority="auto" data-pandoc-image-crossorigin="anonymous" alt="Auto">'
            . '<img src="https://source.example.test/import/posts/bad.jpg" alt="Bad">'
            . '</figure>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $diagnosticCounts = array_count_values($policyDiagnostics);

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('', $fragment->textContent());
        $t->same(['figure', 'img'], $summary['elementNames']);
        $t->same(['base'], $summary['blockedTags']);
        $t->same(['crossorigin', 'data-pandoc-image-loading', 'decoding', 'fetchpriority', 'loading'], $summary['filteredAttributes']);
        $t->same(18, $summary['diagnostics']);
        $t->same(11, $diagnosticCounts['image-resource-policy-review'] ?? 0);
        $t->same(5, $diagnosticCounts['unsafe-attribute'] ?? 0);
        $t->same(1, $diagnosticCounts['blocked-tag'] ?? 0);
        $t->same('figure', $nodes[0]['name']);
        $t->same([
            'src' => 'https://source.example.test/import/posts/hero.jpg',
            'data-pandoc-image-loading' => 'lazy',
            'data-pandoc-image-decoding' => 'async',
            'data-pandoc-image-fetchpriority' => 'high',
            'data-pandoc-image-crossorigin' => 'anonymous',
            'alt' => 'Hero',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same([
            'src' => 'https://source.example.test/import/posts/eager.jpg',
            'data-pandoc-image-loading' => 'eager',
            'data-pandoc-image-decoding' => 'sync',
            'data-pandoc-image-fetchpriority' => 'low',
            'data-pandoc-image-crossorigin' => 'use-credentials',
            'alt' => 'Eager',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same([
            'src' => 'https://source.example.test/import/posts/auto.jpg',
            'data-pandoc-image-decoding' => 'auto',
            'data-pandoc-image-fetchpriority' => 'auto',
            'data-pandoc-image-crossorigin' => 'anonymous',
            'alt' => 'Auto',
        ], $nodes[0]['children'][2]['attrs']);
        $t->same([
            'src' => 'https://source.example.test/import/posts/bad.jpg',
            'alt' => 'Bad',
        ], $nodes[0]['children'][3]['attrs']);
        $t->same('/migration/image-resource-policy-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach ([' loading=', ' decoding=', ' fetchpriority=', ' crossorigin=', 'source-spoof', 'soon', 'urgent', 'credentialed'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected image resource policy sanitizer to remove live or unsafe source content: ' . $blocked);
            $t->true(!str_contains($blocks, $blocked), 'Expected WordPress blocks to remove live or unsafe source content: ' . $blocked);
        }
    },
    'adds source line metadata to image resource policy diagnostics before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            "<figure>\n"
            . "<img src=\"./hero.jpg\" loading=\" Lazy \" decoding=\"ASYNC\" fetchpriority=\"HIGH\" crossorigin=\"\" alt=\"Hero\">\n"
            . "<img src=\"./bad.jpg\" loading=\"soon\" decoding=\"fast\" fetchpriority=\"urgent\" crossorigin=\"credentialed\" alt=\"Bad\">\n"
            . '</figure>',
            'https://source.example.test/import/posts/post.html'
        );
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/image-resource-policy-lines-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $imagePolicyDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static function (array $diagnostic): bool {
                $code = (string) ($diagnostic['code'] ?? '');
                $reason = (string) ($diagnostic['reason'] ?? '');

                return $code === 'image-resource-policy-review'
                    || ($code === 'unsafe-attribute' && $reason === 'invalid-image-resource-policy-metadata');
            }
        ));

        $t->same('<figure>' . "\n"
            . '<img src="https://source.example.test/import/posts/hero.jpg" data-pandoc-image-loading="lazy" data-pandoc-image-decoding="async" data-pandoc-image-fetchpriority="high" data-pandoc-image-crossorigin="anonymous" alt="Hero">' . "\n"
            . '<img src="https://source.example.test/import/posts/bad.jpg" alt="Bad">' . "\n"
            . '</figure>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/image-resource-policy-lines-review.html', $document->children[0]->attr('part'));
        $t->same(8, count($imagePolicyDiagnostics));
        $t->same(
            ['loading', 'decoding', 'fetchpriority', 'crossorigin', 'loading', 'decoding', 'fetchpriority', 'crossorigin'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['attribute'] ?? ''), $imagePolicyDiagnostics)
        );
        $t->same(
            ['image-resource-policy-review', 'image-resource-policy-review', 'image-resource-policy-review', 'image-resource-policy-review', 'unsafe-attribute', 'unsafe-attribute', 'unsafe-attribute', 'unsafe-attribute'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $imagePolicyDiagnostics)
        );
        $t->same([2, 2, 2, 2, 3, 3, 3, 3], array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $imagePolicyDiagnostics));
        $t->true(!str_contains($html, ' loading='), 'Expected live loading attributes to stay stripped');
        $t->true(!str_contains($html, ' urgent'), 'Expected invalid fetchpriority source value to stay diagnostic-only');
        $t->true(!str_contains($blocks, 'credentialed'), 'Expected invalid crossorigin source value to stay diagnostic-only');
    },
    'adds source line metadata to URL repair diagnostics before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            "<article>\n"
            . "<link rel=\"canonical\" href=\" h&#9;ttps://example.test/review \" title=\"Canonical\">\n"
            . "<meta http-equiv=\"refresh\" content=\"0; url=java&#10;script:alert(1)\">\n"
            . "<iframe src=\" ./frame.html&#10;\" title=\"Frame\"></iframe>\n"
            . "<blockquote cite=\" ./quote.html&#10;\"><p>Quote</p></blockquote>\n"
            . "<ins cite=\"java&#10;script:alert(1)\" datetime=\"2026-06-08\">Bad cite</ins>\n"
            . "<map name=\"m\"><area href=\" ./map.html&#10;\" alt=\"Map\"></map>\n"
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/url-diagnostic-lines-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $urlDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static function (array $diagnostic): bool {
                $code = (string) ($diagnostic['code'] ?? '');
                $attribute = (string) ($diagnostic['attribute'] ?? '');

                return in_array($code, ['unsafe-url', 'normalized-url', 'quote-cite-review', 'revision-metadata-review'], true)
                    && in_array($attribute, ['href', 'content', 'src', 'cite', 'datetime'], true);
            }
        ));
        $astUrlDiagnostics = array_values(array_filter(
            $document->children[0]->attr('diagnostics'),
            static function (array $diagnostic): bool {
                $code = (string) ($diagnostic['code'] ?? '');
                $attribute = (string) ($diagnostic['attribute'] ?? '');

                return in_array($code, ['unsafe-url', 'normalized-url', 'quote-cite-review', 'revision-metadata-review'], true)
                    && in_array($attribute, ['href', 'content', 'src', 'cite', 'datetime'], true);
            }
        ));

        $t->contains('<a href="https://example.test/review" data-pandoc-link-rel="canonical" title="Canonical">Canonical</a>', $html);
        $t->contains('<a href="https://source.example.test/import/posts/frame.html" data-pandoc-iframe-src="true" title="Frame">Frame</a>', $html);
        $t->contains('<blockquote data-pandoc-quote-cite="https://source.example.test/import/posts/quote.html"><p>Quote</p></blockquote>', $html);
        $t->contains('<ins data-pandoc-revision-datetime="2026-06-08" data-pandoc-revision-kind="date">Bad cite</ins>', $html);
        $t->contains('<a href="https://source.example.test/import/posts/map.html" data-pandoc-image-map-area="true" data-pandoc-image-map-name="m" data-pandoc-image-map-alt="Map">Map</a>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/url-diagnostic-lines-review.html', $document->children[0]->attr('part'));
        $t->same(8, count($urlDiagnostics));
        $t->same(
            ['normalized-url', 'unsafe-url', 'normalized-url', 'normalized-url', 'quote-cite-review', 'unsafe-url', 'revision-metadata-review', 'normalized-url'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $urlDiagnostics)
        );
        $t->same(
            ['link', 'meta', 'iframe', 'blockquote', 'blockquote', 'ins', 'ins', 'area'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['tag'] ?? ''), $urlDiagnostics)
        );
        $t->same(
            ['href', 'content', 'src', 'cite', 'cite', 'cite', 'datetime', 'href'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['attribute'] ?? ''), $urlDiagnostics)
        );
        $t->same([2, 3, 4, 5, 5, 6, 6, 7], array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $urlDiagnostics));
        $t->same(
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $urlDiagnostics),
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $astUrlDiagnostics)
        );
        $t->true(!str_contains($html, '<link'), 'Expected source link elements to become inert review links');
        $t->true(!str_contains($html, '<meta'), 'Expected source meta refresh elements to be stripped');
        $t->true(!str_contains($html, '<iframe'), 'Expected source iframe elements to become inert review links');
        $t->true(!str_contains($html, '<map'), 'Expected image map wrapper to be stripped');
        $t->true(!str_contains($html, '<area'), 'Expected image map area to become an inert review link');
        $t->true(!str_contains($html, 'javascript:'), 'Expected active URL schemes to stay diagnostic-only');
        $t->true(!str_contains($blocks, 'java&#10;script'), 'Expected WordPress blocks to omit active split-scheme URL sources');
    },
    'adds source line metadata to iframe referrer and image map helper diagnostics before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            "<article>\n"
            . "<iframe src=\"./frame.html\" title=\"Policy\" sandbox=\"allow-scripts bad-token\" referrerpolicy=\"bad policy\"></iframe>\n"
            . "<a href=\"./packet.html\" referrerpolicy=\"strict-origin\">packet</a>\n"
            . "<img src=\"./cover.png\" referrerpolicy=\"no-referrer\" alt=\"Cover\">\n"
            . "<map name=\"review-map\"><area shape=\"star\" coords=\"1, two, 3\" href=\"./map.html\" alt=\"Map\" referrerpolicy=\"origin\"></map>\n"
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/iframe-map-diagnostic-lines-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $diagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static function (array $diagnostic): bool {
                $code = (string) ($diagnostic['code'] ?? '');
                $tag = (string) ($diagnostic['tag'] ?? '');
                $attribute = (string) ($diagnostic['attribute'] ?? '');

                return (
                    $code === 'referrer-policy-review'
                    || ($code === 'unsafe-attribute' && in_array($attribute, ['sandbox', 'referrerpolicy', 'shape', 'coords'], true))
                ) && in_array($tag, ['iframe', 'a', 'img', 'area'], true);
            }
        ));
        $astDiagnostics = array_values(array_filter(
            $document->children[0]->attr('diagnostics'),
            static function (array $diagnostic): bool {
                $code = (string) ($diagnostic['code'] ?? '');
                $tag = (string) ($diagnostic['tag'] ?? '');
                $attribute = (string) ($diagnostic['attribute'] ?? '');

                return (
                    $code === 'referrer-policy-review'
                    || ($code === 'unsafe-attribute' && in_array($attribute, ['sandbox', 'referrerpolicy', 'shape', 'coords'], true))
                ) && in_array($tag, ['iframe', 'a', 'img', 'area'], true);
            }
        ));
        $invalidSrcdocFragment = Html5DomFragment::fromHtml(
            "<article>\n"
            . "<iframe srcdoc=\"&lt;!DOCTYPE html&gt;&lt;p&gt;unsafe&lt;/p&gt;\" src=\"./fallback.html\" title=\"Fallback frame\"></iframe>\n"
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $invalidSrcdocDocument = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $invalidSrcdocFragment->toRawHtmlAst(['part' => '/migration/iframe-srcdoc-diagnostic-lines-review.html']),
        ]);
        $invalidSrcdocDiagnostics = array_values(array_filter(
            $invalidSrcdocFragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'invalid-srcdoc'
        ));
        $invalidSrcdocAstDiagnostics = array_values(array_filter(
            $invalidSrcdocDocument->children[0]->attr('diagnostics'),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') === 'invalid-srcdoc'
        ));

        $t->contains('<a href="https://source.example.test/import/posts/frame.html" data-pandoc-iframe-src="true" title="Policy" data-pandoc-iframe-sandbox="allow-scripts">Policy</a>', $html);
        $t->contains('<a href="https://source.example.test/import/posts/packet.html" data-pandoc-referrerpolicy="strict-origin">packet</a>', $html);
        $t->contains('<img src="https://source.example.test/import/posts/cover.png" data-pandoc-referrerpolicy="no-referrer" alt="Cover">', $html);
        $t->contains('<a href="https://source.example.test/import/posts/map.html" data-pandoc-image-map-area="true" data-pandoc-image-map-name="review-map" data-pandoc-image-map-alt="Map" data-pandoc-referrerpolicy="origin">Map</a>', $html);
        $t->contains($html, $blocks);
        $t->same('/migration/iframe-map-diagnostic-lines-review.html', $document->children[0]->attr('part'));
        $t->same(7, count($diagnostics));
        $t->same(
            ['unsafe-attribute', 'unsafe-attribute', 'referrer-policy-review', 'referrer-policy-review', 'unsafe-attribute', 'unsafe-attribute', 'referrer-policy-review'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''), $diagnostics)
        );
        $t->same(
            ['iframe', 'iframe', 'a', 'img', 'area', 'area', 'area'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['tag'] ?? ''), $diagnostics)
        );
        $t->same(
            ['sandbox', 'referrerpolicy', 'referrerpolicy', 'referrerpolicy', 'shape', 'coords', 'referrerpolicy'],
            array_map(static fn (array $diagnostic): string => (string) ($diagnostic['attribute'] ?? ''), $diagnostics)
        );
        $t->same([2, 2, 3, 4, 5, 5, 5], array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $diagnostics));
        $t->same(
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $diagnostics),
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $astDiagnostics)
        );
        $t->contains('<a href="https://source.example.test/import/posts/fallback.html" data-pandoc-iframe-src="true" title="Fallback frame">Fallback frame</a>', $invalidSrcdocFragment->serialize());
        $t->same(1, count($invalidSrcdocDiagnostics));
        $t->same([2], array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $invalidSrcdocDiagnostics));
        $t->same(
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $invalidSrcdocDiagnostics),
            array_map(static fn (array $diagnostic): ?int => $diagnostic['line'] ?? null, $invalidSrcdocAstDiagnostics)
        );
        $t->true(!str_contains($invalidSrcdocFragment->serialize(), '<!DOCTYPE'), 'Expected invalid srcdoc declaration to stay diagnostic-only');
        $t->true(!str_contains($html, '<iframe'), 'Expected iframe elements to become inert review links or dropped srcdoc content');
        $t->true(!str_contains($html, '<map'), 'Expected map wrapper to be stripped');
        $t->true(!str_contains($html, '<area'), 'Expected area element to become inert review link');
        $t->true(!str_contains($html, 'bad-token'), 'Expected invalid iframe sandbox token to stay diagnostic-only');
        $t->true(!str_contains($html, 'bad policy'), 'Expected invalid iframe referrer policy to stay diagnostic-only');
        $t->true(!str_contains($html, 'data-pandoc-image-map-shape'), 'Expected invalid area shape to stay diagnostic-only');
        $t->true(!str_contains($html, 'data-pandoc-image-map-coords'), 'Expected invalid area coords to stay diagnostic-only');
    },
    'converts base target defaults into inert browsing-context metadata' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<template><base target="inactive-frame"><a href="template-note.html">template note</a></template>'
            . '<base target=" review-frame ">'
            . '<base href="https://source.example.test/import/posts/post.html" target="ignored-frame">'
            . '<article><a href="doc.html">doc</a></article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/base-target-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<span data-pandoc-meta-name="base-target" data-pandoc-meta-source="base" data-pandoc-meta-content="review-frame">Base target: review-frame</span>'
            . '<a href="https://source.example.test/import/posts/template-note.html">template note</a>'
            . '<article><a href="https://source.example.test/import/posts/doc.html">doc</a></article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Base target: review-frametemplate notedoc', $fragment->textContent());
        $t->same(['a', 'article', 'span'], $summary['elementNames']);
        $t->same(['base', 'template'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['base-target-review', 'duplicate-base-ignored', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag'], $policyDiagnostics);
        $t->same('span', $nodes[0]['name']);
        $t->same([
            'data-pandoc-meta-name' => 'base-target',
            'data-pandoc-meta-source' => 'base',
            'data-pandoc-meta-content' => 'review-frame',
        ], $nodes[0]['attrs']);
        $t->same('Base target: review-frame', $nodes[0]['children'][0]['text']);
        $t->same('a', $nodes[1]['name']);
        $t->same('https://source.example.test/import/posts/template-note.html', $nodes[1]['attrs']['href']);
        $t->same('https://source.example.test/import/posts/doc.html', $nodes[2]['children'][0]['attrs']['href']);
        $t->same('/migration/base-target-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<base'), 'Expected base elements to be stripped from sanitized output');
        $t->true(!str_contains($html, 'target='), 'Expected base target to be review metadata rather than a live target attribute');
        $t->true(!str_contains($blocks, 'target='), 'Expected WordPress blocks to omit live target attributes');

        $malformed = Html5DomFragment::fromHtml(
            '<base target="review&#10;<frame">'
            . '<base href="https://source.example.test/import/posts/post.html">'
            . '<a href="./doc.html">doc</a>'
        );
        $malformedDiagnostics = array_values(array_filter(
            $malformed->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same(
            '<span data-pandoc-meta-name="base-target" data-pandoc-meta-source="base" data-pandoc-meta-content="_blank">Base target: _blank</span>'
                . '<a href="https://source.example.test/import/posts/doc.html">doc</a>',
            $malformed->serialize()
        );
        $t->same(['unsafe-attribute', 'base-target-review', 'blocked-tag', 'blocked-tag'], $malformedDiagnostics);
        $t->true(!str_contains($malformed->serialize(), '<frame'), 'Expected malformed target markup text to stay out of review HTML');
    },
    'adds source line metadata to base helper diagnostics before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            "<p>before</p>\n"
            . '<base href="java&#10;script:alert(1)" target="bad{frame">' . "\n"
            . '<a href="doc.html">doc</a>',
            'https://fallback.example.test/import/source.html'
        );
        $baseDiagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['tag'] ?? '') === 'base'
        ));

        $t->same(
            "<p>before</p>\n\n<a href=\"https://fallback.example.test/import/doc.html\">doc</a>",
            $fragment->serialize()
        );
        $t->same('https://fallback.example.test/import/source.html', $fragment->baseUrl());
        $t->same(['normalized-url', 'unsafe-url', 'unsafe-attribute', 'blocked-tag'], array_map(
            static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''),
            $baseDiagnostics
        ));
        $t->same(['href', 'href', 'target', ''], array_map(
            static fn (array $diagnostic): string => (string) ($diagnostic['attribute'] ?? ''),
            $baseDiagnostics
        ));
        $t->same([2, 2, 2, 2], array_map(
            static fn (array $diagnostic): int => (int) ($diagnostic['line'] ?? 0),
            $baseDiagnostics
        ));
        foreach (['<base', 'javascript:', 'bad{frame'] as $blocked) {
            $t->true(!str_contains($fragment->serialize(), $blocked), 'Expected unsafe base helper metadata to stay diagnostic-only: ' . $blocked);
        }

        $unresolved = Html5DomFragment::fromHtml(
            "\n<base href=\"../assets/\">\n<img src=\"cover.png\" alt=\"Cover\">"
        );
        $unresolvedBaseDiagnostics = array_values(array_filter(
            $unresolved->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['tag'] ?? '') === 'base'
        ));

        $t->same("\n\n<img src=\"cover.png\" alt=\"Cover\">", $unresolved->serialize());
        $t->same(null, $unresolved->baseUrl());
        $t->same(['unresolved-base-url', 'blocked-tag'], array_map(
            static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''),
            $unresolvedBaseDiagnostics
        ));
        $t->same(['href', ''], array_map(
            static fn (array $diagnostic): string => (string) ($diagnostic['attribute'] ?? ''),
            $unresolvedBaseDiagnostics
        ));
        $t->same([2, 2], array_map(
            static fn (array $diagnostic): int => (int) ($diagnostic['line'] ?? 0),
            $unresolvedBaseDiagnostics
        ));
    },
    'converts image map areas into inert reviewer links before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<figure><img src="./floorplan.png" usemap="#review-map" alt="Floor plan">'
            . '<map name="review-map">'
            . '<area shape="rect" coords=" 0, 0, 150, 120 " href="./lead.html" alt="Lead story" target="_blank">'
            . '<area shape="circle" coords="75,80,12" href="mailto:editor@example.test" alt="Editor contact" rel="noopener opener">'
            . '<area shape="poly" coords="0,0,10,0,10,10" href="java&#10;script:alert(1)" alt="Bad region">'
            . '<area shape="star" coords="1,2,bad" href="./bad-shape.html" alt="Bad shape">'
            . '</map></figure>',
            'https://fallback.example.test/source.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/image-map-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<figure><img src="https://source.example.test/import/posts/floorplan.png" usemap="#review-map" alt="Floor plan">'
            . '<a href="https://source.example.test/import/posts/lead.html" data-pandoc-image-map-area="true" data-pandoc-image-map-name="review-map" data-pandoc-image-map-shape="rect" data-pandoc-image-map-coords="0,0,150,120" data-pandoc-image-map-alt="Lead story">Lead story</a>'
            . '<a href="mailto:editor@example.test" data-pandoc-image-map-area="true" data-pandoc-image-map-name="review-map" data-pandoc-image-map-shape="circle" data-pandoc-image-map-coords="75,80,12" data-pandoc-image-map-alt="Editor contact" rel="noopener">Editor contact</a>'
            . '<a href="https://source.example.test/import/posts/bad-shape.html" data-pandoc-image-map-area="true" data-pandoc-image-map-name="review-map" data-pandoc-image-map-alt="Bad shape">Bad shape</a>'
            . '</figure>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same('Lead storyEditor contactBad shape', $fragment->textContent());
        $t->same(['a', 'figure', 'img'], $summary['elementNames']);
        $t->same(['area', 'base', 'map'], $summary['blockedTags']);
        $t->same(['coords', 'href', 'rel', 'shape', 'target'], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'unsafe-attribute', 'blocked-tag', 'unsafe-attribute', 'blocked-tag', 'unsafe-url', 'blocked-tag', 'unsafe-attribute', 'unsafe-attribute'], $policyDiagnostics);
        $t->same('figure', $nodes[0]['name']);
        $t->same('img', $nodes[0]['children'][0]['name']);
        $t->same('https://source.example.test/import/posts/floorplan.png', $nodes[0]['children'][0]['attrs']['src']);
        $t->same('#review-map', $nodes[0]['children'][0]['attrs']['usemap']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/lead.html',
            'data-pandoc-image-map-area' => 'true',
            'data-pandoc-image-map-name' => 'review-map',
            'data-pandoc-image-map-shape' => 'rect',
            'data-pandoc-image-map-coords' => '0,0,150,120',
            'data-pandoc-image-map-alt' => 'Lead story',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same([
            'href' => 'mailto:editor@example.test',
            'data-pandoc-image-map-area' => 'true',
            'data-pandoc-image-map-name' => 'review-map',
            'data-pandoc-image-map-shape' => 'circle',
            'data-pandoc-image-map-coords' => '75,80,12',
            'data-pandoc-image-map-alt' => 'Editor contact',
            'rel' => 'noopener',
        ], $nodes[0]['children'][2]['attrs']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/bad-shape.html',
            'data-pandoc-image-map-area' => 'true',
            'data-pandoc-image-map-name' => 'review-map',
            'data-pandoc-image-map-alt' => 'Bad shape',
        ], $nodes[0]['children'][3]['attrs']);
        $t->same('/migration/image-map-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<map'), 'Expected image map wrapper to be stripped');
        $t->true(!str_contains($html, '<area'), 'Expected active area elements to be converted to reviewer links');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe area href to be stripped');
        $t->true(!str_contains($html, 'Bad region'), 'Expected unsafe area alt text to stay hidden with its blocked href');
        $t->true(!str_contains($html, 'shape="star"'), 'Expected unknown area shape to be diagnostics-only');
        $t->true(!str_contains($html, 'coords="1,2,bad"'), 'Expected invalid area coords to be diagnostics-only');
        $t->true(!str_contains($html, 'target='), 'Expected area browsing-context targets to be stripped');
        $t->same(0, preg_match('/(?:^|[\s"])opener(?:[\s"]|$)/', $html), 'Expected opener rel tokens to be stripped');
    },
    'preserves figure caption and legacy alignment as inert reviewer metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<figure align="Right" aria-describedby="cap-note" data-pandoc-figure-caption="source-spoof">'
            . '<img src="./cover.png" alt="Cover">'
            . '<figcaption id="cap-note">  Cover <em>caption</em> <a href="./caption-source.html">source</a> <a href="java&#10;script:alert(1)">bad</a>  </figcaption>'
            . '</figure>'
            . '<figure align="poster"><figcaption>Invalid align caption</figcaption></figure>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/figure-caption-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<figure data-pandoc-aria-describedby="cap-note" data-pandoc-figure-align="right" data-pandoc-figure-caption="Cover caption source bad" data-pandoc-figure-caption-id="cap-note">'
            . '<img src="https://source.example.test/import/posts/cover.png" alt="Cover">'
            . '<figcaption id="cap-note">  Cover <em>caption</em> <a href="https://source.example.test/import/posts/caption-source.html">source</a> <a>bad</a>  </figcaption>'
            . '</figure>'
            . '<figure data-pandoc-figure-caption="Invalid align caption"><figcaption>Invalid align caption</figcaption></figure>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('  Cover caption source bad  Invalid align caption', $fragment->textContent());
        $t->same(['a', 'em', 'figcaption', 'figure', 'img'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['align', 'aria-describedby', 'data-pandoc-figure-caption', 'href'], $summary['filteredAttributes']);
        $t->same([
            'aria-metadata-review',
            'unsafe-attribute',
            'unsafe-url',
            'figure-metadata-review',
            'figure-metadata-review',
            'unsafe-attribute',
            'figure-metadata-review',
        ], $policyDiagnostics);
        $t->same('figure', $nodes[0]['name']);
        $t->same([
            'data-pandoc-aria-describedby' => 'cap-note',
            'data-pandoc-figure-align' => 'right',
            'data-pandoc-figure-caption' => 'Cover caption source bad',
            'data-pandoc-figure-caption-id' => 'cap-note',
        ], $nodes[0]['attrs']);
        $t->same('https://source.example.test/import/posts/cover.png', $nodes[0]['children'][0]['attrs']['src']);
        $t->same('figcaption', $nodes[0]['children'][1]['name']);
        $t->same('cap-note', $nodes[0]['children'][1]['attrs']['id']);
        $t->same('https://source.example.test/import/posts/caption-source.html', $nodes[0]['children'][1]['children'][3]['attrs']['href']);
        $t->same([], $nodes[0]['children'][1]['children'][5]['attrs']);
        $t->same(['data-pandoc-figure-caption' => 'Invalid align caption'], $nodes[1]['attrs']);
        $t->same('/migration/figure-caption-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, ' align='), 'Expected legacy figure alignment to move into inert metadata');
        $t->true(!str_contains($html, 'source-spoof'), 'Expected source-owned figure metadata spoofing to be stripped');
        $t->true(!str_contains($html, 'poster'), 'Expected invalid figure alignment to stay diagnostic-only');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe caption link to be stripped');
    },
    'ignores inactive fallback base elements before resolving reviewer URLs' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<template><base href="https://inactive.example/assets/"><a href="template-note.html">template note</a></template>'
            . '<noscript><base href="https://noscript.example/assets/"><a href="noscript-note.html">noscript note</a></noscript>'
            . '<base href="https://source.example.test/import/posts/post.html">'
            . '<article><a href="doc.html">doc</a><img src="./cover.png" alt="Cover"></article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/inactive-base-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<a href="https://source.example.test/import/posts/template-note.html">template note</a>'
            . '<a href="https://source.example.test/import/posts/noscript-note.html">noscript note</a>'
            . '<article><a href="https://source.example.test/import/posts/doc.html">doc</a>'
            . '<img src="https://source.example.test/import/posts/cover.png" alt="Cover"></article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same(['base', 'noscript', 'template'], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same(['blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag', 'blocked-tag'], $policyDiagnostics);
        $t->same('a', $nodes[0]['name']);
        $t->same('https://source.example.test/import/posts/template-note.html', $nodes[0]['attrs']['href']);
        $t->same('a', $nodes[1]['name']);
        $t->same('https://source.example.test/import/posts/noscript-note.html', $nodes[1]['attrs']['href']);
        $t->same('article', $nodes[2]['name']);
        $t->same('https://source.example.test/import/posts/doc.html', $nodes[2]['children'][0]['attrs']['href']);
        $t->same('https://source.example.test/import/posts/cover.png', $nodes[2]['children'][1]['attrs']['src']);
        $t->same('/migration/inactive-base-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, 'inactive.example'), 'Expected inactive template base URL to be ignored');
        $t->true(!str_contains($html, 'noscript.example'), 'Expected inactive noscript base URL to be ignored');
        $t->true(!str_contains($html, '<base'), 'Expected base elements to be stripped from sanitized output');
    },
    'filters obsolete media URL attributes while preserving local image map references' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<p>'
            . '<img src="/media/cover.png" dynsrc="javascript:alert(1)" lowsrc="mailto:cover@example.test" usemap="javascript:alert(1)" alt="Cover">'
            . '<img src="./safe.png" dynsrc="./intro.avi" lowsrc="../low.jpg" usemap=" #review-map " alt="Safe">'
            . '<map name="review-map"><area href="/review" alt="Review"></map>'
            . '</p>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/obsolete-media-url-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<p>'
            . '<img src="https://source.example.test/media/cover.png" alt="Cover">'
            . '<img src="https://source.example.test/import/posts/safe.png" dynsrc="https://source.example.test/import/posts/intro.avi" lowsrc="https://source.example.test/import/low.jpg" usemap="#review-map" alt="Safe">'
            . '<a href="https://source.example.test/review" data-pandoc-image-map-area="true" data-pandoc-image-map-name="review-map" data-pandoc-image-map-alt="Review">Review</a>'
            . '</p>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same(['dynsrc', 'lowsrc', 'usemap'], $summary['filteredAttributes']);
        $t->same(['unsafe-url', 'unsafe-url', 'unsafe-url', 'normalized-url', 'blocked-tag', 'blocked-tag'], $fragment->diagnosticCodes());
        $t->same('https://source.example.test/media/cover.png', $nodes[0]['children'][0]['attrs']['src']);
        $t->same(['src' => 'https://source.example.test/media/cover.png', 'alt' => 'Cover'], $nodes[0]['children'][0]['attrs']);
        $t->same('https://source.example.test/import/posts/intro.avi', $nodes[0]['children'][1]['attrs']['dynsrc']);
        $t->same('https://source.example.test/import/low.jpg', $nodes[0]['children'][1]['attrs']['lowsrc']);
        $t->same('#review-map', $nodes[0]['children'][1]['attrs']['usemap']);
        $t->same([
            'href' => 'https://source.example.test/review',
            'data-pandoc-image-map-area' => 'true',
            'data-pandoc-image-map-name' => 'review-map',
            'data-pandoc-image-map-alt' => 'Review',
        ], $nodes[0]['children'][2]['attrs']);
        $t->same('/migration/obsolete-media-url-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe obsolete media URLs to be stripped');
        $t->true(!str_contains($html, 'mailto:cover@example.test'), 'Expected mailto lowsrc fetch URL to be stripped');
        $t->true(!str_contains($html, 'https://source.example.test/import/posts/post.html#review-map'), 'Expected local usemap references to avoid base URL expansion');
        $t->true(!str_contains($html, '<map'), 'Expected image map wrapper to be stripped');
        $t->true(!str_contains($html, '<area'), 'Expected image map area to become an inert reviewer link');
    },
    'prunes empty picture sources after unsafe candidate filtering before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<picture data-review="responsive">'
            . '<source srcset="./hero.avif 1x, javascript:alert(1) 2x" media="(min-width: 48em)" type="image/avif" sizes="(min-width: 48em) 50vw, 100vw">'
            . '<source srcset="mailto:bad@example.test 1x" media="(max-width: 47em)" type="image/jpeg">'
            . '<img src="./hero.jpg" srcset="./hero.jpg 1x, ../media/hero@2x.jpg 2x" alt="Hero">'
            . '</picture>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/responsive-picture-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<picture data-review="responsive">'
            . '<source srcset="https://source.example.test/import/posts/hero.avif 1x" media="(min-width: 48em)" type="image/avif" sizes="(min-width: 48em) 50vw, 100vw">'
            . '<img src="https://source.example.test/import/posts/hero.jpg" srcset="https://source.example.test/import/posts/hero.jpg 1x, https://source.example.test/import/media/hero@2x.jpg 2x" alt="Hero">'
            . '</picture>';

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same(['img', 'picture', 'source'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['srcset'], $summary['filteredAttributes']);
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));
        $t->same(['unsafe-url', 'unsafe-url', 'empty-source'], $policyDiagnostics);
        $t->same('picture', $nodes[0]['name']);
        $t->same(2, count($nodes[0]['children']));
        $t->same('source', $nodes[0]['children'][0]['name']);
        $t->same('https://source.example.test/import/posts/hero.avif 1x', $nodes[0]['children'][0]['attrs']['srcset']);
        $t->same('img', $nodes[0]['children'][1]['name']);
        $t->same('https://source.example.test/import/posts/hero.jpg', $nodes[0]['children'][1]['attrs']['src']);
        $t->same('/migration/responsive-picture-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe picture source candidate to be stripped');
        $t->true(!str_contains($html, 'mailto:bad@example.test'), 'Expected non-fetch picture source candidate to be stripped');
        $t->true(!str_contains($html, '(max-width: 47em)'), 'Expected empty unsafe source branch to be pruned');
    },
    'filters unsafe responsive image media and sizes metadata before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<picture data-review="responsive-metadata">'
            . '<source srcset="./safe.avif 1x" media=" (min-width: 48em) " sizes=" (min-width: 48em) 50vw , 100vw " type="image/avif">'
            . '<source srcset="./unsafe-media.webp 1x" media="screen and (background: url(javascript:alert(1)))" sizes="(min-width: 40em) calc(50vw + 2rem)" type="image/webp">'
            . '<source srcset="./unsafe-sizes.jpg 1x" media="(orientation: landscape)" sizes="(min-width: 40em) calc(50vw + url(javascript:alert(1)))" type="image/jpeg">'
            . '<img src="./fallback.jpg" srcset="./fallback.jpg 1x" sizes="(min-width: 30em) calc(100vw + url(javascript:alert(1)))" alt="Fallback">'
            . '</picture>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/responsive-source-metadata-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<picture data-review="responsive-metadata">'
            . '<source srcset="https://source.example.test/import/posts/safe.avif 1x" media="(min-width: 48em)" sizes="(min-width: 48em) 50vw, 100vw" type="image/avif">'
            . '<source srcset="https://source.example.test/import/posts/unsafe-media.webp 1x" sizes="(min-width: 40em) calc(50vw + 2rem)" type="image/webp">'
            . '<source srcset="https://source.example.test/import/posts/unsafe-sizes.jpg 1x" media="(orientation: landscape)" type="image/jpeg">'
            . '<img src="https://source.example.test/import/posts/fallback.jpg" srcset="https://source.example.test/import/posts/fallback.jpg 1x" alt="Fallback">'
            . '</picture>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same(['img', 'picture', 'source'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same(['media', 'sizes'], $summary['filteredAttributes']);
        $t->same(['unsafe-attribute', 'unsafe-attribute', 'unsafe-attribute'], $policyDiagnostics);
        $t->same('picture', $nodes[0]['name']);
        $t->same(4, count($nodes[0]['children']));
        $t->same([
            'srcset' => 'https://source.example.test/import/posts/safe.avif 1x',
            'media' => '(min-width: 48em)',
            'sizes' => '(min-width: 48em) 50vw, 100vw',
            'type' => 'image/avif',
        ], $nodes[0]['children'][0]['attrs']);
        $t->same([
            'srcset' => 'https://source.example.test/import/posts/unsafe-media.webp 1x',
            'sizes' => '(min-width: 40em) calc(50vw + 2rem)',
            'type' => 'image/webp',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same([
            'srcset' => 'https://source.example.test/import/posts/unsafe-sizes.jpg 1x',
            'media' => '(orientation: landscape)',
            'type' => 'image/jpeg',
        ], $nodes[0]['children'][2]['attrs']);
        $t->same([
            'src' => 'https://source.example.test/import/posts/fallback.jpg',
            'srcset' => 'https://source.example.test/import/posts/fallback.jpg 1x',
            'alt' => 'Fallback',
        ], $nodes[0]['children'][3]['attrs']);
        $t->same('/migration/responsive-source-metadata-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe responsive source media and sizes metadata to be stripped');
        $t->true(!str_contains($html, 'background:'), 'Expected CSS-like media URL metadata to stay out of review HTML');
        $t->true(!str_contains($html, 'url('), 'Expected CSS URL tokens to stay out of source size metadata');
        $t->true(str_contains($html, 'unsafe-media.webp'), 'Expected otherwise valid source candidates to remain reviewable after media metadata filtering');
        $t->true(str_contains($html, 'unsafe-sizes.jpg'), 'Expected otherwise valid source candidates to remain reviewable after sizes metadata filtering');
    },
    'converts portal sources and drops orphan source sets before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<base href="https://source.example.test/import/posts/post.html">'
            . '<article>'
            . '<source srcset="./orphan.avif 1x, javascript:alert(1) 2x" type="image/avif">'
            . '<video controls><source src="./movie.mp4" type="video/mp4"><source src="java&#10;script:alert(1)" type="video/webm"></video>'
            . '<portal src="./portal/review.html" referrerpolicy="strict-origin" title="Portal preview"><p>Portal fallback</p></portal>'
            . '<portal src="java&#10;script:alert(1)" title="Bad portal"><p>Bad fallback</p></portal>'
            . '</article>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/portal-source-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article>'
            . '<video data-pandoc-media-controls="true"><source src="https://source.example.test/import/posts/movie.mp4" type="video/mp4"><source type="video/webm"></video>'
            . '<a href="https://source.example.test/import/posts/portal/review.html" data-pandoc-portal-src="true" title="Portal preview" data-pandoc-portal-referrerpolicy="strict-origin">Portal preview</a>'
            . '<p>Portal fallback</p><p>Bad fallback</p>'
            . '</article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('https://source.example.test/import/posts/post.html', $fragment->baseUrl());
        $t->same(['a', 'article', 'p', 'source', 'video'], $summary['elementNames']);
        $t->same(['base', 'portal', 'source'], $summary['blockedTags']);
        $t->same(['controls', 'referrerpolicy', 'src', 'srcset'], $summary['filteredAttributes']);
        $t->same([
            'blocked-tag',
            'unsafe-url',
            'media-resource-policy-review',
            'unsafe-url',
            'blocked-tag',
            'portal-source-review',
            'referrer-policy-review',
            'blocked-tag',
            'unsafe-url',
            'blocked-tag',
        ], $policyDiagnostics);
        $t->same('article', $nodes[0]['name']);
        $t->same('video', $nodes[0]['children'][0]['name']);
        $t->same(['data-pandoc-media-controls' => 'true'], $nodes[0]['children'][0]['attrs']);
        $t->same(2, count($nodes[0]['children'][0]['children']));
        $t->same([
            'src' => 'https://source.example.test/import/posts/movie.mp4',
            'type' => 'video/mp4',
        ], $nodes[0]['children'][0]['children'][0]['attrs']);
        $t->same(['type' => 'video/webm'], $nodes[0]['children'][0]['children'][1]['attrs']);
        $t->same('a', $nodes[0]['children'][1]['name']);
        $t->same([
            'href' => 'https://source.example.test/import/posts/portal/review.html',
            'data-pandoc-portal-src' => 'true',
            'title' => 'Portal preview',
            'data-pandoc-portal-referrerpolicy' => 'strict-origin',
        ], $nodes[0]['children'][1]['attrs']);
        $t->same('Portal fallback', $nodes[0]['children'][2]['children'][0]['text']);
        $t->same('Bad fallback', $nodes[0]['children'][3]['children'][0]['text']);
        $t->same('/migration/portal-source-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        $t->true(!str_contains($html, '<portal'), 'Expected live portal elements to be stripped');
        $t->true(!str_contains($html, 'orphan.avif'), 'Expected orphan source-set candidates to be dropped');
        $t->true(!str_contains($html, 'javascript:'), 'Expected unsafe portal and source URLs to be stripped');
        $t->true(!str_contains($html, ' referrerpolicy='), 'Expected live portal referrer policy to move into inert metadata');
    },
    'filters reserved pandoc data attributes and html namespace declarations before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<article data-source="legacy" data-pandoc-link-rel="canonical" data-pandoc-fragment-root="1" aria-label="Review packet" xmlns="http://www.w3.org/1999/xhtml">'
            . '<p data-pandoc-meta-name="description" data-review="keep" xmlns:xlink="http://www.w3.org/1999/xlink">source</p>'
            . '<svg xmlns="http://www.w3.org/2000/svg" data-pandoc-iframe-src="true"><image xlink:href="./cover.png" data-pandoc-image-map-area="true"></image></svg>'
            . '</article>',
            'https://source.example.test/import/posts/post.html'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/reserved-data-attribute-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<article data-source="legacy" data-pandoc-aria-label="Review packet"><p data-review="keep">source</p><svg xmlns="http://www.w3.org/2000/svg"><image xlink:href="https://source.example.test/import/posts/cover.png"></image></svg></article>';
        $policyDiagnostics = array_values(array_filter(
            $fragment->diagnosticCodes(),
            static fn (string $code): bool => $code !== 'libxml-repair'
        ));

        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same(['article', 'image', 'p', 'svg'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([
            'aria-label',
            'data-pandoc-fragment-root',
            'data-pandoc-iframe-src',
            'data-pandoc-image-map-area',
            'data-pandoc-link-rel',
            'data-pandoc-meta-name',
            'xmlns',
            'xmlns:xlink',
        ], $summary['filteredAttributes']);
        $t->same(['unsafe-attribute', 'unsafe-attribute', 'aria-metadata-review', 'unsafe-attribute', 'unsafe-attribute', 'unsafe-attribute', 'unsafe-attribute', 'unsafe-attribute'], $policyDiagnostics);
        $t->same([
            'data-source' => 'legacy',
            'data-pandoc-aria-label' => 'Review packet',
        ], $nodes[0]['attrs']);
        $t->same(['data-review' => 'keep'], $nodes[0]['children'][0]['attrs']);
        $t->same(['xlink:href' => 'https://source.example.test/import/posts/cover.png'], $nodes[0]['children'][1]['children'][0]['attrs']);
        $t->same('/migration/reserved-data-attribute-review.html', $document->children[0]->attr('part'));
        $t->same('https://source.example.test/import/posts/post.html', $document->children[0]->attr('baseUrl'));
        foreach (['data-pandoc-link-rel', 'data-pandoc-fragment-root', 'data-pandoc-iframe-src', 'data-pandoc-image-map-area', 'data-pandoc-meta-name', 'http://www.w3.org/1999/xhtml', 'xmlns:xlink'] as $blocked) {
            $t->true(!str_contains($html, $blocked), 'Expected source-owned reserved attribute to be stripped: ' . $blocked);
        }
    },
    'treats svg desc descendants as html integration point content before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<svg><desc><p viewBox="html attr"><textPath>HTML fallback</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></desc></svg>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/svg-desc-html-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<svg><desc><p viewbox="html attr"><textpath>HTML fallback</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></desc></svg>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same(['desc', 'linearGradient', 'p', 'svg', 'textpath'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same('svg', $nodes[0]['name']);
        $t->same('desc', $nodes[0]['children'][0]['name']);
        $t->same('p', $nodes[0]['children'][0]['children'][0]['name']);
        $t->same(['viewbox' => 'html attr'], $nodes[0]['children'][0]['children'][0]['attrs']);
        $t->same('textpath', $nodes[0]['children'][0]['children'][0]['children'][0]['name']);
        $t->same(['viewBox' => '0 0 1 1'], $nodes[0]['children'][0]['children'][0]['children'][1]['attrs']);
        $t->same('/migration/svg-desc-html-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, 'viewBox="html attr"'), 'Expected SVG desc fallback attributes to stay in HTML casing');
        $t->true(!str_contains($html, '<textPath>'), 'Expected SVG desc fallback children to stay in HTML casing');
    },
    'treats svg title descendants as html integration point content before WordPress handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<svg><title><p viewBox="html attr"><textPath>Title fallback</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></title></svg>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/svg-title-html-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $expected = '<svg><title><p viewbox="html attr"><textpath>Title fallback</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></title></svg>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same(['linearGradient', 'p', 'svg', 'textpath', 'title'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same('svg', $nodes[0]['name']);
        $t->same('title', $nodes[0]['children'][0]['name']);
        $t->same('p', $nodes[0]['children'][0]['children'][0]['name']);
        $t->same(['viewbox' => 'html attr'], $nodes[0]['children'][0]['children'][0]['attrs']);
        $t->same('textpath', $nodes[0]['children'][0]['children'][0]['children'][0]['name']);
        $t->same(['viewBox' => '0 0 1 1'], $nodes[0]['children'][0]['children'][0]['children'][1]['attrs']);
        $t->same('/migration/svg-title-html-review.html', $document->children[0]->attr('part'));
        $t->true(!str_contains($html, 'viewBox="html attr"'), 'Expected SVG title fallback attributes to stay in HTML casing');
        $t->true(!str_contains($html, '<textPath>'), 'Expected SVG title fallback children to stay in HTML casing');
        $t->true(!str_contains($html, '&lt;p viewBox'), 'Expected SVG title fallback markup to stay parsed instead of escaped as RCDATA');
    },
    'keeps svg element-name casing scoped while normalizing html5 fragments' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<math><lineargradient data-review="math">m</lineargradient><mtext><linearGradient viewBox="html">html</linearGradient></mtext><svg><linearGradient id="g"></linearGradient></svg></math>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $html = $fragment->serialize();

        $mathUnknown = $nodes[0]['children'][0];
        $mathHtmlText = $nodes[0]['children'][1]['children'][0];
        $nestedSvg = $nodes[0]['children'][2];

        $t->same('<math><lineargradient data-review="math">m</lineargradient><mtext><lineargradient viewbox="html">html</lineargradient></mtext><svg><linearGradient id="g"></linearGradient></svg></math>', $html);
        $t->same(['linearGradient', 'lineargradient', 'math', 'mtext', 'svg'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same('math', $nodes[0]['name']);
        $t->same('lineargradient', $mathUnknown['name']);
        $t->same(['data-review' => 'math'], $mathUnknown['attrs']);
        $t->same('lineargradient', $mathHtmlText['name']);
        $t->same(['viewbox' => 'html'], $mathHtmlText['attrs']);
        $t->same('svg', $nestedSvg['name']);
        $t->same('linearGradient', $nestedSvg['children'][0]['name']);
        $t->true(!str_contains($html, '<math><linearGradient'), 'Expected MathML non-SVG descendants to keep their parsed names');
    },
    'adds source line metadata to html fragment sanitizer diagnostics' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            "<article>\n"
            . "<p onclick=\"drop()\">review paragraph</p>\n"
            . "<script>alert(1)</script>\n"
            . "<img src=\"javascript:alert(1)\" alt=\"Bad source\">\n"
            . "<table><tr><td>A</td></tr>loose table note</table>"
            . '</article>'
        );
        $libxmlRepair = null;
        foreach ($fragment->diagnostics() as $diagnostic) {
            if (($diagnostic['code'] ?? '') === 'libxml-repair') {
                $libxmlRepair = $diagnostic;
                break;
            }
        }
        $diagnostics = array_values(array_filter(
            $fragment->diagnostics(),
            static fn (array $diagnostic): bool => ($diagnostic['code'] ?? '') !== 'libxml-repair'
        ));
        $html = $fragment->serialize();

        $t->same(1, is_array($libxmlRepair) ? ($libxmlRepair['line'] ?? null) : null);
        $t->true(is_array($libxmlRepair) && ($libxmlRepair['column'] ?? 0) > 0, 'Expected libxml repair diagnostics to include a source column');
        $t->same(['unsafe-attribute', 'blocked-tag', 'unsafe-url', 'image-alt-fallback', 'table-foster-parented-content'], array_map(
            static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''),
            $diagnostics
        ));
        $t->same(2, $diagnostics[0]['line'] ?? null);
        $t->same('p', $diagnostics[0]['tag'] ?? null);
        $t->same('onclick', $diagnostics[0]['attribute'] ?? null);
        $t->same(3, $diagnostics[1]['line'] ?? null);
        $t->same('script', $diagnostics[1]['tag'] ?? null);
        $t->same(4, $diagnostics[2]['line'] ?? null);
        $t->same('img', $diagnostics[2]['tag'] ?? null);
        $t->same('src', $diagnostics[2]['attribute'] ?? null);
        $t->same(4, $diagnostics[3]['line'] ?? null);
        $t->same('img', $diagnostics[3]['tag'] ?? null);
        $t->same('alt', $diagnostics[3]['attribute'] ?? null);
        $t->same(5, $diagnostics[4]['line'] ?? null);
        $t->same('table', $diagnostics[4]['context'] ?? null);
        $t->same('text', $diagnostics[4]['nodeType'] ?? null);
        $t->same('<article>' . "\n" . '<p>review paragraph</p>' . "\n\n" . '<span data-pandoc-image-alt-fallback="true">Bad source</span>' . "\n" . 'loose table note<table><tr><td>A</td></tr></table></article>', $html);
    },
    'rejects unsafe fragment declarations before libxml can repair them away' => static function (TestRunner $t): void {
        $safe = Html5DomFragment::fromHtml('<p data-source="review">Safe &amp; bounded</p>');

        $t->same('<p data-source="review">Safe &amp; bounded</p>', $safe->serialize());
        $t->same([], $safe->diagnosticCodes());
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml("<p>bad\0packet</p>"));
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml('<!DOCTYPE html><p>bad</p>'));
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml('<!ENTITY reviewer SYSTEM "file:///etc/passwd"><p>&reviewer;</p>'));
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromHtml('<?xml-stylesheet href="https://example.invalid/review.xsl"?><p>bad</p>'));
        $t->throws(InvalidArgumentException::class, static fn (): Html5DomFragment => Html5DomFragment::fromXml('<?xml-stylesheet href="https://example.invalid/review.xsl"?><root/>'));
    },
    'preserves declaration-looking text inside inert html comments before handoff' => static function (TestRunner $t): void {
        $fragment = Html5DomFragment::fromHtml(
            '<!-- <!DOCTYPE html><!ENTITY reviewer SYSTEM "file:///etc/passwd"> -->'
            . '<p data-source="review">Safe packet</p>'
        );
        $summary = $fragment->summary();
        $nodes = $fragment->nodes();
        $document = new AstNode('document', ['source' => 'html5-dom-fragment'], [
            $fragment->toRawHtmlAst(['part' => '/migration/commented-declaration-review.html']),
        ]);
        $blocks = (new WordPressBlockWriter())->write($document);
        $html = $fragment->serialize();

        $expected = '<!-- <!DOCTYPE html><!ENTITY reviewer SYSTEM "file:///etc/passwd"> --><p data-source="review">Safe packet</p>';
        $t->same($expected, $html);
        $t->contains($expected, $blocks);
        $t->same('Safe packet', $fragment->textContent());
        $t->same(2, $summary['topLevelNodes']);
        $t->same(1, $summary['comments']);
        $t->same(['p'], $summary['elementNames']);
        $t->same([], $summary['blockedTags']);
        $t->same([], $summary['filteredAttributes']);
        $t->same([], $fragment->diagnosticCodes());
        $t->same('comment', $nodes[0]['type']);
        $t->same(' <!DOCTYPE html><!ENTITY reviewer SYSTEM "file:///etc/passwd"> ', $nodes[0]['text']);
        $t->same('p', $nodes[1]['name']);
        $t->same('/migration/commented-declaration-review.html', $document->children[0]->attr('part'));
    },
];
