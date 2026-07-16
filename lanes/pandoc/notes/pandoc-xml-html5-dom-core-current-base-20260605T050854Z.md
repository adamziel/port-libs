# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T050854Z`

Base accepted HEAD: `a507c91dfef9ccb6ae9e0ed8b5624323759e56e8`

## Behavior Added

- `XmlHtmlDom::protectHtmlRcdataElements()` protects bounded HTML `title` and
  `textarea` bodies before libxml parses HTML fragments or documents.
- Tag-looking text inside those RCDATA bodies now remains text, so
  `<script>`, `<b>`, and similar source tokens serialize as escaped reviewer
  content instead of becoming child DOM elements.
- `Html5Dom` traversal sees no parsed child elements inside protected
  `title`/`textarea` bodies.
- `Html5DomFragment` sanitizer now unwraps visible textarea review text as
  escaped content instead of dropping blocked child tags or emitting harmless
  parsed markup from source text.
- The WordPress XML/HTML5 DOM smoke now proves a textarea review field reaches
  the raw HTML block with escaped tag-looking content.

## Source Truth

- Source truth is the bounded Pandoc HTML-reader support-library need recorded
  in this lane: HTML fragments must be recovered into deterministic DOM review
  packets without turning source text into active markup before WordPress raw
  HTML handoff.
- HTML `title` and `textarea` use RCDATA semantics: character references are
  decoded, but tag-looking text is text until the matching closing element.
- This is not full HTML5 tree-builder parity, full plaintext-state support, a
  browser sanitizer, CSS/media resource handling, or XHTML-to-AST conversion.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-slice XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 261 assertions, 0 failures`.
- Red-first focused run after adding RCDATA expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 268 assertions, 3 failures` because textarea/title
    bodies parsed tag-looking text as child markup and sanitizer dropped the
    parsed `script` child.
- This slice adds 3 focused PHP PASS cases and 27 XML/HTML DOM assertions.
- Lane status moves `phpPass` `641 -> 644`; manifest mapped checks move
  `1116 -> 1119`.

## Verification

- `php -l lanes/pandoc/src/Html5Dom.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/Html5DomFragment.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php`
  - Result: no syntax errors.
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - Result: both decoded successfully.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 288 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7369 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
  - Result: `xml/html5 dom handoff self-test ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing
with a bounded pre-parser RCDATA protection step. It did not invoke Pandoc,
Cabal, Haskell test binaries, citeproc, BibTeX, Biber, Word, LibreOffice,
office tools, tar, zip/unzip, lz4, external template engines, TeX/PDF engines,
browser renderers, browser layout engines, media players, MathJax, KaTeX,
roff, Typst, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, XML processing
instruction rejection, HTML fragment declaration preflight, raw text
`script`/`style` serialization, HTML5 void/boolean attribute serialization,
SVG/MathML foreign-content casing, integration-point casing, `srcset` URL
filtering, `srcset` descriptor normalization, media fetch URL filtering,
extended URL attribute filtering, `ping` side-effect filtering, visible form
wrapper unwrapping, charset/Unicode width handling, Markdown/HTML reader AST
coverage, ZIP/OPC package behavior, DOCX/ODT/EPUB readers, archive
compression, math/TeX, PDF handoff, BibTeX/CSL, YAML, table geometry, or
legacy DOC/CFB work. It owns only bounded RCDATA handling for `title` and
`textarea` in the XML/HTML5 DOM support layer.

## Follow-Up

Keep full HTML5 tree-builder parity, plaintext-state support, richer sanitizer
policy, CSS cascade/media resource handling, EPUB/XHTML package resource
resolution, and native XHTML-to-AST conversion as separate bounded slices.
