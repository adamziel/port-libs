# XML/HTML5 DOM writing assistance attributes

Bead: `plib-d0cjd`
Date: 2026-06-14 UTC
Area: Pandoc XML/HTML5 DOM primitives
Base: `1680663835`

## Behavior

`XmlHtmlDom::summarizeHtmlFragment()` now preserves HTML writing-assistance
attribute provenance in bounded fragment review packets:

- `autocorrect` exposes raw value, normalized `on`/`off` state, and validity.
- `writingsuggestions` exposes raw value, normalized boolean state, and
  validity.
- `virtualkeyboardpolicy` exposes raw value, normalized `auto`/`manual` state,
  and validity.
- Empty present attributes retain their raw empty string and normalize to the
  enabled/default state. Invalid values remain metadata-only as `null` plus a
  false validity flag.

Deterministic HTML serialization and WordPress raw block propagation remain
unchanged. This is additive reviewer metadata only; it does not claim full
browser editing, keyboard, spellcheck, or DOM tree-builder parity.

No Pandoc binary, office suite, zip/unzip, ZipArchive, browser renderer,
external validator, online service, live provider test, or live-service
provider test was invoked.

## Accounting

- `phpPass`: `3456 -> 3457`
- `phpFail`: `0`
- `mappedXmlHtmlDomWritingAssistanceCases`: `+1`
- `xmlHtmlDomWritingAssistanceAssertions`: `+35`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check -- lanes/pandoc/src/XmlHtmlDom.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/lane-status.json`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 3654 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 80852 assertions, 0 failures`
