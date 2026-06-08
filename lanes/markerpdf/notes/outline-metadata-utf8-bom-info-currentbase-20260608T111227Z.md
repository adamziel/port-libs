# Outline Metadata UTF-8 BOM Info Boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260608T111227Z`

Base accepted HEAD: `e197436967449f47cc9be6a918a5b4cf8f4f2dcf`

## Source Truth

Pinned upstream markerPDF uses pdftext/PDFium-style searchable-PDF metadata and outline surfaces before OCR/model execution. Native markerPDF therefore needs PDF text-string decoding for review metadata without promoting outline or trailer Info strings into visible WordPress paragraphs.

This slice maps PDF 2.0 UTF-8 BOM text strings in the lightweight outline metadata boundary. Full document metadata and outline extraction already decoded UTF-8 BOM strings; `PdfTextExtractor::extractOutlineMetadata()` still decoded trailer `/Info` strings as PDFDocEncoding, producing mojibake such as `ï»¿UTF-8 BOM Info Title`.

## Implementation

`PdfTextExtractor::decodePdfTextStringBytes()` now recognizes UTF-8 BOM text strings (`EF BB BF`) after the existing UTF-16BE/UTF-16LE BOM handling and returns the UTF-8 payload only when it validates as UTF-8. Otherwise it stays fail-closed with an empty decoded string.

The focused fixture keeps:

- an outline `/Title` encoded as a UTF-8 BOM hex string;
- trailer `/Info` `/Title`, `/Author`, and `/Keywords` encoded as UTF-8 BOM hex strings;
- one searchable page content stream proving review metadata stays out of visible WordPress text.

## Red First

Before the decoder patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataUtf8BomInfoBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL decodes UTF-8 BOM outline metadata strings across TOC and trailer Info review
Expected: 'UTF-8 BOM Info Title'
Actual: 'ï»¿UTF-8 BOM Info Title'
PASS keeps UTF-8 BOM outline and Info metadata out of visible WordPress text

1 test files, 8 assertions, 1 failures
```

## Verification

After the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataUtf8BomInfoBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS decodes UTF-8 BOM outline metadata strings across TOC and trailer Info review
PASS keeps UTF-8 BOM outline and Info metadata out of visible WordPress text

1 test files, 17 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-utf8-bom-info-currentbase.php
```

Expected smoke markers: `lightweight_info_title="UTF-8 BOM Info Title"`, `lightweight_info_author="UTF-8 BOM Metadata Team"`, `bom_mojibake_excluded=true`, `visible_text_excludes_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Adjacent outline/text-string regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataUtf8BomInfoBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineTitleEncodingBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataMalformedUtf16TitleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataTrailerRootLightweightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataTrailerRootBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
...
6 test files, 151 assertions, 0 failures
```

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineMetadataUtf8BomInfoBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-metadata-utf8-bom-info-currentbase.php
```

All reported `No syntax errors detected`.

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

No output.

## Non-Overlap

This does not repeat encrypted permission preflight, trailer `/Root` ownership, xref-stream root ownership, duplicate catalog `/Outlines`, outline `/Last`, zero-count child traversal, malformed UTF-16 title rejection, PDFDocEncoding title decoding, outline metadata stream review, action-chain safety review, PageLabels, annotations, forms, image/filter metadata, xref repair, table/OCR supplied-boundary handoffs, or live OCR/model/PDFium execution. The bounded behavior is only UTF-8 BOM PDF text-string decoding for lightweight outline metadata trailer `/Info` fields, with outline title alignment checks.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, text-string decoder, outline extractor, document metadata extractor, lightweight `extractOutlineMetadata()` path, and WordPress smoke output. OCR, Surya/Texify/Torch, pypdfium/PDFium execution, raster rendering, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.
