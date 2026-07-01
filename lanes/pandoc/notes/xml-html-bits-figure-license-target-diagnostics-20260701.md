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

- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check`
- `git diff --cached --check`
- changed-file conflict marker scan
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1` file, `6426` assertions, `0` failures
