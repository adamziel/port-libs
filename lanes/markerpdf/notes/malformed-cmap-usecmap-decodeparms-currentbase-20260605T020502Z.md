# markerPDF malformed UseCMap DecodeParms boundary

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T020502Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets searchable-PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, with low-level CMap/font stream decoding delegated to `pdftext`/PDFium before WordPress-visible Markdown text is emitted. Native PHP must therefore treat ToUnicode CMap streams and object-valued `/UseCMap` base CMap streams as ordinary filtered PDF streams whose `/Filter` and `/DecodeParms` operands are validated before text mapping.

## Behavior

`PdfTextExtractor::extractCMapStreamFilterLengthOwnerReview()` now records object-valued CMap `/UseCMap` references as `use_cmap` reference usages and exposes `use_cmap_stream_count`. This makes malformed inherited CMap streams visible in review metadata even when the stream dictionary itself has no top-level `/Type /CMap` or `/CMapName` marker.

The focused fixture keeps visible WordPress text in a valid derived ToUnicode CMap, while its `/UseCMap 7 0 R` base CMap uses `/Filter /FlateDecode` plus malformed `/DecodeParms << /Predictor /Twelve /Columns 1 >>`. The inherited CMap fails closed, is reported with `decodeparms_operand_policy=reject_malformed_decodeparms_parameters`, and its decoded mapping payload does not leak into Gutenberg paragraphs.

## Evidence

Red baseline after adding the test before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
FAIL reviews malformed inherited UseCMap DecodeParms before current-base text extraction
Values are not identical
Expected: 2
Actual: 1

1 test files, 402 assertions, 1 failures
```

Focused green after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed CMap Filter array operands before current-base text extraction
PASS classifies literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect literal CMap Filter operands as malformed before current-base text extraction
PASS classifies selected indirect CMap Filter arrays with dictionary operands before current-base text extraction
PASS rejects current-generation indirect CMap Filter dictionaries instead of stale valid filters
PASS rejects current-generation malformed CMap DecodeParms parameters before ToUnicode decoding
PASS rejects trailing malformed CMap DecodeParms array entries before ToUnicode decoding
PASS reviews malformed inherited UseCMap DecodeParms before current-base text extraction
PASS classifies stale-generation CMap Filter references by the current xref-selected malformed owner

1 test files, 432 assertions, 0 failures
```

Focused assertion delta: `388 -> 432` in `PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php`.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-usecmap-decodeparms-currentbase.php
```

The smoke emits `safe_wordpress_text="UseCMap Safe Import"`, `use_cmap_stream_count=1`, `inherited_decodeparms_policy="reject_malformed_decodeparms_parameters"`, `inherited_reference_usage="use_cmap"`, `inherited_source_object=6`, `malformed_cmap_payload_excluded=true`, and confirms no Python, model, or external PDF tool execution.

## Non-Overlap

This does not repeat accepted malformed direct CMap `/Filter` operands, indirect literal or dictionary filter operands, stale-generation filter helper rejection, direct CMap DecodeParms parameter validation, trailing DecodeParms array validation, CMap stream `/Length`/`Filter` owner review, Type0 CMap width grouping, CMap comments, named `usecmap` inheritance, encrypted PDF preflight, or inline/image stream-filter boundaries.

The bounded behavior is specifically object-valued `/UseCMap` stream reference review plus malformed inherited DecodeParms fail-closed reporting before WordPress text extraction.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, stream dictionary reader, xref-selected object map, stream filter/DecodeParms validator, ToUnicode CMap parser, and WordPress smoke path.

Full upstream markerPDF parity remains intentionally limited by the current no-GPU/no-model scope: live OCR, Surya/Texify/Torch execution, pypdfium/PDFium rendering parity, table/equation model execution, Streamlit/FastAPI workers, and exact upstream model benchmark parity were not run.
