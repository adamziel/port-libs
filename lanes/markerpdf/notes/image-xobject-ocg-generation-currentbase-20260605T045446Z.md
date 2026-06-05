# Image XObject OCG Generation Boundary Current Base

## Slice

- Lane: `markerpdf`
- Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T045446Z`
- Base accepted HEAD: `48fd42b5dca68647d1ddff43b51b8403b4c5825c`
- Scope: native no-GPU PDF parser behavior for Image XObject optional-content visibility.

## Source Truth

PDF indirect references are generation-qualified. Optional-content `/OCGs`, `/ON`, `/OFF`, `/OC`, and `/OCMD /OCGs` references such as `21 0 R` and `21 1 R` identify different object revisions, so default-view layer state must not collapse them to object number `21` when deciding whether an Image XObject invocation is painted or review-only.

## Change

- `PdfTextExtractor` now stores optional-content state by `object:generation` reference key.
- Optional-content ON/OFF arrays, usage-application OCG lists, page `/Properties`, direct `/OC` references, inline OCMD dictionaries, and membership visibility checks now preserve generation numbers.
- Exact-generation OCG dictionaries are resolved through the existing exact object-body lookup before applying intent and usage state.
- Generation-0 fallback remains only for legacy callers that may pass object-number keyed state arrays.

## Focused Evidence

- Intermediate focused run while the implementation still overmatched whitespace-separated OCG arrays: `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php` => `1 test files / 378 assertions / 2 failures`.
- Focused image run after fix: `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php` => `1 test files / 430 assertions / 0 failures`.
- Optional-content/text parser family run: `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserCommentArrayDictStringTokenCurrentBaseTest.php lanes/markerpdf/tests/PdfParserNameArrayCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserNameEscapeArrayBoundaryCurrentBaseTest.php` => `5 test files / 1091 assertions / 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php` reports `image_xobject_count=4`, `invoked_image_xobject_count=2`, `hidden_object_optional_content_visible=false`, `generation_visible_optional_content_visible=true`, `generation_visible_invoked=true`, `generation_visible_sha256_matches=true`, and no model/external-tool execution.
- PHP lint: `php -l lanes/markerpdf/src/PdfTextExtractor.php && php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php` => no syntax errors.
- Lane status JSON validity: `php -r '...'` => `lane-status.json valid`.
- Diff hygiene: `git diff --check -- lanes/markerpdf` => no output.

## Non-Overlap

This does not repeat accepted Image XObject CTM/Form/clip/mask/filter review, DCT/CCITT exclusion, inline-image tokenization, or xref repair slices. It narrows the existing Image XObject optional-content boundary to object-generation-specific layer state.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF tokenizer, dictionary/array reader, exact-generation object lookup, stream decoder, and Image XObject review path. GPU/OCR/model behavior remains intentionally out of scope for the markerPDF lane.

## Next Task

Continue with non-overlapping native PDF parser fidelity: image filter metadata and review boundaries, xref/object-stream repair, font/CMap widths, page geometry, annotations/forms, outlines, metadata, attachments, and supplied-boundary table/equation handoffs.
