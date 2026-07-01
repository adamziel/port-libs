# XML/HTML5 DOM base URL target provenance

Slice: `plib-riz4d`, XML/HTML5 DOM core blocker.

`XmlHtmlDom` now summarizes HTML `base` element URL and target provenance for
reviewer handoff. The compact metadata records the first effective `base`
`href` and `target` in document order, duplicate ignored base entries, unsafe
base URLs, invalid target fallback state, and href-bearing `a`/`area`/`link`
candidates that would be affected by the effective base URL.

The slice remains metadata-only. It does not resolve URLs, fetch resources,
invoke browser engines, call Pandoc, or use external validators. Direct-format
parity remains active: XML/HTML5 DOM support continues to expose bounded
native PHP review packets without claiming complete browser navigation or
resource-loading behavior.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomBaseUrlTargetReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomBaseUrlTargetReviewTest.php`
  - `1 test files, 58 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php`
  - `74 test files, 9414 assertions, 0 failures`
