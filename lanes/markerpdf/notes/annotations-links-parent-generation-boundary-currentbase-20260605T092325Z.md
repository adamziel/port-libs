# Link Annotation Parent Generation Boundary

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T092324Z`
Session: `port-dev-markerpdf-annotations-links-20260605T092325Z`
Base accepted HEAD: `6691bc265d37822e8e05ed918650e91dc7aa53b1`

## Source Truth

- Upstream markerPDF remains pinned in the manifest at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Searchable-PDF text/link import depends on low-level PDF page geometry before OCR/model stages. Under the no-GPU scope, the native PHP boundary must resolve page-tree references exactly and never execute PDF actions, JavaScript, Python models, PDFium, OCR, or external PDF tools.
- PDF indirect references include object number and generation. A page dictionary `/Parent 2 1 R` must inherit `/MediaBox`, `/CropBox`, `/Rotate`, and `/UserUnit` from object generation `2 1`, not from a stale later-scanned `2 0 obj` with the same object number.

## Implementation

- `PdfLinkAnnotationExtractor::parentInheritedPageGeometry()` now walks `/Parent` references as exact `object generation` references.
- Inherited link geometry now resolves parent page-tree bodies through `objectBodyForReference()` before clipping Link/Widget `/Rect` and `/QuadPoints` candidates to the effective page bbox.
- Added a current-base fixture where `2 1 obj` is the referenced page parent and `2 0 obj` appears later with a tiny stale CropBox. The current link remains promoted, and the stale-parent-only link is excluded.
- Added a WordPress smoke that renders the current link Markdown while preserving review-only payload exclusion.

## Focused Evidence

Syntax:

```text
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationParentGenerationBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfLinkAnnotationParentGenerationBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-parent-generation-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-link-parent-generation-boundary-currentbase.php
```

Focused assigned gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationParentGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses exact page Parent generations before link annotation CropBox boundaries
1 test files, 26 assertions, 0 failures
```

Adjacent link/annotation gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationParentGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 673 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-parent-generation-boundary-currentbase.php
```

The smoke emits `page_link_count=1`, `promoted_annotation_objects=[7]`, `page_bbox=[50,50,250,250]`, `stale_parent_generation_excluded=true`, `stale_span_linked=false`, `visible_text_excludes_link_review_payloads=true`, and all PDF action, JavaScript, Python/model, and external-tool execution flags false.

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP behavior tests move `1669 -> 1670 pass / 0 fail`.
- WordPress scenarios move `1534 -> 1535`.
- Mapped upstream semantics add `pdfLinkAnnotationParentGenerationBoundaryCurrentBase`; the static upstream denominator remains unchanged.

## Non-Overlap

This does not repeat accepted URI extraction, local/remote GoTo actions, exact-generation page `/Annots` annotation resolution, Widget action inheritance, hidden/no-view filtering, rotated/UserUnit link geometry, Link `/QuadPoints`, Link presentation metadata, text-markup annotation geometry, page CropBox clipping, name-tree Limits, catalog URI Base, previous-URI action review, or StructTree link context.

The bounded behavior is specifically exact-generation inherited page-tree geometry for Link annotation clipping and WordPress span promotion.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation-indexed object body map, token-aware dictionary/reference parser, inherited page geometry resolver, link annotation extractor, supplied marker/pdftext span model, and Markdown span merge path. Full upstream parity for pdftext/PDFium rendering, live OCR, Surya/Texify/Torch model execution, raster table/image recognition, and exact GPU/model benchmarks remains intentionally out of scope under the current markerPDF no-GPU directive.
