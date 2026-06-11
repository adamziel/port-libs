# XML/HTML5 DOM time datetime current-base slice

- Bead: `plib-rw01w`
- Scope: XML/HTML5 DOM primitives.
- Refreshed base: `b4580474711b92c2eff20bf5124025bdfc2343ba`.
- Adds bounded reviewer summaries for HTML5 `<time>` elements, preserving display text, `datetime` attribute provenance, text fallback provenance, normalized date/local/global datetime values, invalid values, and missing-value state.
- Reuses the existing native PHP date/datetime classifier for `ins`/`del` revision metadata, preserving deterministic HTML serialization.
- No Pandoc, browser renderers, external validators, online services, live provider tests, or live-service provider tests invoked.

Verification:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> `1 test files, 517 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 63838 assertions, 0 failures`

Accounting:
- `phpPass`: `3066 -> 3067`
