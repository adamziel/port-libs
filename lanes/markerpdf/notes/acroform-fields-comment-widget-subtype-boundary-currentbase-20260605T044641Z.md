# AcroForm Fields Comment Widget Subtype Boundary Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T044641Z`
Base accepted HEAD: `61d89320c957b78cadb6887799e6302745c11378`

## Source Truth

- Upstream markerPDF treats AcroForm fields as structured PDF form metadata, separate from visible page text and OCR/model output.
- PDF comments are lexical whitespace. A name token inside a `% ... EOL` comment must not classify an annotation as `/Subtype /Widget`.
- Current no-GPU markerPDF scope applies: no OCR, Surya, Texify, Torch, Streamlit/FastAPI model workers, browser/PDF renderer, or external PDF tools were run.

## Behavior

- `PdfAcroFormExtractor::isWidget()` now reads the top-level `/Subtype` name with the existing token-aware PDF value reader instead of matching raw dictionary bytes.
- Field `/Kids` traversal now recurses only into real field dictionaries. Non-widget, non-field annotation kids are ignored rather than becoming inherited terminal fields.
- The focused fixture keeps one real Widget plus two decoys:
  - a page Text annotation whose comment line contains `/Subtype /Widget` and whose real dictionary contains `/FT /Tx /T /V`; and
  - a child Text annotation under a field `/Kids` array whose comment line contains `/Subtype /Widget /Parent 6 0 R`.
- The extractor returns only `article.comment`, keeps widget object `8`, excludes `comment.promoted`, and keeps form values/comment decoys out of visible WordPress text.

## Evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php` failed with extra field `comment.promoted` before the production fix.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php` passed with `1 test files, 372 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroForm*.php lanes/markerpdf/tests/PdfSecurityPermissionByteRangeFieldMdpCurrentBaseTest.php` passed with `29 test files, 2914 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php` exits `0` and emits `comment_widget_subtype_decoys_excluded=true`.
- Syntax and whitespace verification were run after this note was written.

## Status Delta

- Focused markerPDF PHP pass count: `1424 -> 1425`.
- Existing WordPress AcroForm fields smoke updated in place; no new example file count claimed.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted AcroForm generation matching, trailer `/Root` ownership, direct Widget `/Fields` normalization, token-aware `/Fields` and `/Kids` array reference parsing, indirect scalar/numeric/type operands, widget appearance/action/XFA/signature review, link annotation comment dictionaries, xref repair, stream filters, image handling, CMaps, outlines, attachments, runtime planners, or OCR/model behavior.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP object scanner, dictionary/array tokenizer, comment skipping, PDF name decoding, field hierarchy builder, page annotation widget map, and text extractor boundaries. Full upstream live OCR/model/rendering parity remains intentionally out of scope under the current no-GPU markerPDF direction.
