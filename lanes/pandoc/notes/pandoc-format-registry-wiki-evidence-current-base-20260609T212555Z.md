# Pandoc Wiki Format Registry Evidence Slice

Mapped one bounded direct-format registry accounting case for Pandoc wiki
upstream evidence packets.

## Source Truth

- Upstream inventory remains pinned to `jgm/pandoc` commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `PandocFormatRegistry::wikiTemplateResources()` derives wiki output template
  resources from audited `pandoc.cabal` data-files.
- `PandocFormatRegistry::wikiFixtureSources()` derives wiki reader/writer
  fixture source paths from audited `pandoc.cabal` extra-source file globs.
- `PandocFormatRegistry::wikiFormatEvidencePacket()` combines direction/status
  accounting, template resources, and fixture sources for the accepted wiki
  tokens.

## Native PHP Status

The evidence packet is accounting metadata only. Direct wiki reader/writer
parity remains explicitly unsupported, and no native PHP wiki reader or writer
implementation class is registered.

No Pandoc executable, wiki renderer, Cabal/Haskell runner, browser renderer,
online service, live provider test, external validator, or external converter
was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `jq empty lanes/pandoc/lane-status.json`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 799 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 58475 assertions, 0 failures

## Metric Delta

- Rebased on current `main` after `c1a965da2`.
- `lane-status.json` `phpPass`: `2908 -> 2909`
- `lane-status.json` focused checks: `811 -> 812`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3096 -> 3097`
- `mappedPandocFormatRegistryWikiEvidenceCases`: `1`
- `pandocFormatRegistryWikiEvidenceAssertions`: `43`
