# markerpdf encrypted permissions crypt-filter parameter trailing-operand current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T044354Z`

## Source truth

- Upstream `sddai/markerPDF` delegates encrypted PDF text extraction to parser/security dependencies before OCR, layout, and Markdown stages. This native PHP lane keeps encrypted content fail-closed unless decryption is explicitly available.
- PDF encryption dictionaries with crypt filters use `/CF` dictionaries whose `/CFM`, `/AuthEvent`, and `/Length` values are security-sensitive scalar operands. A scalar followed by another top-level operand is ambiguous, so native preflight must preserve that ambiguity instead of silently accepting the first token.

## Implementation

- `PdfMetadataExtractor::cryptFilterParameterDeclarationReview()` now uses token-aware top-level value reviews for `/CFM`, `/AuthEvent`, and `/Length`.
- Single crypt-filter parameter entries with trailing top-level operands are recorded as `crypt_filter_parameter_trailing_operand_review` with operand shape, preview, and indirect-reference metadata.
- Existing crypt-filter policy mapping already treats malformed parameter declarations as fail-closed, so the new declaration metadata drives `malformed_crypt_filter_parameter_entry_fail_closed`, `blocked_by_malformed_document_crypt_filter_parameter`, and encrypted text suppression without adding a decryption path.

## Focused test and smoke evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterParameterTrailingOperandCurrentBaseTest.php
```

Failed before the implementation with no `parameter_declaration_review` for the tailed crypt-filter parameters, after `0` assertions.

After implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterParameterTrailingOperandCurrentBaseTest.php
```

Passed with `1 test files, 274 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-crypt-filter-parameter-trailing-operand-currentbase.php
```

Reports encrypted text blocked, permission policy `copy_extract_allowed_but_crypt_filter_preflight_blocked`, content boundary `blocked_by_malformed_document_crypt_filter_parameter`, malformed parameter name `CFM`, trailing operand shape `indirect_reference`, no raw owner/user key exposure, no visible content leakage, and no Python/model/external PDF tool execution.

## Non-overlap

This does not repeat accepted encrypted fail-closed extraction, `/P` permission word parsing, Standard `/Length` trailing-operand review, duplicate/malformed Standard handler parameter review, duplicate crypt-filter parameter review, non-scalar crypt-filter parameter review, crypt-filter role trailing-operand review, default `/StmF`/`StrF`/`EFF` role handling, explicit `/None` handling, unsupported crypt-filter method review, AES generation/key-length checks, public-key recipient permission review, xref `/Encrypt` precedence, encrypted associated-file redaction, or signature ByteRange/DSS/DocMDP/FieldMDP review. The bounded behavior is only trailing top-level operands after scalar crypt-filter parameters inside `/CF` dictionaries.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, dictionary value reviewer, encryption metadata extractor, crypt-filter preflight review, and WordPress smoke renderer. Full decryption/password validation, permission enforcement, live OCR, Surya/Texify/Torch models, PDFium rendering, and exact upstream model benchmark parity remain intentionally out of scope for the current no-GPU markerPDF lane.
