# markerPDF encrypted plus-signed permission preflight

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T054616Z`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `pdftext.extraction.dictionary_output(...)` and pypdfium-style page text extraction before conversion. This native lane keeps encrypted document text blocked until a separate native decryption component exists: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF numeric object syntax permits integer tokens with an optional sign, such as `+17` and `-98`. Object numbers/generation numbers remain stricter and are not changed here: https://opensource.adobe.com/dc-acrobat-sdk-docs/standards/pdfstandards/pdf/PDF32000_2008.pdf
- The PDF Standard security handler stores `/V`, `/R`, `/Length`, and `/P` as integer operands. `/P` permission bits are review metadata only in this lane; they do not authorize immediate import without decryption.

## Implemented

- `PdfMetadataExtractor` now accepts plus-signed integer tokens for dictionary integer operands.
- Standard encryption dictionaries with `/V +4`, `/R +4`, `/Length +128`, and `/P +4294967252` now parse as well-formed Standard handler metadata.
- Plus-signed unsigned `/P` values normalize to the same signed 32-bit permission word as unsigned decimal values:
  - declared `4294967252`;
  - signed `-44`;
  - unsigned `4294967252`;
  - hex `FFFFFFD4`;
  - copy/extract permission review allowed after decryption.
- Encrypted page text and owner/user authentication material remain excluded from visible text and serialized review output.

## Non-overlap

This does not repeat accepted unsigned decimal `/P` normalization, duplicate `/P` fail-closed review, non-integer/unresolved `/P` token review, out-of-range permission words, indirect encryption operands, crypt-filter content-role review, public-key recipient envelopes, authentication digest review, encrypted metadata source priority, signature/DocMDP/DSS review, or decryption.

The new boundary is only plus-signed integer parsing for Standard encryption permission preflight.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPlusIntegerCurrentBaseTest.php` failed on current base with review reason `standard_security_handler_parameters_malformed` because plus-signed `/V`, `/R`, and `/Length` were not parsed as integers.
- Focused test after implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPlusIntegerCurrentBaseTest.php` passed with `1 test files, 53 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-encrypted-plus-permission-preflight-currentbase.php` passed and emitted `encrypted_text_blocked=true`, `standard_parameters_well_formed=true`, `permission_hex=FFFFFFD4`, `copy_or_extract_allowed=true`, `permission_bits_reliable=true`, `raw_material_exposed=false`, and execution flags false.

## Status delta

- Focused markerPDF behavior tests move `1487 -> 1488 pass / 0 fail`.
- WordPress scenarios move `1394 -> 1395`.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser, encryption dictionary parser, Standard permission review, security preflight, and encrypted text fail-closed gate.

Full Standard-handler decryption, password validation, permission authentication from decrypted `/Perms`, public-key CMS parsing, signature validation, permission enforcement, Python/pdftext/pypdfium execution, models, and external PDF tools remain out of scope for this no-GPU markerPDF lane.
