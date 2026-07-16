# Xref Stream Indirect Prev Offset Repair Current Base

Slice: `markerpdf-object-stream-xref-parser-current-base-20260604T234945Z`
Base: `3f016d499e25b6ded3fd2ec3d7582fc58a9b72ec`

## Scope

This patch stays inside the native no-GPU markerPDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, model workers, or external PDF tools.

The bounded behavior is current xref-stream row repair when `/Prev` is an indirect integer helper recovered from a compressed object stream. The parser already used `/Prev` to repair damaged same-generation current xref rows, and it already followed indirect `/Prev` chains. This slice connects those two paths so current direct rows are repaired before stale previous rows are skipped.

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction to parser-backed PDF text layers before model/OCR fallback. In the PHP port, the equivalent native boundary is accurate xref-chain/object resolution before WordPress paragraphs are emitted.

PDF xref streams carry trailer dictionary keys, including `/Prev`, and the stream dictionary may use indirect operands. The parser must resolve the actual `/Prev` integer through the selected object graph rather than reading the object number in `9000 0 R` as a byte offset.

## Behavior

The focused fixture has:

- a previous classic xref table with stale same-generation page text;
- current same-generation catalog/pages/page/content objects after the previous section;
- a current xref stream whose rows for objects 1, 2, 3, and 5 intentionally contain offset `0`;
- `/Prev 9000 0 R`, where object `9000` is a member of object stream `7` and its body is the real previous xref byte offset;
- a Type3 `/CharProcs` guard pointing at the content stream so fallback stream scanning cannot mask a missing page tree.

Before the fix, the xref-stream row repair path treated `/Prev 9000 0 R` as direct integer `9000`, so the repair window was invalid and no page-tree text was selected. After the fix, `/Prev` resolves through the existing safe operand-owner object graph, the damaged current rows are repaired to the current direct objects, and only current page text is emitted.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevOffsetRepairCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs damaged current xref-stream offsets after resolving indirect Prev from compressed helper objects
Actual: array (
)
1 test files, 1 assertions, 1 failures
```

Focused and adjacent parser/xref run after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevOffsetRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevGenerationIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 273 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-indirect-prev-offset-repair-currentbase.php
```

The smoke emits `uses_current_repaired_page=true`, `recovers_prev_xref_offset_from_high_object_number_helper=true`, `high_object_number_not_misread_as_prev_offset=true`, `excludes_stale_prev_page=true`, `excludes_charproc_fallback_scan=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP object scanner, stream dictionary operand-owner resolver, object-stream expander, xref-stream parser, page-tree walker, and WordPress smoke path. The remaining model/OCR gap stays intentionally out of scope for this no-GPU markerPDF slice.
