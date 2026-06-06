# CCITT Fax Non-Terminal Owner Boundary Current Base

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260606T014155Z`

Accepted base: `574bee50882c28fc71f3d812f497f9a400759fcd`

## Behavior

Malformed CCITT Fax DecodeParms with `/EndOfBlock false` can no longer force
the native parser to trust a stale image `/Length` and expose fake nested PDF
objects from the fax payload as page text. When row-terminated ownership cannot
be established because the row DecodeParms are malformed, the parser now falls
back to explicit CCITT terminal markers for stream ownership while keeping the
image review-only and fail-closed for raster decode.

This stays inside the no-GPU markerPDF scope: it improves native PDF stream
filter boundary behavior and does not add OCR/model execution or CCITT raster
decoding.

## Evidence

Red-first before the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 760 assertions, 1 failures`; the new case exposed
`Fake nonterminal invalid owner CCITT leak` between the expected before/after
page text.

After the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 777 assertions, 0 failures`.

Focused WordPress smoke updated:

`php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php`

Expected output includes the existing paragraph import plus metadata flags for
`xobject_nonterminal_invalid_owner_boundary_repaired`,
`xobject_nonterminal_invalid_owner_payload_excluded_from_text`, and
`xobject_nonterminal_invalid_owner_payload_excluded_from_review`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF
tokenizer, stream-boundary recovery, DecodeParms validation, and image review
metadata paths.

## Next

Continue with non-overlapping native markerPDF stream-filter work, especially
other preview-only image filter boundary cases where malformed parameters can
interact with stale stream lengths or fake nested object owners.
