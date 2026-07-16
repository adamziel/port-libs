# Font Width Advance Malformed W2 Array Boundary

Slice: `markerpdf-font-width-advance-boundary-current-base-20260608T072537Z`
Base accepted HEAD: `fa0bf1a496fd8fffbd7a8cd81e2d1c2d1eb8804a`

## Source Truth

- PDF CIDFont `/W2` array-form entries encode vertical metrics in complete triples: `w1y v1x v1y` for each CID after the first CID key.
- The existing horizontal CIDFont `/W` array-form path already rejects an entire malformed array metric segment before using partial advances.
- The prior vertical `/W2` direct array-form path skipped malformed triples and still accepted later triples from the same array, which could hide a real WordPress word gap in vertical searchable-PDF text and distort styled span bboxes.

## Patch

- `PdfTextExtractor::cidVerticalDisplacementsFromW2Array()` now validates direct `/W2` array-form metric lists as complete finite triples before applying any displacement from that list.
- A new focused fixture covers malformed-token and incomplete-triple `/W2` arrays. Both cases must fall back to safe default vertical advances, preserving `Vert Import`, separate styled spans, and bounded line/span bboxes.
- Added a WordPress smoke proving the same malformed-array boundary keeps Gutenberg paragraph text separated without leaking font resource names or launching external tools.

## Evidence

Red-first on accepted base after adding the focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceMalformedW2ArrayBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed direct CIDFont W2 array triples before vertical styled span bboxes on current base
Values are not identical
Expected: array (
  0 => 'Vert Import',
)
Actual: array (
  0 => 'VertImport',
)
FAIL rejects incomplete direct CIDFont W2 array triples before vertical styled span bboxes on current base
Values are not identical
Expected: array (
  0 => 'Vert Import',
)
Actual: array (
  0 => 'VertImport',
)
1 test files, 2 assertions, 2 failures
```

After fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceMalformedW2ArrayBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed direct CIDFont W2 array triples before vertical styled span bboxes on current base
PASS rejects incomplete direct CIDFont W2 array triples before vertical styled span bboxes on current base
1 test files, 22 assertions, 0 failures
```

Adjacent font-width family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceMalformedW2ArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceScalarOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceFontMatrixOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceFontMatrixReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 782 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-malformed-w2-array-currentbase.php
exits 0 with bad_token.wordpress_import_text=Vert Import, incomplete_triple.wordpress_import_text=Vert Import, malformed_w2_array_rejected=true, word_gap_preserved=true, styled_span_bboxes_preserved=true, partial_vertical_metrics_excluded=true, executes_python_or_models=false, and executes_external_pdf_tools=false
```

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, CMap parser, CIDFont width metric parser, text extractor, styled span output, and existing WordPress smoke pattern. GPU/model execution, OCR, pypdfium/PIL rendering, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for markerPDF under the current no-GPU directive.

## Non-Overlap

This does not repeat accepted malformed simple-font `/Widths`, CIDFont `/W` direct/indirect array tails, valid vertical `/W2` advance geometry, Type3 FontMatrix/CharProc width behavior, TJ positioning, scalar operand guards, CMap source-width fallback, xref repair, metadata, annotations, forms, or OCR/model work. It only covers malformed direct CIDFont `/W2` array-form triples before native searchable-PDF text grouping and WordPress paragraph import.
