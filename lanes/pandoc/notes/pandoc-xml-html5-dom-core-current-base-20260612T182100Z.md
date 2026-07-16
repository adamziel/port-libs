# XML/HTML5 DOM Template Content Review

Date: 2026-06-12 UTC
Lane: pandoc
Slice: pandoc-xml-html-template-content-review
Base accepted HEAD: a0e620b5f8

## Behavior

- `XmlHtmlDom` now records bounded inert review provenance for HTML
  `template` content without changing raw template serialization.
- Template source remains escaped in raw HTML handoff, while reviewer metadata
  now records parse status, byte length, SHA-256, top-level element names,
  normalized text hash, link hrefs, image sources, form actions, active
  descendant names, embedded descendant names, and unsafe/unparseable
  diagnostics.
- Unsafe template content declarations are reported as diagnostics instead of
  being loaded as live document structure.

## Non-Overlap

This does not repeat accepted template fallback unwrapping, noscript handling,
iframe `srcdoc` review provenance, object param review, form constraints,
details disclosure metadata, or raw text escaping. The change is limited to
metadata extracted from already-inert template source using the native PHP DOM
loader.

## Mapping Delta

- `phpPass`: `3257` -> `3258`.
- `mappedXmlHtmlDomTemplateContentReviewCases`: `1`.
- `xmlHtmlDomTemplateContentReviewAssertions`: `25`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3244` -> `3245`.
- Aggregate XML/HTML DOM core cases: `8` -> `9`.
- Aggregate XML/HTML DOM core assertions: `241` -> `266`.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 1637 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php`
  - `5 test files, 4572 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 72808 assertions, 0 failures`
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc`

No Pandoc, Cabal/Haskell runner, browser renderer, external XML/HTML validator,
online service, live provider test, or live-service provider test was executed.
