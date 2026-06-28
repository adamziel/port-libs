# XML/HTML5 DOM Source Size List Review - 20260627

Slice: `plib-o70b4`

## Scope

This slice adds native PHP review metadata for responsive source-size lists:

- `img sizes`
- `source sizes`
- `link imagesizes` for image preload handoff

`XmlHtmlDom` now parses source-size list items into media-condition and source-size records, tracks `auto` and default source sizes, accepts bounded CSS lengths and simple `calc()`/`min()`/`max()`/`clamp()` values, and reports empty entries, percent sizes, unsafe values, and invalid source-size values as reviewer diagnostics.

The implementation does not invoke browser layout, resource loading, Pandoc, or external validators.

## Status Movement

- `phpPass`: `466 -> 467`
- Added 1 focused XML/HTML DOM behavior test with 50 assertions.

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomSourceSizeListReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomSourceSizeListReviewTest.php`
  - Result: 1 file, 50 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomSourceSizeListReviewTest.php lanes/pandoc/tests/XmlHtmlDomSrcsetResourceReviewTest.php lanes/pandoc/tests/XmlHtmlDomLinkFetchPolicyReviewTest.php`
  - Result: 3 files, 165 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomSourceSizeListReviewTest.php lanes/pandoc/tests/XmlHtmlDomSrcsetResourceReviewTest.php lanes/pandoc/tests/XmlHtmlDomLinkFetchPolicyReviewTest.php`
  - Result: 4 files, 6,389 assertions, 0 failures.
