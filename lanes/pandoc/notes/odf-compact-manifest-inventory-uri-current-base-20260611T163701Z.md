# ODF Compact Manifest Inventory URI Path Slice

Slice: `plib-09axl` / ODF/ODT OpenDocument package ingestion.

This bounded native PHP slice keeps compact `OpenDocumentPackage` ZIP inventory
records explicit about both ODF manifest URI provenance and the resolved package
part name used for ZIP lookup. Inventory entries now expose `manifestPackagePath`
beside the raw `manifestPath`, so a manifest full-path such as
`Pictures/source%20hero.png` remains reviewable while the decoded ZIP entry
`Pictures/source hero.png` is still treated as manifest-declared.

The focused regression case verifies:

- encoded manifest full-paths stay out of undeclared-entry counts;
- decoded ZIP entries retain `manifest-declared` and `media-resource` roles;
- raw `manifestPath`, resolved `manifestPackagePath`, and media type survive in
  compact package inventory review packets.

No Pandoc, office suite, `zip`/`unzip`, browser renderer, external validator,
online service, live provider test, or live-service provider test was invoked.

Verification on current main `51a89684e`:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  passed 1 test file, 299 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  passed 44 test files, 63967 assertions, 0 failures
