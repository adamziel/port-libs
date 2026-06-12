# XML/HTML5 DOM canvas fallback metadata

Slice: `plib-dpyhx`

Base: current main `2a569e4541`

This slice adds native PHP reviewer metadata for HTML `canvas` elements without invoking Pandoc, browser renderers, online sanitizers, external validators, online services, live provider tests, or live-service provider tests.

The handoff now records:

- `canvasWidthRaw` and `canvasHeightRaw`
- defaulted `canvasWidth` and `canvasHeight`
- fallback text and serialized fallback HTML
- child fallback presence
- the `canvas-fallback-metadata-only` review policy

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` (1 test file, 1092 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 test files, 68329 assertions, 0 failures)
