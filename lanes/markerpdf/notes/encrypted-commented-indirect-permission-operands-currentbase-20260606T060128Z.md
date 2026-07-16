# markerPDF encrypted commented indirect permission operands current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260606T060128Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260606T060128Z`
Base accepted HEAD: `cbe94735952446b9228cc760180b01c8a74b619b`

## Source Truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
`marker/pdf/extract_text.py::get_text_blocks()` delegates searchable-PDF text to
`pdftext.extraction.dictionary_output(...)`, and `marker/convert.py::convert_single_pdf()`
consumes parser/PDFium/pdftext output before OCR/layout/model work. In the current
no-GPU PHP lane, encrypted PDF admission is therefore a native parser preflight
boundary: report encryption and permission metadata, block visible text before
decryption, and do not validate passwords, enforce permissions, execute PDF actions,
launch Python/model workers, or call external PDF tools.

PDF comments are lexical whitespace. Standard encryption dictionary scalar operands
can be indirect objects whose resolved object body contains leading or trailing PDF
comments around the scalar token. The native preflight must strip those comments and
parse the first value token before deciding whether `/V`, `/R`, `/Length`, `/P`,
`/EncryptMetadata`, or crypt-filter `/Length` are well-formed.

## Behavior

`PdfMetadataExtractor` now normalizes resolved scalar PDF values through a bounded
`firstPdfValueToken()` helper before interpreting selected Standard security-handler
integers, booleans, authentication strings, EncryptMetadata declarations, and
permission-word declarations. The helper strips PDF whitespace/comments and reads
only the first PDF value token, so trailing comments do not make otherwise scalar
indirect operands look malformed.

The new fixture keeps encrypted page content blocked, resolves the commented indirect
Standard operands as version 4, revision 4, key length 128 bits, `/P -44`, explicit
`/EncryptMetadata true`, and `StdCF` crypt-filter key length 16 bytes. Permission
metadata reports copy/extract as allowed only after decryption is available; native
text extraction remains blocked now, and raw page/authentication bytes are not exposed.

## Red / Green Evidence

Red-first before source edits:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCommentedIndirectOperandsCurrentBaseTest.php
```

Result: `1 test files, 3 assertions, 1 failures`; the encrypted preflight reported
`standard_security_handler_parameters_malformed` instead of resolving commented
indirect Standard scalar operands.

Focused passing command after source edits:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCommentedIndirectOperandsCurrentBaseTest.php
```

Result: `1 test files, 67 assertions, 0 failures`.

Adjacent encrypted-permission/security regression command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Result: `42 test files, 3920 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-commented-indirect-operands-currentbase.php
```

Result: emitted `permission_source=standard_security_handler_permissions`,
`permission_policy=copy_extract_allowed_after_decryption`,
`content_extraction_boundary=blocked_until_decryption_password_available`,
`permission_hex=FFFFFFD4`, `copy_or_extract_allowed=true`,
`permission_word_status=well_formed_standard_permissions`,
`standard_parameters_well_formed=true`, `key_length_bits=128`,
`encrypt_metadata_status=well_formed_encrypt_metadata_boolean`,
`crypt_filter_key_length_bytes=16`, `raw_auth_material_exposed=false`,
`executes_decryption=false`, `executes_permission_enforcement=false`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax/diff checks:

```bash
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfEncryptedPermissionCommentedIndirectOperandsCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-commented-indirect-operands-currentbase.php
git diff --check -- lanes/markerpdf
```

Passed.

## Non-Overlap

This does not repeat accepted encrypted text blocking, direct Standard permission-bit
decoding, unsigned/out-of-range `/P`, plus-signed integers, missing `/P`, duplicate
or selected-entry `/P` handling, direct composite `/P` operands, malformed reserved
bits, duplicate Standard handler parameters, explicit malformed parameter operands,
indirect operands without PDF comments, generation-specific auth material, escaped
auth keys, `/Perms` digest operand review, duplicate authentication material,
EncryptMetadata duplicate handling, crypt-filter default/method/AuthEvent/key-length
checks without comments, public-key recipient envelopes, encrypted attachment
redaction, trailer `/Encrypt` precedence, DSS/signature/DocMDP review, OCR/model
execution, or stream-filter `/Crypt` DecodeParms behavior. The bounded new behavior
is comment stripping around resolved indirect scalar operands before Standard
security-handler and permission preflight interpretation.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner,
comment-aware token reader, dictionary parser, Standard permission-word decoder,
metadata extractor, security preflight, text extractor, and WordPress smoke path.
Full Standard-handler decryption, password validation, permission enforcement,
public-key CMS/PKCS#7 decoding, live `pdftext`, PDFium/pypdfium rendering, Surya,
Texify, Torch/model execution, tabled-pdf, Streamlit/FastAPI workers, benchmark
runners, and external OCR/rendering helpers remain intentionally out of scope for
this no-GPU markerPDF slice.

## Follow-Up

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and
converter behavior: fonts, CMaps, stream filters, xref repair, metadata, annotations,
forms, page geometry, image/filter metadata, and supplied-boundary table/equation
handoffs.
