# Font Width Advance FontMatrix Reference Boundary

Slice: `markerpdf-font-width-advance-boundary-current-base-20260607T075859Z`  
Base accepted HEAD: `912c56d812f68fca8f6ea91b90c49265da9a9a1d`

## Source Truth

- PDF Type3 `/FontMatrix` is an array of six numeric values. It must be parsed as array items, not by harvesting every number-like token in the raw array body.
- This matters for native markerPDF text import because Type3 `d0`/`d1`, `/Widths`, and descriptor fallback advances are transformed through `/FontMatrix` before line grouping and styled bbox generation.
- The prior parser accepted malformed unresolved reference tokens such as `99 0 R` inside `/FontMatrix` as numeric matrix entries `99` and `0`, hiding real positioned word gaps and creating oversized styled bboxes.

## Patch

- `PdfTextExtractor::topLevelPdfMatrixValueAfterName()` now tokenizes the matrix array with `pdfArrayItems()` and resolves each of the first six values through `pdfNumberValueAt()`.
- Unresolved or non-finite matrix entries now fail closed to the caller's existing default Type3 matrix path.
- Added a focused fixture where `/FontMatrix [99 0 R 0 0.001 0 0]` must not turn the unresolved reference into a `99` scale factor before Type3 CharProc width advance grouping.
- Added a WordPress smoke proving the visible paragraph remains `AB CD`, Type3 CharProc payload text stays excluded, bboxes remain finite, and no Python/model/OCR/external PDF tools execute.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceFontMatrixReferenceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects unresolved Type3 FontMatrix reference tokens before width advance grouping on current base
Values are not identical
Expected: array (
  0 => 'AB CD',
)
Actual: array (
  0 => 'ABCD',
)
1 test files, 1 assertions, 1 failures
```

After fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceFontMatrixReferenceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects unresolved Type3 FontMatrix reference tokens before width advance grouping on current base
1 test files, 13 assertions, 0 failures
```

Affected family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceFontMatrixReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthPrecedenceBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 667 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-fontmatrix-reference-currentbase.php
exits 0 with wordpress_import_text=AB CD, bboxes_are_finite=true, word_gap_preserved=true, payload_excluded=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF tokenizer/parser, Type3 font metrics, and existing WordPress smoke pattern. GPU/model execution, OCR, pypdfium/PIL rendering, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for markerPDF under the current no-GPU directive.

## Non-Overlap

This does not duplicate accepted simple-font width arrays, CIDFont `/W`/`W2`, Type3 normal `FontMatrix` vector advance, malformed width rows, huge finite width/Tf/Tz/TJ guards, or CMap source-width fallback work. It only covers malformed unresolved Type3 `/FontMatrix` reference tokens before width-derived text advance grouping.
