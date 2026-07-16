# CCITT Fax Indirect Height Row Boundary Current Base

Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T184454Z`

Accepted base: `7639a657de450051d770a4d1b4b5bc75b5240c02`

## Source Truth

Upstream markerPDF keeps PDF image extraction separate from searchable text extraction: CCITTFaxDecode image bytes are image data, not page text. In the native no-GPU PHP port, CCITT stream-owner recovery must therefore reject stale `endstream` markers embedded inside fax bytes before the object table can expose nested fake objects as visible WordPress paragraphs.

This slice extends the existing current-base CCITT row-EOL ownership behavior. When `/DecodeParms` sets `/EndOfBlock false` and omits `/Rows`, the parser already uses the image dictionary `/Height` as the row count. The new boundary is that `/Height` may be an indirect numeric operand available before the image stream. The direct-object stream scanner now preloads safe `/Height` helper objects, and the CCITT owner-boundary checks resolve `/Height` through the object table before counting row EOL markers.

## Evidence

Red-first focused run after adding the regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 553 assertions, 1 failures`

Failure: `resolves indirect image Height as CCITT row count before row EOL stream ownership` leaked `Fake indirect-height CCITT owner leak` into extracted text.

After the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 574 assertions, 0 failures`

WordPress smoke path updated:

`php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php`

Observed emitted fields include `xobject_indirect_height_row_eol_boundary_repaired=true`, `xobject_indirect_height_row_eol_height=2`, `xobject_indirect_height_row_eol_effective_height=2`, `xobject_indirect_height_row_eol_height_source=image_dictionary`, `xobject_indirect_height_row_eol_payload_excluded_from_review=true`, and `xobject_indirect_height_row_eol_payload_excluded_from_text=true`.

## Non-Overlap

This does not repeat the accepted direct `/Height` row-count fallback, inline `/H` fallback, null-filter DecodeParms alignment, malformed/unresolved DecodeParms fail-closed handling, direct EOFB/RTC ownership, or Flate/Crypt-wrapped CCITT owner-boundary slices. The new behavior is specifically indirect `/Height` resolution during direct stream-owner recovery before fake object headers inside CCITT row data can enter the object table.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP PDF dictionary/object-reference parsing, safe direct helper-object lookup, and CCITT metadata review paths. No OCR, Surya, Texify, Torch, Python, pypdfium, external PDF tooling, or model execution is required.

## Next Task

Continue with non-overlapping native markerPDF stream/filter work, especially unsupported image/filter metadata boundaries, xref repair, CMaps/fonts, annotations/forms/security preflight, and supplied-boundary table/equation handoffs under the no-GPU scope.
