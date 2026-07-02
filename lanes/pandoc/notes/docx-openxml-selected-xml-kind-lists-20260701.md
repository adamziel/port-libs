# DOCX OpenXML Selected XML Kind Lists

Slice: `plib-1420t`

`DocxOpenXmlReader` now exposes deterministic kind lists for selected XML package
parts alongside the existing aggregate counts:

- all selected XML definition kinds;
- existing and missing selected kinds;
- relationship-selected kinds;
- byte-digested kinds;
- missing required or referenced kinds.

The fields are also copied into `packageProvenance['summary']` so package review
callers can classify the selected XML surface without scanning each item. This
is package-provenance metadata only; it does not shell out to Pandoc, Office,
external validators, or live services, and it does not expose XML payload bytes.
