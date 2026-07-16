# Link Annotation Same-Page Destination Boundary

Slice: `markerpdf-annotations-links-boundary-current-base-20260605T163539Z`

Source truth:

- Upstream markerPDF is pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; this no-GPU lane ports native PDF/parser and WordPress conversion behavior without executing PDF actions, Python models, or external PDF tools.
- The current pdftext link merger treats same-page destination references without an explicit destination position as self links to skip during text-span linking. Source checked: `https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/pdf/links.py`, `merge_links`.

Implemented boundary:

- `PdfLinkAnnotationExtractor` now keeps same-page `/Dest [current-page /Fit]` link annotations in page-level review metadata, but suppresses their rectangle candidates before WordPress span promotion when the destination is the current page and `view_position` has no numeric position.
- Same-page destinations with explicit coordinates such as `/XYZ 72 720 0`, cross-page `/Fit` destinations, and safe URI links still promote to the matching text span.
- Visible text and Markdown output exclude annotation review payload strings and do not execute PDF actions.

Focused evidence:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationSelfDestinationBoundaryCurrentBaseTest.php` => `1 test files / 33 assertions / 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationSelfDestinationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationUriControlBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriBoundaryCurrentBaseTest.php` => `5 test files / 265 assertions / 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-link-self-destination-boundary-currentbase.php` emits `self_fit_page_link_reviewed=true`, `self_fit_span_promoted=false`, `self_xyz_span_promoted=true`, `other_page_span_promoted=true`, `uri_span_promoted=true`, `annotation_payload_text_visible=false`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php -l` passed for `PdfLinkAnnotationExtractor.php`, `PdfLinkAnnotationSelfDestinationBoundaryCurrentBaseTest.php`, and `wordpress-pdf-link-self-destination-boundary-currentbase.php`.
- `jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/markerpdf` passed.

Non-overlap:

- This does not change URI control, escaped annotation keys, generation-exact annotation resolution, QuadPoints geometry, rotated/UserUnit rectangle mapping, previous URI review, remote GoToR action review, widget parent inheritance, crop handling, or xref/name-tree parsing.

Dependency closure:

- No new support component is needed. The slice reuses the native PHP link annotation extraction, annotation action review, Markdown post-processing, and text extraction components. No Python, pdftext runtime, pypdfium/PIL, Surya/Texify/Torch/model execution, browser service, or external PDF tool is required.
