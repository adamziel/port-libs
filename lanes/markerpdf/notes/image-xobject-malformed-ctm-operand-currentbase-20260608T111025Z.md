# Image XObject Malformed CTM Operand Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260608T111025Z`
Accepted base: `01ba3eaa0944b4717c660abd6dc1418c3de0715f`

## Source Truth

Upstream markerPDF separates searchable text extraction from image rendering: `marker/pdf/extract_text.py` builds text blocks from pdftext output, while `marker/pdf/images.py::render_image()` renders page pixels through PDFium/PIL and converts them to RGB. In this no-GPU PHP lane, Image XObject payloads remain review-only media handoff metadata and must not become WordPress paragraph text.

PDF content-stream `cm` updates the current transformation matrix only when it has exactly six numeric operands. A malformed `cm` before an Image XObject `Do` should keep the prior CTM behavior but expose that boundary in review metadata instead of silently making the image look like an ordinary identity placement.

## Red Probe

Before the source change:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectMalformedCtmOperandCurrentBaseTest.php` failed with `1 test files / 13 assertions / 1 failures`.
- Failure: the Image XObject review row had no `malformed_ctm_operand_count` field, so WordPress import review could not distinguish a true identity image placement from a placement after a rejected `cm` operator.

## Implementation

- `PdfTextExtractor` now records malformed `cm` operand details in the Image XObject invocation state.
- Review rows expose `malformed_ctm_operand_count`, `malformed_ctm_operands`, `malformed_ctm_operand_policy`, and `malformed_ctm_operand_review_only`.
- The existing no-op CTM behavior is preserved for malformed `cm` operands, so accepted placement expectations remain stable.
- Form XObject recursion carries the malformed CTM review stack into nested image rows when the malformed transform occurs on the invoking form placement.
- Image payload bytes remain excluded from visible text and review JSON.

## Verification

- Red-first focused run before implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectMalformedCtmOperandCurrentBaseTest.php` => `1 test files / 13 assertions / 1 failures`.
- Focused run after implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectMalformedCtmOperandCurrentBaseTest.php` => `1 test files / 43 assertions / 0 failures`.
- Image XObject family: `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObject*CurrentBaseTest.php` => `37 test files / 2743 assertions / 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-image-xobject-cm-operand-boundary-currentbase.php` exits 0 and emits `malformed_cm_operand_count=1`, `malformed_cm_operand_policy=preserve_prior_ctm_and_review_image_placement`, `valid_sibling_malformed_cm_operand_count=0`, and `payload_in_visible_text=false`.
- PHP lint: `php -l lanes/markerpdf/src/PdfTextExtractor.php`, `php -l lanes/markerpdf/tests/PdfImageXObjectMalformedCtmOperandCurrentBaseTest.php`, and `php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-cm-operand-boundary-currentbase.php` all report no syntax errors.
- Lane status JSON validates with `json_decode(..., JSON_THROW_ON_ERROR)`, and `git diff --check -- lanes/markerpdf` exits 0.

## Non-Overlap

This does not repeat accepted Image XObject optional-content generation, resource-wrapper, Do-operand, CTM no-op placement, clipping/path, Form XObject, Type3 CharProc, DCT/CCITT/JPX/JBIG2 filter, soft-mask/mask, OPI, dimension, duplicate-resource, or xref repair slices. It only adds review metadata for malformed `cm` operands that already preserve the prior CTM before Image XObject placement.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, numeric operand parser, graphics-state stack, Image XObject review path, and stream decoder. GPU/OCR/model execution, PDFium/PIL raster parity, and external PDF tools remain intentionally out of scope.

## Next Task

Continue non-overlapping native markerPDF parser fidelity around image/filter metadata, xref repair, fonts/CMaps, metadata, annotations/forms, page geometry, and supplied-boundary table/equation handoffs.
