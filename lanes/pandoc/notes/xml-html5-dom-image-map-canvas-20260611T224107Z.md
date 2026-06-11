# XML/HTML5 DOM Image-Map And Canvas Slice

## Scope

- Added native PHP XML/HTML5 DOM reviewer summaries for image-map and canvas handoff.
- `img` summaries now expose `usemap` and `ismap` state.
- `map` summaries now expose map name, area count, and normalized area hyperlink/shape metadata.
- `canvas` summaries now expose raw/default dimensions and fallback text.

No Pandoc, browser renderer, external validator, online service, or live provider tooling was invoked.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 test file, 832 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66710 assertions, 0 failures

## Metrics

- Added one `XmlHtmlDomTest` case with 26 assertions.
- `phpPass`: 3133 -> 3134.
- Added `mappedXmlHtmlDomImageMapCanvasCases=1`.
- Added `xmlHtmlDomImageMapCanvasAssertions=26`.
