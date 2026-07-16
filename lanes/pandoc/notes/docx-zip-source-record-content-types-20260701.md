# DOCX ZIP source record content type buckets

Slice: `plib-lnmev`, DOCX/OpenXML package ingestion.

`DocxOpenXmlReader::readZipPackage()` now summarizes loaded ZIP source-record
byte spans by OPC content type base. The package provenance summary exposes
content-type counts, source-record byte totals, data-descriptor part counts,
source-span issue counts, and detailed per-content-type buckets with directory,
role, compression, and largest-part metadata.

The data remains metadata-only: ZIP local records, compressed payloads, central
directory records, comments, and extra fields are accounted for reviewer handoff
without exposing package bytes into AST attributes.

Focused coverage: `DocxOpenXmlZipSourceRecordContentTypesTest.php`.
