# markerPDF stream filter stack parser-comment reference boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T095745Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, with pdftext/PDFium handling low-level PDF stream decoding before OCR/layout/model stages.
- PDF comments are whitespace. Indirect-reference operands inside stream `/Filter` and `/DecodeParms` stacks can therefore be split as `10 % comment\n 0 R`; native parsing must still resolve the referenced filter name or DecodeParms dictionary before WordPress text extraction.

## Behavior

`PdfTextExtractor` now routes stream filter and DecodeParms indirect-reference operands through the existing tokenized PDF reference scanner instead of local whitespace-only regexes. The updated paths cover:

- top-level `/Filter 12 %...\n 0 R`;
- filter-array entries such as `/Filter [ 10 %...\n 0 R null /FlateDecode ]`;
- top-level `/DecodeParms 13 %...\n 0 R`;
- DecodeParms array entries such as `[ null null 11 %...\n 0 R ]`;
- indirect integer/boolean DecodeParms values.

The focused fixture decodes two page content streams through ASCII85 and Flate predictor stacks while excluding helper-object text from visible WordPress paragraphs. Adjacent broad extractor fixtures were kept aligned with accepted default Identity Crypt behavior by making their unsupported-filter guards use unknown filters or explicitly named private Crypt filters.

## Evidence

Focused stack test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves parser-comment split indirect references inside stream filter stacks
1 test files, 206 assertions, 0 failures
```

Adjacent parser/filter/comment gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserNameArrayCommentBoundaryCurrentBaseTest.php
8 test files, 906 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `parser_comment_split_filter_references_resolved=true`, `parser_comment_split_decodeparms_references_resolved=true`, `parser_comment_split_helper_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with the new visible paragraphs `Comment Split Filter Array`, `Comment Split DecodeParms Applies`, `Top Split Filter Reference`, `Top Split DecodeParms Reference`, and `Visible After Split References`.

## Non-Overlap

This does not repeat accepted ASCII85/Flate stack recovery, stale or short `/Length` recovery, RunLength/LZW EOD recovery, singleton or compact DecodeParms alignment around null filters, all-null stacks, stray DecodeParms handling, indirect null filter objects, identity/default/private Crypt stack semantics, extra DecodeParms fail-closed behavior, fallback trailing-null DecodeParms scans, xref/object-stream filter-chain helper resolution, CMap-specific DecodeParms handling, image-filter exclusion, or inline-image tokenizer boundaries.

The bounded behavior is specifically parser-comment split indirect references inside ordinary page content stream filter stacks and DecodeParms operands.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenized indirect-reference parser, stream dictionary reader, stream filter resolver, DecodeParms parser, predictor decoder, content-token parser, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
