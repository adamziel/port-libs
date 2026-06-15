# EPUB Metadata Item Authoring

Slice: `plib-g8qx8`, EPUB3 package ingestion.

`EpubPackage` now preserves OPF metadata child authoring provenance for compact
package ingestion. Accepted DC metadata children and OPF `meta` children carry
raw attributes, structural attributes, custom attributes, language, direction,
scheme, `refines`/subject IDs, and `xml:base` review policy into
`metadataItemAuthoring`, `compactPackageReport`, and WordPress import packets.

This stays under `lanes/pandoc` and does not invoke Pandoc, EPUBCheck,
zip/unzip, ZipArchive, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file, 3163 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 87826 assertions, 0 failures
- PHP JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

Accounting:

- `phpPass`: `3707 -> 3708`
- `phpFail`: `0`
- upstream mapped cases: `3731 -> 3732`
- `mappedEpubMetadataItemAuthoringCases`: `1`
- `epubMetadataItemAuthoringAssertions`: `37`

Non-overlap:

This does not repeat root package authoring, metadata-root authoring, metadata
refinement target resolution, package metadata links, manifest/spine authoring,
navigation diagnostics, OCF sidecars, encryption, media overlays, or XHTML AST
handoff. The new surface is only per-metadata-child authoring provenance in the
compact ZIP/OPF ingestion path.
