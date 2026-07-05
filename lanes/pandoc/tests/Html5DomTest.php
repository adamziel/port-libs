<?php

declare(strict_types=1);

use PortLibs\Pandoc\Html5Dom;

return [
    'parses and serializes bounded HTML5 fragments without wrapper nodes' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<section data-source="wp"><p>AT&amp;T<br>review</p><figure><img src="cover.png" alt="Cover"><figcaption>Cover</figcaption></figure></section>'
        );
        $section = Html5Dom::firstChildElement($body, 'section');
        $figure = $section instanceof DOMElement ? Html5Dom::firstChildElement($section, 'figure') : null;

        $t->true($section instanceof DOMElement, 'Expected section child from HTML fragment body');
        $t->same(['data-source' => 'wp'], Html5Dom::attributes($section));
        $t->true($figure instanceof DOMElement, 'Expected HTML5 figure element to survive DOM parse');
        $t->same('AT&TreviewCover', $section->textContent);
        $t->same('AT&T reviewCover', Html5Dom::normalizedText($section));
        $t->same(
            '<section data-source="wp"><p>AT&amp;T<br>review</p><figure><img alt="Cover" src="cover.png"><figcaption>Cover</figcaption></figure></section>',
            Html5Dom::serializeHtmlChildren($body)
        );
    },
    'keeps void element siblings when bridging HTMLDocument output' => static function (TestRunner $t): void {
        if (!class_exists('Dom\\HTMLDocument')) {
            $t->true(true, 'Dom\\HTMLDocument is unavailable on this PHP runtime');

            return;
        }

        $body = Html5Dom::parseHtmlFragment(
            '<embed src="plugin.swf" type="application/x-shockwave-flash"></embed><object data="diagram.svg"><param name="quality" value="high">Fallback</object>'
        );
        $children = Html5Dom::childElements($body);

        $t->same(['embed', 'object'], array_map(static fn (DOMElement $element): string => strtolower($element->localName), $children));
        $t->same(0, $children[0]->childNodes->length);
        $t->same('Fallback', $children[1]->textContent);
        $t->same(
            '<embed src="plugin.swf" type="application/x-shockwave-flash"><object data="diagram.svg"><param name="quality" value="high"></param>Fallback</object>',
            Html5Dom::serializeHtmlChildren($body)
        );
    },
    'bridges HTML5 tree construction through legacy DOMDocument helpers' => static function (TestRunner $t): void {
        if (!class_exists('Dom\\HTMLDocument')) {
            $t->true(true, 'Dom\\HTMLDocument is unavailable on this PHP runtime');

            return;
        }

        $document = Html5Dom::parseHtmlDocument(
            '<!doctype html><html><body><p>one<section><p>two</section>three</body></html>'
        );
        $body = $document->getElementsByTagName('body')->item(0);
        $section = $body instanceof DOMElement ? Html5Dom::firstChildElement($body, 'section') : null;

        $t->true($body instanceof DOMElement, 'Expected body from bridged HTML5 document parse');
        $t->true($section instanceof DOMElement, 'Expected section to be fostered out of the paragraph');
        $t->same(
            '<p>one</p><section><p>two</p></section>three',
            $body instanceof DOMElement ? Html5Dom::serializeHtmlChildren($body) : ''
        );
    },
    'reports HTMLDocument fragment context without source tag scanning' => static function (TestRunner $t): void {
        if (!class_exists('Dom\\HTMLDocument')) {
            $t->true(true, 'Dom\\HTMLDocument is unavailable on this PHP runtime');

            return;
        }

        $orphan = Html5Dom::parseHtmlFragment('<td>A</td><td>B</td><tr><td>C</td></tr><p>after</p>');
        $explicit = Html5Dom::parseHtmlFragment('<table><tr><td>A</td></tr></table><p>after</p>');

        $t->same(
            Html5Dom::HTML_FRAGMENT_CONTEXT_TABLE,
            Html5Dom::htmlFragmentTreeConstructionContext('<td>A</td><td>B</td><tr><td>C</td></tr><p>after</p>')
        );
        $t->same(
            Html5Dom::HTML_FRAGMENT_CONTEXT_BODY,
            Html5Dom::htmlFragmentTreeConstructionContext('<table><tr><td>A</td></tr></table><p>after</p>')
        );
        $t->same(
            '<table><tbody><tr><td>A</td><td>B</td></tr><tr><td>C</td></tr></tbody></table><p>after</p>',
            Html5Dom::serializeHtmlChildren($orphan)
        );
        $t->same(
            '<table><tbody><tr><td>A</td></tr></tbody></table><p>after</p>',
            Html5Dom::serializeHtmlChildren($explicit)
        );
    },
    'uses HTMLDocument tree construction for adoption agency and foster parenting repairs' => static function (TestRunner $t): void {
        if (!class_exists('Dom\\HTMLDocument')) {
            $t->true(true, 'Dom\\HTMLDocument is unavailable on this PHP runtime');

            return;
        }

        $source = '<!doctype html><html><body><p><b>one<p>two</b>three<table>loose<tr><td>A</td></tr></table>tail</body></html>';
        $document = Html5Dom::parseHtmlDocument($source);
        $body = $document->getElementsByTagName('body')->item(0);
        $serialized = $body instanceof DOMElement ? Html5Dom::serializeHtmlChildren($body) : '';

        $legacyPrevious = libxml_use_internal_errors(true);
        $legacy = new DOMDocument('1.0', 'UTF-8');
        $legacy->loadHTML('<?xml encoding="UTF-8">' . $source, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $legacyBody = $legacy->getElementsByTagName('body')->item(0);
        $legacySerialized = '';
        if ($legacyBody instanceof DOMElement) {
            foreach ($legacyBody->childNodes as $child) {
                $legacySerialized .= (string) $legacy->saveHTML($child);
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($legacyPrevious);

        $t->same(
            '<p><b>one</b></p><p><b>two</b>three</p>loose<table><tbody><tr><td>A</td></tr></tbody></table>tail',
            $serialized
        );
        $t->true(str_contains($serialized, '<p><b>two</b>three</p>'), 'Expected adoption-agency formatting repair from HTMLDocument');
        $t->true(str_contains($serialized, '</p>loose<table>'), 'Expected table foster parenting repair from HTMLDocument');
        $t->true($legacySerialized !== $serialized, 'Legacy DOMDocument must not produce the accepted HTML5 tree');
        $t->true(!str_contains($legacySerialized, '<p><b>two</b>three</p>'), 'Legacy DOMDocument should not satisfy the HTML5 formatting repair');
    },
    'passes ordinary malformed html raw to HTMLDocument tree construction' => static function (TestRunner $t): void {
        if (!class_exists('Dom\\HTMLDocument')) {
            $t->true(true, 'Dom\\HTMLDocument is unavailable on this PHP runtime');

            return;
        }

        $source = '<p><b>one<p>two</b>three<table>loose<tr><td>A</td></tr></table>tail';
        $body = Html5Dom::parseHtmlFragment($source);

        $t->same($source, Html5Dom::htmlTreeConstructionInput($source));
        $t->same(
            '<p><b>one</b></p><p><b>two</b>three</p>loose<table><tbody><tr><td>A</td></tr></tbody></table>tail',
            Html5Dom::serializeHtmlChildren($body)
        );
    },
    'recognizes raw html tag boundaries through the HTMLDocument facade' => static function (TestRunner $t): void {
        $opening = Html5Dom::markdownRawHtmlOpeningTagBoundary('   <A data-id="7" disabled>tail');
        $closing = Html5Dom::rawHtmlClosingTagAt('x</A >y', 1);

        $t->same('a', $opening['name'] ?? null);
        $t->same('<A data-id="7" disabled>', $opening['source'] ?? null);
        $t->same(27, $opening['next'] ?? null);
        $t->same(['data-id', 'disabled'], $opening['attributeNames'] ?? []);
        $t->same('a', $closing['name'] ?? null);
        $t->same('</A >', $closing['source'] ?? null);
        $t->same(6, $closing['next'] ?? null);
        $t->same(null, Html5Dom::markdownRawHtmlOpeningTagBoundary('    <div>over-indented'));
        $t->true(Html5Dom::rawHtmlOpeningTagLineIsStandalone('<hr data-review=ok />', 'hr'));
        $t->true(Html5Dom::rawHtmlLineHasOpeningAndClosingTag('<object data="x">fallback</object>', 'object'));
        $t->true(Html5Dom::rawHtmlSourceContainsOpeningTag('<pre><code>safe</code></pre>', 'code'));
    },
    'limits pre-tree literal payload protection to inert source payload elements' => static function (TestRunner $t): void {
        $ordinary = '<p><b>one<p>two</b>three</p>';
        $template = '<template><p><strong>visible</strong></p></template>';
        $textarea = '<textarea><b>literal</b></textarea>';

        $t->same($ordinary, Html5Dom::htmlTreeConstructionInput($ordinary));
        $t->same(
            '<template>&lt;p&gt;&lt;strong&gt;visible&lt;/strong&gt;&lt;/p&gt;</template>',
            Html5Dom::htmlTreeConstructionInput($template)
        );
        $t->same(
            '<textarea>&lt;b&gt;literal&lt;/b&gt;</textarea>',
            Html5Dom::htmlTreeConstructionInput($textarea)
        );
    },
    'decodes HTML entities once and keeps comparison text safe on serialization' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment('<p>AT&amp;T &lt;source&gt; &copy;</p><p>AT&amp;amp;T</p>');
        $paragraphs = Html5Dom::childElements($body, 'p');
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->same(2, count($paragraphs));
        $t->same('AT&T <source> ©', Html5Dom::normalizedText($paragraphs[0]));
        $t->same('AT&amp;T', Html5Dom::normalizedText($paragraphs[1]));
        $t->contains('<p>AT&amp;T &lt;source&gt; ©</p>', $serialized);
        $t->contains('<p>AT&amp;amp;T</p>', $serialized);
    },
    'decodes bounded html5 named character references before reader handoff' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<p title="A&NoBreak;B" data-legacy="&nbsp &copy">A&NoBreak;B&NewLine;C&Tab;D &hopf; &nbsp &copy</p>'
            . '<p data-literal="&NoBreak test">Literal &NoBreak test</p>'
            . '<p data-math="&af;&it;&ic;">f&ApplyFunction;g&InvisibleTimes;h&MediumSpace;comma&InvisibleComma;zero&ZeroWidthSpace;neg&NegativeThinSpace;</p>'
            . '<p data-spacing="&NonBreakingSpace;&ThinSpace;&ThickSpace;&VeryThinSpace;&hairsp;">Spaces: non&NonBreakingSpace;thin&ThinSpace;alias&thinsp;thick&ThickSpace;very&VeryThinSpace;hair&hairsp;neg&NegativeVeryThinSpace;&NegativeMediumSpace;&NegativeThickSpace;</p>'
            . '<textarea>&NoBreak;&Tab;</textarea>'
            . '<script type="application/json">{"ref":"&NoBreak;"}</script>'
        );
        $paragraphs = Html5Dom::childElements($body, 'p');
        $paragraph = $paragraphs[0] ?? null;
        $literalParagraph = $paragraphs[1] ?? null;
        $mathParagraph = $paragraphs[2] ?? null;
        $spacingParagraph = $paragraphs[3] ?? null;
        $textarea = Html5Dom::firstChildElement($body, 'textarea');
        $script = Html5Dom::firstChildElement($body, 'script');
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($paragraph instanceof DOMElement, 'Expected named-reference paragraph to parse');
        $t->same("A\u{2060}B\nC\tD \u{1D559} \u{00A0} ©", $paragraph instanceof DOMElement ? $paragraph->textContent : null);
        $t->same([
            'title' => "A\u{2060}B",
            'data-legacy' => "\u{00A0} ©",
        ], $paragraph instanceof DOMElement ? Html5Dom::attributes($paragraph) : []);
        $t->same('Literal &NoBreak test', $literalParagraph instanceof DOMElement ? $literalParagraph->textContent : null);
        $t->same(['data-literal' => '&NoBreak test'], $literalParagraph instanceof DOMElement ? Html5Dom::attributes($literalParagraph) : []);
        $t->same("f\u{2061}g\u{2062}h\u{205F}comma\u{2063}zero\u{200B}neg\u{200B}", $mathParagraph instanceof DOMElement ? $mathParagraph->textContent : null);
        $t->same(['data-math' => "\u{2061}\u{2062}\u{2063}"], $mathParagraph instanceof DOMElement ? Html5Dom::attributes($mathParagraph) : []);
        $t->same("Spaces: non\u{00A0}thin\u{2009}alias\u{2009}thick\u{205F}\u{200A}very\u{200A}hair\u{200A}neg\u{200B}\u{200B}\u{200B}", $spacingParagraph instanceof DOMElement ? $spacingParagraph->textContent : null);
        $t->same(['data-spacing' => "\u{00A0}\u{2009}\u{205F}\u{200A}\u{200A}\u{200A}"], $spacingParagraph instanceof DOMElement ? Html5Dom::attributes($spacingParagraph) : []);
        $t->same("\u{2060}\t", $textarea instanceof DOMElement ? $textarea->textContent : null);
        $t->same('{"ref":"&NoBreak;"}', $script instanceof DOMElement ? $script->textContent : null);
        $t->contains("A\u{2060}B\nC\tD \u{1D559} \u{00A0} ©", $serialized);
        $t->contains('<p data-literal="&amp;NoBreak test">Literal &amp;NoBreak test</p>', $serialized);
        $t->contains('<p data-math="' . "\u{2061}\u{2062}\u{2063}" . '">f' . "\u{2061}" . 'g' . "\u{2062}" . 'h' . "\u{205F}" . 'comma' . "\u{2063}" . 'zero' . "\u{200B}" . 'neg' . "\u{200B}" . '</p>', $serialized);
        $t->contains('<p data-spacing="' . "\u{00A0}\u{2009}\u{205F}\u{200A}\u{200A}\u{200A}" . '">Spaces: non' . "\u{00A0}" . 'thin' . "\u{2009}" . 'alias' . "\u{2009}" . 'thick' . "\u{205F}\u{200A}" . 'very' . "\u{200A}" . 'hair' . "\u{200A}" . 'neg' . "\u{200B}\u{200B}\u{200B}" . '</p>', $serialized);
        $t->contains('<textarea>' . "\u{2060}\t" . '</textarea>', $serialized);
        $t->contains('<script type="application/json">{"ref":"&NoBreak;"}</script>', $serialized);
        $t->true(!str_contains($serialized, '&amp;NoBreak;</p>'), 'Expected NoBreak to decode in ordinary text');
        $t->true(!str_contains($serialized, '&amp;hopf;'), 'Expected astral HTML5 named reference to decode before handoff');
        $t->true(!str_contains($serialized, '&amp;InvisibleTimes;'), 'Expected math spacing reference to decode before handoff');
        $t->true(!str_contains($serialized, '&amp;NegativeThinSpace;'), 'Expected zero-width spacing reference to decode before handoff');
        $t->true(!str_contains($serialized, '&amp;NonBreakingSpace;'), 'Expected named non-breaking space reference to decode before handoff');
        $t->true(!str_contains($serialized, '&amp;ThickSpace;'), 'Expected multi-codepoint spacing reference to decode before handoff');
        $t->true(!str_contains($serialized, '&amp;NegativeMediumSpace;'), 'Expected negative spacing aliases to decode before handoff');
    },
    'decodes bounded html5 punctuation named character references before reader handoff' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<p title="Issue&num;42&colon; &lpar;draft&rpar;" data-symbols="&dollar;&percnt;&commat;&lsqb;&rsqb;&lcub;&rcub;&vert;">'
            . 'Review&colon; packet&semi; status&equals;ok &lpar;ready&rpar; &lsqb;A&rsqb; &lcub;B&rcub; path&sol;to&bsol;asset &plus; &ast; &excl;&quest;'
            . '</p><textarea>Field&colon; &lpar;text&rpar; &semi;</textarea>'
            . '<script type="application/json">{"literal":"&colon;&semi;"}</script>'
        );
        $paragraph = Html5Dom::firstChildElement($body, 'p');
        $textarea = Html5Dom::firstChildElement($body, 'textarea');
        $script = Html5Dom::firstChildElement($body, 'script');
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($paragraph instanceof DOMElement, 'Expected punctuation-reference paragraph to parse');
        $t->same([
            'title' => 'Issue#42: (draft)',
            'data-symbols' => '$%@[]{}|',
        ], $paragraph instanceof DOMElement ? Html5Dom::attributes($paragraph) : []);
        $t->same('Review: packet; status=ok (ready) [A] {B} path/to\\asset + * !?', $paragraph instanceof DOMElement ? $paragraph->textContent : null);
        $t->true($textarea instanceof DOMElement, 'Expected punctuation-reference textarea to parse');
        $t->same('Field: (text) ;', $textarea instanceof DOMElement ? $textarea->textContent : null);
        $t->true($script instanceof DOMElement, 'Expected punctuation-reference script to parse');
        $t->same('{"literal":"&colon;&semi;"}', $script instanceof DOMElement ? $script->textContent : null);
        $t->same(
            '<p data-symbols="$%@[]{}|" title="Issue#42: (draft)">Review: packet; status=ok (ready) [A] {B} path/to\\asset + * !?</p>'
                . '<textarea>Field: (text) ;</textarea>'
                . '<script type="application/json">{"literal":"&colon;&semi;"}</script>',
            $serialized
        );
        foreach (['colon', 'semi', 'lpar', 'rsqb', 'dollar', 'commat', 'bsol', 'vert'] as $entityName) {
            $t->true(!str_contains($serialized, '&amp;' . $entityName . ';'), 'Expected punctuation reference ' . $entityName . ' to decode before handoff');
        }
        $t->contains('{"literal":"&colon;&semi;"}', $serialized);
    },
    'decodes safe semicolon html5 named references before reader handoff' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<p data-math="&NotEqualTilde;&DoubleLongRightArrow;&realine;">'
            . '&CounterClockwiseContourIntegral;&LeftTriangleBar;&NotNestedGreaterGreater;&angmsdaa;&bnequiv;&nparsl;&suphsol;&rarrfs;&nGg;&gesles;&lesg;&angzarr;'
            . '</p><p data-core="&quot;&amp;&lt;">core &quot;&amp;&lt;</p>'
            . '<script type="application/json">{"literal":"&NotEqualTilde;"}</script>'
        );
        $paragraphs = Html5Dom::childElements($body, 'p');
        $mathParagraph = $paragraphs[0] ?? null;
        $coreParagraph = $paragraphs[1] ?? null;
        $script = Html5Dom::firstChildElement($body, 'script');
        $serialized = Html5Dom::serializeHtmlChildren($body);
        $attribute = "\u{2242}\u{0338}\u{27F9}\u{211B}";
        $text = "\u{2233}\u{29CF}\u{2AA2}\u{0338}\u{29A8}\u{2261}\u{20E5}\u{2AFD}\u{20E5}\u{27C9}\u{291E}\u{22D9}\u{0338}\u{2A94}\u{22DA}\u{FE00}\u{237C}";

        $t->same(['data-math' => $attribute], $mathParagraph instanceof DOMElement ? Html5Dom::attributes($mathParagraph) : []);
        $t->same($text, $mathParagraph instanceof DOMElement ? $mathParagraph->textContent : null);
        $t->same(['data-core' => '"&<'], $coreParagraph instanceof DOMElement ? Html5Dom::attributes($coreParagraph) : []);
        $t->same('core "&<', $coreParagraph instanceof DOMElement ? $coreParagraph->textContent : null);
        $t->same('{"literal":"&NotEqualTilde;"}', $script instanceof DOMElement ? $script->textContent : null);
        $t->same('<p data-math="' . $attribute . '">' . $text . '</p><p data-core="&quot;&amp;&lt;">core "&amp;&lt;</p><script type="application/json">{"literal":"&NotEqualTilde;"}</script>', $serialized);
        foreach (['NotEqualTilde', 'CounterClockwiseContourIntegral', 'NotNestedGreaterGreater', 'bnequiv', 'angzarr'] as $entityName) {
            $t->true(!str_contains($serialized, '&amp;' . $entityName . ';'), 'Expected HTML5 reference ' . $entityName . ' to decode before reader handoff');
        }
        $t->contains('{"literal":"&NotEqualTilde;"}', $serialized);
    },
    'maps HTML fragment attributes and descendant elements for reviewer links' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<article id="post-42" class="legacy source" data-source="html" aria-label="Packet"><h1>Packet</h1><p><a href="/edit" title="Edit &amp; verify">edit</a></p></article>'
        );
        $article = Html5Dom::firstChildElement($body, 'article');
        $links = $article instanceof DOMElement ? Html5Dom::descendantElements($article, 'a') : [];

        $t->true($article instanceof DOMElement, 'Expected article child from HTML fragment body');
        $t->same([
            'id' => 'post-42',
            'class' => 'legacy source',
            'data-source' => 'html',
            'aria-label' => 'Packet',
        ], Html5Dom::attributes($article));
        $t->same(1, count($links));
        $t->same('/edit', Html5Dom::attributes($links[0])['href'] ?? null);
        $t->same('Edit & verify', Html5Dom::attributes($links[0])['title'] ?? null);
    },
    'ignores declaration looking text inside closed comments during safe source scans' => static function (TestRunner $t): void {
        $htmlComment = '<!-- <!DOCTYPE html><!ENTITY reviewer SYSTEM "file:///etc/passwd"><?xml-stylesheet href="https://example.invalid/review.xsl"?> -->';
        $htmlFragment = Html5Dom::parseHtmlFragment($htmlComment . '<p>Safe fragment</p>');
        $htmlDocument = Html5Dom::parseHtmlDocument('<!doctype html><!-- <!DOCTYPE svg><!ATTLIST html x CDATA #IMPLIED><?review href="file:///etc/passwd"?> --><html><body><main>Safe document</main></body></html>');
        $htmlDocumentBody = $htmlDocument->getElementsByTagName('body')->item(0);
        $xmlComment = '<!-- <!DOCTYPE x [<!ENTITY x SYSTEM "file:///etc/passwd">]><?review href="file:///etc/passwd"?> -->';
        $xmlFragment = Html5Dom::parseXmlFragment($xmlComment . '<root>Safe XML fragment</root>');
        $xmlDocument = Html5Dom::parseXmlDocument('<pkg><!-- <!ELEMENT pkg ANY><?review href="file:///etc/passwd"?> --><item>Safe XML document</item></pkg>');

        $t->same($htmlComment . '<p>Safe fragment</p>', Html5Dom::serializeHtmlChildren($htmlFragment));
        $t->same('Safe fragment', Html5Dom::normalizedText($htmlFragment));
        $t->true($htmlDocumentBody instanceof DOMElement, 'Expected HTML document body to parse');
        $t->same('<main>Safe document</main>', $htmlDocumentBody instanceof DOMElement ? Html5Dom::serializeHtmlChildren($htmlDocumentBody) : '');
        $t->same($xmlComment . '<root>Safe XML fragment</root>', Html5Dom::serializeXmlChildren($xmlFragment));
        $t->same('Safe XML fragment', Html5Dom::normalizedText($xmlFragment));
        $t->same('Safe XML document', $xmlDocument->documentElement instanceof DOMElement ? Html5Dom::normalizedText($xmlDocument->documentElement) : null);
    },
    'parses complete HTML documents with safe doctypes and rejects DTD or processing inputs' => static function (TestRunner $t): void {
        $dom = Html5Dom::parseHtmlDocument(
            '<!doctype html><html><head><title>Review</title></head><body><article data-source="export"><h1>Packet</h1><p>Imported<br>line</p></article></body></html>'
        );
        $body = $dom->getElementsByTagName('body')->item(0);
        $article = $body instanceof DOMElement ? Html5Dom::firstChildElement($body, 'article') : null;

        $t->true($dom->documentElement instanceof DOMElement, 'Expected complete HTML document to parse');
        $t->same('html', strtolower($dom->documentElement?->tagName ?? ''));
        $t->true($article instanceof DOMElement, 'Expected article body child from complete HTML document');
        $t->same(['data-source' => 'export'], $article instanceof DOMElement ? Html5Dom::attributes($article) : []);
        $t->same('PacketImported line', $article instanceof DOMElement ? Html5Dom::normalizedText($article) : null);
        $t->same('<article data-source="export"><h1>Packet</h1><p>Imported<br>line</p></article>', $body instanceof DOMElement ? Html5Dom::serializeHtmlChildren($body) : '');
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!DOCTYPE html [<!ENTITY reviewer SYSTEM "file:///etc/passwd">]><html><body>&reviewer;</body></html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!ELEMENT html ANY><html><body>bad</body></html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<?xml-stylesheet href="https://example.invalid/review.xsl"?><html><body>bad</body></html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument("<html><body>bad\0packet</body></html>"));
    },
    'preflights html declarations outside protected raw text content' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<script type="application/json">{"doctype":"<!DOCTYPE html>","pi":"<?review href=\"file\"?>"}</script>'
                . '<style>body:before{content:"<!ENTITY reviewer SYSTEM file>"}</style>'
                . '<textarea><!ENTITY reviewer SYSTEM "file:///etc/passwd"></textarea>'
                . '<template><!DOCTYPE html [<!ENTITY reviewer SYSTEM "file:///etc/passwd">]></template>'
                . '<iframe><?xml-stylesheet href="file"?></iframe>'
        );
        $document = Html5Dom::parseHtmlDocument(
            '<!doctype html><html><head><title>Review <!DOCTYPE html></title></head><body>'
                . '<script>{"doctype":"<!DOCTYPE html>"}</script>'
                . '<textarea><?review href="file"?></textarea>'
                . '</body></html>'
        );
        $documentBody = $document->getElementsByTagName('body')->item(0);
        $title = $document->getElementsByTagName('title')->item(0);
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->same('Review <!DOCTYPE html>', $title instanceof DOMElement ? $title->textContent : null);
        $t->same(
            '<script type="application/json">{"doctype":"<!DOCTYPE html>","pi":"<?review href=\"file\"?>"}</script>'
                . '<style>body:before{content:"<!ENTITY reviewer SYSTEM file>"}</style>'
                . '<textarea>&lt;!ENTITY reviewer SYSTEM "file:///etc/passwd"&gt;</textarea>'
                . '<template>&lt;!DOCTYPE html [&lt;!ENTITY reviewer SYSTEM "file:///etc/passwd"&gt;]&gt;</template>'
                . '<iframe>&lt;?xml-stylesheet href="file"?&gt;</iframe>',
            $serialized
        );
        $t->same(
            '<script>{"doctype":"<!DOCTYPE html>"}</script><textarea>&lt;?review href="file"?&gt;</textarea>',
            $documentBody instanceof DOMElement ? Html5Dom::serializeHtmlChildren($documentBody) : ''
        );
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseHtmlFragment('<p>bad</p><!DOCTYPE html>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!doctype html><html><body><p>bad</p><!ENTITY reviewer SYSTEM "file:///etc/passwd"></body></html>'
        ));
    },
    'rejects external and non-html complete document doctypes before parser loading' => static function (TestRunner $t): void {
        $dom = Html5Dom::parseHtmlDocument('<!DOCTYPE html><html><body><main><p>Review packet</p></main></body></html>');
        $body = $dom->getElementsByTagName('body')->item(0);

        $t->true($body instanceof DOMElement, 'Expected simple HTML doctype document to parse');
        $t->same('<main><p>Review packet</p></main>', $body instanceof DOMElement ? Html5Dom::serializeHtmlChildren($body) : '');
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!DOCTYPE html SYSTEM "file:///etc/passwd"><html><body><p>bad</p></body></html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "https://example.invalid/xhtml.dtd"><html><body><p>bad</p></body></html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!DOCTYPE svg><html><body><p>bad</p></body></html>'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseHtmlDocument(
            '<!DOCTYPE html><!DOCTYPE html><html><body><p>bad</p></body></html>'
        ));
    },
    'preserves bounded svg and mathml foreign content names for HTML reader handoff' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<figure><svg viewBox="0 0 10 10" preserveAspectRatio="xMidYMid meet"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg><math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math></figure>'
        );
        $figure = Html5Dom::firstChildElement($body, 'figure');
        $svg = $figure instanceof DOMElement ? Html5Dom::firstChildElement($figure, 'svg') : null;
        $gradient = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'linearGradient') : null;
        $textPath = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'textPath') : null;
        $math = $figure instanceof DOMElement ? Html5Dom::firstChildElement($figure, 'math') : null;
        $mi = $math instanceof DOMElement ? Html5Dom::firstChildElement($math, 'mi') : null;
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($svg instanceof DOMElement, 'Expected SVG foreign-content root to survive parsing');
        $t->same([
            'viewBox' => '0 0 10 10',
            'preserveAspectRatio' => 'xMidYMid meet',
        ], Html5Dom::attributes($svg));
        $t->true($gradient instanceof DOMElement, 'Expected adjusted linearGradient lookup to work');
        $t->true($textPath instanceof DOMElement, 'Expected adjusted textPath lookup to work');
        $t->true($mi instanceof DOMElement, 'Expected MathML mi child to survive parsing');
        $t->same(['definitionURL' => '#x'], Html5Dom::attributes($mi));
        $t->contains('<svg preserveAspectRatio="xMidYMid meet" viewBox="0 0 10 10"><linearGradient id="g"><stop offset="0"></stop></linearGradient><textPath href="#label">Logo</textPath></svg>', $serialized);
        $t->contains('<math><mi definitionURL="#x">x</mi><annotation-xml encoding="MathML-Content"><ci>x</ci></annotation-xml></math>', $serialized);
    },
    'preserves svg stitchTiles filter attribute casing for HTML reader handoff' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<svg><filter id="noise"><feTurbulence baseFrequency="0.8" numOctaves="2" stitchTiles="stitch"></feTurbulence></filter></svg>'
        );
        $svg = Html5Dom::firstChildElement($body, 'svg');
        $filter = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'filter') : null;
        $turbulence = $filter instanceof DOMElement ? Html5Dom::firstChildElement($filter, 'feTurbulence') : null;
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($turbulence instanceof DOMElement, 'Expected SVG feTurbulence element to preserve foreign casing');
        $t->same([
            'baseFrequency' => '0.8',
            'numOctaves' => '2',
            'stitchTiles' => 'stitch',
        ], $turbulence instanceof DOMElement ? Html5Dom::attributes($turbulence) : []);
        $t->same(
            '<svg><filter id="noise"><feTurbulence baseFrequency="0.8" numOctaves="2" stitchTiles="stitch"></feTurbulence></filter></svg>',
            $serialized
        );
        $t->true(!str_contains($serialized, 'stitchtiles='), 'Expected SVG stitchTiles attribute to serialize with HTML5 foreign-content casing');
    },
    'treats svg foreignObject and math annotation html descendants as html' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<svg><foreignObject><div viewBox="html attr"><linearGradient>HTML child</linearGradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject></svg>'
                . '<math><annotation-xml encoding="application/xhtml+xml"><div viewBox="math html"><textPath>HTML text</textPath></div></annotation-xml><annotation-xml encoding="MathML-Content"><ci definitionURL="#x">x</ci></annotation-xml></math>'
        );
        $svg = Html5Dom::firstChildElement($body, 'svg');
        $foreignObject = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'foreignObject') : null;
        $foreignDiv = $foreignObject instanceof DOMElement ? Html5Dom::firstChildElement($foreignObject, 'div') : null;
        $htmlGradient = $foreignDiv instanceof DOMElement ? Html5Dom::firstChildElement($foreignDiv, 'lineargradient') : null;
        $nestedSvg = $foreignDiv instanceof DOMElement ? Html5Dom::firstChildElement($foreignDiv, 'svg') : null;
        $nestedGradient = $nestedSvg instanceof DOMElement ? Html5Dom::firstChildElement($nestedSvg, 'linearGradient') : null;
        $math = Html5Dom::firstChildElement($body, 'math');
        $annotations = $math instanceof DOMElement ? Html5Dom::childElements($math, 'annotation-xml') : [];
        $mathHtmlDiv = isset($annotations[0]) ? Html5Dom::firstChildElement($annotations[0], 'div') : null;
        $mathHtmlTextPath = $mathHtmlDiv instanceof DOMElement ? Html5Dom::firstChildElement($mathHtmlDiv, 'textpath') : null;
        $mathCi = isset($annotations[1]) ? Html5Dom::firstChildElement($annotations[1], 'ci') : null;
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($foreignObject instanceof DOMElement, 'Expected SVG foreignObject to retain foreign-content casing');
        $t->true($foreignDiv instanceof DOMElement, 'Expected HTML div child inside foreignObject');
        $t->same(['viewbox' => 'html attr'], Html5Dom::attributes($foreignDiv));
        $t->true($htmlGradient instanceof DOMElement, 'Expected HTML child name to stay lowercase inside foreignObject');
        $t->true($nestedGradient instanceof DOMElement, 'Expected nested SVG child to re-enter foreign casing');
        $t->true($mathHtmlDiv instanceof DOMElement, 'Expected MathML annotation HTML child');
        $t->same(['viewbox' => 'math html'], Html5Dom::attributes($mathHtmlDiv));
        $t->true($mathHtmlTextPath instanceof DOMElement, 'Expected HTML descendant in annotation-xml to stay lowercase');
        $t->same(['definitionURL' => '#x'], $mathCi instanceof DOMElement ? Html5Dom::attributes($mathCi) : []);
        $t->contains('<foreignObject><div viewbox="html attr"><lineargradient>HTML child</lineargradient><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></div></foreignObject>', $serialized);
        $t->contains('<annotation-xml encoding="application/xhtml+xml"><div viewbox="math html"><textpath>HTML text</textpath></div></annotation-xml>', $serialized);
    },
    'treats svg desc descendants as html integration point content' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<svg><desc><p viewBox="html attr"><textPath>HTML fallback</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></desc></svg>'
        );
        $svg = Html5Dom::firstChildElement($body, 'svg');
        $desc = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'desc') : null;
        $paragraph = $desc instanceof DOMElement ? Html5Dom::firstChildElement($desc, 'p') : null;
        $textPath = $paragraph instanceof DOMElement ? Html5Dom::firstChildElement($paragraph, 'textpath') : null;
        $nestedSvg = $paragraph instanceof DOMElement ? Html5Dom::firstChildElement($paragraph, 'svg') : null;
        $nestedGradient = $nestedSvg instanceof DOMElement ? Html5Dom::firstChildElement($nestedSvg, 'linearGradient') : null;
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($desc instanceof DOMElement, 'Expected SVG desc integration point to survive parsing');
        $t->true($paragraph instanceof DOMElement, 'Expected HTML paragraph descendant inside SVG desc');
        $t->same(['viewbox' => 'html attr'], $paragraph instanceof DOMElement ? Html5Dom::attributes($paragraph) : []);
        $t->true($textPath instanceof DOMElement, 'Expected SVG-style textPath token inside desc to stay HTML lowercase');
        $t->true($nestedGradient instanceof DOMElement, 'Expected nested SVG descendant to re-enter foreign-content casing');
        $t->same('<svg><desc><p viewbox="html attr"><textpath>HTML fallback</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></desc></svg>', $serialized);
    },
    'treats svg title descendants as html integration point content' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<svg><title><p viewBox="html attr"><textPath>Title fallback</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></title></svg>'
        );
        $svg = Html5Dom::firstChildElement($body, 'svg');
        $title = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'title') : null;
        $paragraph = $title instanceof DOMElement ? Html5Dom::firstChildElement($title, 'p') : null;
        $textPath = $paragraph instanceof DOMElement ? Html5Dom::firstChildElement($paragraph, 'textpath') : null;
        $nestedSvg = $paragraph instanceof DOMElement ? Html5Dom::firstChildElement($paragraph, 'svg') : null;
        $nestedGradient = $nestedSvg instanceof DOMElement ? Html5Dom::firstChildElement($nestedSvg, 'linearGradient') : null;
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($title instanceof DOMElement, 'Expected SVG title integration point to survive parsing');
        $t->true($paragraph instanceof DOMElement, 'Expected HTML paragraph descendant inside SVG title');
        $t->same(['viewbox' => 'html attr'], $paragraph instanceof DOMElement ? Html5Dom::attributes($paragraph) : []);
        $t->true($textPath instanceof DOMElement, 'Expected SVG-style textPath token inside title to stay HTML lowercase');
        $t->true($nestedGradient instanceof DOMElement, 'Expected nested SVG descendant to re-enter foreign-content casing');
        $t->same('<svg><title><p viewbox="html attr"><textpath>Title fallback</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></p></title></svg>', $serialized);
        $t->true(!str_contains($serialized, '&lt;p viewBox'), 'Expected SVG title fallback markup to stay parsed instead of escaped as RCDATA');
    },
    'treats mathml token text descendants as html integration points' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<math><mtext><span viewBox="html attr"><textPath>HTML text</textPath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></span></mtext>'
                . '<mi><a href="/review">link</a></mi><mo><mglyph src="glyph.png"></mglyph></mo></math>'
        );
        $math = Html5Dom::firstChildElement($body, 'math');
        $mtext = $math instanceof DOMElement ? Html5Dom::firstChildElement($math, 'mtext') : null;
        $span = $mtext instanceof DOMElement ? Html5Dom::firstChildElement($mtext, 'span') : null;
        $textPath = $span instanceof DOMElement ? Html5Dom::firstChildElement($span, 'textpath') : null;
        $nestedSvg = $span instanceof DOMElement ? Html5Dom::firstChildElement($span, 'svg') : null;
        $nestedGradient = $nestedSvg instanceof DOMElement ? Html5Dom::firstChildElement($nestedSvg, 'linearGradient') : null;
        $mi = $math instanceof DOMElement ? Html5Dom::firstChildElement($math, 'mi') : null;
        $link = $mi instanceof DOMElement ? Html5Dom::firstChildElement($mi, 'a') : null;
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($mtext instanceof DOMElement, 'Expected MathML mtext token');
        $t->true($span instanceof DOMElement, 'Expected HTML span descendant inside mtext');
        $t->same(['viewbox' => 'html attr'], $span instanceof DOMElement ? Html5Dom::attributes($span) : []);
        $t->true($textPath instanceof DOMElement, 'Expected textPath descendant to remain HTML lowercase textpath');
        $t->true($nestedGradient instanceof DOMElement, 'Expected nested SVG to re-enter foreign-content casing');
        $t->true($link instanceof DOMElement, 'Expected HTML link descendant inside mi');
        $t->same(['href' => '/review'], $link instanceof DOMElement ? Html5Dom::attributes($link) : []);
        $t->same('<math><mtext><span viewbox="html attr"><textpath>HTML text</textpath><svg viewBox="0 0 1 1"><linearGradient id="nested"></linearGradient></svg></span></mtext><mi><a href="/review">link</a></mi><mo><mglyph src="glyph.png"></mglyph></mo></math>', $serialized);
    },
    'keeps mathml mglyph and malignmark in foreign content under text integration points' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<math><mi><malignmark definitionURL="#mark"><svg viewBox="0 0 1 1"><linearGradient id="g"></linearGradient></svg></malignmark><mglyph definitionURL="#glyph"></mglyph><span definitionURL="#html">HTML</span></mi></math>'
        );
        $math = Html5Dom::firstChildElement($body, 'math');
        $mi = $math instanceof DOMElement ? Html5Dom::firstChildElement($math, 'mi') : null;
        $malignmark = $mi instanceof DOMElement ? Html5Dom::firstChildElement($mi, 'malignmark') : null;
        $mglyph = $mi instanceof DOMElement ? Html5Dom::firstChildElement($mi, 'mglyph') : null;
        $span = $mi instanceof DOMElement ? Html5Dom::firstChildElement($mi, 'span') : null;
        $svg = $malignmark instanceof DOMElement ? Html5Dom::firstChildElement($malignmark, 'svg') : null;
        $gradient = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'linearGradient') : null;
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->same(['definitionURL' => '#mark'], $malignmark instanceof DOMElement ? Html5Dom::attributes($malignmark) : []);
        $t->same(['definitionURL' => '#glyph'], $mglyph instanceof DOMElement ? Html5Dom::attributes($mglyph) : []);
        $t->same(['definitionurl' => '#html'], $span instanceof DOMElement ? Html5Dom::attributes($span) : []);
        $t->true($gradient instanceof DOMElement, 'Expected SVG nested below MathML exception to retain foreign-content casing');
        $t->same('<math><mi><malignmark definitionURL="#mark"><svg viewBox="0 0 1 1"><linearGradient id="g"></linearGradient></svg></malignmark><mglyph definitionURL="#glyph"></mglyph><span definitionurl="#html">HTML</span></mi></math>', $serialized);
    },
    'parses html foreign-content cdata sections as text for reader handoff' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<svg><desc><![CDATA[Reviewer <source> & notes]]></desc><text><![CDATA[A < B & C]]></text></svg>'
                . '<math><annotation encoding="application/x-tex"><![CDATA[x < y & z]]></annotation></math>'
        );
        $svg = Html5Dom::firstChildElement($body, 'svg');
        $desc = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'desc') : null;
        $text = $svg instanceof DOMElement ? Html5Dom::firstChildElement($svg, 'text') : null;
        $math = Html5Dom::firstChildElement($body, 'math');
        $annotation = $math instanceof DOMElement ? Html5Dom::firstChildElement($math, 'annotation') : null;
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($desc instanceof DOMElement, 'Expected SVG desc CDATA container to survive parsing');
        $t->true($text instanceof DOMElement, 'Expected SVG text CDATA container to survive parsing');
        $t->same('Reviewer <source> & notes', $desc instanceof DOMElement ? Html5Dom::normalizedText($desc) : null);
        $t->same('A < B & C', $text instanceof DOMElement ? Html5Dom::normalizedText($text) : null);
        $t->true($annotation instanceof DOMElement, 'Expected MathML annotation CDATA container to survive parsing');
        $t->same(['encoding' => 'application/x-tex'], $annotation instanceof DOMElement ? Html5Dom::attributes($annotation) : []);
        $t->same('x < y & z', $annotation instanceof DOMElement ? Html5Dom::normalizedText($annotation) : null);
        $t->same('<svg><desc>Reviewer &lt;source&gt; &amp; notes</desc><text>A &lt; B &amp; C</text></svg><math><annotation encoding="application/x-tex">x &lt; y &amp; z</annotation></math>', $serialized);
        $t->true(!str_contains($serialized, '<![CDATA['), 'Expected CDATA delimiters to be normalized away before serialization');
        $t->true(!str_contains($serialized, '<source>'), 'Expected CDATA tag-looking source text to stay escaped');
    },
    'treats html title and textarea bodies as rcdata text before dom traversal' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<textarea data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></textarea>'
                . '<title>Packet <em>literal</em> &amp; title</title>'
        );
        $textarea = Html5Dom::firstChildElement($body, 'textarea');
        $title = Html5Dom::firstChildElement($body, 'title');
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($textarea instanceof DOMElement, 'Expected textarea review field to survive DOM parsing');
        $t->true($title instanceof DOMElement, 'Expected title element to survive DOM parsing');
        $t->same('Reviewer <script>alert(1)</script> & <b>note</b>', $textarea instanceof DOMElement ? $textarea->textContent : null);
        $t->same('Packet <em>literal</em> & title', $title instanceof DOMElement ? $title->textContent : null);
        $t->same([], $textarea instanceof DOMElement ? Html5Dom::childElements($textarea) : []);
        $t->same([], $title instanceof DOMElement ? Html5Dom::childElements($title) : []);
        $t->same(
            '<textarea data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp; &lt;b&gt;note&lt;/b&gt;</textarea><title>Packet &lt;em&gt;literal&lt;/em&gt; &amp; title</title>',
            $serialized
        );
    },
    'treats obsolete html raw text fallback bodies as literal source text' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<xmp data-source="legacy">Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea></xmp>'
                . '<noembed>Fallback <img src=x> & source</noembed>'
                . '<noframes>Frame fallback <a href="/edit">edit</a></noframes><p>after</p>'
        );
        $xmp = Html5Dom::firstChildElement($body, 'xmp');
        $noembed = Html5Dom::firstChildElement($body, 'noembed');
        $noframes = Html5Dom::firstChildElement($body, 'noframes');
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($xmp instanceof DOMElement, 'Expected xmp fallback container to survive DOM parsing');
        $t->true($noembed instanceof DOMElement, 'Expected noembed fallback container to survive DOM parsing');
        $t->true($noframes instanceof DOMElement, 'Expected noframes fallback container to survive DOM parsing');
        $t->same('Reviewer <script>alert(1)</script> &amp; <textarea><b>note</b></textarea>', $xmp instanceof DOMElement ? $xmp->textContent : null);
        $t->same('Fallback <img src=x> & source', $noembed instanceof DOMElement ? $noembed->textContent : null);
        $t->same('Frame fallback <a href="/edit">edit</a>', $noframes instanceof DOMElement ? $noframes->textContent : null);
        $t->same([], $xmp instanceof DOMElement ? Html5Dom::childElements($xmp) : []);
        $t->same([], $noembed instanceof DOMElement ? Html5Dom::childElements($noembed) : []);
        $t->same([], $noframes instanceof DOMElement ? Html5Dom::childElements($noframes) : []);
        $t->same(
            '<xmp data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;textarea&gt;&lt;b&gt;note&lt;/b&gt;&lt;/textarea&gt;</xmp><noembed>Fallback &lt;img src=x&gt; &amp; source</noembed><noframes>Frame fallback &lt;a href="/edit"&gt;edit&lt;/a&gt;</noframes><p>after</p>',
            $serialized
        );
        $t->true(!str_contains($serialized, '<textarea>'), 'Expected raw text textarea-looking source to serialize as escaped text');
        $t->true(!str_contains($serialized, '<script>alert(1)</script>'), 'Expected tag-looking raw text to serialize as escaped text');
        $t->true(!str_contains($serialized, '<img src=x>'), 'Expected fallback image-looking source text to serialize as escaped text');
    },
    'treats html noscript bodies as escaped source during dom traversal' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<noscript data-source="legacy">Fallback <script>alert(1)</script> & source <img src=x></noscript><p>after</p>'
        );
        $noscript = Html5Dom::firstChildElement($body, 'noscript');
        $paragraph = Html5Dom::firstChildElement($body, 'p');
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->true($noscript instanceof DOMElement, 'Expected noscript fallback container to survive DOM parsing');
        $t->same(['data-source' => 'legacy'], $noscript instanceof DOMElement ? Html5Dom::attributes($noscript) : []);
        $t->same('Fallback <script>alert(1)</script> & source <img src=x>', $noscript instanceof DOMElement ? $noscript->textContent : null);
        $t->same([], $noscript instanceof DOMElement ? Html5Dom::childElements($noscript) : []);
        $t->true($paragraph instanceof DOMElement, 'Expected following paragraph to stay outside noscript text');
        $t->same('after', $paragraph instanceof DOMElement ? Html5Dom::normalizedText($paragraph) : null);
        $t->same(
            '<noscript data-source="legacy">Fallback &lt;script&gt;alert(1)&lt;/script&gt; &amp; source &lt;img src=x&gt;</noscript><p>after</p>',
            $serialized
        );
        $t->true(!str_contains($serialized, '<script>alert(1)</script>'), 'Expected noscript script-looking source to stay escaped');
        $t->true(!str_contains($serialized, '<img src=x>'), 'Expected noscript image-looking source to stay escaped');
    },
    'treats html plaintext as escaped source text without capturing wrapper tags' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<textarea><plaintext>literal</textarea><p>after</p>'
                . '<plaintext data-source="legacy">Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>hidden</p>'
        );
        $textarea = Html5Dom::firstChildElement($body, 'textarea');
        $plaintext = Html5Dom::firstChildElement($body, 'plaintext');
        $serialized = Html5Dom::serializeHtmlChildren($body);
        $expectedPlaintext = 'Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>hidden</p>';

        $t->true($textarea instanceof DOMElement, 'Expected textarea to stay separate from plaintext handling');
        $t->same('<plaintext>literal', $textarea instanceof DOMElement ? $textarea->textContent : null);
        $t->true($plaintext instanceof DOMElement, 'Expected plaintext review source to survive DOM parsing');
        $t->same(['data-source' => 'legacy'], $plaintext instanceof DOMElement ? Html5Dom::attributes($plaintext) : []);
        $t->same($expectedPlaintext, $plaintext instanceof DOMElement ? $plaintext->textContent : null);
        $t->same(
            '<textarea>&lt;plaintext&gt;literal</textarea><p>after</p><plaintext data-source="legacy">Reviewer &lt;script&gt;alert(1)&lt;/script&gt; &amp;amp; &lt;b&gt;note&lt;/b&gt;&lt;/plaintext&gt;&lt;p&gt;hidden&lt;/p&gt;</plaintext>',
            $serialized
        );
        $t->true(!str_contains($serialized, '</body>'), 'Expected synthetic wrapper close tags not to leak into plaintext text');
        $t->true(!str_contains($serialized, '<p>hidden</p>'), 'Expected following paragraph source to stay plaintext text');
    },
    'treats html template contents as inert escaped source during reader traversal' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<template data-source="legacy"><p>Template <script>drop()</script> &amp; <b>note</b></p></template><p>after</p>'
        );
        $template = Html5Dom::firstChildElement($body, 'template');
        $paragraph = Html5Dom::firstChildElement($body, 'p');
        $serialized = Html5Dom::serializeHtmlChildren($body);
        $expectedTemplateText = '<p>Template <script>drop()</script> &amp; <b>note</b></p>';
        $expectedHtml = '<template data-source="legacy">&lt;p&gt;Template &lt;script&gt;drop()&lt;/script&gt; &amp;amp; &lt;b&gt;note&lt;/b&gt;&lt;/p&gt;</template><p>after</p>';

        $t->true($template instanceof DOMElement, 'Expected template element to survive parser traversal');
        $t->same(['data-source' => 'legacy'], $template instanceof DOMElement ? Html5Dom::attributes($template) : []);
        $t->same($expectedTemplateText, $template instanceof DOMElement ? $template->textContent : null);
        $t->same([], $template instanceof DOMElement ? Html5Dom::childElements($template) : []);
        $t->true($paragraph instanceof DOMElement, 'Expected following paragraph to stay outside inert template text');
        $t->same('after', $paragraph instanceof DOMElement ? Html5Dom::normalizedText($paragraph) : null);
        $t->same($expectedHtml, $serialized);
        $t->true(!str_contains($serialized, '<script>drop()</script>'), 'Expected template script-looking source to stay escaped');
        $t->true(!str_contains($serialized, '<b>note</b>'), 'Expected template inline tag-looking source to stay escaped');

        $document = Html5Dom::parseHtmlDocument(
            '<!doctype html><html><body><template><img src="cover.png"></template><p>doc</p></body></html>'
        );
        $documentBody = $document->getElementsByTagName('body')->item(0);
        $t->same(
            '<template>&lt;img src="cover.png"&gt;</template><p>doc</p>',
            $documentBody instanceof DOMElement ? Html5Dom::serializeHtmlChildren($documentBody) : ''
        );
    },
    'keeps html template boundaries across nested template and raw text sentinels' => static function (TestRunner $t): void {
        $templateSource = '<template data-inner="1"><p>Inner</p></template>'
            . '<noscript><script>const fallback = "</template>";</script><p>Fallback</p></noscript>'
            . '<script>const sentinel = "</template>";</script><p>Tail</p>';
        $body = Html5Dom::parseHtmlFragment('<template id="outer">' . $templateSource . '</template><p>after</p>');
        $children = Html5Dom::childElements($body);
        $template = $children[0] ?? null;
        $paragraph = $children[1] ?? null;
        $serialized = Html5Dom::serializeHtmlChildren($body);
        $expectedEscaped = '&lt;template data-inner="1"&gt;&lt;p&gt;Inner&lt;/p&gt;&lt;/template&gt;'
            . '&lt;noscript&gt;&lt;script&gt;const fallback = "&lt;/template&gt;";&lt;/script&gt;&lt;p&gt;Fallback&lt;/p&gt;&lt;/noscript&gt;'
            . '&lt;script&gt;const sentinel = "&lt;/template&gt;";&lt;/script&gt;&lt;p&gt;Tail&lt;/p&gt;';

        $t->same(2, count($children));
        $t->true($template instanceof DOMElement, 'Expected outer template to survive as the first body child');
        $t->same('template', $template instanceof DOMElement ? strtolower($template->tagName) : null);
        $t->same(['id' => 'outer'], $template instanceof DOMElement ? Html5Dom::attributes($template) : []);
        $t->same($templateSource, $template instanceof DOMElement ? $template->textContent : null);
        $t->same([], $template instanceof DOMElement ? Html5Dom::childElements($template) : []);
        $t->true($paragraph instanceof DOMElement, 'Expected following paragraph to stay outside the outer template');
        $t->same('after', $paragraph instanceof DOMElement ? Html5Dom::normalizedText($paragraph) : null);
        $t->same('<template id="outer">' . $expectedEscaped . '</template><p>after</p>', $serialized);
        $t->true(!str_contains($serialized, '<p>Tail</p><p>after</p>'), 'Expected template tail paragraph to stay escaped inside the outer template');
    },
    'serializes invalid table-scope children before the table for html5 reader handoff' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<table class="legacy"><caption>Review rows</caption><p>Loose note</p><tr><td>A</td></tr>orphan text<tr><td>B</td></tr></table><p>after</p>'
        );
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->same('<p>Loose note</p>orphan text<table class="legacy"><caption>Review rows</caption><tbody><tr><td>A</td></tr><tr><td>B</td></tr></tbody></table><p>after</p>', $serialized);
        $t->true(!str_contains($serialized, '</caption><p>Loose note</p>'), 'Expected loose paragraph to move outside the table');
        $t->true(!str_contains($serialized, '</tr>orphan text<tr>'), 'Expected loose text to move outside the table rows');
    },
    'wraps orphan table fragments before html5 reader handoff serialization' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment(
            '<td>A</td><td>B</td><tr><td>C</td></tr><col span="2"><p>after</p>'
        );
        $serialized = Html5Dom::serializeHtmlChildren($body);

        $t->same('ABCafter', Html5Dom::normalizedText($body));
        $t->same(
            '<table><tbody><tr><td>A</td><td>B</td></tr><tr><td>C</td></tr></tbody><colgroup><col span="2"></colgroup></table><p>after</p>',
            $serialized
        );
        $t->true(!str_starts_with($serialized, '<td>'), 'Expected orphan table cells to be wrapped before raw handoff');
        $t->true(!str_contains($serialized, '</tr><col '), 'Expected orphan columns to be wrapped in a generated colgroup');
    },
    'parses XML fragments with namespaces and serializes multiple root children' => static function (TestRunner $t): void {
        $fragment = Html5Dom::parseXmlFragment(
            '<m:math xmlns:m="urn:math"><m:mi>x</m:mi></m:math><w:t xmlns:w="urn:word" xml:space="preserve"> reviewer text </w:t>'
        );
        $children = Html5Dom::childElements($fragment);
        $wordText = Html5Dom::childElements($fragment, 't', 'urn:word')[0] ?? null;
        $serialized = Html5Dom::serializeXmlChildren($fragment);

        $t->same(2, count($children));
        $t->same('math', $children[0]->localName);
        $t->same('urn:math', $children[0]->namespaceURI);
        $t->true($wordText instanceof DOMElement, 'Expected namespace-filtered Word text child');
        $t->same(['xml:space' => 'preserve'], Html5Dom::attributes($wordText));
        $t->same('x reviewer text', Html5Dom::normalizedText($fragment));
        $t->contains('<m:math xmlns:m="urn:math"><m:mi>x</m:mi></m:math>', $serialized);
        $t->contains('<w:t xmlns:w="urn:word" xml:space="preserve"> reviewer text </w:t>', $serialized);
    },
    'parses XML documents with declarations and rejects processing instruction nodes' => static function (TestRunner $t): void {
        $dom = Html5Dom::parseXmlDocument(
            '<?xml version="1.0" encoding="UTF-8"?><pkg xmlns="urn:packet"><item>Review packet</item></pkg>',
            'declared XML document'
        );
        $root = $dom->documentElement;

        $t->true($root instanceof DOMElement);
        $t->same('pkg', $root->localName);
        $t->same('urn:packet', $root->namespaceURI);
        $t->same('Review packet', Html5Dom::normalizedText($root));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseXmlDocument(
            '<?xml-stylesheet href="https://example.invalid/review.xsl"?><pkg/>',
            'stylesheet XML document'
        ));
        $t->throws(InvalidArgumentException::class, static fn (): DOMDocument => Html5Dom::parseXmlDocument(
            '<?xml version="1.0"?><pkg><?review href="file:///etc/passwd"?></pkg>',
            'review PI XML document'
        ));
    },
    'rejects unsafe XML declarations doctypes entities and NUL bytes before parsing' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<?xml version="1.0"?><root/>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<!DOCTYPE root><root/>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<!ENTITY reviewer SYSTEM "https://example.invalid/reviewer"><root/>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<?xml-stylesheet href="https://example.invalid/review.xsl"?><root/>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseHtmlFragment("review\0packet"));
        $t->throws(RuntimeException::class, static fn (): DOMElement => Html5Dom::parseXmlFragment('<root><child></root>'));
    },
    'rejects unsafe HTML fragment declarations before parser repair' => static function (TestRunner $t): void {
        $body = Html5Dom::parseHtmlFragment('<p data-review="ok">Safe</p>');

        $t->same('<p data-review="ok">Safe</p>', Html5Dom::serializeHtmlChildren($body));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseHtmlFragment('<!DOCTYPE html><p>bad</p>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseHtmlFragment('<!ENTITY reviewer SYSTEM "file:///etc/passwd"><p>&reviewer;</p>'));
        $t->throws(InvalidArgumentException::class, static fn (): DOMElement => Html5Dom::parseHtmlFragment('<?xml-stylesheet href="https://example.invalid/review.xsl"?><p>bad</p>'));
    },
];
