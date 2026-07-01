# DOCX/OpenXML ZIP package manifest summary handoff

Work item: `plib-tvn3d`

## Summary

`DocxOpenXmlReader` now promotes native `ZipPackage::packageManifestPreflight()`
identity fields into `packageProvenance.summary` for DOCX/OpenXML imports.
The DOCX handoff includes compact `zipPackageManifest*` package-source,
local-header, local-record, central-directory, EOCD, compression-method, and
directory-root byte buckets while preserving the full manifest under
`packageProvenance.zipPackage.packageManifest`.

This closes a DOCX package-ingestion gap where downstream review gates had to
inspect the full ZIP manifest to account for manifest-level central-directory
review bytes, entry comments, extra fields, directory roots, and package-source
hashes. The exposed fields remain metadata-only and do not expose package entry
payload bytes.

## Non-overlap

This slice does not change ZIP parsing, OPC graph construction, document XML
conversion, media extraction, comments, ActiveX/VBA, or relationship target
policy behavior. It only wires existing shared ZIP manifest provenance into the
DOCX/OpenXML package summary.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`

Focused DOCX validation passed with 1 file, 10,818 assertions, and 0 failures.

Direct-format parity remains active for the Pandoc lane. No Pandoc binary,
office suite, TeX/browser engine, unzip/zip command, Jupyter, Node tooling, or
external validator was invoked.
