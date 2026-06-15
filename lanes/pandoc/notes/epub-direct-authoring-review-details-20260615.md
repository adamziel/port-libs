# EPUB Direct Authoring Review Details

Slice: `plib-op4ii`

Scope:
- Expanded direct `EpubPackageReader` compact OPF authoring reports for already-parsed manifest and spine attributes.
- `manifestAuthoring` now carries manifest item properties, fallback, fallback-style, media-overlay, href suffix, media-type parameter, and diagnostic rollups.
- `spineAuthoring` now carries itemref properties, explicit linear values, non-linear item buckets, and diagnostic rollups.

Mapped case:
- `reports direct manifest and spine authoring review details`
- `mappedEpubDirectAuthoringReviewDetailsCases = 1`
- `epubDirectAuthoringReviewDetailsAssertions = 36`

Verification before post-rebase gate:
- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
