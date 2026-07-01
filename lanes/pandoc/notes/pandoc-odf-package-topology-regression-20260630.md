# Pandoc ODF package topology regression

## Scope

- Verified the current ODF/ODT package ingestion primitive for package areas and package path depths.
- Fixed the focused package regression to match actual ZIP compression behavior for the default `Pictures/hero.png` fixture.
- Fixed the compact package identity assertion to read `packageIdentity` from the summary root, matching `OpenDocumentPackage::summarize()`.
- Kept the work under `lanes/pandoc` and did not invoke Pandoc, office suites, ZIP CLIs, browser engines, Node tooling, or external validators.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
