# Outline Title Encoding Boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T013647Z`
Base accepted HEAD: `5c1e831a4cd16b50e19b19a5942fd02353b5a990`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets outline rows through the PDFium-backed document TOC boundary and exposes bookmark titles as decoded PDF text strings. Native PHP markerPDF must therefore decode outline `/Title` text strings with the same PDF text-string encoding boundary before WordPress TOC/navigation review, without treating bookmark text as page body text.

## Behavior

`PdfOutlineExtractor` now applies the lane's PDFDocEncoding fallback after UTF-16 BOM handling for outline/navigation PDF text strings. This covers direct literal title operands, hex-string title operands, indirect title string objects, and other review-only outline text-string fields parsed by the extractor.

`PdfTextExtractor::extractOutlineMetadata()` now uses its existing PDF text-string decoder for lightweight outline and trailer-info string values, so high-bit PDFDocEncoding title bytes no longer surface as replacement characters.

`PdfMetadataExtractor` now keeps direct literal dictionary values as raw PDF literal tokens while reading top-level dictionaries, then decodes them once through the existing review text-string path. This prevents double-decoding direct PDFDocEncoding outline titles in document metadata.

The WordPress smoke demonstrates decoded bookmark titles in navigation markup while visible Gutenberg paragraphs remain page-content text only.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineTitleEncodingBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL decodes PDFDocEncoding outline titles for TOC navigation and document metadata
Expected: ["Import \ufb01nance \u2022 Summary","Review \u2013 Dash","Checklist \u2212 Sign"]
Actual: ["Import \ufffdnance \ufffd Summary","Review \ufffd Dash","Checklist \ufffd Sign"]
FAIL keeps decoded outline title metadata out of visible WordPress text
1 test files, 3 assertions, 2 failures
```

## Verification

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineTitleEncodingBoundaryCurrentBaseTest.php
=> 1 test files, 23 assertions, 0 failures
```

Adjacent outline/metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineTitleEncodingBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php
=> 6 test files, 1372 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-title-encoding-boundary-currentbase.php
=> pdfdocencoding_literal_octals_decoded=true; pdfdocencoding_hex_string_decoded=true; pdfdocencoding_indirect_title_decoded=true; visible_text_excludes_outline_titles=true; executes_python_or_models=false; executes_external_pdf_tools=false
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted outline trailer `/Root`, `/Info`, `/Prev`, `/Last`, parent, missing-parent, generation-exact, EOF, named-destination, action-context, color, structure-element, PageLabels, or metadata PDFDocEncoding Info-string slices. The bounded behavior is specifically outline `/Title` PDF text-string decoding for native TOC/navigation/document-outline metadata while keeping decoded bookmark titles out of visible page text.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, outline extractor, text extractor, metadata extractor, PDFDocEncoding table, and WordPress smoke path. Live OCR, Surya/Texify/Torch execution, pypdfium/PDFium rendering, PIL, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope for the current no-GPU markerPDF lane.
