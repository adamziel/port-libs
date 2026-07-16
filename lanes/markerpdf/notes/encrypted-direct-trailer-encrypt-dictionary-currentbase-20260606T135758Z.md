# markerPDF encrypted direct trailer Encrypt dictionary current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260606T135758Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260606T135758Z`
Base accepted HEAD: `7cab681ac262f77d28f83c3f5e2d54da93e01472`

## Source Truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text extraction to `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` uses pypdfium page text extraction. In this native PHP lane, a selected trailer `/Encrypt` entry is therefore a parser/security preflight boundary that must fail closed before page stream text is exposed.

PDF trailer `/Encrypt` can be the document encryption dictionary itself, not only an indirect reference. This slice covers that direct-dictionary boundary for the selected trailer.

## Behavior

Before this slice, `PdfTextExtractor::topLevelPdfEncryptValueAfterName()` normalized an already-extracted trailer dictionary body through the generic object-body helper. When `/Encrypt` was a direct dictionary, the first nested `<< ... >>` was mistaken for the outer dictionary body, so the encryption sentinel was missed and native text extraction continued into encrypted content streams.

`PdfTextExtractor` now checks the selected dictionary body for top-level `/Encrypt` entries before falling back to generic object-body parsing. `PdfMetadataExtractor` also records resolved encryption operand provenance for successful entries, so `PdfSecurityPreflight` can report direct dictionary resolution alongside Standard permission policy metadata.

The WordPress import boundary now blocks plaintext from a direct trailer encryption dictionary while retaining review-only fields such as permission hex `FFFFFFD4`, `copy_extract_allowed_after_decryption`, and `blocked_until_decryption_password_available`.

## Verification

Red-first before implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDirectEncryptDictionaryCurrentBaseTest.php
```

Failed with both focused cases leaking `Direct trailer Encrypt encrypted text leak` through `PdfTextExtractor`.

Passing after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDirectEncryptDictionaryCurrentBaseTest.php
```

Passed: 1 test file, 63 assertions, 0 failures.

Adjacent encrypted/security/text extractor gate:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*Test.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Passed: 47 test files, 5063 assertions, 0 failures.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-direct-encrypt-dictionary-currentbase.php
```

Passed with `encrypted_text_blocked=true`, `encrypt_dictionary_resolved=true`, `encrypt_operand_shape=dictionary`, `encrypt_operand_status=encrypt_dictionary_direct_dictionary_resolved`, `permission_policy=copy_extract_allowed_after_decryption`, `raw_key_material_exposed=false`, `executes_decryption=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax/diff checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfEncryptedPermissionDirectEncryptDictionaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-direct-encrypt-dictionary-currentbase.php
git diff --check -- lanes/markerpdf
```

Passed.

## Non-Overlap

This does not repeat accepted Standard `/P` decoding, unsigned/out-of-range `/P`, duplicate `/P`, missing `/P`, reserved-bit review, indirect `/Encrypt` references, duplicate trailer `/Encrypt`, malformed unresolved trailer `/Encrypt`, crypt-filter role/parameter handling, public-key recipient review, authentication material readiness, xref `/Prev` encryption inheritance, signature/DSS review, encrypted attachments, or stream-filter `/Crypt` DecodeParms behavior. The bounded behavior is only a selected trailer `/Encrypt << ... >>` direct dictionary that must block native text extraction and feed Standard permission preflight.

## Dependency Closure

No new support component is needed. This reuses native PHP trailer scanning, PDF dictionary parsing, encryption metadata extraction, security preflight, text extraction blocking, and the WordPress smoke path. Password validation, encrypted string/stream decryption, permission enforcement, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane.
