# AcroForm widget default-state edge, 2026-06-02

Slice: `markerpdf-acroform-current-value-widget-edge-current-base-20260602T072352Z`

Scope: `PdfAcroFormExtractor` now compares button-field defaults against the effective current state after widget `/AS` fallback. A checkbox or radio group with no field `/V`, a checked widget `/AS`, and matching `/DV` is no longer reported as changed from default. `/Off` and missing state are normalized as the same unchecked value for this comparison.

Source truth: upstream `sddai/markerPDF` commit `da6a2f5` keeps PDF extraction at the native PDF boundary through `marker/pdf/extract_text.py` and its pdftext/pypdfium dependency path; this PHP port keeps AcroForm review metadata native and non-executing. PDF 32000-1:2008 section 12.7 defines `/DV` as the reset default field value and widget annotation `/AS` as the selected normal appearance state; button widgets use `/Off` for the unselected state.

Focused evidence:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` -> 1 file, 314 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-current-values-import.php` emitted `appearance_state_fallbacks=["review.consent"]`, `widget_default_matches=["review.consent"]`, and `executes_form_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`.
- `php tools/run-tests.php lanes/markerpdf/tests` -> 59 files, 2548 assertions, 0 failures.
- `php -l` passed for `lanes/markerpdf/src/PdfAcroFormExtractor.php`, `lanes/markerpdf/tests/PdfAcroFormExtractorTest.php`, and `lanes/markerpdf/examples/wordpress-pdf-acroform-current-values-import.php`.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `git diff --check -- lanes/markerpdf` passed.

Status delta: behavior tests move 427 -> 428; mapped markerPDF/PDF semantics move 280 -> 281 / 78.

Non-overlap: this does not repeat the accepted broad AcroForm `/V` `/DV` `/I` `/Opt` `/AS` current-value slice, calculation/action JavaScript review metadata, SubmitForm/ResetForm review metadata, XFA packet extraction, Type0 CMap width-priority, object-stream, destination, inline-image, or font/parser slices. It only fixes the widget `/AS` fallback default-comparison edge for button fields.

Dependency closure: no new support component is needed. This reuses the native PDF object parser, AcroForm field/widget traversal, PDF name decoder, and existing review metadata path without Python, pdftext, pypdfium, Poppler, Ghostscript, form-action execution, JavaScript execution, or model downloads.
