# markerPDF AcroForm XFA signature boundary current-base

Micro-slice: `acroform-xfa-signature-boundary-currentbase-20260602T1408Z`

## Source truth

- Upstream markerPDF source remains `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The relevant boundary is `marker/pdf/extract_text.py::get_text_blocks`, which delegates static PDF text extraction to `pdftext.dictionary_output`, and `marker/convert.py::convert_single_pdf`, which keeps extraction/model stages separate before Markdown cleanup.
- This native slice maps the local PDF object/XFA review boundary needed before WordPress import: XFA packet data can describe form values and signature-like XML, but AcroForm `/FT /Sig` signing state remains sourced from `/V` signature dictionaries only.

## Behavior

- `PdfAcroFormExtractor` now records XFA `data_paths`, `signature_field_names`, `has_signature_field`, and non-executing signature review flags on XFA packets.
- Fields referenced by XFA packet field names or data paths now receive `xfa_boundary` metadata with matched packet names/objects, matched field names, matched data paths, dynamic-value presence, and explicit `value_used_for_import=false`.
- Signature fields additionally mark XFA values as not used for signing, signature validation, or AcroForm `/V` signature state. An unsigned signature field with XFA `approval.signature` data remains unsigned until a real `/V` signature dictionary is present.
- The WordPress smoke emits only review metadata and static AcroForm values; it does not render XFA signature-like data as visible form content, execute XFA JavaScript, validate signatures, sign PDFs, run Python/models, or call external PDF tools.

## Non-overlap

- This does not repeat accepted XFA packet-array extraction, UTF-16 XDP stream decoding, XFA signature widget annotation-state metadata, AcroForm calculation/signature `/CO` and `/Lock` state, signature seed-value `/SV`, FieldMDP/UR3 reference transforms, DSS review, or security preflight byte-range policy.
- The new behavior is limited to linking XFA packet field/data references back to AcroForm fields and keeping XFA signature-like values review-only.

## Verification

- Red-first check: `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` failed the new test on missing `data_paths` metadata.
- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-xfa-signature-boundary-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` passed with 1 file, 608 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with 2 files, 781 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-xfa-signature-boundary-currentbase.php` emitted `xdp_packet_names=[template,datasets,signature]`, `signature_field_names=[approval.signature]`, `matched_signature_data_paths=[approval.signature]`, `static_title_value=Static AcroForm title`, `signature_signed=false`, `xfa_value_used_for_signature=false`, `value_used_for_import=false`, `executes_xfa_javascript=false`, `executes_signature_validation=false`, `executes_signing=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php tools/run-tests.php lanes/markerpdf/tests` passed with 66 files, 4378 assertions, and 0 failures.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser, AcroForm field traversal, stream filter decoder, PDF string/name helpers, and bounded XFA XML review helpers. Full XFA layout rendering, XFA data binding, XFA JavaScript, CMS/PKCS#7 signature validation, signing, trust-chain validation, and external PDF/model execution remain out of scope and would require separate native support components before activation.
