# markerPDF Markup Annotation Free-Object Boundary

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260608T060543Z`

Base accepted HEAD: `cfec77028507d7bdc4213fc9124ee422079c0937`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Searchable-PDF imports treat annotation actions and text-markup payloads as review metadata only; they must not execute PDF actions or leak annotation text into visible WordPress content.
- Current xref free rows mark objects unavailable in the active PDF revision. Link and general annotation extraction already suppress stale freed annotation objects; text-markup review must share that boundary before applying Highlight/Underline/Squiggly/StrikeOut rows to supplied pdftext spans.

## Implementation

- `PdfMarkupAnnotationExtractor` now initializes `PdfXrefFreeObjectMap::freeObjectNumbers()` during extraction, removes freed object bodies from its object maps, and rejects freed indirect references in `objectBodyForReference()`.
- `PdfMarkupAnnotationFreedObjectBoundaryCurrentBaseTest.php` covers a current incremental update where page `/Annots` still references a live Link annotation and a stale Highlight annotation, but the latest xref section marks only the Highlight object free.
- `wordpress-pdf-markup-annotation-freed-object-currentbase.php` exercises the WordPress path: the current Link remains promoted to Markdown, the stale Highlight produces no markup review row, and annotation payload text stays out of visible extraction.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMarkupAnnotationFreedObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL suppresses xref-free text markup annotations while preserving current link promotion
A text markup annotation freed by the current xref table must not become review metadata.
Expected: array (
)
Actual: array (... annotation_object => 9, contents => 'Stale freed highlight review' ...)
1 test files, 6 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMarkupAnnotationFreedObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS suppresses xref-free text markup annotations while preserving current link promotion
1 test files, 15 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMarkupAnnotationFreedObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationIndirectOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 540 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreedAnnotationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefFreeAnnotationFilterStackCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 44 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-markup-annotation-freed-object-currentbase.php
```

The smoke emits `free_annotation_object_marked=true`, `annotation_objects=[7]`, `promoted_link_objects=[7]`, `markup_count=0`, `freed_markup_promoted=false`, `current_link_promoted=true`, `visible_text_imported=true`, `annotation_payload_text_visible=false`, and all PDF-action, Python/model, and external-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Status Delta

- Adds 1 focused PHP PASS case and 15 focused assertions.
- Adds 1 WordPress smoke/example for current xref free-row suppression of stale text-markup annotations.
- Expected lane-status movement: `phpPass` `2936 -> 2937`; `wordpressScenarios` `2444 -> 2445`.
- Mapped upstream denominator is unchanged; this deepens the native annotation/link xref boundary.

## Non-Overlap

This does not repeat accepted link annotation free-object suppression, freed indirect action-object suppression, xref-stream filter-stack free-map decoding, page `/Annots` tokenization, escaped annotation names, generation-exact annotation references, object-stream annotation offsets, Link QuadPoints, optional-content hidden links, widget field inheritance, previous URI metadata, or markup QuadPoints geometry. The bounded behavior is specifically text-markup annotation review using the current xref free-object map before WordPress review-span application.

## Dependency Closure

No new support component is needed. This reuses the native PHP xref free-object map, page annotation traversal, text-markup extractor, link span applier, text extractor, Markdown postprocessor, and WordPress smoke harness. GPU/model/OCR execution, Surya/Texify/Torch, live PDFium/PIL rendering, JavaScript/PDF action execution, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.
