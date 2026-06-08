# markerpdf-annotations-links-boundary-current-base-20260608T064711Z

Base accepted HEAD: `c73ab3af9ca883f50ffd6b3d1d33ae6c6162db8c`

Scope: native no-GPU markerPDF Link annotation presentation operand boundaries. PDF Link annotations are still promoted when `/Rect` and `/A` are valid, but malformed direct or indirect visual metadata operands now fail closed:

- `/C` color arrays with top-level tails no longer donate stale border color.
- `/BS` border-style dictionaries with top-level tails no longer donate stale border style or dash metadata.
- `/Border` arrays with top-level tails no longer donate stale border geometry.
- `/H` and `/CA` scalar presentation values with top-level tails no longer donate stale highlight or opacity.

Source-truth behavior: PDF annotation presentation keys are single PDF objects. This matches the existing markerPDF native parser policy used for `/A`, `/Dest`, `/Rect`, `/QuadPoints`, page `/Annots`, stream filters, and DecodeParms: extra top-level operands are malformed boundary data and must not shape WordPress import context. The patch does not execute PDF actions, JavaScript, Python models, OCR, raster rendering, pypdfium/PIL, or external PDF tools.

Red evidence before fix:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationOperandBoundaryCurrentBaseTest.php`
- Failed after 5 assertions because indirect object `60 0 R` with body `[1 0 0] 90 0 R` donated `#ff0000` review color through `/C`.

Green evidence after fix:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationOperandBoundaryCurrentBaseTest.php`
- Result: `1 test files, 54 assertions, 0 failures`

Adjacent focused coverage:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRectOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationActionOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationDictionaryDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php`
- Result: `7 test files, 627 assertions, 0 failures`

WordPress smoke:

- `php lanes/markerpdf/examples/wordpress-pdf-link-presentation-operand-boundary-currentbase.php`
- Result: exits `0`; `promoted_link_objects=[7,8,9,10,11]`, all tainted presentation metadata flags are `false`, visible text excludes annotation payloads, and execution flags remain `false`.

Status delta:

- Adds 1 focused PHP PASS case.
- Adds 54 focused assertions.
- Adds 1 WordPress smoke scenario.
- Updates `lane-status.json` from `phpPass=2949` to `phpPass=2950` and `wordpressScenarios=2452` to `wordpressScenarios=2453`.

Non-overlap:

- Avoids the accepted inline DCTDecode filter-tail DecodeParms slice, DCT/CCITT/JBIG image filter boundaries, annotation `/A` and `/Dest` tailed operand boundary, malformed `/Rect`, malformed `/QuadPoints`, page `/Annots`, object-stream annotation, optional-content link, xref-free annotation, and named-destination/action-review slices.

Dependency closure:

- No new support component is needed. The patch reuses the native PDF token/dictionary/array scanners and focused PHP tests already present in `lanes/markerpdf`.

Follow-up:

- While building the red fixture, a separate parser membership quirk was observed: a valid `/H /P` highlight-mode value can be mistaken by fallback name scanning for an annotation page `/P` reference. This was kept out of this slice to preserve the single-behavior contract; a future annotation page-membership boundary slice should cover it.

Root harness: not run - isolated micro-slice.
