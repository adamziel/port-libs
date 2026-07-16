# pandoc-odf-compact-object-replacements-20260612T190934Z

Slice: `plib-2263k` ODF/ODT OpenDocument package ingestion core blocker.

## Summary

Added compact `OpenDocumentPackage` review handling for `ObjectReplacements/` sidecars.

- Declared, missing, encrypted, invalid-media-type, and undeclared replacement assets now surface in `packageObjectReplacements`.
- Manifest review rows expose `objectReplacementPackagePart` plus object-replacement aggregate counts/items.
- Package inventory assigns `object-replacement` roles and `objectReplacementPartCount`.
- Replacement sidecars are blocked from document media byte handoff with `object-replacement-package-bytes-blocked`, while stored byte length, compression method, CRC, declaration, and issue provenance remain reviewable.

No Pandoc, office suite, `zip`/`unzip`, `ZipArchive`, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - Result: 1 test file, 995 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after final rebase onto `origin/main` (`cd831bddc8`): 44 test files, 72959 assertions, 0 failures.

## Direct-Format Accounting

- `phpPass`: 3262 -> 3263
- `phpFail`: 0
- `mappedOdfCompactObjectReplacementPackageCases`: 1
- `odfCompactObjectReplacementPackageAssertions`: 47
