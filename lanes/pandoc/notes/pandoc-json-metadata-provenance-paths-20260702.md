# Pandoc JSON metadata provenance paths

Mapped one bounded JSON/native metadata compatibility case for metadata keys
that require JSON pointer escaping in constructor provenance paths.

## Source Truth

- Upstream inventory remains pinned to `jgm/pandoc` commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `PandocJsonReader` and `NativeReader` record metadata constructor provenance
  under escaped JSON pointer paths.
- `PandocJsonWriter` and `NativeWriter` use the same paths to preserve unchanged
  native `Meta*` payloads while regenerating edited sibling metadata.

## Native PHP Status

The new focused case covers metadata keys containing both `/` and `~`, including
`owner~team`, `owner/team~lead`, and a newly generated `added/key~name` field.
Unchanged metadata sidecars are preserved, edited list items regenerate canonical
constructors, and stale edited container sidecars are dropped.

No Pandoc executable, Cabal/Haskell runner, TeX/PDF engine, browser renderer,
office suite, online service, live provider test, external validator, or external
converter was used.

## Verification

- `php -l lanes/pandoc/tests/PandocJsonMetadataProvenancePathTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonMetadataProvenancePathTest.php`
  - 1 test file, 40 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonMetadataProvenancePathTest.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 2 test files, 6,318 assertions, 0 failures

## Metric Delta

- `lane-status.json` `phpPass`: `474 -> 475`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2318 -> 2319`
- `mappedPandocJsonMetadataProvenancePathCases`: `1`
- `pandocJsonMetadataProvenancePathAssertions`: `40`
