# markerPDF encrypted indirect Filter operand current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T122222Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260608T122222Z`
Base accepted HEAD: `1216d2e660c60a15fb578b6dfd0473fc7e462592`

## Source Truth

Upstream `sddai/markerPDF` delegates native PDF text extraction to `pdftext.extraction.dictionary_output(...)` before model/OCR work, and `marker/convert.py::convert_single_pdf()` consumes those blocks before later processing. For the no-GPU PHP lane, encrypted-document security metadata must therefore be preflighted before searchable text can be imported. PDF security-handler `/Filter` is a scalar dictionary operand; indirect scalar values can appear in real parser inputs, while indirect arrays/dictionaries must stay fail-closed.

## Behavior

`PdfMetadataExtractor` now resolves `/Filter` and `/SubFilter` through a single indirect name/string operand before filling the legacy scalar metadata fields. Unresolved references, composite operands, and trailing operands stay out of those scalar fields and are left to the existing declaration reviews to fail closed.

The focused behavior fixes two preflight edges:

- `/Filter 9 0 R` with object `9 0 obj (Standard) endobj` is now treated like a direct malformed string Standard handler: it resolves to `filter=Standard`, runs Standard parameter review, and fails closed as malformed Standard security-handler parameters.
- `/Filter 9 0 R` with object `9 0 obj [/Standard] endobj` now fails closed through the security-handler declaration review without publishing `filter=9` or treating `9` as an unsupported handler.

No decryption, password validation, permission enforcement, Python/model execution, external PDF tools, or raw credential-byte exposure is introduced.

## Verification

Red-first before implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectFilterOperandCurrentBaseTest.php
```

Failed with 1 test file, 4 assertions, 2 failures: the indirect literal case missed the Standard parameter row, and the indirect composite case reported `filter` as `9`.

Passing after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectFilterOperandCurrentBaseTest.php
```

Passed: 1 test file, 64 assertions, 0 failures.

Adjacent security-handler regression gate:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionFilterOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCompositeFilterHandlerCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateFilterHandlerCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php
```

Passed: 5 test files, 710 assertions, 0 failures.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-indirect-filter-operand-currentbase.php
```

Passed with `literal_filter=Standard`, `literal_permission_source=standard_security_handler_malformed_parameters`, `array_filter=null`, `array_security_handler_declaration_status=malformed_security_handler_filter_entries_review`, `raw_material_exposed=false`, and all execution flags false.

## Non-Overlap

This does not repeat the accepted direct composite `/Filter` operand, duplicate `/Filter`, direct literal/hex `/Filter`, well-formed indirect Standard/public-key operand, encrypted permission-word, authentication material, crypt-filter AuthEvent, or crypt-filter length slices. The new boundary is specifically the resolved indirect security-handler `/Filter` scalar path and the suppression of bogus numeric scalar metadata for indirect composite handlers.

## Dependency Closure

No new support component is needed. This reuses the native PHP dictionary operand scanner, indirect-object resolver, security-handler declaration review, Standard parameter review, security preflight, text extractor, and WordPress smoke path. Full upstream parity remains limited by the current no-GPU scope: no live OCR, Surya/Texify/Torch model execution, PDFium benchmark parity, or external PDF tools were run.
