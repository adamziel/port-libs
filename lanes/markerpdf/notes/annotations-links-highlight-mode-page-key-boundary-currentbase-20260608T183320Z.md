# markerpdf-annotations-links-highlight-mode-page-key-boundary-currentbase-20260608T183320Z

Base accepted HEAD: `ac3303553ece8d04b2ac6e7da7800926d228ca87`

Scope: native no-GPU markerPDF Link annotation page-membership boundary. A valid PDF Link highlight mode may use `/H /P` for the push visual state; malformed highlight-mode tails such as `/H /P 4 0 R` must not masquerade as the annotation dictionary's page ownership `/P` reference.

Source-truth behavior: PDF annotation `/P` ownership is a top-level annotation dictionary key. Other keys' PDF name operands, including `/H /P`, are not annotation page references. The patch keeps real top-level `/P 4 0 R` cross-page ownership intact, preserves safe URI link promotion, and keeps tailed highlight presentation metadata fail-closed. It does not execute PDF actions, JavaScript, Python models, OCR, raster rendering, pypdfium/PIL, or external PDF tools.

Red evidence before fix:

- Inline fixture using `/H /P 4 0 R` on a page-owned Link annotation returned empty annotation and link rows because the highlight-mode tail was treated as a page `/P` reference.
- Observed output: `array ( 0 => array ( ), 1 => array ( ), )`.

Green evidence after fix:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkHighlightModePageKeyBoundaryCurrentBaseTest.php`
- Result: `1 test files, 31 assertions, 0 failures`

WordPress smoke:

- `php lanes/markerpdf/examples/wordpress-pdf-annotation-link-highlight-mode-page-key-currentbase.php`
- Result: exits `0`; `page_one_annotation_objects=[7,8]`, `page_two_annotation_objects=[10]`, `page_one_link_objects=[7,8]`, `page_two_link_objects=[10]`, `tailed_highlight_metadata_imported=false`, `real_page_reference_leaked_to_page_one=false`, `visible_text_excludes_annotation_payloads=true`, `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Status delta:

- Adds 1 focused PHP PASS case.
- Adds 31 focused assertions.
- Adds 1 WordPress smoke scenario.
- Updates `lane-status.json` from `phpPass=3389` to `phpPass=3390` and `wordpressScenarios=2759` to `wordpressScenarios=2760`.

Non-overlap:

- Avoids the accepted decoded-collision named-destination alias slice, Link presentation operand boundary, `/A` and `/Dest` tailed operand boundaries, malformed `/Rect`, malformed `/QuadPoints`, page `/Annots`, duplicate action keys, object-stream links, optional-content links, xref-free links, link generation, and page `/P` trailing-operand rejection slices.

Dependency closure:

- No new support component is needed. The patch reuses the native PDF dictionary/token scanners and focused PHP tests already present in `lanes/markerpdf`.

Follow-up:

- Continue native no-GPU markerPDF link/annotation coverage around non-overlapping action dictionaries, structure context, object-stream boundaries, page geometry, and supplied WordPress review metadata.

Root harness: not run - isolated micro-slice.
