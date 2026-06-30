# ODF package area/depth provenance - 2026-06-30

## Slice

`plib-qtaxj` adds metadata-only ODF/ODT package area and directory-depth review summaries to the native PHP package ingestion path.

## Coverage

- `OpenDocumentPackage::summarize()` now annotates each package inventory part with normalized path segments, top-level package segment, containing directory, directory depth, and base name.
- Compact package inventory now exposes top-level segment counts/summaries, directory-depth counts/summaries, deepest package parts, and per-role area/depth summaries without changing byte exposure policy.
- `OdfReader` rich `packageProvenance` mirrors the same package area/depth fields and carries the deterministic counts through `packageIdentity`.
- The focused regression covers core root files, media, script sidecars, configuration sidecars, embedded object package entries, signature sidecars, and undeclared private parts.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackageAreaDepthProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageAreaDepthProvenanceTest.php` (45 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php` (1,896 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfManifestSidecarOrderFlagsTest.php lanes/pandoc/tests/OdfReaderZipPlatformAttributesProvenanceTest.php lanes/pandoc/tests/OdfReaderDocumentPartRootAttributesTest.php lanes/pandoc/tests/OdfReaderSignatureTransformProvenanceTest.php lanes/pandoc/tests/OdfReaderStylePackageProvenanceTest.php` (233 assertions, 0 failures)

No Pandoc, office suite, unzip/zip CLI, browser, Node tooling, or external validator was invoked.
