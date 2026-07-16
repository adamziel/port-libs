# markerpdf annotations links boundary current base 2026-06-05T17:15:01Z

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T171501Z`
- Accepted base: `601ffedf79c2212413bd91bec50d947b009a257d`
- Implemented native no-GPU PDF action-review behavior for Link annotations whose action dictionaries store `/S` as an indirect PDF name object.

## Source Truth

- PDF action dictionaries use `/S` as the action subtype name. PDF objects may carry names indirectly, so `/S 20 0 R` with object `20 0 obj /URI endobj` must dispatch the same as `/S /URI`.
- Upstream markerPDF imports link metadata through pdftext/PDF parser annotation refs for searchable PDFs; this slice ports the native parser/action boundary without Python, pdftext, pypdfium2, OCR, Surya, Texify, Torch, Streamlit/FastAPI workers, or external PDF tools.

## Behavior

- `PdfActionReviewExtractor` now resolves the `/S` value before action-type dispatch.
- Primary Link URI actions with indirect `/S /URI` promote to safe WordPress Markdown links.
- Indirect `/S /JavaScript` chained actions and indirect `/S /Launch` primary actions remain review-only metadata and never execute or donate clickable span metadata.
- Indirect `/S /URI` additional actions are preserved as non-executing review rows.
- Annotation payload/action strings remain out of visible extracted WordPress text.

## Evidence

- Red-first before source edit:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectActionSubtypeBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 3 assertions, 1 failures`
  - Failure: expected `['URI', 'JavaScript']`, actual `[]` for indirect `/S` action dispatch.
- Focused after source edit:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectActionSubtypeBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 31 assertions, 0 failures`
- Adjacent Link annotation family:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotation*Test.php`
  - Result: `26 test files, 948 assertions, 0 failures`
- Action reviewer family:
  - `php tools/run-tests.php lanes/markerpdf/tests/Pdf*Action*Test.php`
  - Result: `49 test files, 2737 assertions, 0 failures`
- Page annotation boundary subset:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotation*Test.php lanes/markerpdf/tests/PdfPageAnnotation*Test.php`
  - Result: `14 test files, 874 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-link-annotation-indirect-action-subtype-currentbase.php`
  - Emits `promoted_link_objects=[7]`, `link_action_types=["URI","JavaScript"]`, `additional_action_uri=mailto:indirect-subtype@example.test`, `launch_promoted=false`, `annotation_payload_text_visible=false`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

- No new support component is needed. This reuses the existing native PHP PDF tokenizer/object resolver and action-review parser.
- GPU/model/OCR parity remains intentionally out of scope under the current markerPDF no-GPU directive.

## Non-Overlap

- This does not repeat accepted Link annotation URI base, direct `/S` actions, primary action array rejection, previous URI metadata, IsMap, QuadPoints, crop, generation, parent-generation, name-tree Limits, widget-parent, or annotation StructTree coverage.
- This slice is specifically the indirect action subtype-name boundary for Link annotation action dictionaries.
