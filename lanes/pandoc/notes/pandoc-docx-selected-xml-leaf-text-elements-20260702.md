# DOCX selected XML leaf text element provenance

Slice for `plib-3uo90`.

`DocxOpenXmlReader` now carries metadata-only leaf text element provenance for selected DOCX OpenXML parts through `selectedXmlParts` and `packageProvenance.summary`.

The selected-part review rows include leaf element paths, namespaces, local/qualified names, prefixes, text/CDATA/whitespace/line-break counters, byte lengths, CRC32, and SHA-256 digests. They do not expose raw XML text, CDATA payloads, selected XML bytes, or selected part attribute values.

Focused coverage:

- `summarizes docx selected openxml leaf text elements for package review`

This extends the selected OpenXML package-ingestion surface without overlapping package-wide XML leaf text inventory, selected XML text-node/CDATA provenance, entity-reference, doctype, processing-instruction, root namespace, or relationship-target slices.
