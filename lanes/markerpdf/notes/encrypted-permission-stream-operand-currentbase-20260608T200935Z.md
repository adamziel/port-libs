# markerpdf encrypted permission stream operand current-base slice

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T200330Z`

Accepted base: `04e99b68d5dc6e073f4bb0aa436e72dabb16d510`

## Source-truth boundary

PDF Standard security-handler permission word `/P` is a scalar integer entry in
the encryption dictionary. If `/P` points at an indirect stream object, the
stream container is not a scalar permission word and its payload must not be
used to grant copy/extract permissions before decryption or password
authentication.

This slice keeps the no-GPU markerPDF scope: native searchable-PDF security
preflight only, with no Python, OCR, model, raster, pypdfium/PIL, or external
PDF tool execution.

## Change

`PdfMetadataExtractor` now detects resolved stream containers while reviewing
Standard `/P` permission operands and records
`permission_word_stream_operand_review` with
`selected_entry_stream_container=true`. `PdfSecurityPreflight` maps that status
to the review reason `permission_word_stream_operand`.

WordPress imports still block encrypted visible text and raw Standard
authentication bytes, and they do not expose stream payload bytes as decoded
permission bits.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionStreamOperandCurrentBaseTest.php`

Failed before implementation with `1 test files, 3 assertions, 1 failures`
because the stream-backed `/P` operand reported
`permission_word_trailing_operand` instead of
`permission_word_stream_operand`.

Green:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionStreamOperandCurrentBaseTest.php`

`1 test files, 71 assertions, 0 failures`

Adjacent operand regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionStreamOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCompositeOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectTrailingOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionWordGenerationOperandCurrentBaseTest.php`

`4 test files, 294 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-encrypted-stream-permission-operand-currentbase.php`

Exits 0 and reports `encrypted_text_blocked=true`,
`permission_word_stream_operand`, `selected_entry_stream_container=true`,
`permission_bits_decoded=false`, `executes_decryption=false`,
`executes_permission_enforcement=false`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the existing native PDF object
resolver, stream-object boundary detection, metadata extractor, security
preflight report, PHP focused test runner, and local WordPress smoke path.
