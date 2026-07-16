# XMP Indirect Object Boundary Current Base - 2026-06-08

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T131058Z`  
Base accepted HEAD: `eaf19e1f6617047d412ce09c461d8bd2634185f2`

## Behavior

Catalog `/Metadata` now records a distinct fail-closed review when the selected
indirect object is not a PDF stream object, including scalar nested-reference
wrappers and array wrappers. The native parser does not chase those wrapper
objects into hidden `/Type /Metadata /Subtype /XML` streams, so document XMP
promotion falls back to trailer `/Info`, and wrapper action references remain
review-only.

This maps markerPDF's searchable-PDF metadata boundary before OCR/model stages:
root document XMP is only promoted when Catalog `/Metadata` directly targets a
metadata XML stream object.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpIndirectObjectBoundaryCurrentBaseTest.php`

Result: `1 test files, 13 assertions, 2 failures`.

Both focused cases already suppressed hidden XMP, but reported wrapper objects
as `unreadable_metadata_stream` instead of preserving the non-stream object
boundary and referenced object numbers.

## Verification

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpIndirectObjectBoundaryCurrentBaseTest.php`

Result: `1 test files, 36 assertions, 0 failures`.

Focused metadata/XMP family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php $(rg --files lanes/markerpdf/tests | rg '/PdfMetadataXmp.*CurrentBaseTest\.php$' | sort)`

Result: `66 test files, 3922 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-indirect-object-boundary-currentbase.php`

Result: passed. The smoke reports `review_status="rejected_non_stream_metadata_object"`,
`object_value_type="array"`, `referenced_object_numbers=[6,7]`,
`hidden_xmp_not_promoted=true`, `hidden_xmp_not_visible_text=true`, and
`action_tail_not_promoted=true`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `3107 -> 3109` from 2 new focused TestRunner PASS cases.
- `wordpressScenarios`: `2560 -> 2561` from the new WordPress smoke.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` null, duplicate-key,
direct-dictionary, unresolved-reference, unreadable stream, missing stream
`/Type`, duplicate stream role, tailed role operand, comment-split reference,
stream-object tail, filter/DecodeParms/Length operand, XMP packet/root,
XMP property parsing, encrypted metadata, OutputIntent, PieceInfo, attachment,
xref, annotation, form, image/filter, OCR, or model-execution slices. The
bounded behavior is only non-stream indirect objects selected by Catalog
`/Metadata`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary
scanner, indirect-reference tokenizer, stream decoder boundary, XMP parser,
metadata review summarizer, text extractor, and WordPress smoke harness. No
Python, PDFium, OCR, Surya, Texify, Torch, external PDF tools, live services,
or model workers were run.
