# markerPDF xref hybrid generation repair

Slice: `xref-hybrid-generation-repair-currentbase`
Session: `port-dev-markerpdf-xref38pdf-20260602T1855Z`
Base accepted HEAD: `28240b72b0f77821c5ac2cf978b4d8bf8469270e`

## Source truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes text extraction through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates page text to `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` delegates to `pypdfium2` page text extraction. That makes object streams, hybrid xref `/XRefStm` rows, and generation-aware indirect references PDF parser/dependency behavior for this native PHP lane.

The upstream cache named in the manifest is not present in this isolated worktree, so I checked the pinned upstream file over HTTPS:
`https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`.

## Behavior

`PdfTextExtractor::pdfObjects()` now reruns generation-aware direct-object repair after object-stream expansion. `withReferencedDirectGenerationObjects()` can also replace an expanded type-2 compressed generation-zero member when the selected graph references an available nonzero direct generation for the same object number.

The focused fixture maps a current hybrid xref table with companion `/XRefStm` rows selecting a compressed `/Pages` node. That compressed page-tree node contains `/Kids [4 1 R]`, while the companion xref stream also selects a stale generation-zero compressed page for object `4`. Before this repair, native WordPress paragraph extraction emitted the stale compressed page. After repair, the generation-one direct page is recovered and the stale compressed member stays excluded.

## Red Baseline

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridGenerationRepairCurrentBaseTest.php
```

Result before source repair:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs generation one page references discovered after hybrid object-stream expansion (lanes/markerpdf/tests/PdfXrefHybridGenerationRepairCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current hybrid page-tree generation',
  1 => 'Compressed pages node repaired',
)
Actual: array (
  0 => 'Stale compressed hybrid generation page',
)

1 test files, 1 assertions, 1 failures
```

## Green Evidence

Focused xref gate:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridGenerationRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefHybridReferenceRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php
```

Result:

```text
Focused test run: 8 selected test files (root lock skipped)
PASS keeps current hybrid table direct generation before stale xref-stream object member
PASS repairs generation one page references discovered after hybrid object-stream expansion
PASS keeps referenced generation one direct page before stale hybrid object-stream generation zero
PASS repairs hybrid xref direct rows when current page tree references a newer generation
PASS skips previous type-2 rows whose object-stream carrier was never selected before incremental free repair
PASS keeps current xref-stream free row before stale Prev object-stream member
PASS skips Prev type-2 rows when the object-stream carrier is only a compressed previous-generation decoy
PASS keeps current xref-stream object-stream owner before stale Prev hybrid type-2 rows

8 test files, 74 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-generation-repair-currentbase.php
```

Key result:

```text
uses_current_hybrid_page_tree_generation=true
repairs_compressed_pages_node_reference=true
excluded_stale_compressed_generation_zero_page=true
excluded_stale_generation_zero_metadata=true
```

Syntax and diff checks:

```sh
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefHybridGenerationRepairCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-generation-repair-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg()."\n"); exit(1); } echo "manifest json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

## Non-Overlap

This does not repeat accepted current hybrid table direct-generation precedence, explicit direct page-tree generation-one repair before object-stream expansion, companion `/XRefStm` direct-row stale generation repair, hybrid free-entry conflict precedence, object-stream `/Prev` carrier ownership guards, zero-width member-index recovery, object-stream indirect operand recovery, or xref-stream invalid-offset repair.

The new behavior is specifically a generation-one direct page reference that is only discovered after expanding a currently selected hybrid object-stream `/Pages` node.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP direct-object scanner, startxref/xref table/xref-stream parser, hybrid `/XRefStm` merger, object-stream decoder, page-tree walker, content-stream text extractor, and WordPress smoke path. Full upstream runner parity remains gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime tooling, benchmark/model downloads, and external OCR/rendering tools.
