# Pandoc Wiki Format Registry Slice

2026-06-25 UTC

Mapped one bounded native PHP format-registry accounting slice for Pandoc wiki
formats. This is registry metadata only; it does not add a wiki converter or
claim complete direct reader/writer parity.

## Source Truth

- `PandocFormatRegistry::wikiInputFormats()` tracks the current wiki-family
  upstream input tokens: `creole`, `dokuwiki`, `jira`, `mediawiki`,
  `tikiwiki`, `twiki`, and `vimwiki`.
- `PandocFormatRegistry::wikiOutputFormats()` tracks the current wiki-family
  upstream output tokens: `dokuwiki`, `jira`, `mediawiki`, `xwiki`, and
  `zimwiki`.
- `PandocFormatRegistry::wikiFormatRegistry()` reports direction, PHP support
  status, implementation class, extension inference, and direct-parity booleans
  for the 9 unique wiki-family tokens.
- Extension inference remains bounded to `.dokuwiki => dokuwiki` and
  `.wiki => mediawiki`.

## Native PHP Status

- `jira` input is explicitly `partial` through `PortLibs\Pandoc\JiraReader`.
- The other wiki-family input tokens remain `unsupported` as direct native PHP
  readers.
- All wiki-family output tokens remain `unsupported` as direct native PHP
  writers.
- `directReaderParityClaimed` and `directWriterParityClaimed` remain false.

No Pandoc executable, Cabal/Haskell runner, wiki renderer, browser renderer,
office suite, Node tooling, online service, live provider test, external
validator, or external converter was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 238 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/JiraReaderTest.php lanes/pandoc/tests/PandocConverterTest.php`
  - 3 test files, 368 assertions, 0 failures

The broad `php tools/run-tests.php lanes/pandoc/tests` gate was attempted after
the slice and is currently red in unrelated existing areas, including BibTeX CSL
legacy handoff expectations and YAML metadata provenance indexing. The registry,
Jira, and converter-focused gates for this slice are green.
