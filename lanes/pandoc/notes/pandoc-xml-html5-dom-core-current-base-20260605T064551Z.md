# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T064551Z`

Base accepted HEAD: `648921bad2812fb886ed9ddc4a44b11bdbf63665`

## Behavior Added

- Added bounded HTML plaintext-state source protection to the shared
  XML/HTML5 DOM support layer.
- `XmlHtmlDom::protectHtmlRcdataElements()` now processes raw/RCDATA elements
  in source order and treats the first `<plaintext>` start tag as consuming the
  rest of the user-provided HTML fragment as raw escaped text.
- `Html5Dom::parseHtmlFragment()` protects user input before adding synthetic
  wrapper tags so wrapper `</body></html>` markup cannot leak into plaintext
  text.
- `Html5DomFragment` unwraps sanitized `plaintext` review content, records a
  `blocked-tag` diagnostic, and preserves the visible source tail for
  WordPress raw HTML block review.
- The WordPress HTML5 DOM smoke now proves a legacy plaintext tail reaches the
  review block as escaped source text while the obsolete wrapper is stripped.

## Source Truth

- Source truth is the lane-local Pandoc XML/HTML5 DOM support contract plus the
  HTML5 plaintext-state behavior needed by HTML-reader and XHTML review
  handoffs: after a `<plaintext>` start tag, tag-looking text must not become
  active DOM markup.
- Pre-edit probes showed libxml parsed `<script>` and `<b>` children inside
  `<plaintext>`, while the sanitizer dropped the script node and lost visible
  reviewer source text.
- This is bounded fragment support. It is not a full HTML5 tree builder,
  template inert-content model, CSS/media policy, browser sanitization parity,
  or native XHTML-to-AST conversion.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-edit XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 357 assertions, 0 failures`.
- Pre-edit behavior probe:
  - `XmlHtmlDom::loadHtmlFragment('<plaintext>Reviewer <script>alert(1)</script> &amp; <b>note</b></plaintext><p>after</p>')`
  - Result: summary contained a real `script` child and a real `b` child under
    `plaintext`; sanitized output dropped the script text.
- Focused green verification:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 387 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.
- This slice adds 3 focused PHP PASS cases and 30 XML/HTML DOM assertions.
- Lane status moves `phpPass` `719 -> 722`; manifest mapped checks move
  `1179 -> 1182`.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/Html5Dom.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/Html5DomFragment.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both JSON files decode.
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing,
the shared deterministic serializer, the existing `Html5DomFragment`
sanitizer, `AstNode`, and `WordPressBlockWriter`. It did not invoke Pandoc,
Cabal, Haskell test binaries, citeproc, BibTeX, Biber, Word, LibreOffice,
office tools, tar, zip/unzip, lz4, external template engines, TeX/PDF engines,
browser renderers, browser layout engines, media players, MathJax, KaTeX,
roff, Typst, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, XML processing
instruction rejection, HTML fragment declaration preflight, raw text
`script`/`style` serialization, RCDATA handling for `title`/`textarea`,
obsolete raw-text fallback handling for `xmp`/`noembed`/`noframes`, HTML5
void/boolean attribute serialization, SVG/MathML foreign-content casing,
integration-point casing, URL/srcset filtering, visible form unwrapping,
table foster-parenting, charset/Unicode width handling, Markdown/HTML reader
AST coverage, ZIP/OPC package behavior, DOCX/ODT/EPUB readers, archive
compression, math/TeX, PDF handoff, BibTeX/CSL, YAML, table geometry, or
legacy DOC/CFB work. It owns only bounded plaintext-state source protection in
the XML/HTML5 DOM support layer.

## Follow-Up

Keep template inert-content handling, richer sanitizer policy, CSS/media
resource handling, full HTML5 tree-builder parity, and native XHTML-to-AST
conversion as separate bounded slices.
