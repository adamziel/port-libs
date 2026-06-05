# Pandoc XML HTML5 DOM Core Current Base

## Scope

- Added `Html5Dom`, a bounded native PHP support helper for HTML fragment,
  complete HTML document, XML fragment, and XML document parsing using
  `DOMDocument` with `LIBXML_NONET`, external resolution disabled, and entity
  substitution disabled.
- Added serializer helpers for wrapper-free HTML and XML fragment children,
  direct/descendant element filtering, normalized text extraction, and
  attribute maps for downstream Markdown, EPUB, DOCX/ODT, and package readers.
- XML fragments reject XML declarations, doctypes, entity declarations, and
  NUL bytes before DOM parsing so package readers do not accidentally activate
  external entity behavior.
- HTML fragments preserve bounded HTML5-ish elements such as `section`,
  `article`, `figure`, `figcaption`, `img`, and `br` for WordPress import
  review without invoking Pandoc, browser renderers, external template
  engines, or online services.

## Source Truth

- Existing lane source truth remains the Pandoc HTML reader fixtures recorded
  in `UPSTREAM_TEST_MANIFEST.json`: `test/html-reader.html`,
  `test/html-reader.native`, `test/tables/nordics.html5`, and the existing
  HTML/XML/JATS fixture inventory.
- This slice is support-library work only. It maps the parser/serializer
  contract needed by richer document readers; it does not claim full HTML5
  tree-builder parity and does not run the Haskell Pandoc test runner.

## Dependency Closure

- No new support component is needed. This reuses PHP's DOM/libxml extension
  already used by accepted MarkdownReader, OPC, DOCX, ODT, and EPUB slices.
- Follow-up activation gate: route selected existing reader XML/HTML loading
  paths through `Html5Dom` in bounded per-reader slices after focused
  regression tests are selected. Do not combine those migrations with unrelated
  DOCX/EPUB/ODT feature work.

## Verification

- `php -l lanes/pandoc/src/Html5Dom.php` passed.
- `php -l lanes/pandoc/tests/Html5DomTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
  passed.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php` passed:
  5 focused PASS cases, 29 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed.
- Root harness not run; isolated micro-slice.
