# markerpdf xfa xdp stream packet review current base

## Source truth

- Upstream markerPDF source remains `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The relevant native boundary is upstream `marker/pdf/extract_text.py::get_text_blocks` and `marker/convert.py::convert_single_pdf`, which delegate PDF text extraction to `pdftext`/`pypdfium2` before downstream layout/OCR/model stages.
- PDF/XFA source truth: the AcroForm interactive form dictionary can carry an `/XFA` entry, and XFA can be an XML stream or an array of packet name / stream pairs. This slice handles the stream form as review metadata only.

## Behavior

- `PdfAcroFormExtractor` now decodes UTF-16BE and UTF-16LE BOM-prefixed `/AcroForm /XFA` stream XML to UTF-8 before packet root, field-name, data-node, and preview inspection.
- Single-stream XDP packages now report `is_xdp_package`, `xml_encoding`, `decoded_to_utf8`, and top-level `xdp_packet_names` such as `template`, `datasets`, and `config`.
- Namespaced XFA child packets such as `xfa:template` and `xfa:datasets` are classified as template/datasets packets without merging dynamic XFA datasets into static AcroForm fields.
- The WordPress smoke renders only packet names, field names, data-node names, and encoding review metadata; it does not render XFA dynamic values as page content.

## Non-overlap

- This does not repeat the accepted XFA packet-array extraction, AcroForm current/default value-state metadata, SubmitForm/ResetForm review metadata, field/widget JavaScript action review, signature seed-value/lock dictionaries, or optional-content behavior.
- This slice is limited to `/XFA` stream XML decoding and XDP packet review metadata.

## Verification

- Red-first check before implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` failed the new UTF-16 XDP stream test with expected packet name `xdp:xdp` and actual `xfa`.
- Post-change focused verification: `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` passed with 1 file, 276 assertions, and 0 failures.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-xfa-xdp-stream-import.php` emitted `xdp:xdp`, `UTF-16BE`, and `template,datasets,config` review metadata with `executes_xfa_javascript=false`.
- Changed PHP lint passed for `PdfAcroFormExtractor.php`, `PdfAcroFormExtractorTest.php`, and `wordpress-pdf-xfa-xdp-stream-import.php`.
- Full markerPDF lane verification passed: `php tools/run-tests.php lanes/markerpdf/tests` reported 58 files, 2360 assertions, and 0 failures.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency closure

No new support component is needed. The slice reuses the native PDF object parser, AcroForm dictionary parser, stream filter decoder, PDF string/name helpers, and bounded regex XML review helpers. Full XFA rendering, form layout reflow, data binding, JavaScript execution, and Python `pdftext`/`pypdfium2`/model execution remain out of scope.
