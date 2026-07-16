# markerPDF AcroForm XFA Signature Widget State Boundaries

Micro-slice: `acroform-xfa-signature-widget-state-boundaries-20260602T082211Z`

## Source truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text and page geometry through `marker/pdf/extract_text.py::get_text_blocks()` and `pdftext.extraction.dictionary_output()` before Markdown/block cleanup.
- `marker/convert.py::convert_single_pdf()` keeps PDF parser output, document metadata, image rendering, layout, OCR, and cleanup as separate stages. This native slice preserves that boundary: XFA packet data, signature dictionaries, and widget annotation state are review metadata, not executed form state or visible page text.
- PDF parser behavior: `/AcroForm /XFA` may be a stream or packet array, signature fields can be fused with `/Subtype /Widget`, and widget annotation `/F` flags plus `/AS` appearance state describe review visibility separately from the signature `/V` dictionary.

## Behavior

`PdfAcroFormExtractor` now exposes widget annotation-state boundaries for AcroForm widgets:

- decoded `/F` annotation flag names;
- `annotation_visibility`, `visible`, `hidden`, `printable`, and `no_view`;
- page annotation order via `page_annotation_index`;
- whether the widget was reached from page `/Annots`.

The focused fixture combines a UTF-16 XDP `/XFA` stream with a fused signature field/widget. The XFA dynamic value remains packet metadata, the static AcroForm text field stays unchanged, and the signature widget reports `/F 36` as printable but `no_view`, with `/AS /Signed` and `/AP /N` states preserved for review.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` passed: 1 file, 348 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-xfa-signature-widget-state.php` emitted `xfa_packet_root=xdp:xdp`, `widget_visibility=no_view`, `widget_flags=["print","no_view"]`, `widget_appearance_state=Signed`, and no signing/XFA/action/external execution.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, AcroForm parser, stream decoder, PDF string/name helpers, and bounded XML review helpers. Full XFA rendering, data binding, signature validation/signing, form action execution, Python `pdftext`/`pypdfium2`, and model execution remain out of scope.
