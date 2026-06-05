# markerPDF Image XObject ExtGState Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260605T060326Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T060326Z`
Base accepted HEAD: `e52793fde5f02e1281af42ed0ed1df5107454746`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates text extraction from PDF page/image rendering:

- `marker/pdf/extract_text.py` delegates searchable text to `pdftext.extraction.dictionary_output()` and PDFium text pages.
- `marker/pdf/images.py` renders pages/crops through PDFium, disables annotation drawing, converts rendered output to RGB, and returns raster image data outside the text pipeline.

Source URLs inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py`

This no-GPU native PHP slice maps the parser-side boundary for Image XObjects painted under graphics-state resources. PDFium rendering would apply `/ExtGState` transparency and blend state at the `Do` call; the native importer records that state as review-only metadata without raster execution.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now applies top-level `/Resources /ExtGState` entries to Image XObject invocation metadata:

- recognizes the content-stream `gs` operator before `Do`;
- carries the current graphics state through `q`/`Q` and into nested Form XObject image scans;
- records applied ExtGState resource names, object/generation refs, `/CA`, `/ca`, `/AIS`, `/BM`, and graphics-state `/SMask` dictionaries or references;
- keeps nested private ExtGState dictionaries from becoming callable resource names;
- keeps image payload bytes and soft-mask group payloads out of visible WordPress text and review JSON.

The WordPress smoke emits a paragraph with the searchable text plus a review comment showing alpha state, soft-mask state, blend modes, and execution flags.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records ExtGState transparency at image XObject invocation boundaries
array_column(): Argument #1 ($array) must be of type array, null given
1 test files, 461 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
```

```text
php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-extgstate-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-extgstate-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records ExtGState transparency at image XObject invocation boundaries
1 test files, 488 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-extgstate-boundary-currentbase.php
```

The smoke exits 0 and reports `alpha_extgstate_resources=["Alpha State"]`, `alpha_nonstroking_alpha=0.42`, `alpha_blend_modes=["Multiply"]`, `soft_extgstate_resources=["Soft Mask State"]`, `soft_mask_type="graphics_state_soft_mask"`, `soft_mask_subtype="Luminosity"`, `private_extgstate_rejected=true`, `payload_in_visible_text=false`, and both execution flags false.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused test growth: `PdfImageXObjectBoundaryCurrentBaseTest.php` grew to `488` assertions.
- PHP pass count: `1500 -> 1501`.
- WordPress scenario count: `1405 -> 1406`.
- Mapped upstream denominator: unchanged; this refines the already mapped Image XObject rendering/review boundary.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, clipping, optional content, exact object generation, SMask/Mask image stream metadata, alternates, metadata streams, named ColorSpace resources, top-level XObject dictionary parsing, inline-image parsing, DCT/CCITT/JPX/JBIG2 preview filters, or Form-resource image discovery. The bounded behavior is specifically graphics-state `/ExtGState` transparency/blend/soft-mask metadata at Image XObject invocation boundaries.

## Dependency Closure

No new support component is needed. This reuses native PDF object scanning, top-level resource dictionary parsing, content-token parsing, graphics-state q/Q handling, Form XObject traversal, and existing image review rows. Full rendered pixel parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya, Texify, Torch, Streamlit/FastAPI model workers, Poppler, Ghostscript, and other external PDF tools were not run.
