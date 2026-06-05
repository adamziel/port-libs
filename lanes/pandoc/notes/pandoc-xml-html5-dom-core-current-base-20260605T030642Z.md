# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T030642Z`

Base accepted HEAD: `ddcf206af6d96f39c283c9cb57b47988ee857ab3`

## Behavior Added

- Added bounded HTML integration-point handling to the shared XML/HTML5 DOM
  support helpers.
- `XmlHtmlDom` and `Html5Dom` now stop treating descendants of SVG
  `foreignObject` as SVG foreign content, while nested SVG/MathML descendants
  still re-enter foreign-content casing.
- `XmlHtmlDom`, `Html5Dom`, and `Html5DomFragment` now treat descendants of
  MathML `annotation-xml` with `encoding="text/html"` or
  `encoding="application/xhtml+xml"` as HTML descendants instead of applying
  MathML/SVG foreign-content case rewrites.
- The WordPress HTML5 DOM handoff smoke now proves these descendants serialize
  into raw HTML blocks with HTML casing, while nested SVG/MathML names and
  attributes remain cased for reviewer fidelity.

## Source Truth

- Source truth is the Pandoc HTML reader support-library need recorded in
  `UPSTREAM_TEST_MANIFEST.json` plus HTML5 foreign-content integration-point
  behavior for SVG `foreignObject` and MathML HTML annotations.
- This slice is deliberately bounded to parser/serializer casing and raw HTML
  handoff. It does not implement a full HTML5 tree builder, browser layout,
  CSS cascade, media selection, sanitizer policy expansion, or XHTML-to-AST
  conversion.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Pre-slice focused baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 189 assertions, 0 failures`.
- Red-first focused run after adding expectations failed with 3 focused
  failures because SVG `foreignObject` descendants and MathML
  `annotation-xml` HTML descendants were still cased as foreign content.
- This slice adds 3 focused PHP PASS cases and 35 XML/HTML DOM assertions.
- Focused XML/HTML DOM family now passes 28 cases with 224 assertions across
  `XmlHtmlDomTest.php`, `Html5DomTest.php`, and `Html5DomFragmentTest.php`.
- Lane status is updated to 567 PHP pass / 0 fail and 1,045 mapped native
  checks.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: `1 test files, 68 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
  - Result: `1 test files, 53 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 103 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 224 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6203 assertions, 0 failures`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses PHP DOM/libxml and the
lane-local XML/HTML5 DOM sanitizer/serializer boundary. It did not invoke
Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX, Biber, Word,
LibreOffice, office tools, tar, zip/unzip, lz4, external template engines,
TeX/PDF engines, browser renderers, browser layout engines, media players,
MathJax, KaTeX, roff, Typst, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, raw text
`script`/`style` serialization, HTML5 boolean attributes, XML/HTML
declaration preflight, SVG/MathML foreign-content casing outside integration
points, `srcset` URL filtering, `srcset` descriptor normalization, Markdown/
HTML reader AST coverage, syntax highlighting, EPUB3 package handoff, DOCX/ODT
readers, ZIP/OPC package behavior, table geometry, archive compression, PDF
handoff, math/TeX conversion, charset/Unicode helpers, BibTeX/CSL, YAML,
doctemplate, or legacy DOC/CFB work.

## Follow-Up

Keep full HTML5 tree-builder parity, broader sanitizer policy, CSS/media
resource handling, browser-grade media candidate selection, richer MathML/SVG
integration points beyond this bounded casing behavior, and XHTML-to-AST
conversion as separate bounded slices.
