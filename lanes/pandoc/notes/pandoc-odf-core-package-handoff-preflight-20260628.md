# ODF Core Package Handoff Preflight

Hook: `plib-wazxm`, Pandoc ODF/ODT OpenDocument package ingestion core blocker slice.

This slice adds metadata-only selected-entry handoff preflight for ODT core package parts by reusing `ZipPackage::entryHandoffPreflight()` from both the compact `OpenDocumentPackage` summary and the rich `OdfReader` import report.

The new `corePackageHandoff` report covers `mimetype`, `META-INF/manifest.xml`, `content.xml`, `styles.xml`, `meta.xml`, and `settings.xml`, preserving shared ZIP selected-entry provenance while adding ODF manifest declaration states:

- `package-mimetype-entry` for the stored first `mimetype` ZIP entry, linked back to manifest `/`.
- `package-manifest-entry` for `META-INF/manifest.xml`.
- `declared` for manifest-declared core XML package parts.
- `undeclared` for present optional core XML parts not listed in the manifest.
- `not-declared` for absent optional core XML parts.

No package payload bytes are exposed in document media or WordPress handoff. The report remains metadata-only under `odf-core-package-handoff-metadata-only` and carries hashes/counts from the existing bounded ZIP preflight path.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderCorePackageHandoffPreflightTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderCorePackageHandoffPreflightTest.php` passed: 1 file, 45 assertions, 0 failures.
- ODF package-focused gate passed: 16 files, 3,135 assertions, 0 failures.
- ODF ZIP/document-part focused gate passed: 5 files, 271 assertions, 0 failures.
