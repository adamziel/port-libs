# XML/HTML5 DOM Canvas Fallback Slice

Bead: plib-p9wzk
Base: 70c9c9ef1f

This slice adds native PHP review metadata for HTML `canvas` elements in `XmlHtmlDom`.
Canvas summaries now expose:

- `embeddedResource: canvas` and `canvas: canvas`
- raw `width`/`height` attributes
- bounded/defaulted review dimensions using HTML defaults of 300 by 150
- normalized fallback text
- existing child summaries for fallback markup such as images

The focused test covers explicit dimensions, missing dimensions, invalid raw dimensions,
fallback text, nested fallback image provenance, deterministic raw HTML serialization,
and WordPress raw block propagation.

Verification performed before submission:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed 1 file, 1004 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files, 67777 assertions, 0 failures

No Pandoc, browser renderers, online sanitizers, external validators, online services,
live provider tests, or live-service provider tests were invoked.
