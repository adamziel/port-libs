<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return trim($text);
};

$cases = [
    '01 html comment single line' => [
        'markdown' => "<!-- review packet -->\n\nAfter",
        'raw' => '<!-- review packet -->',
    ],
    '02 html comment multiline markdown looking list' => [
        'markdown' => "<!--\n- not a list\n-->\n\nAfter",
        'raw' => "<!--\n- not a list\n-->",
    ],
    '03 processing instruction single line' => [
        'markdown' => "<?review import?>\n\nAfter",
        'raw' => '<?review import?>',
    ],
    '04 processing instruction multiline' => [
        'markdown' => "<?review\npacket?>\n\nAfter",
        'raw' => "<?review\npacket?>",
    ],
    '05 doctype declaration' => [
        'markdown' => "<!DOCTYPE html>\n\nAfter",
        'raw' => '<!DOCTYPE html>',
    ],
    '06 custom declaration with attributes' => [
        'markdown' => "<!review data-source=\"batch\">\n\nAfter",
        'raw' => '<!review data-source="batch">',
    ],
    '07 cdata single line' => [
        'markdown' => "<![CDATA[<source> &copy; **raw**]]>\n\nAfter",
        'raw' => '<![CDATA[<source> &copy; **raw**]]>',
    ],
    '08 cdata multiline markdown looking heading' => [
        'markdown' => "<![CDATA[\n# not a heading\n]]>\n\nAfter",
        'raw' => "<![CDATA[\n# not a heading\n]]>",
    ],
    '09 script json payload' => [
        'markdown' => "<script type=\"application/json\">\n{\"a\":1}\n</script>\n\nAfter",
        'raw' => "<script type=\"application/json\">\n{\"a\":1}\n</script>",
    ],
    '10 script preserves blank lines until close' => [
        'markdown' => "<script>\n# not a heading\n\nstill raw\n</script>\n\nAfter",
        'raw' => "<script>\n# not a heading\n\nstill raw\n</script>",
    ],
    '11 style preserves blank lines until close' => [
        'markdown' => "<style>\n.review { color: red; }\n\n# not heading\n</style>\n\nAfter",
        'raw' => "<style>\n.review { color: red; }\n\n# not heading\n</style>",
    ],
    '12 pre without code stays raw' => [
        'markdown' => "<pre>\n# not heading\n\nliteral\n</pre>\n\nAfter",
        'raw' => "<pre>\n# not heading\n\nliteral\n</pre>",
    ],
    '13 textarea stays raw until close' => [
        'markdown' => "<textarea>\n# source note\n</textarea>\n\nAfter",
        'raw' => "<textarea>\n# source note\n</textarea>",
    ],
    '14 noscript preserves internal blank line' => [
        'markdown' => "<noscript>\n# fallback\n\nstill fallback\n</noscript>\n\nAfter",
        'raw' => "<noscript>\n# fallback\n\nstill fallback\n</noscript>",
    ],
    '15 xmp preserves internal blank line' => [
        'markdown' => "<xmp>\n# literal\n\nstill literal\n</xmp>\n\nAfter",
        'raw' => "<xmp>\n# literal\n\nstill literal\n</xmp>",
    ],
    '16 section quoted greater than attribute' => [
        'markdown' => "<section data-title=\"a > b\">\nraw section\n</section>\n\nAfter",
        'raw' => "<section data-title=\"a > b\">\nraw section\n</section>",
    ],
    '17 article raw block' => [
        'markdown' => "<article>\nraw article\n</article>\n\nAfter",
        'raw' => "<article>\nraw article\n</article>",
    ],
    '18 aside raw block' => [
        'markdown' => "<aside>\nraw aside\n</aside>\n\nAfter",
        'raw' => "<aside>\nraw aside\n</aside>",
    ],
    '19 footer raw block' => [
        'markdown' => "<footer>\nraw footer\n</footer>\n\nAfter",
        'raw' => "<footer>\nraw footer\n</footer>",
    ],
    '20 header raw block' => [
        'markdown' => "<header>\nraw header\n</header>\n\nAfter",
        'raw' => "<header>\nraw header\n</header>",
    ],
    '21 main raw block' => [
        'markdown' => "<main>\nraw main\n</main>\n\nAfter",
        'raw' => "<main>\nraw main\n</main>",
    ],
    '22 nav raw block' => [
        'markdown' => "<nav>\nraw nav\n</nav>\n\nAfter",
        'raw' => "<nav>\nraw nav\n</nav>",
    ],
    '23 figure raw block' => [
        'markdown' => "<figure>\nraw figure\n</figure>\n\nAfter",
        'raw' => "<figure>\nraw figure\n</figure>",
    ],
    '24 figcaption raw block' => [
        'markdown' => "<figcaption>\nraw caption\n</figcaption>\n\nAfter",
        'raw' => "<figcaption>\nraw caption\n</figcaption>",
    ],
    '25 details summary raw block' => [
        'markdown' => "<details open>\n<summary>Title</summary>\nbody\n</details>\n\nAfter",
        'raw' => "<details open>\n<summary>Title</summary>\nbody\n</details>",
    ],
    '26 dialog raw block' => [
        'markdown' => "<dialog open>\nraw dialog\n</dialog>\n\nAfter",
        'raw' => "<dialog open>\nraw dialog\n</dialog>",
    ],
    '27 fieldset raw block' => [
        'markdown' => "<fieldset>\n<legend>Legend</legend>\nbody\n</fieldset>\n\nAfter",
        'raw' => "<fieldset>\n<legend>Legend</legend>\nbody\n</fieldset>",
    ],
    '28 form raw block' => [
        'markdown' => "<form action=\"/review\">\n<input name=\"packet\">\n</form>\n\nAfter",
        'raw' => "<form action=\"/review\">\n<input name=\"packet\">\n</form>",
    ],
    '29 iframe raw block' => [
        'markdown' => "<iframe src=\"packet.html\">\nfallback\n</iframe>\n\nAfter",
        'raw' => "<iframe src=\"packet.html\">\nfallback\n</iframe>",
    ],
    '30 body raw block' => [
        'markdown' => "<body data-review=\"1\">\nraw body\n</body>\n\nAfter",
        'raw' => "<body data-review=\"1\">\nraw body\n</body>",
    ],
    '31 caption raw block' => [
        'markdown' => "<caption>\nraw caption\n</caption>\n\nAfter",
        'raw' => "<caption>\nraw caption\n</caption>",
    ],
    '32 colgroup raw block' => [
        'markdown' => "<colgroup>\n<col>\n</colgroup>\n\nAfter",
        'raw' => "<colgroup>\n<col>\n</colgroup>",
    ],
    '33 tbody raw block' => [
        'markdown' => "<tbody>\n<tr><td>A</td></tr>\n</tbody>\n\nAfter",
        'raw' => "<tbody>\n<tr><td>A</td></tr>\n</tbody>",
    ],
    '34 tfoot raw block' => [
        'markdown' => "<tfoot>\n<tr><td>Total</td></tr>\n</tfoot>\n\nAfter",
        'raw' => "<tfoot>\n<tr><td>Total</td></tr>\n</tfoot>",
    ],
    '35 table row raw block' => [
        'markdown' => "<tr>\n<td>Cell</td>\n</tr>\n\nAfter",
        'raw' => "<tr>\n<td>Cell</td>\n</tr>",
    ],
    '36 source void raw block' => [
        'markdown' => "<source src=\"clip.webm\" type=\"video/webm\">\n\nAfter",
        'raw' => '<source src="clip.webm" type="video/webm">',
    ],
    '37 track void raw block' => [
        'markdown' => "<track kind=\"captions\" src=\"captions.vtt\">\n\nAfter",
        'raw' => '<track kind="captions" src="captions.vtt">',
    ],
    '38 link void raw block' => [
        'markdown' => "<link rel=\"stylesheet\" href=\"style.css\">\n\nAfter",
        'raw' => '<link rel="stylesheet" href="style.css">',
    ],
    '39 meta void raw block' => [
        'markdown' => "<meta charset=\"utf-8\">\n\nAfter",
        'raw' => '<meta charset="utf-8">',
    ],
    '40 param void raw block' => [
        'markdown' => "<param name=\"movie\" value=\"review.swf\">\n\nAfter",
        'raw' => '<param name="movie" value="review.swf">',
    ],
    '41 svg raw block with internal blank' => [
        'markdown' => "<svg viewBox=\"0 0 10 10\">\n<circle cx=\"5\" cy=\"5\" r=\"4\" />\n\n<text>raw</text>\n</svg>\n\nAfter",
        'raw' => "<svg viewBox=\"0 0 10 10\">\n<circle cx=\"5\" cy=\"5\" r=\"4\" />\n\n<text>raw</text>\n</svg>",
    ],
    '42 math raw block with internal blank' => [
        'markdown' => "<math>\n<mrow><mi>x</mi><mo>=</mo><mn>1</mn></mrow>\n\n<mtext>raw</mtext>\n</math>\n\nAfter",
        'raw' => "<math>\n<mrow><mi>x</mi><mo>=</mo><mn>1</mn></mrow>\n\n<mtext>raw</mtext>\n</math>",
    ],
    '43 canvas raw block' => [
        'markdown' => "<canvas width=\"10\" height=\"10\">\nfallback\n</canvas>\n\nAfter",
        'raw' => "<canvas width=\"10\" height=\"10\">\nfallback\n</canvas>",
    ],
    '44 template raw block with markdown looking content' => [
        'markdown' => "<template>\n# template heading\n- template list\n</template>\n\nAfter",
        'raw' => "<template>\n# template heading\n- template list\n</template>",
    ],
    '45 object raw block' => [
        'markdown' => "<object data=\"packet.svg\">\nfallback\n</object>\n\nAfter",
        'raw' => "<object data=\"packet.svg\">\nfallback\n</object>",
    ],
    '46 picture raw block' => [
        'markdown' => "<picture>\n<source srcset=\"wide.png\">\n<img src=\"narrow.png\" alt=\"narrow\">\n</picture>\n\nAfter",
        'raw' => "<picture>\n<source srcset=\"wide.png\">\n<img src=\"narrow.png\" alt=\"narrow\">\n</picture>",
    ],
    '47 ruby raw block' => [
        'markdown' => "<ruby>\nkan<rt>reading</rt>\n</ruby>\n\nAfter",
        'raw' => "<ruby>\nkan<rt>reading</rt>\n</ruby>",
    ],
    '48 meter raw block' => [
        'markdown' => "<meter value=\"0.5\">\nhalf\n</meter>\n\nAfter",
        'raw' => "<meter value=\"0.5\">\nhalf\n</meter>",
    ],
    '49 progress raw block' => [
        'markdown' => "<progress max=\"10\" value=\"7\">\nseven\n</progress>\n\nAfter",
        'raw' => "<progress max=\"10\" value=\"7\">\nseven\n</progress>",
    ],
    '50 generic custom raw block line' => [
        'markdown' => "<review-block data-x=\"1\">\ncustom raw\n\nAfter",
        'raw' => "<review-block data-x=\"1\">\ncustom raw",
    ],
    '51 basefont blank-terminated raw block' => [
        'markdown' => "<basefont color=\"red\" face=\"serif\">\nlegacy font metadata\n\nAfter",
        'raw' => "<basefont color=\"red\" face=\"serif\">\nlegacy font metadata",
    ],
];

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream markdown reader raw html block surge ' . $name] =
        static function (TestRunner $t) use ($case, $plainText): void {
            $document = (new MarkdownReader())->read($case['markdown']);
            $raw = $document->children[0] ?? new AstNode('missing');
            $after = $document->children[1] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('raw_html', $raw->type, $case['raw']);
            $t->same($case['raw'], $raw->attr('html'), $case['raw']);
            $t->same('paragraph', $after->type, $case['raw']);
            $t->same('After', $plainText($after), $case['raw']);
            $t->contains("<!-- wp:html -->\n" . $case['raw'] . "\n<!-- /wp:html -->", $blocks, $case['raw']);
        };
}

$tests['records markdown raw html block surge mapped-case count'] = static function (TestRunner $t) use ($cases): void {
    $t->same(51, count($cases));
};

return $tests;
