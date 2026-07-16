# Type3 CharProc FontMatrix Vector Advance Current Base

Slice: `markerpdf-font-width-advance-boundary-current-base-20260605T202248Z`
Base: `9aa35d009f07fabee9a32a57e5e751856e526db5`
Date: 2026-06-05 UTC

## Source Truth

The pinned markerPDF conversion path routes searchable PDFs through native PDF text extraction before Marker spans/lines/Markdown. In the no-GPU PHP lane, Type3 CharProc `d0`/`d1` width vectors therefore need to follow PDF font-matrix geometry before WordPress paragraph spacing and styled span bbox review.

Existing current-base coverage already normalized scalar `/Widths` through `/FontMatrix` and covered nonzero-`wy` Type3 width-vector projection. This slice covers the pure-horizontal CharProc boundary where `wx 0 d0` combines with a FontMatrix that has a nonzero `b` component. Using only the transformed X projection under-advances a `[0.0006 0.0008 0 0.001 0 0]` matrix by 600 units instead of the full 1000-unit vector extent, which creates a false `AB CD` gap and collapsed styled bboxes.

## Implementation

- `PdfTextExtractor::type3FontMatrixWidthVectorAdvance()` now uses the full transformed vector length for pure-horizontal Type3 CharProc width vectors.
- Existing nonzero-`wy` behavior remains on the previous horizontal projection path so prior Type3 vertical/vector tests stay intact.
- The focused fixture keeps stale `/Widths [100 ...]` entries to prove CharProc `d0` widths win and keeps CharProc payload text excluded from extracted output.

## Evidence

Red-first before the source fix, after adding the focused fixture:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files, 422 assertions, 1 failures`; the first line split as `AB CD` instead of `ABCD`.

After the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files, 438 assertions, 0 failures`.

Adjacent Type3 gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthPrecedenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPathSetupBoundaryCurrentBaseTest.php`

Result: `4 test files, 32 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php`

Result: emitted `type3_charproc_fontmatrix_vector_false_gap_excluded=true`, `type3_charproc_fontmatrix_vector_real_gap_preserved=true`, bbox flags true, `type3_charproc_fontmatrix_vector_lines=["ABCD","EF GH"]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PHP behavior cases: `+1` (`phpPass` 2194 -> 2195).
- WordPress smoke scenarios: `+1` (`wordpressScenarios` 1890 -> 1891).
- Mapped upstream denominator: unchanged; this stays inside the existing native Type3/font-width behavior cluster.

## Non-Overlap

This does not repeat Type3 scalar `/Widths` FontMatrix normalization, nonzero-`wy` width-vector projection, Type3 fallback-payload exclusion, CID vertical `/W2`, text-rise, TJ backtracking, xref repair, metadata, or object-stream carrier work. It is limited to pure-horizontal Type3 CharProc width vectors with a nonzero FontMatrix `b` component.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object scanner, stream decoder, Type3 CharProc width parser, FontMatrix parser, text advance calculator, styled span extraction, and WordPress smoke path. GPU/OCR/model execution and external PDF tools remain intentionally out of scope.
