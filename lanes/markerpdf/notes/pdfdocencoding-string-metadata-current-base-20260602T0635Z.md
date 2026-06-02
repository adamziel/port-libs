# markerPDF PDFDocEncoding String Metadata Slice

Date: 2026-06-02 06:35 UTC

Micro-slice: `markerpdf-pdfdocencoding-string-metadata-current-base-20260602T0635Z`

## Behavior

`PdfMetadataExtractor` now decodes non-BOM PDF text strings through PDFDocEncoding before cleaning trailer `/Info`, catalog, and OutputIntent review metadata. UTF-16BE and UTF-16LE BOM strings remain on the existing Unicode path.

This fixes high-bit PDFDocEncoding metadata bytes such as bullet, dagger, smart quotes, minus, per-mille, ligatures, `Lslash`/`lslash`, Euro, and Latin-1 letters in trailer `/Info` values before WordPress document metadata review.

## Source Truth

- Upstream markerPDF source truth remains the native boundary around document metadata extraction before WordPress import; live `pdftext`/`pypdfium`/model execution is still dependency-gated in this lane.
- PDF text-string source truth: Adobe PDF Reference text strings are encoded as PDFDocEncoding or Unicode with a BOM.
- Parser dependency source truth: Apache PDFBox `PDFDocEncoding` documents that the encoding is used only in PDF text strings and initializes from ISO-8859-1 with ISO 32000-1 PDFDocEncoding deviations.

## Evidence

Red-first focused gate before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: failed `decodes PDFDocEncoding Info strings for WordPress metadata review`; expected `WordPress• PDF ﬁﬂ Import €`, actual `NULL`.

Post-fix focused gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: `1 test files, 131 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-pdfdocencoding-metadata-import.php`

Result: emitted decoded PDFDocEncoding title/authors/keywords, `metadata_not_visible_text=true`, and `PDFDocEncoding Metadata Body` without executing Python/models or external PDF tools.

## Dependency Closure

No new support component is needed. This reuses the existing native PDF object parser, literal/hex string readers, metadata merge path, and PHP `mb_chr` UTF-8 conversion. Full upstream Python/model benchmark parity remains gated by the existing heavy dependency blocker.

## Next

Continue bounded markerPDF metadata/parser work on current base, favoring remaining object, AcroForm, annotation, xref, and review metadata edges that can ship with focused PHP tests and native WordPress smokes.
