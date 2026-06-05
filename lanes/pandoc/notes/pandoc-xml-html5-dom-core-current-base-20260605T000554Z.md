# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T000554Z`

Base accepted HEAD: `d93839bf1059e9e384bbc118a734c74a08e4f5ec`

## Behavior Added

- Added bounded SVG/MathML foreign-content case adjustment to the shared native
  XML/HTML5 DOM helpers.
- `XmlHtmlDom` now serializes and summarizes common foreign-content names such
  as `viewBox`, `preserveAspectRatio`, `linearGradient`, `textPath`, and
  MathML `definitionURL` without lowercasing them through libxml's HTML parser.
- `Html5Dom` now reuses the shared deterministic HTML serializer for fragment
  children and lets helper lookups match adjusted SVG element names such as
  `linearGradient` and `textPath`.
- `Html5DomFragment` now normalizes foreign-content names before raw HTML AST
  handoff while preserving the existing DTD/entity rejection, active-tag
  filtering, unsafe attribute filtering, and unsafe URL filtering boundaries.
- The WordPress XML/HTML5 DOM smoke now includes inline SVG and MathML review
  content so raw HTML block handoff proves the same casing survives.

## Source Truth

- Source truth is the accepted lane manifest's Pandoc HTML-reader and raw HTML
  support-library contract plus prior XML/HTML5 DOM notes. A local Pandoc
  checkout was not present under `.upstream-cache`, so this slice did not run
  upstream Haskell tests.
- This is bounded support-library behavior for document readers and WordPress
  review queues. It is not full HTML5 tree-builder parity, browser layout,
  CSS cascade, SVG rendering, MathML rendering, or XHTML-to-AST conversion.

## Evidence

- Before this slice, the committed DOM bucket recorded 4 mapped XML/HTML DOM
  cases and 35 assertions.
- This slice adds 3 focused PHP PASS cases and 25 assertions in the DOM bucket,
  raising the manifest bucket to 7 mapped XML/HTML DOM cases and 60 assertions.
- Current direct XML/HTML DOM focused coverage passes 18 cases with 141
  assertions across `XmlHtmlDomTest.php`, `Html5DomTest.php`, and
  `Html5DomFragmentTest.php`.
- Current full Pandoc lane verification passes 18 test files with 4,352
  assertions, 0 failures, and 438 PASS lines.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/src/Html5Dom.php`
- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php -l lanes/pandoc/tests/Html5DomTest.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: 1 test file, 52 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
  - Result: 1 test file, 37 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 1 test file, 52 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
  - Result: `xml/html5 dom handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 18 test files, 4,352 assertions, 0 failures; 438 PASS lines.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses PHP DOM/libxml and lane-local
native serializers only. It did not invoke Pandoc, Cabal, Haskell test
binaries, office tools, archive tools, external template engines, TeX/PDF
engines, browser renderers, browser layout engines, SVG/MathML renderers,
online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, raw text
`script`/`style` serialization, HTML5 boolean attributes, DOM sanitization
policy, Markdown/HTML reader AST coverage, syntax highlighting, EPUB3 package
handoff, DOCX/ODT readers, ZIP/OPC package behavior, table geometry, archive
compression, PDF handoff, math/TeX conversion, charset/Unicode helpers,
BibTeX/CSL, YAML, doctemplate, or legacy DOC/CFB work. It owns only the
bounded SVG/MathML foreign-content casing boundary in the XML/HTML5 DOM core.

## Follow-Up

Keep full HTML5 tree-builder parity, sanitizer policy expansion, CSS cascade
and media handling, broader SVG/MathML namespace integration, rendering, and
XHTML-to-AST conversion as separate bounded slices.
