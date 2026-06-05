# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T020805Z`

Base accepted HEAD: `927c0bebf9176d6d86819fbec882fef400f8d3f6`

## Behavior Added

- Added fail-closed declaration preflight for bounded HTML/XML fragment
  handoff paths.
- `Html5DomFragment::fromHtml()`, `Html5Dom::parseHtmlFragment()`, and
  `XmlHtmlDom::loadHtmlFragment()` now reject fragment-level DTD/entity
  declarations and XML-style processing instructions before libxml can repair
  or discard them.
- `Html5DomFragment::fromHtml()` and `Html5DomFragment::fromXml()` now reject
  NUL bytes before parsing. XML fragment processing instructions are rejected
  explicitly instead of being silently dropped from the normalized review tree.
- The WordPress HTML5 DOM handoff smoke now proves unsafe fragment
  declarations are blocked before a raw HTML block is produced.

## Source Truth

- Source truth is the lane-local XML/HTML5 DOM support-library contract for
  safe bounded fragment parsing, active-content filtering, deterministic HTML5
  serialization, and WordPress raw HTML handoff.
- The gap was observable on the accepted base: libxml repaired or discarded
  `<!DOCTYPE>`, `<!ENTITY>`, processing-instruction, and NUL-bearing HTML
  fragments before the sanitizer could record the source structure.
- This does not implement full browser tree-builder parity, CSS cascade,
  media selection, online sanitization, or XHTML-to-AST conversion.

## Evidence

- Pre-slice focused baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 1 test file, 75 assertions, 0 failures.
- Pre-slice XML/HTML DOM family baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 3 test files, 164 assertions, 0 failures.
- Red-first focused runs after adding expectations failed as expected:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 1 test file, 78 assertions, 1 failure because unsafe fragment
    declarations were accepted.
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
  - Result: 1 test file, 40 assertions, 1 failure because unsafe HTML fragment
    declarations were accepted.
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: 1 test file, 54 assertions, 1 failure because unsafe HTML fragment
    declarations were accepted.
- This slice adds 3 focused PHP PASS cases and 15 XML/HTML DOM assertions.
- Focused XML/HTML DOM family now passes 24 cases with 179 assertions across
  `XmlHtmlDomTest.php`, `Html5DomTest.php`, and `Html5DomFragmentTest.php`.
- Lane status is updated to 529 PHP pass / 0 fail and 1,004 mapped native
  checks.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 1 test file, 82 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php`
  - Result: 1 test file, 42 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: 1 test file, 55 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 3 test files, 179 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test`
  - Result: `wordpress-html5-dom-handoff self-test passed`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 19 test files, 5576 assertions, 0 failures.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses PHP DOM/libxml and lane-local
preflight helpers. It did not invoke Pandoc, Cabal, Haskell test binaries,
citeproc, BibTeX, Biber, Word, LibreOffice, office tools, tar, zip/unzip, lz4,
external template engines, TeX/PDF engines, browser renderers, browser layout
engines, media players, MathJax, KaTeX, roff, Typst, online sanitizers, or
online services.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection for complete XML
documents, raw text `script`/`style` serialization, HTML5 void/boolean
serialization, SVG/MathML foreign-content casing, `srcset` URL filtering,
`srcset` descriptor normalization, Markdown/HTML reader AST coverage, syntax
highlighting, EPUB3 package handoff, DOCX/ODT readers, ZIP/OPC package
behavior, table geometry, archive compression, PDF handoff, math/TeX
conversion, charset/Unicode helpers, BibTeX/CSL, YAML, doctemplate, or legacy
DOC/CFB work. It owns only bounded unsafe declaration preflight for XML/HTML
fragment parsing and WordPress raw HTML handoff.

## Follow-Up

Keep full HTML5 tree-builder parity, broader sanitizer policy, image candidate
selection, CSS/media resource handling, XHTML-to-AST conversion, and browser
grade template/media behavior as separate bounded slices.
