# Pandoc ODF package directory base names

Work item: `plib-vzxx0`

## Summary

ODF/ODT compact and rich package provenance now exposes exact package directory
base-name summary rows alongside the existing case-fold and stem inventories.
The handoff includes `duplicatePackageDirectoryBaseNameCount`,
`duplicatePackageDirectoryBaseNames`, and detailed
`packageDirectoryBaseNames` rows with per-directory counts, media/type/role
rollups, byte totals, entry names, and largest-entry provenance.

The fields are carried through compact package inventory, compact package
identity, rich package provenance, rich package identity, and document manifest
package provenance so downstream consumers can review raw directory-base-name
collisions without relying on case-folded buckets.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackageCaseFoldDirectoryBaseNameInventoryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageCaseFoldDirectoryBaseNameInventoryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageCaseFoldDirectoryBaseNameInventoryTest.php lanes/pandoc/tests/OdfPackageDirectoryBaseNameStemInventoryTest.php lanes/pandoc/tests/OdfPackageBasenameInventoryTest.php`
- `git diff --check`

No Pandoc binary, office suite, TeX/browser engine, unzip/zip command, Node
tooling, or external validator was invoked.
