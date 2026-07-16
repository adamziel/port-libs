# markerpdf font width non-finite advance boundary current-base

Session: `port-dev-markerpdf-font-width-advance-20260605T114348Z`
Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T114348Z`
Base: `d131079cbe8230639ebc16908a136f20ee209427`

## Source Truth

Upstream markerPDF delegates searchable-PDF glyph extraction and geometry to pdftext/PDFium before assembling Marker/WordPress spans. In the native no-GPU PHP fallback, PDF font advance metrics are the local source of truth for current text position, word-gap decisions, and styled bbox review geometry. PDF font width arrays are numeric metric operands; values that parse to non-finite floats in PHP are not usable glyph advances and must be treated as absent metrics before they reach WordPress import metadata.

## Behavior

`PdfTextExtractor` now ignores non-finite parsed font advance metrics from:

- simple-font `/Widths`;
- simple-font `/MissingWidth`;
- CIDFont `/W` and `/DW`;
- vertical CIDFont `/W2` and `/DW2`;
- Type3 `/Widths` or CharProc widths after FontMatrix normalization.

The focused fixture uses a simple font with a very large decimal `/Widths` entry that PHP would otherwise parse as `INF`. The extractor now falls back to finite adjacent/default metrics, preserves the real `Td` word gap as `AB CD`, and keeps styled span bboxes finite: `[[0,0,24,12],[48,0,72,12]]`.

## Non-Overlap

This does not repeat accepted average-positive missing-width fallback, quote spacing, terminal `Tc`, relative/absolute `Td`/`Tm` gaps, scaled/rotated text matrices, `TJ` backtracking, `LastChar` clipping, malformed `FirstChar`/`LastChar`, indirect width arrays, negative first CID rejection, vertical `W2` backtracking, Type3 FontMatrix width scaling, CMap source-width segmentation, xref repair, stream filters, images, annotations, forms, security preflight, OCR, or table/equation supplied-boundary work. The new boundary is specifically non-finite parsed font advance operands before current-position and styled-bbox propagation.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php` => `1 test files / 327 assertions / 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php` emits `nonfinite_width_current_gap_preserved=true`, `nonfinite_width_infinite_bbox_excluded=true`, `nonfinite_width_styled_bboxes_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- Root harness not run: isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, width-array parser, CMap/source-key mapping, content stream text-state interpreter, and WordPress smoke renderer. Full upstream OCR/model parity remains intentionally out of scope under the current no-GPU markerPDF directive.
