# AcroForm Signature XFA Widget Action Bundle Current Base

## Source Truth

- Upstream markerPDF source remains `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/pdf/extract_text.py` delegates page text extraction to `pdftext.extraction.dictionary_output`, and `marker/pdf/images.py::render_image()` renders with annotations disabled. The native port keeps form widgets, XFA packets, signatures, and PDF actions as parser/review metadata rather than visible WordPress paragraphs or executed behavior.
- PDF page `/Annots` arrays are the authoritative page-local annotation order. A terminal signature field may also be a widget dictionary while additional widget annotations appear in `/Kids` or only on page `/Annots`.

## Behavior

- `PdfAcroFormExtractor` now orders widget references by page `/Annots` position when widgets are page-referenced, preserving the PDF parser/PDFium-style page annotation boundary before field-tree fallback order.
- Signature fields now expose `signature_widget_action_bundle` plus `signature_widget_review.action_bundle`.
- The bundle ties together signature state, XFA packet matches, inherited AcroForm `/DA` and `/DR` resources, mixed field/widget dictionaries, page annotation widget order, field/widget action rows, duplicate mixed-dictionary action edges, action target field names, and signature-lock target fields.
- All bundled actions remain review-only: SubmitForm, ResetForm, Launch, URI, GoToR, Hide, JavaScript, signing, validation, XFA JavaScript, Python/model execution, and external PDF tooling are disabled.

## Non-Overlap

This does not repeat the accepted page-only widget attachment slice, XFA packet decoder, signature state review, seed/lock dictionary parsing, action-chain walker, widget appearance extraction, page widget link promotion, or AcroForm submit/reset value reviews. The new behavior is page annotation ordering for mixed signature field/widget dictionaries plus a single review bundle that crosses signature, XFA, widget appearance, and action target metadata.

## Evidence

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionBundleCurrentBaseTest.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-signature-xfa-widget-action-bundle-currentbase.php`
  - `No syntax errors detected`
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-signature-xfa-widget-action-bundle-currentbase.php`
  - emitted page annotation order `[8,6]`, mixed widget `[6]`, submit target `https://example.test/bundle-submit`, unsafe URI `javascript:bundleWidget()`, and all execution flags false.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionBundleCurrentBaseTest.php`
  - `1 test files, 77 assertions, 0 failures`
  - 2 PASS cases added.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormXfaWidgetCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfAcroFormActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSignatureFieldActionStateCurrentBaseTest.php`
  - `7 test files, 1049 assertions, 0 failures`
- `php -r '$path="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($path), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg()."\n"); exit(1); } echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/markerpdf`
  - passed

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, page annotation map, AcroForm field traversal, XFA packet decoder, stream decoder, action review walker, default appearance resource resolver, signature lock review, text extractor, and WordPress smoke path. Full upstream runner parity remains gated by the live Python/model/PDF stack (`pdftext`, `pypdfium2`, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI, and external PDF/OCR tooling).

## Next

Continue AcroForm parity on remaining field-tree edges: radio/signature multi-widget state grouping, inherited widget resource override summaries across nested parent fields, and additional action target resolution boundaries while keeping action execution disabled.
