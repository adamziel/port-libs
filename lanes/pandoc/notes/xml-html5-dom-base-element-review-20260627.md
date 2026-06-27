# XML/HTML5 DOM Base Element Review

Slice: `xml-html5-dom-base-element-review-20260627`
Issue: `plib-oeoon`

## Scope

This slice adds bounded native PHP review metadata for HTML `<base>` elements in
`XmlHtmlDom::summarizeHtmlFragment()`.

The summary now records:

- raw and trimmed base `href` provenance;
- URL kind, scheme, unsafe state, trusted absolute HTTP(S) classification, and
  caller-base dependency;
- first-active versus duplicate active `href` declarations;
- raw and normalized base `target` provenance;
- reserved target detection, `_blank` normalization for control-separated or
  markup-bearing targets, invalid target diagnostics, and duplicate target
  declarations.

The implementation is review-only. It does not resolve document URLs, fetch
resources, execute browser base-target behavior, or change `Html5DomFragment`
sanitizer output.

## Evidence

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomBaseElementReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomBaseElementReviewTest.php`
  - Result: `1 test files, 57 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomBaseElementReviewTest.php`
  - Result: `2 test files, 6,281 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php`
  - Result: `41 test files, 7,784 assertions, 0 failures`

## Non-Overlap

This does not repeat accepted `Html5DomFragment` base URL resolution, base
target inert span handoff, duplicate base sanitizer diagnostics, inactive base
isolation, passive link metadata, meta refresh handling, or browser-level base
navigation semantics. The new surface is only parsed-DOM summary metadata for
reviewers and downstream package handoff code that consumes `XmlHtmlDom`
directly.
