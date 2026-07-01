# XML/HTML JATS Funding Backlink Collisions

Slice: `plib-6s49m`
Date: 2026-07-01

This follow-up adds focused `XmlHtmlDom` coverage for JATS funding-statement
backlinks that collide on the same ref-list target. The fixture keeps the
review packet metadata-only while asserting:

- missing funding reference backlinks sort before duplicate found backlinks,
  followed by single found backlinks
- duplicate backlinked award/source conflicts preserve per-link provenance
- funding statement and citation payloads stay blocked, with only length and
  SHA-256 metadata exposed
- funding diagnostic issue ordering remains stable with `directReaderParity`
  false

No Pandoc binary, XML validators, browser renderers, Node tooling, online
services, live providers, or external validators were invoked.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1` file, `6402` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `82` files, `12711` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/*.php`
  - `534` files, `142340` assertions, `8912` failures
  - remains red outside this focused `XmlHtmlDom` slice
