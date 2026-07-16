# markerPDF parser xref-stream indirect W/Index current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260602T230009Z`
Session: `port-dev-markerpdf-object-xref-20260602T230009Z`
Base accepted HEAD: `1c11c94b45001e6d7041475e1155fe1067d73191`

## Source Truth

Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page extraction through `marker/pdf/extract_text.py`, where `get_text_blocks()` delegates low-level PDF text dictionaries to `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` delegates fallback text extraction to pypdfium. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

That makes xref-stream dictionary decoding a native parser boundary for this PHP lane. For PDF 1.5 object streams, `/W` defines field widths, `/Index` maps decoded rows to object-number ranges, and type-2 rows select compressed object members by object-stream carrier and member index. Current indirect dictionary operands must resolve before stale direct/fallback object-stream expansion can own visible page text.

## Behavior

`PdfTextExtractor::xrefStreamEntriesFromDefinition()` now resolves xref-stream `/W`, `/Index`, and `/Size` operands through current indirect helper objects before it parses decoded rows. The same current-object operand-owner scan that already protected `/Length`, `/Filter`, and `/DecodeParms` now includes `W`, `Index`, and `Size`, so helper values from the current section can override stale same-number fallback objects.

The focused fixture builds a current xref stream with `/W 30 0 R`, `/Index 31 0 R`, and `/Size 32 0 R`. Its type-2 row selects page object `4` from object stream `6`. A later stale object stream `7` also contains object `4`; without indirect `/W` and `/Index` resolution, the parser misses the current type-2 row and fallback expansion leaks stale page text. After the fix, the xref-selected carrier wins and WordPress paragraphs contain only current page text.

## Evidence

Red-first focused test before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect xref-stream W and Index arrays before object-stream current-base selection
Values are not identical
Expected: ['Current indirect xref array page', 'Indirect W Index selected']
Actual: ['Stale indirect xref array leak', 'Fallback object stream expanded']

1 test files, 1 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect xref-stream W and Index arrays before object-stream current-base selection

1 test files, 18 assertions, 0 failures
```

Adjacent parser/xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamGenerationOffsetOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 798 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-xref-stream-indirect-index-width-currentbase.php
```

The smoke emits review metadata with `uses_current_indirect_xref_array_page=true`, `indirect_w_index_selected=true`, `excludes_stale_fallback_object_stream=true`, `compressed_entry_count=1`, `object_stream_owner_policy=xref_selected_object_stream_carrier`, and `selection_policy=explicit_member_index`, followed by Gutenberg paragraphs for the current page only.

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-xref-stream-indirect-index-width-currentbase.php
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
passed
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted xref-stream `/Prev` repair, malformed sparse `/Index` offset repair, zero-width `/W` generation repair, invalid explicit-offset rejection, hybrid `/XRefStm` carrier generation review, xref-stream indirect `/Filter` and `/DecodeParms` owner recovery, direct stream `/Length` owner scanning, object-stream `/N` and `/First` helper resolution, or object-stream member-index zero-width recovery.

The bounded behavior here is specifically indirect `/W`, `/Index`, and `/Size` operand resolution for the latest xref stream before type-2 compressed object-stream page selection.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, current object-stream helper expansion, xref-stream decoder, object-stream expander, page-tree walker, content-token extractor, review metadata path, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, pypdfium/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers.
