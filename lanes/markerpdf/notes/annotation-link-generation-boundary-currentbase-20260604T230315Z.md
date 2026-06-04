# markerPDF Link Annotation Generation Boundary Current Base

Session: `port-dev-markerpdf-annotations-links-20260604T230315Z`
Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260604T230315Z`
Base accepted HEAD: `dfe72a34a9a7921b2b472d062fc3e25f4922e152`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text and page metadata through `marker/pdf/extract_text.py` into `pdftext.extraction.dictionary_output(...)` before downstream Markdown/WordPress conversion.
- The native no-GPU PHP lane owns the parser boundary before that handoff: page `/Annots`, Link `/Rect`, `/F`, `/A`, `/AA`, `/Next`, and destination operands are indirect PDF references and must match the referenced object generation. A stale same-object-number generation must not become current WordPress link metadata.

## Implemented Behavior

- `PdfLinkAnnotationExtractor` now keeps an object body map by generation and resolves page `/Annots`, annotation array members, `/Rect`, `/F`, and other indirect scalar/array operands only when the `N G R` generation exists.
- `PdfActionReviewExtractor` now preserves reference generations in parsed object values and resolves `/A`, `/AA`, `/Next`, name-tree destination, and indirect action operands against exact object generations.
- Existing public review rows still expose annotation/action object numbers as before; the generation check is a parser-boundary guard, not a public API rename.

## Evidence

Focused new boundary test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps link annotation object generations exact before WordPress span promotion

1 test files, 32 assertions, 0 failures
```

Adjacent link/action family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedActionCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
10 PASS lines
3 test files, 207 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-generation-boundary-currentbase.php
```

The smoke emits `page_link_count=2`, `link_uris=["https://example.com/current-generation-link"]`,
`additional_action_uris=["mailto:current-generation@example.test"]`,
`excludes_stale_generation_links=true`, `visible_text_excludes_link_targets=true`,
and all PDF action, JavaScript, Python/model, and external PDF tool execution flags false.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-annotation-generation-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed. Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass` / WordPress scenarios move `1100 -> 1101`.
- Adds one focused behavior case with 32 assertions.

## Non-Overlap

This does not repeat accepted escaped Link dictionary keys, hidden/no-view link flags, Widget link promotion, rotated/UserUnit link rectangles, annotation StructParent associated-action context, generic annotation action review, AcroForm field generation boundaries, or xref/current trailer repair. The bounded behavior is specifically generation-exact resolution for Link annotation and action operands at the current page `/Annots` boundary.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, Link annotation extractor, action review walker, destination resolver, span-link promotion path, Markdown post-processor, and WordPress smoke path. Full upstream markerPDF runner parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were executed for this bounded no-GPU parser slice.
