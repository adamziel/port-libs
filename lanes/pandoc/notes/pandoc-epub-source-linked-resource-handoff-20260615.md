# pandoc-epub-source-linked-resource-handoff-20260615

Slice: EPUB3 package ingestion, OPF source metadata linked-resource handoff after
rebase onto current main `6a55215f18`.

## Behavior

`EpubPackage` now carries OPF `dc:source` metadata links into source metadata
review rows:

- `sourceDetails[*].linkedResources` preserves package `link` targets that
  refine a `dc:source` id;
- `sourceSummary` rolls up local, external, missing, and rel-token counts;
- WordPress metadata review packets receive the same source details and summary;
- local linked records keep ZIP byte length, CRC32, query, fragment, and manifest
  provenance, while missing and external records remain metadata-only diagnostics.

## Evidence

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  passed with `1 test files, 3234 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed with `46 test files, 88279 assertions, 0 failures`.

## Scope

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.

This does not repeat accepted EPUB source metadata parsing, subject/rights
linked-resource handling, metadata link media-type/target policy, collection
membership, OCF sidecar, manifest/spine, navigation, or byte-provenance slices.
The new surface is limited to `dc:source` metadata link handoff.
