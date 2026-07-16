# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260604T230517Z`

Base accepted HEAD: `fbe3fc8556507be78718a50156c3db0ac6373d94`

## Behavior Added

- Added `XmlHtmlDom` as a shared native PHP support helper for document
  readers and handoff paths:
  - safe XML loading with `LIBXML_NONET`, external resolution disabled,
    entity substitution disabled, NUL-byte rejection, and DTD/doctype
    rejection before package XML is exposed;
  - DOM-backed HTML fragment recovery for bounded imported source snippets;
  - deterministic HTML5 fragment serialization with void-element handling,
    boolean-attribute handling, entity/attribute escaping, comments, and
    stable attribute ordering;
  - fragment summaries and normalized text helpers for reader tests.
- Routed OPC content-types, OPC relationships, EPUB3 package XML, and DOCX
  XML loading through the shared helper so package readers use the same safe
  XML boundary.
- Added a WordPress example smoke that recovers a source HTML fragment and
  hands it to `WordPressBlockWriter` as a raw HTML block.

## Source Truth

- This slice follows Pandoc's document-reader need for safe XML/HTML fragment
  handling without shelling out to Pandoc or browser renderers. It preserves
  the existing bounded reader behavior while centralizing libxml safety
  settings and adding deterministic HTML5 fragment serialization needed by
  EPUB/XHTML, DOCX/OPC, HTML-reader, and WordPress review handoff work.
- This is intentionally not a full HTML5 tree-builder, sanitizer, CSS cascade,
  browser layout engine, or XHTML-to-AST converter.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php && php -l lanes/pandoc/src/OpcContentTypes.php && php -l lanes/pandoc/src/OpcRelationships.php && php -l lanes/pandoc/src/EpubReader.php && php -l lanes/pandoc/src/DocxReader.php && php -l lanes/pandoc/tests/XmlHtmlDomTest.php && php -l lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php`
  - Result: all changed PHP files reported no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: `1 test files, 35 assertions, 0 failures`
  - PASS lines: 4
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php lanes/pandoc/tests/EpubReaderTest.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `3 test files, 569 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `14 test files, 3,733 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
  - Result: `xml/html5 dom handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses PHP DOM/libxml already used by
the lane, but centralizes the safety boundary and deterministic fragment
serializer. It does not invoke Pandoc, Cabal, Haskell test binaries, Word,
LibreOffice, `zip`, `unzip`, external template engines, TeX/PDF engines,
browser renderers, online sanitizers, roff, Typst, or online services.

## Non-Overlap

This patch does not repeat accepted Markdown/HTML reader coverage, EPUB3
package handoff, DOCX body/media/comment/bookmark/field-code support, ZIP/OPC
relationship graph behavior, archive compression streams, PDF engine
fake-runner diagnostics, BibTeX/CSL, YAML, doctemplate, table geometry,
math/TeX, charset/Unicode, or legacy DOC/CFB FIB preflight slices. It owns
only the shared XML/HTML5 DOM support boundary and its WordPress raw HTML
handoff smoke.

## Follow-Up

Keep full HTML5 tree-builder parity, sanitizer policy, CSS cascade/media
resource handling, EPUB XHTML-to-AST conversion, and browser-layout-like
behavior as separate bounded slices.
