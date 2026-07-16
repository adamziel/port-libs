# Outline Named Destination Transition Thread Security Current Base

Micro-slice: `outline-named-destination-transition-thread-security-currentbase`

Base accepted HEAD: `c3a3b3436899d5af64fa2dad7e137908759c83df`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates PDF bookmark resolution to the PDF engine and keeps TOC rows as title/level/page navigation metadata: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py
- Upstream `marker/pdf/extract_text.py::get_text_blocks` obtains page text blocks separately from the returned TOC metadata, which matches this lane's boundary that outline/action dictionaries must not become visible WordPress paragraphs: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Relevant PDF parser/dependency boundary: pypdf constants classify catalog `/Outlines` and `/Threads`, page `/Dur` and `/Trans`, and trailer `/Encrypt` as structural/security dictionaries rather than page text operands: https://pypdf.readthedocs.io/en/5.7.0/_modules/pypdf/constants.html

## Implementation

- `PdfSecurityPreflight::documentActionSecurityReview()` now imports `PdfOutlineExtractor::getNavigationReviewMetadata(..., false)` outline action review rows as `source=outline_action`.
- Outline action security rows preserve:
  - outline title/object/level;
  - named-destination action key;
  - target page label, display duration, and transition;
  - target article-thread bead/title context;
  - DocMDP/FieldMDP/UR permission transform context after the existing permission annotator runs.
- Added `outline_action_security_review`, `outline_action_count`, outline action permission-status fields, and outline object fallback as the action container for direct outline actions.
- Added a WordPress smoke proving unsafe JavaScript/Launch outline actions stay review-only while visible page text remains clean.

## Verification

- Red-first behavior gap: before this bridge, the focused fixture's signed document had outline JavaScript/Launch actions only; `PdfSecurityPreflight` reported no document action rows for those outline actions, so the new `outline_action_security_review` assertions would fail.
- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` passed.
- `php -l lanes/markerpdf/tests/PdfOutlineNamedDestinationTransitionThreadSecurityCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-outline-named-destination-transition-thread-security-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNamedDestinationTransitionThreadSecurityCurrentBaseTest.php` passed: `1 test files, 100 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNamedDestinationTransitionThreadSecurityCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineLaunchThreadTransitionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed: `6 test files, 792 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-outline-named-destination-transition-thread-security-currentbase.php` passed and emitted `outline_action_count=4`, `unsafe_outline_action_count=2`, `target_transition_styles=["Dissolve"]`, `target_article_thread_titles=["Security Deck Thread"]`, `blocked_operations=["signature_validation","signing","pdf_action_execution"]`, `raw_signature_material_exposed=false`, and `visible_text_excludes_outline_security_operands=true`.
- `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- PHP behavior tests move `859 -> 862` from the three new focused TestRunner PASS cases.
- Mapped markerPDF/PDF semantics move `605 -> 606 / 78`.

## Non-Overlap

This does not repeat existing outline named-destination TOC resolution, outline target transition context, article-thread navigation metadata, catalog OpenAction security review, page additional-action review, annotation action review, AcroForm action review, signature byte-range review, or DocMDP permission extraction. The new behavior is the security-preflight composition boundary for outline named-destination action chains that target a transition/thread page.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `PdfOutlineExtractor`, `PdfSecurityPreflight`, `PdfTextExtractor`, `PdfMetadataExtractor`, `PdfAcroFormExtractor`, and the lane-native object parser/action walker. Full upstream markerPDF parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers, none of which were run for this bounded PHP slice.
