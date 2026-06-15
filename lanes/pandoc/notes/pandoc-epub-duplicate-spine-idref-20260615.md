# EPUB Duplicate Spine IDRef Slice (2026-06-15)

Slice: bounded native PHP EPUB3 compact package validation on current main `df2df82b11`.

`EpubPackage` now reports duplicate OPF spine `itemref` `idref` groups without aborting compact package ingestion. The validation report preserves every reading-order occurrence with indexes, itemref IDs, selected package part, media type, and linear flags, and the same duplicate groups are exposed through the WordPress import review handoff.

Mapped evidence:

- `mappedEpubDuplicateSpineIdrefCases`: 1
- `epubDuplicateSpineIdrefAssertions`: 30
- `phpPass`: 3697 -> 3698
- `mapped`: 3722 -> 3723
- `phpFail`: 0

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` -> 1 file, 3052 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 46 files, 87426 assertions, 0 failures

No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, online service, live provider test, live-service provider test, or external validator was invoked.

This does not repeat prior EPUB package work for duplicate manifest IDs, missing spine targets, malformed spine attributes, NCX binding, page-spread/page-progression metadata, OPF authoring attributes, package inventory, or nav/page-list target diagnostics. The new surface is only duplicate compact OPF spine `idref` provenance.
