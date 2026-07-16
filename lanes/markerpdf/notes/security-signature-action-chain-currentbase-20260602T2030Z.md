# security-signature-action-chain-currentbase

Session: port-dev-markerpdf-security47-20260602T2030Z
Base accepted HEAD: 2bf77cd5f648f9f608014de847ea7b020b711784

## Source Truth

- Upstream markerPDF pinned source `sddai/markerPDF@da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF text extraction in `marker/pdf/extract_text.py`, delegating structured text extraction to `pdftext.extraction.dictionary_output(...)` and fallback text extraction to `pypdfium2` rather than executing PDF actions.
- Upstream `marker/convert.py::convert_single_pdf()` consumes those text blocks before OCR/model/table/rendering paths, so action dictionaries are security review metadata for imports and must not become visible paragraph text.
- PDF actions can be direct dictionaries on annotations, pages, fields, widgets, catalog OpenAction, or nested through `/Next`; a signature ByteRange review for a direct action dictionary needs the owning container object span when there is no separate indirect action object.

Upstream references used:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py
- ISO 32000-1 action and signature ByteRange semantics as implemented by existing markerPDF lane security preflight review code.

## Behavior Added

`PdfSecurityPreflight` now annotates document action rows with `action_container_object` and `action_container_source`, choosing the owning annotation, widget, field, page, or catalog object when an action dictionary is direct. Signature ByteRange review now falls back from `action_object` to that container object, records `action_byte_range_review_object` and `action_byte_range_review_source`, and counts post-signature inline `/A` plus `/Next` action chains as unsigned actions when the container was appended after the signed revision.

The focused fixture appends annotation object `90 0 R` after a signed ByteRange. The annotation holds an inline safe URI action, an inline `/Launch` action, and an inline `javascript:` URI action through `/Next`. The visible import text remains `Signed inline action chain import`; unsafe action operands and signature bytes stay out of extracted paragraphs and JSON output.

## Evidence

- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php`
  Result: no syntax errors detected.
- `php -l lanes/markerpdf/tests/PdfSecuritySignatureActionChainCurrentBaseTest.php`
  Result: no syntax errors detected.
- `php -l lanes/markerpdf/examples/wordpress-pdf-security-signature-action-chain-currentbase.php`
  Result: no syntax errors detected.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecuritySignatureActionChainCurrentBaseTest.php`
  Result: 1 test file, 72 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecuritySignatureActionChainCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssActionByteRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityCertPermissionOpenActionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php`
  Result: 6 test files, 825 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfJavaScriptActionInspectorTest.php`
  Result: 4 test files, 1346 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-security-signature-action-chain-currentbase.php`
  Result: smoke passed; decision `review_required_signature_boundary`, 3 post-signature actions, post-signature action object `[90]`.
- `git diff --check -- lanes/markerpdf`
  Result: passed.

Status delta: markerPDF behavior tests move 781 -> 783 pass / 0 fail. Mapped semantics move 555 -> 556 / 78.

## Non-Overlap

This slice does not repeat accepted DSS indirect action ByteRange review, certificate permission OpenAction review, Launch/URI permission review, AcroForm permission-action review, JavaScript action cycle inspection, rich-media/media annotation metadata, or xref/object-stream repair. The bounded behavior here is direct inline action-chain review through the owning annotation container object after a signed revision.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF parser, annotation/action inspection, security preflight, and text extraction surfaces. It does not require Python, pdftext, pypdfium2, models, decryption, cryptographic signature validation, raster engines, or external PDF tools.
