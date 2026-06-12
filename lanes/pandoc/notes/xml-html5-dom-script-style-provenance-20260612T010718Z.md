# XML/HTML5 DOM Script/Style Provenance - 2026-06-12

Slice: `plib-h6f5r`

Implemented bounded raw-text provenance for HTML `script` and `style`
elements in `XmlHtmlDom`:

- Script type normalization and kind classification for classic, module,
  importmap, JSON data-block, and non-executable data cases.
- Script source, async/defer/nomodule, blocking, crossorigin, integrity,
  referrer policy, fetch priority, external/inline state, and raw text
  length/hash metadata.
- Style type/media/disabled/blocking metadata plus raw text length/hash,
  `@import`, `url(...)`, and rule-like count review flags.
- WordPress raw HTML handoff coverage for deterministic serialized output.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 file, 1118 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 68218 assertions, 0 failures
