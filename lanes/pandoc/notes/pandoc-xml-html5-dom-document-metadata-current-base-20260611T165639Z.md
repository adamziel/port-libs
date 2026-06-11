# Pandoc XML/HTML5 DOM Document Metadata

Slice: plib-r4hlc, Pandoc XML/HTML5 DOM core blocker slice 20260611T165639Z.

Current-base implementation on `6995e705a` extends `XmlHtmlDom` fragment summaries for HTML document metadata elements: `base`, `meta`, and `link`. Reviewer handoff now preserves base href/target, meta charset/name/http-equiv/property/itemprop/content/media, link rel token provenance, MIME type, resource hints, cross-origin mode, integrity, referrer policy, sizes, image source hints, title, and disabled state while retaining deterministic HTML5 void-element serialization.

Coverage is in `XmlHtmlDomTest.php` and verifies metadata summaries plus serialized output for `base`, multiple `meta` forms, stylesheet/preload links, and icon/image-source links. The lane remains native PHP only: no Pandoc, browser renderers, online sanitizers, external validators, online services, or live provider tests.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed 1 test file, 540 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 64120 assertions, 0 failures.
