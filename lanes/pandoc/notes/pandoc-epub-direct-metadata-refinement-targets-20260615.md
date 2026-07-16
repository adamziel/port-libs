# pandoc-epub-direct-metadata-refinement-targets-20260615

Slice: `pandoc-epub-direct-metadata-refinement-targets`

`EpubPackageReader` now exposes a direct-reader OPF metadata refinement target
report for package review. The report inventories local refinement targets from
package IDs, DC metadata IDs, OPF `meta` and `link` IDs, manifest item IDs,
spine itemref IDs, collection IDs, collection link IDs, and collection metadata
IDs. OPF metadata `meta` and `link` `refines` entries are classified as local,
external, or package-relative, with resolved target kinds, unresolved local-id
diagnostics, and invalid empty-fragment diagnostics.

This stays in the native PHP EPUB3 package ingestion boundary and does not
invoke Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers, external
validators, online services, live provider tests, or live-service provider
tests.

Counters:

- `phpPass`: `15334 -> 15335`
- mapped upstream cases: `14992 -> 14993`
- root mapped inventory: `15005 -> 15006`
- `mappedEpubDirectMetadataRefinementTargetCases`: `1`
- `epubDirectMetadataRefinementTargetAssertions`: `31`

Verification:

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  - 1 file, 1591 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 181 files, 165703 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

Non-overlap:

This does not repeat the compact `EpubPackage` metadata refinement target
report. It covers the directory-level `EpubPackageReader` handoff so direct
package imports expose the same reviewable target inventory before richer ZIP
package expansion or `EpubReader` conversion behavior.
