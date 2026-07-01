# DOCX OpenXML package area expansion ratios

## Scope

- Added package-area expansion-ratio maps to DOCX OpenXML package provenance summaries.
- Carried the ratios through package identity and main-document package identity payloads.
- Kept byte exposure metadata-only: ratios are derived from existing uncompressed and compressed length metadata.

## Verification

- `DocxOpenXmlPackageAreaByteLengthIdentityTest` covers direct in-memory package reads and ZIP-backed package reads.
