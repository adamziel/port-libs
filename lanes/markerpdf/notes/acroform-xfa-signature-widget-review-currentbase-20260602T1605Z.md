# markerPDF AcroForm XFA Signature Widget Review Current Base

Micro-slice: `acroform-xfa-signature-widget-review-currentbase-20260602T1605Z`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF extraction at the pdftext/PDFium boundary through `marker/pdf/extract_text.py::get_text_blocks()` and keeps conversion/model stages separate in `marker/convert.py::convert_single_pdf()`.
- This native slice maps the review boundary for PDF AcroForm signature widgets: XFA packet data, `/V` signature dictionaries, widget annotation flags, selected `/AP /N` appearance state, `/SV` seed constraints, `/Lock` scope, and field/widget actions are import review metadata only.

## Behavior

- `PdfAcroFormExtractor` now adds a synthesized `signature_widget_review` block to `/FT /Sig` fields.
- The block summarizes the page-referenced widget object/order/rectangle/visibility, selected appearance state/object/hash, signed `/V` dictionary metadata, XFA packet matches, seed-value constraints, lock scope, action counts, and lock-state effects.
- Fused field/widget dictionaries expose field and widget action source counts, while the combined action count is de-duplicated by action object/trigger/type.
- The review explicitly keeps XFA values, appearance streams, JavaScript actions, signature validation, signing, Python/model execution, and external PDF tools disabled.

## Red-first evidence

Baseline before the new test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php`

Result: `1 test files, 650 assertions, 0 failures`.

The first implementation pass caught the fused field/widget action boundary: the test expected zero field actions, but the same `/AA` action is visible from the fused field dictionary and the widget dictionary. The final review now reports `field_action_count=1`, `widget_action_count=1`, and de-duplicated `action_count=1`.

## Verification

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-xfa-signature-widget-review.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` passed with `1 test files, 740 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-xfa-signature-widget-review.php` emitted `signature_signed=true`, `widget_visibility=no_view`, `widget_appearance_state=Signed`, `selected_appearance_object=50`, `xfa_matched_data_paths=[approval.signature]`, `seed_required_constraints=[filter,subfilter,reason,digest_method]`, `lock_field_names=[article.title]`, `title_locked_by_signature=true`, and all execution flags false.

## Non-overlap

This does not repeat XFA packet extraction, UTF-16 XDP decoding, XFA signature field/data-path boundary, standalone signature seed/lock metadata, signature Reference FieldMDP/UR3 transform review, annotation widget appearance/action review, AcroForm non-JavaScript action review, or security preflight ByteRange policy. The new behavior is the synthesized current-base review block that correlates those already-parsed pieces for AcroForm signature widgets.

## Dependency closure

No new support component is needed. The slice reuses the native PDF object parser, AcroForm field/widget traversal, XFA XML review helpers, signature metadata parser, seed/lock dictionaries, action walker, stream decoder, and appearance-stream review path. Full XFA layout/data binding, XFA JavaScript, CMS/PKCS#7 validation, signing, timestamp/trust-chain validation, appearance rendering, pypdfium/PIL, Python models, and external PDF tools remain out of scope.
