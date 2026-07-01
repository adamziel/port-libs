# DOCX OpenXML package XML doctype provenance

Slice: `plib-7jqg4`, DOCX/OpenXML package ingestion core blocker.

`DocxOpenXmlReader` now records metadata-only `DOCTYPE` provenance for XML-like
package parts in the loaded package inventory and summary. The handoff captures
doctype names, PUBLIC/SYSTEM references, internal-subset and entity counts,
bounded byte/hash provenance, issue-code rollups, and affected part names without
exposing raw declarations or internal-subset text.

This complements the selected XML doctype metadata by covering all XML-like
package parts during DOCX package ingestion, including malformed or unclosed
doctype declarations that should not abort bounded package review.

Direct-format parity remains active. No external Pandoc, office suite, TeX or
browser engine, Typst, Node, zip/unzip, validator, or live service is used.
