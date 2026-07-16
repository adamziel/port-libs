# markerpdf font width indirect array tail current-base slice

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260606T094937Z`

Accepted base: `460c4764cad1ddae97088426a34692370c81dfca`

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text through PDF font advances before Marker converts text into spans, lines, and Markdown. In the native no-GPU PHP lane, a simple-font `/Widths` value can be an indirect array object, but that referenced helper object must be a single array object. If the helper body has extra operands after the array, the native parser should fail closed and fall back to safe Base14/default advances instead of letting stray width data collapse WordPress paragraph spacing.

## Implementation

- Added a font-width-specific strict array resolver in `PdfTextExtractor`.
- Switched simple-font `/Widths` parsing to require direct or indirect helper arrays to be single array values.
- Preserved generic PDF array resolution for other review/extraction paths.
- Added a focused fixture where `/Widths 8 0 R` points at `[1000 ...] /BadWidth`; the helper tail is rejected and Helvetica Base14 widths preserve `Ill Word`.
- Added a WordPress smoke for the same import boundary.

## Evidence

Red-first inline probe before the source edit:

`extractTextLines()` returned `["IllWord"]` and styled bboxes used explicit 1000-unit helper advances for object `8 0 obj [1000 ...] /BadWidth endobj`.

Focused test after fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files, 43 assertions, 0 failures`.

Adjacent font-width family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleEncodingIndirectWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontTextStateSpacingAdvanceBoundaryCurrentBaseTest.php`

Result: `5 test files, 645 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-indirect-array-tail-currentbase.php`

Result: emits `indirect_width_array_tail_rejected=true`, `base14_word_gap_preserved_for_wordpress_paragraph=true`, `width_payload_visible_text_leaked=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PHP behavior cases: `+1` (`phpPass` 2498 -> 2499).
- Focused malformed-width assertion count: `29 -> 43` (`+14` assertions).
- WordPress smoke scenarios: `+1` (`wordpressScenarios` 2124 -> 2125).
- Mapped upstream denominator: unchanged; this stays inside the existing native font-width advance behavior cluster.

## Non-Overlap

This does not repeat malformed tokens inside direct `/Widths` arrays, trailing tokens after `/LastChar` inside an array, exact-generation `/Widths` resolution, indirect numeric width entries, CIDFont `/W`/`/W2` indirect arrays, Type3 FontMatrix behavior, CMap source-width fallback, CMap/filter fail-closed handling, xref repair, image/filter metadata, annotations/forms/security, OCR/model execution, or supplied-boundary table/equation handoffs.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, exact object-generation lookup, font width parser, Base14 width metrics, text advance grouping, styled span extraction, and WordPress smoke path. GPU/OCR/model execution, Surya/Texify/Torch workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope.
