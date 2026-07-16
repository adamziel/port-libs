# AcroForm Fields Child Root Boundary Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T051900Z`
Base accepted HEAD: `96334dbac988bdc3165c7544ce095425467bbccd`

## Source Truth

- Upstream markerPDF treats AcroForm values as structured PDF form metadata, not visible page text or OCR/model output.
- PDF AcroForm `/Fields` arrays are expected to list root fields, but malformed or incrementally updated PDFs can point directly at child field dictionaries or merged field-widget dictionaries. When those children have a valid `/Parent` field chain, WordPress import review should preserve the root-qualified name and inherited field attributes instead of importing orphan `email` or `visibility` fields.
- Current no-GPU markerPDF scope applies: no OCR, Surya, Texify, Torch, PDFium rendering, Streamlit/FastAPI model workers, browser rendering, JavaScript/action execution, or external PDF tools were run.

## Behavior

- `PdfAcroFormExtractor::rootFieldReferencesFromAcroFormReferences()` now normalizes non-widget child `/Fields` entries through a generation-checked `/Parent` chain, matching the existing pure-widget parent-root recovery.
- Direct child field entries preserve parent-qualified names such as `profile.email`, inherited `/FT`, `/DV`, `/DA`, `/MaxLen`, widget page annotation order, alternate labels, and mapping names.
- Merged field-widget entries with their own `/Subtype /Widget`, `/T`, and `/V` now normalize through their valid parent root, preserving inherited choice `/Opt` values and selected option review.
- Detached parent decoys and form values remain review-only and do not surface in visible WordPress paragraphs.

## Evidence

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php` passed with `1 test files, 423 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroForm*.php lanes/markerpdf/tests/PdfSecurityPermissionByteRangeFieldMdpCurrentBaseTest.php` passed with `29 test files, 2965 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php` exits `0` and emits `child_field_entries_normalized_to_parent_roots=true`, `merged_widget_field_entries_normalized_to_parent_roots=true`, `comment_widget_subtype_decoys_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused markerPDF PHP pass count: `1457 -> 1458`.
- WordPress scenario count: `1375 -> 1376`.
- Manifest mapped behavior `pdfAcroFormFieldsGenerationBoundaryCurrentBaseBehaviors`: `2 -> 3`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted AcroForm page-widget discovery, pure Widget `/Fields` normalization, token-aware escaped `/Fields` parsing, indirect `/Fields` and `/Kids` arrays, generation-exact field refs, scalar/numeric/type operands, alternate `/TU` and `/TM` review, comment-only widget subtype exclusion, widget appearance/action/XFA/signature review, submit/reset review, page widget link promotion, encryption/security preflight, xref repair, stream filters, image handling, CMaps, outlines, annotations, or metadata clusters. The bounded behavior is specifically direct child field and merged field-widget entries in catalog `/AcroForm /Fields` normalizing through a valid parent field chain.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP object scanner, generation-valid reference checks, dictionary/array parser, field hierarchy builder, page widget map, action-safe AcroForm review metadata, and existing WordPress smoke path. Full upstream model/OCR/rendering parity remains intentionally out of scope under the current no-GPU markerPDF direction.
