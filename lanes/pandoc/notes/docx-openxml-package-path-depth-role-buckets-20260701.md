# DOCX OpenXML Package Path-Depth Role Buckets

Scope: `plib-61zmj` DOCX/OpenXML package ingestion core-blocker slice.

- Added package-part path-depth cross-tabs for inventory roles and metadata-only byte exposure policies.
- Carried the same maps into deterministic DOCX package identity, including per-entry path depth and byte policy metadata.
- Covered both array-backed `readPackage()` and native `ZipPackage::fromParts()` ingestion without Pandoc, office suites, external zip tools, or validators.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackagePathDepthRoleBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePathDepthRoleBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXml*.php` passed post-rebase.
