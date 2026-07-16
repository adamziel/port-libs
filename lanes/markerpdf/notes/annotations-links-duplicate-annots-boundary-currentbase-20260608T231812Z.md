# markerPDF annotations duplicate Annots boundary

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260608T231812Z`
Base accepted HEAD: `c2add54cd754c7dc4d03da25c95616285192e050`

## Source Truth

- Upstream Marker converts PDFs into Markdown/JSON/HTML and explicitly preserves links and references as formatted document output: <https://github.com/datalab-to/marker>.
- The no-GPU PHP lane owns the native searchable-PDF parser boundary before WordPress import. Page `/Annots` values can be direct arrays, indirect arrays, or chained references; repeated references to the same indirect annotation object should not inflate review rows or attach duplicate markup/link metadata to a WordPress span.

## Behavior

Before this slice, the cycle guard for page `/Annots` references was scoped to each recursive value walk. If a page annotation array included an indirect array fragment and then repeated the same annotation references as siblings, `PdfAnnotationExtractor`, `PdfLinkAnnotationExtractor`, and `PdfMarkupAnnotationExtractor` emitted the same indirect annotation objects again.

This patch deduplicates emitted indirect annotation references after nested array expansion, per page annotation array. Direct inline annotation dictionaries remain preserved because they do not have stable object/generation identity.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkDuplicateAnnotsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL deduplicates repeated page Annots references before link and markup review
Expected: [7, 8, 9]
Actual: [7, 8, 9, 7, 8, 8, 9]
1 test files, 2 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkDuplicateAnnotsBoundaryCurrentBaseTest.php
PASS deduplicates repeated page Annots references before link and markup review
1 test files, 25 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLink*CurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotation*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnots*CurrentBaseTest.php lanes/markerpdf/tests/PdfMarkupAnnotation*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotation*CurrentBaseTest.php
89 test files, 3186 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php && php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php && php -l lanes/markerpdf/src/PdfMarkupAnnotationExtractor.php && php -l lanes/markerpdf/tests/PdfAnnotationLinkDuplicateAnnotsBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-annotation-link-duplicate-annots-currentbase.php
No syntax errors detected in all changed PHP files.
```

```text
php lanes/markerpdf/examples/wordpress-pdf-annotation-link-duplicate-annots-currentbase.php
exits 0; emits annotation_objects=[7,8,9], link_annotation_objects=[7], markup_annotation_objects=[8], span_review_annotation_count=1, annotation_payload_text_excluded_from_visible_text=true, executes_pdf_actions=false, executes_python_or_models=false, executes_external_pdf_tools=false.
```

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, page `/Annots` traversal, annotation/link/markup extractors, WordPress span promotion, and Markdown post-processing. Live OCR, Surya/Texify/Torch/model execution, pypdfium/Python rendering, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted page `/Annots` tokenization, escaped `/Ann#6fts`, chained `/Annots` reference resolution, tailed `/Annots` operands, annotation `/P` page-reference boundaries, generation-exact link/markup selection, object-stream annotation repair, QuadPoints geometry, URI safety, or action review behavior. The bounded behavior is only duplicate indirect annotation references after nested page `/Annots` array expansion.
