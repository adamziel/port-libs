# Pandoc ZIP Package Comment Source Provenance - 2026-06-30

Slice: `plib-jzrj8`

Shared ZIP package preflight now exposes metadata-only source provenance for the EOCD package comment.

`ZipPackage` adds:

- `packageCommentSourcePreflight()` for constructed packages.
- `rawPackageCommentSourcePreflight()` for raw bytes before package construction succeeds.

The existing `commentPreflight()` and `commentPolicyPreflight()` records now carry:

- `packageCommentSourceAvailable`
- `packageCommentOffset`
- `packageCommentBytes`
- `packageCommentEnd`
- `packageCommentSha256`
- `packageCommentPreviewHex`
- `packageCommentPreviewByteCount`
- `packageCommentByteExposurePolicy`
- `canExposePackageCommentBytes`

The raw helper derives offsets from the end-of-central-directory record directly, so comment source provenance remains available even when local header issues prevent constructing a package object. It does not expose package comment payload bytes to readers; only bounded offsets, counts, previews, and digest metadata are surfaced for review.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> 1 file, 4903 assertions, 0 failures
