# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260604T233636Z`

Base accepted HEAD: `a5e387c4d1094f3921390a1c90f9966afea84bd2`

## Behavior Added

- Extended `XmlHtmlDom` HTML5 serialization for bounded document-reader
  handoff:
  - `script` and `style` text content now serializes as HTML raw text instead
    of escaping comparison operators and ampersands into visible source
    corruption;
  - HTML5 boolean attribute handling now covers common document/export
    attributes such as `defer`, `async`, `hidden`, `controls`, `nomodule`, and
    related flags;
  - added public `serializeHtmlNode()` and `serializeHtmlChildren()` helpers so
    readers that construct detached DOM nodes or fragments can reuse the same
    deterministic HTML5 serializer without manufacturing a wrapped fragment
    document.
- Updated the WordPress XML/HTML5 DOM smoke with a disabled style review note
  and an `application/json` script metadata packet to prove raw text survives
  handoff into a raw HTML block.

## Source Truth

- This follows Pandoc's support-library need for deterministic XML/HTML
  fragment handling across readers and writers while staying bounded to native
  PHP DOM/libxml behavior. It does not implement a full HTML5 tree builder,
  sanitizer policy, CSS cascade, browser layout behavior, or XHTML-to-AST
  converter.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php && php -l lanes/pandoc/tests/XmlHtmlDomTest.php && php -l lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php`
  - Result: all changed PHP files reported no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: `1 test files, 45 assertions, 0 failures`
  - PASS lines: 6
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `16 test files, 3,902 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
  - Result: `xml/html5 dom handoff self-test ok`
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses PHP DOM/libxml and the existing
lane-local WordPress block writer. It did not invoke Pandoc, Cabal, Haskell
test binaries, Skylighting, citeproc, BibTeX, Biber, Word, LibreOffice, office
tools, `tar`, `zip`, `unzip`, `lz4`, external template engines, TeX/PDF
engines, browser renderers, roff, Typst, MathJax, KaTeX, online sanitizers, or
online services.

## Non-Overlap

This patch does not repeat accepted Markdown/HTML reader AST coverage,
syntax-highlighting tokenization, EPUB3 package handoff, DOCX/ODT XML readers,
ZIP/OPC package behavior, table geometry, archive compression, PDF engine
handoff, math/TeX, charset/Unicode, BibTeX/CSL, YAML, doctemplate, or legacy
DOC/CFB work. It only extends the shared XML/HTML5 DOM serializer boundary and
the matching WordPress raw HTML handoff smoke.

## Follow-Up

Keep full HTML5 tree-builder parity, sanitizer policy, CSS cascade/media
resource handling, foreign-content case adjustment, and XHTML-to-AST conversion
as separate bounded slices.
