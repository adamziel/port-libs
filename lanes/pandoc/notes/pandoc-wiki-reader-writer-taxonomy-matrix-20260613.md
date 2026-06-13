# Pandoc wiki reader/writer taxonomy matrix slice

Bounded native PHP registry coverage now adds one explicit wiki-family reader/writer
unsupported taxonomy matrix. `PandocFormatRegistry::wikiReaderWriterUnsupportedTaxonomyMatrix()`
compares accepted wiki input tokens, output tokens, direction buckets, file-extension
aliases, reader/writer unsupported reason payloads, empty native implementation
records, and per-format capability flags while keeping direct reader and writer parity
unsupported.

The matrix is registry evidence only. It does not implement wiki conversion and does not
invoke Pandoc, wiki renderers, browser renderers, Node tooling, online services, live
providers, or external validators.

Counters:

- `mappedPandocFormatRegistryWikiReaderWriterTaxonomyCases`: `1`
- `pandocFormatRegistryWikiReaderWriterTaxonomyAssertions`: `214`
- `phpPass`: `3354 -> 3355`
- mapped upstream cases: `3314 -> 3315`

Verification:

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
