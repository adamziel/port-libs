# Pandoc wiki output token taxonomy

Mapped one bounded Pandoc format-registry taxonomy case for wiki-family output
writer tokens.

## Scope

- `PandocFormatRegistry::wikiOutputTokenTaxonomyPacket()` records the five
  accepted wiki output tokens: `dokuwiki`, `jira`, `mediawiki`, `xwiki`, and
  `zimwiki`.
- Each token exposes a stable `wiki-writer-not-ported` unsupported writer
  reason payload, upstream writer provenance, writer fixture paths, template
  evidence where present, empty native implementation records,
  `externalToolFree=true`, and `directWriterParitySupported=false`.
- Existing wiki input extension inference is unchanged.

This is explicit no-writer taxonomy evidence. No native PHP wiki writer was
implemented or registered.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

Focused registry run: 1 file, 1,506 assertions, 0 failures.
Full Pandoc suite: 46 files, 75,592 assertions, 0 failures.

External tools not run: Pandoc, wiki renderers, browser renderers, Node
tooling, online services, live provider tests, or external validators.

## Counters

- `phpPass`: `3352 -> 3353`
- `mapped`: `3312 -> 3313`
- `mappedPandocFormatRegistryWikiOutputTaxonomyCases`: `1`
- `pandocFormatRegistryWikiOutputTaxonomyAssertions`: `81`
