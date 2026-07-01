# DOCX chart sidecar package provenance - 2026-07-01

Slice: plib-70tfy, DOCX OpenXML package ingestion.

Direct-format parity accounting:
- Added native PHP metadata-only ingestion for chart-local style, color style, and chart user-shapes sidecar relationships.
- Preserves relationship ids, target suffixes, content types, root provenance, byte lengths, CRC32, SHA-256, missing/external diagnostics, and package inventory roles.
- Keeps chart sidecar XML and any related bytes out of rendered media/document output; this is package provenance only.
- No shell-outs to Pandoc, office suites, zip/unzip, browsers, Node, or external validators were used.

Focused gate:
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
