# Link Annotation StructTree Generation Boundary Current Base

## Slice

- Lane: `markerpdf`
- Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T080841Z`
- Accepted base: `4ebc3c31816e39da5e62e366b5a64877e49deb7a`
- Scope: native no-GPU PDF parser/converter behavior for Link annotations, page `/Annots`, StructTree OBJR review rows, and WordPress span promotion.

## Source Truth

- Upstream markerPDF behavior is bounded here to searchable-PDF extraction and link annotation metadata handoff. The local upstream cache did not contain a `sddai/markerPDF` checkout, so this slice uses the lane manifest plus PDF object-reference semantics as source truth.
- A page `/Annots` entry such as `7 1 R` identifies both object number and generation. A StructTree `/K << /Type /OBJR /Obj 7 1 R >>` row must only associate with that exact annotation generation. Same-object-number stale generations such as `7 0 R` must not donate structure title, actual text, action context, or span metadata to current links.
- No OCR, Surya, Texify, Torch, pypdfium rendering, Python model worker, JavaScript/PDF action execution, or external PDF tool execution is needed for this behavior.

## Red-First Evidence

Before the source change, the focused current-base test failed because annotation rows discarded generation metadata:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationStructTreeGenerationBoundaryCurrentBaseTest.php`

Result before fix: `1 test files, 3 assertions, 1 failures`; expected annotation generations `[1,0]`, actual `[null,null]`.

## Implementation

- `PdfAnnotationExtractor` now preserves annotation generation from page `/Annots` references and stores StructTree OBJR annotation references as exact `object:generation` keys while keeping the older object-number summary for review compatibility.
- `PdfLinkAnnotationExtractor` now carries annotation generation through link rows, action context lookup, structure-review lookup, and WordPress span metadata.
- The fallback object-only structure lookup remains available for legacy/direct annotation dictionaries that have no indirect annotation generation.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationStructTreeGenerationBoundaryCurrentBaseTest.php` => `1 test files / 39 assertions / 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationStructTreeGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationAssociatedStructTreeReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php` => `5 test files / 546 assertions / 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-link-annotation-struct-tree-generation-currentbase.php` => smoke emitted generation-exact annotation/link metadata and no action/model/external-tool execution.
- PHP lint passed for `PdfAnnotationExtractor.php`, `PdfLinkAnnotationExtractor.php`, `PdfLinkAnnotationStructTreeGenerationBoundaryCurrentBaseTest.php`, and `wordpress-pdf-link-annotation-struct-tree-generation-currentbase.php`.
- `php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $f . ": " . json_last_error_msg() . "\n"); exit(1); } echo $f . ": valid JSON\n"; }'` => both evidence JSON files valid.
- `git diff --check -- lanes/markerpdf` => passed with no whitespace errors.

Root harness not run: isolated micro-slice.

## Status Delta

- Added 1 focused TestRunner behavior case.
- Added 1 WordPress-relevant smoke scenario.
- `lane-status.json` records `phpPass` 1605 -> 1606 and `wordpressScenarios` 1487 -> 1488.
- `UPSTREAM_TEST_MANIFEST.json` records `pdfLinkAnnotationStructTreeGenerationCurrentBase` as 1 mapped behavior.

## Dependency Closure

No new support component is needed. The slice reuses existing native PDF object parsing, annotation extraction, StructTree review extraction, and WordPress span promotion helpers. Remaining model/OCR parity is intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This slice does not repeat existing link URI, link rectangle geometry, escaped dictionary/name, link generation object resolution, PageAnnotation associated StructTree fallback, AcroForm widget generation, or runtime/model-preflight slices. It specifically covers StructTree OBJR association when the same annotation object number exists at different generations.
