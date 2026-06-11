# XML/HTML5 DOM Global Interaction Metadata Current-Base Slice

Bead: plib-f14wi
Date: 2026-06-11 UTC
Base: origin/main 96af5e2beeb1d2960f253ea956e89a3d8f437062

Implemented a bounded native PHP XML/HTML5 DOM package-ingestion blocker slice in `XmlHtmlDom`:

- HTML fragment summaries now expose global interaction metadata for `lang`, `dir`, `translate`, `spellcheck`, `contenteditable`, `draggable`, `hidden`, `inert`, `popover`, `accesskey`, `class`, and `title`.
- Enumerated states are normalized while raw values remain visible through existing attributes and selected raw fields.
- No Pandoc, browser renderer, online sanitizer, external validator, online service, or live provider test was invoked.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> 1 test file, 542 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 64029 assertions, 0 failures

Accounting:

- `phpPass` 3073 -> 3074
- mapped denominator 3195 -> 3196
- `mappedXmlHtmlDomGlobalInteractionMetadataCases`: 1
- `xmlHtmlDomGlobalInteractionMetadataAssertions`: 33
