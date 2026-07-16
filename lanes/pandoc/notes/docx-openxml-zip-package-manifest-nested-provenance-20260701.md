# DOCX/OpenXML nested ZIP package manifest provenance

Work item: `plib-02z84`

## Summary

`DocxOpenXmlReader` now promotes native
`ZipPackage::packageManifestPreflight()` aggregate fields directly onto
`packageProvenance.zipPackage` as `packageManifest...` metadata. This mirrors
the existing `packageProvenance.summary.zipPackageManifest...` handoff while
keeping the full raw manifest at `packageProvenance.zipPackage.packageManifest`.

The nested ZIP package provenance now exposes package-source, archive,
central-directory, EOCD, byte-layout, compression, directory-root, path-depth,
entry-comment, review-field, order-name, and hash-count rollups without forcing
review consumers to parse the raw manifest entries.

## Non-overlap

This does not change ZIP parsing, OPC relationship resolution, document XML
conversion, media extraction, or byte exposure policy. DOCX package entry bytes
remain metadata-only under `docx-zip-entry-metadata-only`.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 11,260 assertions, 0 failures

Direct-format parity remains active for the Pandoc lane. No Pandoc binary,
office suite, TeX/browser engine, unzip/zip command, Jupyter, Node tooling, or
external validator was invoked.
