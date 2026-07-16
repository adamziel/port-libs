# markerPDF Link Annotation State Boundary Current Base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T213631Z`

Base accepted HEAD: `b79799c53675732856746c61eec3aaf4b4e97d74`

## Source Truth

- PDF Link annotations are ordinary annotation dictionaries. Common annotation state entries such as `/Subj`, `/NM`, `/M`, and `/CA` are review metadata for importers, while `/A`, `/Dest`, and `/AA` remain non-executing action metadata.
- markerPDF/pdftext-style extraction should promote only visible searchable page text into WordPress paragraphs. Annotation state strings, hidden annotation dictionaries, URI/action payloads, Python/OCR/model outputs, and external PDF tool execution remain outside visible paragraph text.

## Implementation

- `PdfLinkAnnotationExtractor` now carries visible Link annotation `/Subj`, `/NM`, `/M`, and `/CA` into promoted link rows as `subject`, `name`, `modified_at`, and clamped `opacity`.
- `applyLinksToPages()` mirrors those fields onto overlapping WordPress/pdftext spans as `link_annotation_subject`, `link_annotation_name`, `link_annotation_modified_at`, and `link_annotation_opacity`.
- Numeric scalar parsing now requires a PDF token boundary before accepting a direct number, so indirect numeric operands like `/CA 24 0 R` resolve to the referenced object instead of matching the `24` prefix and clamping it to `1.0`.
- Hidden Link annotations still do not promote state metadata or URIs into WordPress spans.

## Red-First Evidence

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationStateBoundaryCurrentBaseTest.php
```

failed with:

```text
FAIL carries visible Link annotation name subject modified date and opacity review state onto WordPress spans
Expected: 'Migration link'
Actual: NULL
1 test files, 6 assertions, 1 failures
```

After the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationStateBoundaryCurrentBaseTest.php
```

passed:

```text
PASS carries visible Link annotation name subject modified date and opacity review state onto WordPress spans
1 test files, 40 assertions, 0 failures
```

The WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-annotation-link-state-boundary-currentbase.php
```

emits `promoted_link_subjects=["Migration link","Indirect migration link"]`, `promoted_link_names=["named-link-1","indirect-link-2"]`, `promoted_link_opacities=[0.65,0.4]`, `hidden_link_state_excluded=true`, `annotation_state_text_excluded_from_visible_text=true`, and all Python/model/external-tool/action execution flags false.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary parser, Link annotation extractor, PDF string/indirect-object resolution, Markdown span merger, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat accepted Link annotation URI base, URI control-byte safety, presentation border/color/highlight metadata, previous URI `/PA`, primary-action selection, action arrays/scalars, QuadPoints, stale Rect rescue, crop/page generation, StructTree, object-stream, or xref/free-entry annotation slices. The bounded behavior here is common visible Link annotation state fields `/Subj`, `/NM`, `/M`, and `/CA` plus indirect numeric `/CA` resolution before WordPress span promotion.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations/forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
