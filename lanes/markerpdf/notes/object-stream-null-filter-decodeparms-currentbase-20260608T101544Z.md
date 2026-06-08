# Object Stream Null Filter DecodeParms Current Base

Slice: `markerpdf-object-stream-xref-parser-current-base-20260608T101544Z`

Base accepted HEAD: `6627fd3fe2124af1f38c317937d8d573f3055520`

## Source Truth

- PDF object streams use a stream filter stack to decode the compressed member table before xref type-2 entries can select compressed indirect objects.
- Stream filter arrays may contain `null` placeholders. DecodeParms arrays align by filter slot, so parameters aligned with a `null` filter do not belong to a real decoder.
- The native parser already applies this null-filter DecodeParms alignment for ordinary page/content streams; this patch carries the same bounded behavior into object-stream member expansion.

## Patch

- `PdfTextExtractor::decodedObjectStreamMemberTable()` now calls the existing stream decoder with null-filter DecodeParms alignment enabled.
- The change remains object-stream scoped. Malformed or unresolved DecodeParms entries aligned to real filters still fail closed through the existing stream-filter boundary tests.
- Added a focused xref-stream fixture where object 4 is a compressed page dictionary inside an `/ObjStm` with `/Filter [ null /FlateDecode ]` and `/DecodeParms [ 99 0 R null ]`.
- Added a WordPress smoke showing the compressed page imports as Gutenberg paragraphs while the unresolved DecodeParms token does not leak into visible text.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamNullFilterDecodeParmsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores DecodeParms entries aligned to null object-stream filter slots before expanding xref members
Values are not identical
Expected: array (
  0 => 'Direct null-filter guard page',
  1 => 'Current null-filter object-stream page',
  2 => 'Null DecodeParms slot ignored',
)
Actual: array (
  0 => 'Direct null-filter guard page',
)

1 test files, 1 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamNullFilterDecodeParmsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores DecodeParms entries aligned to null object-stream filter slots before expanding xref members

1 test files, 21 assertions, 0 failures
```

Adjacent object-stream/filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 57 selected test files (root lock skipped)
...
57 test files, 1714 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-null-filter-decodeparms-currentbase.php --self-test
exit 0; marker summary reports object_stream_page_visible=true, null_decodeparms_slot_ignored=true, compressed_entry_count=1, strict_dependency_rejection_count=0, object_stream_carrier_has_filter=true, executes_python_or_models=false, executes_external_pdf_tools=false.
```

## Dependency Closure

No new support component is needed. This reuses the existing native PHP stream decoder, xref-stream parser, object-stream expander, and FlateDecode support under `lanes/markerpdf/src`.

No GPU/model execution, OCR, raster rendering, external PDF tools, live service calls, or PDF action execution were used or added.

## Non-Overlap

This patch does not change CMap/font handling, page geometry, annotations, attachments, metadata extraction, encryption preflight, xref `/Prev` precedence, nested object-stream filter rejection, or normal content-stream DecodeParms failure policy. It is limited to object-stream member table decoding when DecodeParms entries are aligned to `null` filter placeholders.
