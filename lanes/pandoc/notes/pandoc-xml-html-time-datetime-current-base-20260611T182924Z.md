# XML/HTML5 DOM time datetime provenance

Bead: `plib-dltnu`

Base: current `origin/main` at `caf9a25cb`.

## Slice

- `XmlHtmlDom` now summarizes HTML `time` element datetime provenance.
- Each `time` summary preserves the visible text, raw machine-readable value,
  whether that value came from `datetime` or text fallback, normalized date or
  global/local datetime values, and invalid date classification.
- The parser reuses the bounded native DOM datetime preflight already used for
  insertion/deletion revision metadata.
- This remains native PHP only: no Pandoc, browser renderer, online sanitizer,
  external validator, online service, or live provider invocation.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  passed: `1 test files, 717 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: `44 test files, 65484 assertions, 0 failures`.
