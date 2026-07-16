# markerPDF encrypted permission top-level boundary current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T103911Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260605T103911Z`
Accepted base: `7f71cfc6116b03249ff3e806369e892ec5de9b31`

## Source Truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. In the no-GPU PHP lane, encrypted PDF handling is a native parser preflight boundary before `pdftext`, OCR, layout, table, equation, or model stages. Standard security-handler permission review must read only real top-level encryption-dictionary `/P` entries. Literal strings, nested dictionaries, arrays, hex strings, and comments are not additional permission-word declarations.

## Behavior

- `PdfMetadataExtractor::dictionaryTopLevelRawValues()` and `dictionaryTopLevelEntries()` now skip non-key PDF tokens as complete literal, array, nested dictionary, hex-string, comment, or scalar tokens before matching top-level names.
- `normalizedDictionaryBody()` still unwraps actual `<<dict>>` values and xref stream `<<dict>> stream` object bodies, but no longer unwraps a stray leading nested dictionary inside an already-unwrapped dictionary body.
- Encrypted Standard permission preflight now ignores malformed decoys before the real top-level `/P -44`, preserving one decoded permission word, no duplicate-permission review, encrypted text blocked until decryption, and no raw authentication material in review output.

## Evidence

Red-first focused run before source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionTopLevelBoundaryCurrentBaseTest.php`

Result: `1 test files, 3 assertions, 1 failures`; malformed literal/dictionary decoys before the real `/P` were reported as `permission_word_duplicate_entries`.

Focused result after source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionTopLevelBoundaryCurrentBaseTest.php`

Result: `1 test files, 260 assertions, 0 failures`.

Regression check for the xref stream normalization path touched by this parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php`

Result: `1 test files, 30 assertions, 0 failures`.

Adjacent encrypted/security sweep:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php`

Result: `25 test files, 3353 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-top-level-boundary-currentbase.php`

Result: exits `0` and emits `declared_permission_entries=1`, `duplicate_permission_entries=false`, `permission_hex=FFFFFFD4`, `text_blocked=true`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false` for literal, nested dictionary, array, hex-string, and comment decoys.

## Non-Overlap

This does not repeat accepted Standard permission bit decoding, unsigned 32-bit normalization, out-of-range `/P`, plus-signed `/P`, duplicate real `/P` entries, malformed direct `/P` operands, indirect/generation-exact `/P` resolution, Standard parameter validation, crypt-filter role/default/AuthEvent/key-length review, public-key recipient envelopes, encrypted associated-file metadata, xref `/Encrypt` precedence, signature/DSS/DocMDP review, or CMap/filter boundary work. The bounded behavior is only token-aware top-level dictionary scanning before encrypted Standard permission preflight.

## Dependency Closure

No new support component is needed. This reuses the native PHP object parser, dictionary value scanner, Standard permission metadata review, security preflight, encrypted text blocking, and WordPress smoke path. Full password validation/decryption, permission enforcement, live OCR, Surya/Texify/Torch model execution, PDFium rendering, Streamlit/FastAPI model workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF direction.
