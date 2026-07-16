# markerpdf font-width negative first CID array boundary current-base

Slice: `markerpdf-font-width-advance-boundary-current-base-20260605T110727Z`
Session: `port-dev-markerpdf-font-width-advance-20260605T110727Z`
Base accepted HEAD: `0147d7cd16fbde22482892e48538f86512fde76c`

## Source truth

Upstream markerPDF delegates searchable PDF text to `pdftext.extraction.dictionary_output(...)` and consumes span text, font, and bbox dictionaries before downstream conversion:
`https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`.

For this no-GPU native PHP parser slice, the source-truth contract is the PDF CIDFont `/W` and `/W2` array form: the leading CID starts the contiguous metrics array. A negative first CID is malformed and must not shift later array entries onto valid CID 0+ advances.

## Red Case

Before the fix, `/W [-1 [250 250 250 250]]` partially shifted the later width entries onto valid CIDs. The searchable line became `Wi de`, and styled-span bboxes shifted to `[[0,0,6,12],[6,0,21,12]]` instead of preserving the default-width spans for `Wide`.

Before the fix, `/W2 [-1 [-250 500 880 ...]]` did the same for vertical metrics. The searchable line became `Ve rt`, and styled-span bboxes shifted to `[[0,0,12,6],[12,0,24,15]]` instead of the default vertical displacements for `Vert`.

## Implementation

`PdfTextExtractor::cidWidthsFromWArray()` and `PdfTextExtractor::cidVerticalDisplacementsFromW2Array()` now reject array-form width/displacement segments whose first CID is negative or beyond `0xffff`. The malformed segment is skipped wholesale before any current advance or bbox geometry is calculated.

## Status Delta

- `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: 25 PASS / 293 assertions -> 27 PASS / 315 assertions.
- `lane-status.json` `phpPass`: 1758 -> 1760.
- `lane-status.json` `wordpressScenarios`: 1602 -> 1603.
- `UPSTREAM_TEST_MANIFEST.json` `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: 3 -> 5.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php` => 1 test files / 315 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php` exits 0 and emits all `negative_first_cid_w*` and `negative_first_cid_w2*` smoke flags as `true`, with `executes_python_or_models=false` and `executes_external_pdf_tools=false`.
- Full root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat the existing font-width coverage for average simple-font widths, quote spacing, terminal `Tc`, relative/scaled `Td`, text matrix scale/rotation, text rise, `TJ` backtracking, unresolved width slots, `LastChar`, malformed simple-font ranges, indirect `W`/`W2`, vertical `W2`, Type3 FontMatrix, or CMap source-width fallback. This slice only owns malformed array-form Type0 `/W` and `/W2` segments with invalid first CIDs before current advance calculation.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP parser, CMap handling, CIDFont `/W` and `/W2` scanners, text advance calculation, and styled-span bbox extraction paths. No OCR, Surya, Texify, Torch, GPU/model workers, PDFium, or external PDF tools were invoked.
