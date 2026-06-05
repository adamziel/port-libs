# markerPDF XMP Language Alternative Boundary

Date: 2026-06-05 03:59 UTC

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T035913Z`

## Behavior

`PdfMetadataExtractor` now treats XMP `rdf:Alt` `xml:lang="x-default"` values
case-insensitively. Uppercase or mixed-case `X-DEFAULT` title and description
entries are selected before localized fallback values, while XMP packet text
still remains metadata-only and outside visible WordPress paragraphs.

Rejected catalog `/Metadata` streams that are not `/Type /Metadata` and
`/Subtype /XML` continue to emit only redacted review summaries; neither the
default nor localized XMP alternatives are promoted to document metadata.

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit
  `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets searchable-PDF metadata
  through the pdftext/PDFium document-loading boundary before model-dependent
  layout or OCR stages.
- The native no-GPU lane owns this parser boundary for WordPress import:
  XMP language alternatives are document metadata, not body text, and the
  default-language XMP alternative should win independent of letter case.

## Evidence

Red-first focused gate before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpLangAltBoundaryCurrentBaseTest.php`

Result: failed `prefers uppercase XMP x-default language alternatives before
WordPress metadata import`; expected `Current Lang Alt XMP Title`, actual
`Localized Lang Alt Decoy Title`.

Post-fix focused gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpLangAltBoundaryCurrentBaseTest.php`

Result: `1 test files, 39 assertions, 0 failures`.

Focused metadata family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Test.php`

Result: `25 test files, 1867 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-lang-alt-boundary-currentbase.php`

Result: exits 0 and emits `title_from_uppercase_x_default=true`,
`description_from_uppercase_x_default=true`,
`localized_decoy_excluded=true`, `visible_text_excludes_xmp=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Status Delta

- Focused markerPDF PHP tests add `2` PASS cases and `39` assertions for the
  XMP language alternative boundary.
- WordPress scenarios add one smoke for uppercase `X-DEFAULT` XMP metadata
  selection before import.
- The upstream manifest maps one additional native PDF XMP metadata boundary
  behavior.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, stream decoder, XML/XMP parser, metadata merger, redacted XMP review
summary path, and text extractor. No Python, pdftext, pypdfium, Surya, Texify,
Torch, OCR/model, image raster, online service, or external PDF tool execution
is needed or run for this slice.

## Non-Overlap

This does not repeat accepted XMP packet bounding, XML comment/CDATA/entity
guards, UTF-16 decoding, Windows-1252 fallback, qualified `rdf:value`
selection, nested qualifier suppression, catalog metadata stream type/subtype
rejection, XMP language catalog metadata, associated-file XMP review, PDF/A
schema association, xref trailer metadata precedence, or encrypted metadata
source policies. The bounded behavior is only case-insensitive
default-language selection inside XMP `rdf:Alt` metadata arrays.

## Next Task

Continue with non-overlapping native markerPDF parser/converter boundaries:
metadata, annotations, forms, font/CMap widths, image/filter review,
xref repair, page geometry, or supplied-boundary table/equation handoffs under
the no-GPU markerPDF scope.
