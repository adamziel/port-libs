# Encrypted Permission Indirect Trailing Operand Current Base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260606T235936Z`

Base: `6d04ff33b7840d32f2f83f995941f5ec6af06983`

## Source Truth

- Upstream markerPDF delegates encrypted/searchable PDF admission to parser-level PDF handling before model/OCR conversion. Under the current no-GPU markerPDF scope, native PHP import must fail closed at the PDF security preflight boundary and must not run decryption, permission enforcement, Python models, external PDF tools, or OCR.
- PDF Standard security `/P` is one permission integer. A helper object referenced by `/P` may contain comments around that integer, but an object body like `-44 /P -64` is not one permission operand. Trusting the first token would silently discard a second top-level permission-looking operand.

## Implemented Behavior

- `PdfMetadataExtractor` now requires resolved dictionary integers to consume one PDF value token after whitespace/comment skipping.
- Standard permission declaration review records `permission_word_trailing_operand_review` when a resolved `/P` value starts with an integer but has another top-level operand.
- `PdfSecurityPreflight` maps that status to the user-visible review reason `permission_word_trailing_operand`.
- WordPress smoke coverage confirms encrypted text stays blocked, decoded permission bits are omitted, and authentication material is not exposed.

## Evidence

Red-first before source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectTrailingOperandCurrentBaseTest.php
FAIL fails closed when indirect Standard permission integer has trailing operands
Expected review reason: permission_word_trailing_operand
Actual review reason: copy_or_extract_allowed_but_decryption_required
1 test files, 3 assertions, 1 failures
```

Focused after source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectTrailingOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when indirect Standard permission integer has trailing operands
1 test files, 65 assertions, 0 failures
```

Adjacent regression check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCommentedIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCompositeOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionTopLevelBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
13 PASS cases
4 test files, 739 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-indirect-trailing-operand-currentbase.php
permission_word_trailing_operand
permissions_malformed_blocked_without_decryption
raw_auth_material_exposed=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat the accepted direct non-integer `/P`, duplicate `/P`, composite `/P`, commented indirect integer, malformed `/Filter`, crypt-filter, metadata, xref, page resource, or OCR/model handoff slices. The new boundary is specifically an indirect Standard `/P` helper object that contains a valid integer followed by an extra top-level operand.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF token reader, indirect-object resolver, encrypted permission preflight, and WordPress smoke harness.
