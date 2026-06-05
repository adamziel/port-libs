# markerPDF Annotation Link Generation Boundary Current Base

Session: `port-dev-markerpdf-annotations-links-20260605T025333Z`
Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T025333Z`
Base accepted HEAD: `eee2b26c7e4190b7a49f028c56f653361d9caf62`

## Source Truth

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` / `pdftext` and keeps annotations outside visible page text unless a downstream importer explicitly promotes link/markup review metadata.
- PDF page `/Annots` arrays store indirect object references with object number and generation. A page annotation reference such as `7 1 R` must select generation 1, not a stale `7 0 obj` fallback scanned later in the file.
- WordPress import should promote only the current page annotation generation into link and text-markup review metadata while keeping stale annotation payload strings out of visible paragraphs.

## Implemented Behavior

- `PdfAnnotationExtractor` now builds a generation-indexed object map and resolves page `/Annots` indirect references by exact generation for annotation review rows.
- `PdfMarkupAnnotationExtractor` now applies the same exact-generation resolution before promoting text-markup `/QuadPoints` review rows onto supplied pdftext spans.
- `PdfLinkAnnotationExtractor` already had exact-generation annotation resolution; the new focused test keeps link behavior covered alongside generic annotation and markup promotion.

## Evidence

Red-first focused failure after adding the fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php
1 test files, 3 assertions, 1 failures
Expected current generation link/markup contents; actual stale generation-zero contents.
```

Focused passing gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php
1 test files, 23 assertions, 0 failures
```

Additional verification, WordPress smoke, lint, and diff hygiene were run for the final handoff and are reflected in the lane status/final report.

Adjacent annotation/link/markup family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkGenerationBoundaryCurrentBaseTest.php
5 test files, 536 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-annotation-link-generation-boundary-currentbase.php
```

The smoke emitted `annotation_objects=[7,9]`, `link_uris=["https://example.com/current-generated-link"]`,
`stale_generation_uri_excluded=true`, `stale_generation_markup_excluded=true`,
`annotation_payload_text_excluded_from_visible_text=true`, and all Python/model,
external-tool, and PDF-action execution flags false.

## Status Delta

- `phpPass` moves `1319 -> 1320`.
- `wordpressScenarios` moves `1273 -> 1274`.
- Focused assertion delta is `+20` over the red-first fixture run (`3` assertions before failure, `23` assertions after implementation).
- Root harness: not run - isolated micro-slice.

## Next Task

Continue with a non-overlapping native searchable-PDF parser boundary: annotation/widget generation inside xref-selected object streams, form action review, font/CMap/width behavior, image/filter metadata, xref repair, page geometry, attachments, or supplied-boundary table/equation review.

## Non-Overlap

This does not repeat escaped `/Annots` names, annotation action review, appearance/popup import, border/color/geometry metadata, link destination/action review, widget action inheritance, text-markup rotation/UserUnit mapping, or private PieceInfo annotation decoy exclusion. The bounded behavior is exact object-generation selection for page `/Annots` references before link/markup review promotion.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, annotation array parser, link extractor, markup extractor, text extractor, Markdown span merger, and WordPress smoke path. GPU/model/OCR/PDFium/PIL/external PDF execution remains intentionally out of scope under the markerPDF no-GPU directive.
