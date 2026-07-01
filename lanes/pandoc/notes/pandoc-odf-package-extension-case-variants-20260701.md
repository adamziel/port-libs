# ODF Package Extension Case Variants

Slice: `plib-8sfgi` ODF/ODT OpenDocument package ingestion.

Added metadata-only package identity accounting for normalized package part
extensions that appear with case variants in the ZIP package, such as `PNG`
and `PnG` both mapping to `png`.

Surfaced through:

- compact `OpenDocumentPackage::summarize()` package inventory and package identity
- rich `OdfReader::readPackage()` package provenance
- rich document manifest package identity mirror

The handoff now records the case-variant extension count, normalized extension
names, uppercase part count, and per-extension summaries with raw extension
counts, raw extension part names, role/media buckets, and largest-part metadata.

Direct-format parity: this extends the existing raw-extension and normalized
extension ODF accounting without exposing package bytes or changing ODT document
content parsing.
