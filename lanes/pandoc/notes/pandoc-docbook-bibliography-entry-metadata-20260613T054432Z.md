# DocBook Bibliography Entry Metadata

Added a bounded XmlHtmlDom review packet follow-up for DocBook bibliography entries.
The packet keeps `directReaderParity=false` and makes entry metadata explicit:

- `xml:id` versus plain `id` provenance, including conflicting id diagnostics.
- `title`/`citetitle` records, author and editor contributor records, year/date records, and publisher records.
- Entry-level missing and duplicate metadata diagnostics.
- Aggregate `linkend`/citation target summaries while preserving existing xref target, missing target, duplicate id, and unsupported child summaries.

This remains metadata-only review coverage. It does not invoke Pandoc, XML validators, browsers, Node tooling, online services, live providers, or external validators.

Verification on base `996124560f`:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed `1` file, `1965` assertions, `0` failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed `45` files, `75418` assertions, `0` failures.
- `jq empty lanes/pandoc/lane-status.json`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
