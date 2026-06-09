# pandoc-epub3-package-core-current-base-20260609T014907Z

## Scope

This slice adds bounded native EPUB3 package support for OPF collection link review summaries. EPUB collections already preserved raw link records, but WordPress import review packets also need aggregate `rel` and `properties` evidence so series, set, preview, record, missing, remote, and encrypted collection references are visible without executing Pandoc, EPUBCheck, browser tooling, or remote fetches.

## Implementation

- Added `EpubReader` collection `linkReport` metadata with stable first-seen `relTokens`, `relCounts`, `linksByRel`, `propertyTokens`, and `propertyCounts`.
- Added local/external/missing/encrypted collection-link counts, record-link counts, review-required counts, and flattened diagnostics.
- Collection links without `rel` now produce a bounded `missing-collection-link-rel` diagnostic for review classification.
- Exposed the report through each collection, the import report, and document attributes while preserving the existing raw `links` array.
- Updated the WordPress EPUB3 handoff smoke to assert and print collection-link relation/external-link summary fields.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Passed: `1 test files, 3323 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Passed: `epub3 package handoff self-test ok`.
- PHP lint:
  - `php -l lanes/pandoc/src/EpubReader.php`
  - `php -l lanes/pandoc/tests/EpubReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
- `git diff --check -- lanes/pandoc`
  - Passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2077 -> 2078`.
- Manifest mapped denominator: `2489 -> 2490`.
- EPUB3 package support row: `mappedEpub3PackageCoreCases 6 -> 7`.
- EPUB3 package support assertions: `112 -> 149`.
- Focused EPUB reader test delta from latest recorded EPUB3 baseline: `3286 -> 3323` assertions, `+1` PHP PASS case.

## Non-Overlap

This does not repeat accepted EPUB OCF container/rootfile validation, metadata sidecars, OPF metadata, unique identifiers, collection membership metadata, collection role vocabulary, manifest/spine parsing, nav/NCX/page-list parsing, navigation target reconciliation, guide type summaries, fallback chains, bindings, media overlays, XHTML resource/script/link/form/switch/trigger/semantic scans, CSS resource/font/page-rule reports, remote-resource declarations, cover assets, encryption exposure policy, or CFI/media-fragment parsing. The covered gap is specifically package-level aggregation of OPF collection link relation/property and target-policy evidence.

## Dependency Closure

No new support component is needed. This reuses native `ZipPackage` fixtures, `EpubReader` OPF collection parsing, package-reference resolution, OCF encryption metadata, focused PHP tests, and the existing WordPress EPUB3 package handoff example. Pandoc, Cabal/Haskell runners, EPUBCheck, Word, LibreOffice, zip/unzip, ZipArchive, browser renderers, JavaScript runtimes, online services, live provider tests, and live-service provider tests remain out of scope.

## Next

Useful non-overlapping EPUB3 follow-ups include nav-to-AST rendering, CSS cascade/export policy, richer encrypted-resource review decisions, and EPUBCheck-style static validation diagnostics.
