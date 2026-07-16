# Pandoc wiki reader/writer taxonomy matrix slice

Bounded native PHP registry coverage now adds one explicit wiki-family
reader/writer unsupported taxonomy matrix.
`PandocFormatRegistry::wikiReaderWriterUnsupportedTaxonomyMatrix()` compares wiki
input tokens, output tokens, direction buckets, file-extension aliases,
reader/writer unsupported reason payloads, empty native implementation records,
per-format capability flags, and direct parity booleans while keeping wiki
conversion unsupported.

The matrix is registry evidence only. It does not implement wiki conversion and
does not invoke Pandoc, wiki converters, browser renderers, Node tooling, online
services, live providers, or external validators.

Counters:

- `mappedPandocFormatRegistryWikiReaderWriterTaxonomyCases`: `1`
- `pandocFormatRegistryWikiReaderWriterTaxonomyAssertions`: `212`
- `phpPass`: `3510 -> 3511`
- mapped upstream cases: `3428 -> 3429`

Verification:

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
