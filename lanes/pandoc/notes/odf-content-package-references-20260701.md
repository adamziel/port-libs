# ODF content package references

Hook: `plib-osst3`

Slice: native ODF/ODT rich package-ingestion metadata for content references.

Change:
- `OdfReader` now reports metadata-only `contentPackageReferences` for ODT content nodes that reference package parts.
- The report covers `draw:image`, MathML `draw:object`, and `draw:object-ole` targets, including manifest URI provenance, declared/undeclared and missing/encrypted counters, byte-exposure policy buckets, and per-part byte metadata when the existing media/object policy already allows it.
- The same report is available from the document manifest attributes, top-level `readPackage()` result, `importReport.manifest.contentPackageReferences`, and `importReport.content.packageReferences`.

Validation:
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` (1 file, 5,393 assertions, 0 failures)

No Pandoc, office suite, zip/unzip tool, browser engine, validator, online service, or external package reader was invoked.
