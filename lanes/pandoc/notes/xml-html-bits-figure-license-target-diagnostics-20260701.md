# XML/HTML BITS Figure License Target Diagnostics

Slice: `plib-orqm0`
Date: 2026-07-01

This follow-up adds focused `XmlHtmlDom` coverage for BITS figure permission
license target diagnostics. The fixture keeps figure/media handling
metadata-only while asserting:

- `license`, `license-ref`, and nested `ext-link` targets keep figure/media
  provenance, permission ids, target kind, scheme, and source positions
- missing, duplicate, unsafe, and external target classification remains stable
- copyright year and holder metadata is preserved for figure and media
  permissions
- figure/media linkage, caption metadata, `directReaderParity=false`, and
  `payloadBytesExposed=false` are preserved

No Pandoc binary, XML validators, browser renderers, Node tooling, online
services, live providers, or external validators were invoked.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1` file, `6426` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `82` files, `12735` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/*.php`
  - `534` files, `142364` assertions, `8912` failures
  - remains red outside this focused `XmlHtmlDom` slice
