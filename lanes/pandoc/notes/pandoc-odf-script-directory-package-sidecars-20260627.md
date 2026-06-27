# Pandoc ODF Script Directory Package Sidecars

Bead: `plib-awnog`
Date: 2026-06-27 UTC
Area: Pandoc ODF/ODT OpenDocument package ingestion

## Behavior

`OpenDocumentPackage::summarize()` now preserves manifest-declared ODF script
directory entries such as `Basic/`, `Scripts/`, and `Dialogs/` in
`packageScripts` instead of only reporting script files. Directory rows are
metadata-only, use `script-directory` kind, retain ZIP stored-byte provenance
when an explicit directory entry exists, and remain under
`directory-entry-no-bytes` so macro/dialog payload bytes are still blocked.

This keeps compact package review aligned with the richer `OdfReader`
`scriptMetadata.directories` surface and the existing package inventory role
counts.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfDialogPackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfDialogPackageSidecarTest.php`
  - 1 file, 105 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 1895 assertions, 0 failures

No Pandoc binary, office suite, external ZIP tooling, browser renderer,
network service, or external validator was invoked.
