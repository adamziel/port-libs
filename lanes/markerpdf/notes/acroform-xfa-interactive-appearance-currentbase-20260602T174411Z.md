# AcroForm XFA Interactive Appearance Current Base

Micro-slice: `form-acroform-xfa-appearance-currentbase-20260602T174411Z`

Source truth:
- Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text import through PDF text extraction and renders page images with annotation drawing disabled in `marker/pdf/images.py::render_image(... draw_annots=False)`.
- PDF widget `/AP` dictionaries may carry normal (`/N`), rollover (`/R`), and down (`/D`) appearances. For this native PHP lane those interactive appearance streams are review metadata only; the AcroForm `/V` signature dictionary and static field values remain authoritative for import.

Implemented behavior:
- `PdfAcroFormExtractor` now reviews widget `/AP /R` and `/AP /D` state dictionaries selected by `/AS`, alongside the existing normal appearance review.
- Signature widget review summarizes selected rollover/down appearance object IDs and decoded SHA-256 hashes while keeping `interactive_appearance_value_used_for_import`, `interactive_appearance_payload_text_exposed`, `executes_appearance_streams`, `renders_appearances`, `imports_xfa_payload`, `executes_signature_validation`, and `executes_signing` false.
- The WordPress smoke fixture keeps an XFA packet value for `approval.signature`, but the visible/static `article.title` and signature `/V` state remain authoritative.

Red-first evidence:
- Before the extractor change, `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormXfaAppearanceCurrentBaseTest.php` failed with missing `rollover_appearance` and `down_appearance` widget metadata after 20 assertions.

Verification after implementation:
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormXfaAppearanceCurrentBaseTest.php` passed: `1 test files, 50 assertions, 0 failures`.
- Additional lint/focused-family/example/diff verification is recorded in the worker final response.

Non-overlap:
- This slice does not repeat the accepted AcroForm XFA signature widget-state/action review, widget normal appearance/action cycle review, signature field action-state review, calculation order, choice/rich-text submit/reset, or DocMDP/DSS security slices. It adds the missing interactive `/R` and `/D` appearance-state review boundary only.

Dependency closure:
- No new support component is needed. The existing bounded PDF object/dictionary parsing, stream decoding, XFA packet review, and AcroForm appearance stream review helpers are reused.
