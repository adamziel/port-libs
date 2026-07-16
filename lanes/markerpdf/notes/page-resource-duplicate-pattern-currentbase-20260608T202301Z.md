# Page Resource Duplicate Pattern Current Base, 2026-06-08

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260608T202301Z`

Base: `e804d88dd32d5db061bbd8258db113c523e8f8c3`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable-PDF extraction in the PDF parser/text layer before model or OCR fallback: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF page `/Resources` are inherited down the page tree, and content-stream resource names are resolved from the current effective resource dictionary. Ambiguous duplicate names in a resource subdictionary must not let stale or later duplicate resources win during native review.

## Change

- `PdfTextExtractor::patternResourceReferences()` now applies the duplicate top-level resource-name guard already used by Font, XObject, Properties, and inline ColorSpace resource consumers.
- `PdfTextExtractor::graphicsStatePatternResourceReview()` now reports duplicate Pattern names as unresolved instead of marking the name as resolved from `/Resources.Pattern`.
- The focused fixture uses an inherited page-tree `/Resources /Pattern` dictionary with duplicate `/Dup Tile` entries and one valid sibling `/Valid Tile`; only the valid sibling can contribute tiling Pattern image review metadata before WordPress import.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicatePatternCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects duplicate inherited Pattern resource names before WordPress image review walks stale tiles
Values are not identical
Expected: 1
Actual: 2

1 test files, 1 assertions, 1 failures
```

The failing count showed that the native image-boundary review accepted the last duplicate `/Dup Tile` Pattern resource in addition to the valid sibling Pattern.

## Verification

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicatePatternCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects duplicate inherited Pattern resource names before WordPress image review walks stale tiles

1 test files, 25 assertions, 0 failures
```

Adjacent Pattern/image-resource regression set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicatePatternCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectPatternWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBBoxOperandTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectIndirectBBoxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternColorSpaceBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1477 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-duplicate-pattern-currentbase.php
```

Result: exits `0`; emits two Gutenberg paragraph blocks and metadata with `pattern_names=["Valid Tile"]`, `duplicate_pattern_images_excluded=true`, `valid_pattern_image_retained=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, inherited page resource resolver, duplicate resource-name detector, tiling Pattern image-boundary review, page-boundary metadata extractor, and WordPress smoke path. GPU/model OCR, Surya, Texify, Torch, pypdfium/PIL raster rendering, live service providers, and exact upstream model benchmark parity remain intentionally out of scope for the current no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted page resource null/omitted inheritance, parent lineage repair, comment-delimited references, object wrappers, malformed resource fail-closed behavior, duplicate Font/XObject/Properties/ColorSpace filtering, inline-image ColorSpace inheritance, Type3 CharProc Pattern fallback exclusion, xref repair, annotations/forms/security preflight, page geometry, or model/OCR work. The new boundary is specifically duplicate inherited `/Pattern` resource names before tiling Pattern image review and Pattern color-state metadata.

Root harness status: not run - isolated micro-slice.
