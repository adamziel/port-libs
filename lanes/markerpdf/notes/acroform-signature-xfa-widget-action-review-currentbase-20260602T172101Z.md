# markerPDF AcroForm Signature XFA Widget Action Review Current Base

Micro-slice: `acroform-signature-xfa-widget-action-review-currentbase-20260602T172101Z`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF page/text extraction at the `pdftext`/PDFium boundary in `marker/pdf/extract_text.py::get_text_blocks()` and keeps conversion/model stages separate in `marker/convert.py::convert_single_pdf()`.
- The native PHP boundary follows that split: AcroForm `/A`, `/AA`, and `/Next` action dictionaries on signed XFA-backed widgets are review metadata only. Submit/reset/import/hide/URI/JavaScript actions must not submit data, reset values, hide widgets, run JavaScript, validate signatures, sign, or merge XFA data during WordPress import.

## Behavior

- `PdfAcroFormExtractor` now adds `signature_widget_review.action_review` for `/FT /Sig` fields.
- The review summarizes field and widget action rows together with de-duplicated action counts, action types, triggers, sources, action objects, target schemes, submit targets, unsafe URI targets, form-action field names, hide targets, JavaScript/submit/reset/import/hide/remote-GoTo counts, chain safety counters, and explicit non-execution flags.
- Stable flattened `action_types`, `action_triggers`, and `action_safety_labels` remain available on `signature_widget_review` for WordPress import UIs that already read the synthesized widget summary.
- XFA packet data, selected widget appearance, `/V` signature dictionaries, `/Lock`, and field values remain authoritative through the already accepted parsers; this slice only correlates action policy for the signature-widget review surface.

## Red-First Evidence

Initial focused run after adding the new test failed before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionReviewCurrentBaseTest.php`

Result: `Undefined array key "action_review"` and expected source `acroform_xfa_signature_widget_action_review_boundary` was `NULL` after 8 assertions.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionReviewCurrentBaseTest.php` passed with `1 test files, 45 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfAcroFormSignatureFieldActionStateCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormChoiceRichTextSubmitResetCurrentBaseTest.php` passed with `4 test files, 882 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-signature-xfa-widget-action-review-currentbase.php` emitted `action_types=["SubmitForm","ImportData","URI","Hide","JavaScript","ResetForm"]`, `submit_targets=["https://example.test/signed-submit"]`, `unsafe_uri_targets=["javascript:signatureImport()"]`, and all execution flags false.
- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php`, `php -l lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionReviewCurrentBaseTest.php`, and `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-signature-xfa-widget-action-review-currentbase.php` passed.
- `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This does not repeat XFA packet array/stream decoding, UTF-16 XDP handling, XFA field/data-path matching, signature `/V` metadata, seed-value `/SV`, `/Lock`, DocMDP permissions, signature transform references, submit/reset value-state review, widget appearance extraction, widget action cycle walking, or security preflight. The bounded behavior is only the synthesized action-policy summary attached to AcroForm signature widget review metadata.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, AcroForm field/widget traversal, stream decoder, XFA XML review helpers, signature metadata parser, action walker, field-target resolver, and selected widget appearance review path. Full XFA layout/data binding, XFA JavaScript, PDF action execution, CMS/PKCS#7 validation, signing, timestamp/trust-chain validation, pypdfium/PIL rendering, Python models, and external PDF tools remain out of scope.
