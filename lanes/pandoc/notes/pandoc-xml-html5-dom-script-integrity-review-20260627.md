# Pandoc XML/HTML5 DOM Script Integrity Review

Slice: `pandoc-xml-html5-dom-script-integrity-review-20260627`

## Implementation

- `XmlHtmlDom` now summarizes `<script integrity>` as bounded
  subresource-integrity review metadata.
- The review packet records raw/tokens, hash algorithms, unsupported
  algorithms, duplicate tokens, malformed tokens, and whether the integrity
  attribute applies to an external executable script.
- Raw HTML and WordPress handoff preserve the original source attribute; no
  external script source is fetched and no hash validation request is made.

## Evidence

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomScriptIntegrityReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomScriptIntegrityReviewTest.php`
  passed with `1 test files, 41 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php lanes/pandoc/tests/Html5Dom*.php`
  passed with `37 test files, 10449 assertions, 0 failures`.

## Mapping Delta

- `lane-status.json phpPass`: `458` -> `459`.
- Added focused XML/HTML DOM script integrity review coverage with 41
  assertions.

## Non-Overlap

This slice does not repeat hyperlink/script `attributionsrc`, meta CSP,
link fetch policy, script loading mode, nonce provenance, import-map JSON
review, URL filtering, or browser execution behavior. It only owns metadata
review for the existing `<script integrity>` source attribute.
