# markerPDF XMP metadata comment-reference boundary

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260607T100611Z`

Base accepted HEAD: `9249a8421a3ff1980e89d00422073eb64b55016c`

## Source truth

Upstream `sddai/markerPDF` at the manifest-pinned commit routes searchable PDF metadata through PDFium/pdftext parser layers before OCR/layout/model stages. PDF comments are whitespace in indirect-object operands, so native catalog `/Metadata` parsing must treat `5 % comment\n0 R` as the same stream reference as `5 0 R` while still rejecting real trailing operands at the document-XMP trust boundary.

## Change

`PdfMetadataExtractor` now tokenizes indirect references with PDF-comment-aware whitespace. The parser preserves the original token span for dictionary offset accounting, but resolves normalized object and generation numbers for catalog `/Metadata`, stream filter/DecodeParms helpers, trailer references, and review metadata.

The focused fixture covers:

- valid catalog `/Metadata 5 % ...\n 0 % ...\n R` promotion to document XMP, with trailer Info preserved;
- malformed catalog `/Metadata 5 % ...\n 0 R 7 0 R` rejection, preserving object `5` and trailing reference object `7` in review metadata;
- visible WordPress text isolation from both promoted XMP values and rejected action-tail operands.

## Red-first evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpCommentReferenceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL promotes catalog XMP metadata when the indirect reference is split by PDF comments
FAIL rejects comment-split catalog Metadata references with trailing operands before XMP promotion
1 test files, 9 assertions, 2 failures
```

After the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpCommentReferenceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS promotes catalog XMP metadata when the indirect reference is split by PDF comments
PASS rejects comment-split catalog Metadata references with trailing operands before XMP promotion
1 test files, 33 assertions, 0 failures
```

Adjacent family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfMetadata.*Test\.php$' | sort)
Focused test run: 62 selected test files (root lock skipped)
62 test files, 3683 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-comment-reference-boundary-currentbase.php
```

Result: exits `0` and emits `promotes_comment_split_xmp=true`, `info_fallback_preserved=true`, `xmp_not_visible_text=true`, `trailing_operand_rejected=true`, `trailing_operand_object_number=5`, `trailing_reference_object_numbers=[7]`, `trailing_action_redacted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted XMP packet/root bounds, null `/Metadata`, duplicate `/Metadata`, direct dictionary, unresolved reference, non-stream metadata object, unreadable stream, omitted `/Type`, duplicate stream type keys, indirect Filter/DecodeParms operand, namespace, generation, encrypted metadata, PieceInfo, OutputIntent, or associated-file XMP slices. The bounded behavior is only PDF-comment-split catalog `/Metadata` indirect references and their trailing-operand accounting.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, dictionary value reader, stream decoder, XMP parser, metadata review builder, text extractor, and WordPress smoke renderer. GPU/OCR/model execution, PDFium rendering, external PDF tools, and live-service provider tests were not run.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and review behavior: stream filter owner boundaries, xref repair, page geometry, fonts/CMaps, attachments, annotations/forms, and supplied-boundary table/equation handoffs.
