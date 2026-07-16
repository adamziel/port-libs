# markerPDF PageLabels direct kid dictionary boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T211015Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page through `pdftext`/PDFium before OCR/layout/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel coverage models `/PageLabels` as a catalog number tree whose `/Nums` arrays pair page-index keys with page-label dictionaries. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- This no-GPU slice keeps a malformed-but-bounded number-tree tolerance: direct child dictionaries inside `/Kids` are accepted alongside indirect kid references, while nested private decoy dictionaries remain excluded.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now builds child PageLabels nodes from direct dictionary tokens in `/Kids` arrays as well as exact-generation indirect kid references.
- `MarkerAppPreview::pageLabelSections()` mirrors the same direct-dictionary child handling so preview/page-inventory metadata stays aligned with native text extraction.
- Added a focused fixture where the first `/Kids` entry is a direct dictionary for pages 0-1, the second is an indirect node for pages 2-3, and a nested private dictionary contains a stale label decoy.

## Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsDirectKidDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps direct PageLabels kid dictionaries before WordPress page metadata
Expected: ["Front ii","Body 7","App-Z","Back-9"]
Actual: ["1","2","App-Z","Back-9"]
1 test files, 1 assertions, 1 failures
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsDirectKidDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps direct PageLabels kid dictionaries before WordPress page metadata
1 test files, 12 assertions, 0 failures
```

Focused PageLabels family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsCatalogDuplicateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsDuplicateNumsDictionaryKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsDirectKidDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsMalformedSameLowerKidBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsOutOfRangeOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsScalarCommentBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 324 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-direct-kid-dictionary-currentbase.php
```

The smoke emits `page_labels=["Front ii","Body 7","App-Z","Back-9"]`, `preview_page_labels=["Front ii","Body 7","App-Z","Back-9"]`, `direct_kid_labels_applied=true`, `stale_nested_private_labels_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Delta

- Focused PHP PASS cases: `2216 -> 2217`
- New focused assertions: `12`
- Focused PageLabels family: `7 test files / 324 assertions / 0 failures`
- WordPress scenarios: `1909 -> 1910`
- `mappedPdfPageLabelsDirectKidDictionaryCurrentBaseBehaviors`: `0 -> 1`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, inherited/local/indirect/malformed `/Limits`, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Nums` dictionary keys, descending or out-of-range `/Nums` keys, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only direct dictionary objects in the `/Kids` array of a catalog `/PageLabels` number tree.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Live OCR, Surya/Texify/Torch models, PDFium/pypdfium raster execution, PIL, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.
