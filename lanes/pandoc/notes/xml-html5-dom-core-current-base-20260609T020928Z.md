# XML/HTML5 DOM Facade Handoff - 2026-06-09

Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260609T020928Z`
Base: `ae05f994f04ccc78db62e7bd6dd42669f76246b1`

## Behavior

This slice routes the legacy `XmlHtml5Dom` compatibility facade through the hardened native DOM helpers already used by the current HTML5 reader stack:

- HTML document parsing now reuses `Html5Dom::parseHtmlDocument()`.
- HTML fragment parsing now reuses `Html5Dom::parseHtmlFragment()`.
- XML document parsing now reuses `XmlHtmlDom::loadXmlDocument()`.
- HTML fragment serialization now reuses deterministic HTML5 serialization from `Html5Dom`/`XmlHtmlDom`.

The added coverage proves facade callers now get bounded HTML5 named-reference decoding (`NoBreak`, `hopf`, semicolonless `copy`), RCDATA/template source-text protection, SVG foreign-content casing, deterministic void-element/attribute serialization, and unsafe HTML/XML declaration rejection before libxml repair.

## Red-First Evidence

Before the implementation, `php tools/run-tests.php lanes/pandoc/tests/XmlHtml5DomTest.php` failed with:

- `routes legacy facade html fragments through hardened html5 parsing`: expected `A...B ...` decoded text, got `A&NoBreak;B &hopf; &copy`.
- `rejects unsafe html and xml facade inputs before libxml repair`: expected `InvalidArgumentException`, none thrown.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `4 test files, 4752 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
  - Result: `xml/html5 dom handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded native PHP DOM support (`Html5Dom` and `XmlHtmlDom`) and does not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, zip/unzip, tar/gzip, browser renderers, online sanitizers, TeX/PDF engines, or online services.

## Non-Overlap

This does not repeat the accepted standalone `Html5Dom`/`XmlHtmlDom` entity, RCDATA, foreign-content, table-foster, or raw-text clusters. It specifically closes the remaining facade gap used by existing `MarkdownReader` HTML paths and the WordPress XML/HTML5 DOM handoff example.
