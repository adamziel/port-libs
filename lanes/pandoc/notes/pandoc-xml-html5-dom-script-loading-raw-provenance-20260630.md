# XML/HTML5 DOM script loading raw provenance

Slice: `plib-1j99m`

`XmlHtmlDom::summarizeHtmlFragment()` now preserves script-loading fetch policy
and blocking-token provenance for reviewer handoff:

- raw `crossorigin`, `fetchpriority`, and `referrerpolicy` attribute values are
  surfaced alongside the normalized validity fields;
- `blocking` attribute presence is reported separately from parsed token counts;
- ordered `blocking` tokens are preserved before duplicate/invalid-token
  rollups;
- `render` token presence is surfaced as a direct boolean for migration gates.

This stays metadata-only for direct raw HTML and WordPress handoff. It does not
fetch linked scripts, evaluate script payloads, invoke browsers, run external
validators, or shell out to Pandoc.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomScriptLoadingPolicyReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomScriptLoadingPolicyReviewTest.php`
  passed with 1 file, 72 assertions, and 0 failures.
- `php tools/run-tests.php $(rg --files lanes/pandoc/tests | rg '/XmlHtmlDom.*Test\.php$' | sort)`
  passed with 34 files, 7,468 assertions, and 0 failures.
