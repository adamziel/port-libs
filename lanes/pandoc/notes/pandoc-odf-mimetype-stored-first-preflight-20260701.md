# ODF/ODT mimetype stored-first preflight

Slice: `plib-wyvu2`

This slice aligns compact `OpenDocumentPackage` ODF/ODT mimetype validation with the shared ZIP stored-first-entry preflight used by rich package readers.

## Scope

- `OpenDocumentPackage::assertTextPackageMimetype()` now delegates to `ZipPackage::assertStoredFirstEntry('mimetype', OpenDocumentPackage::TEXT_MIMETYPE, 'ODT mimetype entry')`.
- Compact `mimetypeEntry` summaries now expose the shared preflight fields: `entryName`, `exists`, `firstLocalEntryName`, `isFirstLocalEntry`, `generalPurposeFlags`, `usesDataDescriptor`, `isStored`, `expectedBytes`, `contentBytes`, `contentsMatch`, `isValid`, and shared `diagnostics`.
- Compact package ingestion now rejects `mimetype` entries with central ZIP extra fields or data descriptors before manifest/package exposure.
- Package bytes remain blocked under `odf-mimetype-validation-only`; no ODF sidecar or document payload bytes are exposed by this metadata.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfMimetypeStoredFirstPreflightTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfMimetypeStoredFirstPreflightTest.php`
  - 1 file, 27 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfMimetypeStoredFirstPreflightTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/ZipPackageTest.php`
  - 4 files, 12,745 assertions, 0 failures

No external Pandoc, office suites, browser engines, TeX, Node tooling, zip/unzip tools, validators, Jupyter, or network services were invoked.
