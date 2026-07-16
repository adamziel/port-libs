# ZIP Selected Handoff Package-Part Identity

Hook: `plib-qfu48`, Pandoc shared ZIP/OPC package core blocker slice.

## Scope

`ZipPackage::entryHandoffPreflight()` now carries shared package-part identity
metadata on selected-entry handoff rows and in the deterministic
`selectedHandoffManifest`.

Selected DOCX, EPUB, ODF/ODT, and generic OPC package readers can review the
same normalized ZIP identity fields that the full package manifest uses:
path segments, segment-position reviews, directory depth, package-part base
name, case-fold base name, base-name stem, extension key, and extensionless
state. Missing requests stay marked as missing with null package-part identity.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

## Limits

This does not add ZIP64 support, encrypted payload support, extraction,
external validators, office-suite validation, `zip`/`unzip` calls, or broader
archive tooling. It only extends native PHP selected-entry metadata before
payload handoff.
