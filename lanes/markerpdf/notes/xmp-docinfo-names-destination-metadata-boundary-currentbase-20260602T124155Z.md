# markerPDF XMP DocInfo Names Destination Metadata Boundary

Date: 2026-06-02 12:41 UTC

Micro-slice: `xmp-docinfo-names-destination-metadata-boundary-currentbase-20260602T124155Z`

## Behavior

`PdfMetadataExtractor` now exposes catalog destination name metadata in the document metadata review payload while keeping it separate from document title/author fields and visible text extraction.

- XMP metadata remains the preferred document title/description source.
- Trailer `/Info` remains the fallback source for missing document metadata such as authors and producer.
- Catalog `/Names /Dests` name trees and legacy catalog `/Dests` dictionaries are reported under `document_destinations`.
- Destination review rows include name, source, zero-based page index, one-based page number, page object, view mode, raw view-position operands, and named view parameters for `XYZ`, `FitH`, `FitBH`, `FitV`, `FitBV`, and `FitR`.
- Indirect name-tree string keys, destination dictionaries, destination arrays, and indirect view operands are resolved with cycle guards.
- Stale/unresolved destination rows are counted but not promoted to review names or visible WordPress text.

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains initial PDF blocks plus TOC metadata through `marker/pdf/extract_text.py::get_text_blocks`, which calls `marker.cleaners.toc.get_pdf_toc(doc)`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` preserves PDFium-resolved TOC title, level, and page index as metadata rows.
- Upstream `marker/convert.py::convert_single_pdf` carries `pdf_toc` in output metadata before later layout/OCR/model steps.
- Native PHP source-truth boundary for this slice: PDF destination name trees and legacy destination dictionaries are navigation metadata for review UIs, not body text or document-title fallback strings.

## Evidence

Red-first focused gate before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: failed `keeps XMP and DocInfo metadata distinct from catalog destination names`; actual `source` was `["xmp","info"]` instead of `["xmp","info","catalog"]`.

Post-fix focused gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: `1 test files, 259 assertions, 0 failures`.

Full markerPDF lane gate:

`php tools/run-tests.php lanes/markerpdf/tests`

Result: `65 test files, 3991 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-docinfo-names-destination-metadata-boundary.php`

Result: emitted `source=["xmp","info","catalog"]`, destination names `Chapter One`, `Indirect Dest`, `Review Deck`, and `LegacyAppendix`, `destination_count=4`, `unresolved_destination_count=2`, `stale_destination_filtered=true`, `destination_names_hidden_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Changed PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-docinfo-names-destination-metadata-boundary.php`

Result: no syntax errors.

Diff hygiene:

`git diff --check -- lanes/markerpdf`

Result: passed.

## Status Delta

- Behavior tests move `500 -> 501`.
- Focused `PdfMetadataExtractorTest` assertions move `233 -> 259`.
- Full markerPDF lane assertions move `3964 -> 3991`.
- Mapped source/dependency semantics move `348 -> 349 / 78` for the isolated patch.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary/value parser, XMP parser, `/Info` decoder, page-tree traversal, and existing text extractor. Full upstream Python/model/benchmark parity remains dependency-gated on pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, OCR tooling, and live benchmark workflows.

## Non-Overlap

This does not repeat accepted catalog XMP extraction, PDFDocEncoding `/Info` decoding, XMP/Info timezone normalization, xref-stream trailer metadata precedence, encryption metadata priority, catalog language/viewer preferences, outline TOC named-destination parsing, indirect destination-view operand resolution, page-label transition/action review rows, or PieceInfo/OutputIntent metadata boundaries. The bounded behavior is specifically document metadata review for catalog `/Names /Dests` plus legacy `/Dests` alongside XMP and DocInfo fields.

## Next Task

Continue with non-overlapping markerPDF metadata, parser, annotation, AcroForm, image/color, embedded-file, structure, or security boundaries that preserve import fidelity without Python/model/external PDF tools.
