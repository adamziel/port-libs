# markerPDF xref Prev-chain hybrid companion merge

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T025937Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260605T025937Z`
Base accepted HEAD: `a73f3f3a902b40cdaf0ad2e12031eda87dba4604`

## Source Truth

Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The upstream searchable-PDF path delegates low-level PDF parsing/text extraction to `pdftext`/PDFium before marker's OCR/layout/model stages. Under the current no-GPU scope, this PHP lane owns the native parser boundary: current xref sections, hybrid companion xref streams, `/Prev` recursion, metadata, attachments, and text extraction must be resolved before any WordPress import or model handoff.

PDF hybrid-reference files can use a classic xref table trailer with `/XRefStm` to point at a companion xref stream in the same current update section. Entries from that companion stream are current-section rows, not inherited previous-section rows. Only the trailer `/Prev` chain should mark rows as inherited from earlier xref sections.

## Behavior Fixed

`PdfTextExtractor::xrefEntriesFromOffsetChain()` previously called `xrefEntryInheritedFromPreviousSection()` while merging companion `/XRefStm` entries that did not exist in the current classic table. That branch ran before `$previousOffset` was loaded, causing an undefined-variable warning and a type error on companion-stream-only rows.

The patch keeps companion `/XRefStm` entries as ordinary current-section rows. `/Prev` rows are still marked as inherited after the previous xref offset is resolved. This preserves current hybrid table precedence while still allowing previous xref sections to fill missing rows.

## Red-First Evidence

Before the patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridReferenceRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainHybridTableCompressedPrevCurrentBaseTest.php
```

Failed with `2 test files / 0 assertions / 2 failures`, including `Undefined variable $previousOffset` in `PdfTextExtractor.php` and a type error in `xrefEntryInheritedFromPreviousSection()`.

After the patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridReferenceRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainHybridTableCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
```

Passed with `3 test files / 228 assertions / 0 failures`.

Broader focused xref/parser family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXref*.php lanes/markerpdf/tests/PdfParserXref*.php lanes/markerpdf/tests/PdfParserTrailerXrefNameCommentCurrentBaseTest.php
```

Passed with `59 test files / 1247 assertions / 0 failures`.

WordPress smokes:

```bash
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-hybrid-table-compressed-prev-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
```

Both exited `0`. The hybrid-table smoke emits `uses_current_base_page=true`, `resolves_compressed_prev_helper=true`, `keeps_current_info_metadata=true`, `imports_current_attachment=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`. The incremental-update smoke preserves current XMP/Info/catalog/page text and current attachments while excluding stale previous-section data.

## Non-Overlap

This does not repeat accepted malformed `/Index` repair, same-generation damaged explicit offsets, stale explicit offsets, wrong current-object offsets, classic table damaged offsets, indirect `/Prev` helper resolution, compressed `/Prev` helper resolution, damaged middle `/Prev` repair, object-stream generation repair, hybrid free-entry ownership, xref-stream trailer metadata, or encryption `/Prev` inheritance. The bounded fix is only the current hybrid table companion `/XRefStm` merge path before `/Prev` recursion.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, classic xref parser, xref-stream parser, `/Prev` chain walker, text extractor, metadata extractor, EmbeddedFiles extractor, and WordPress smoke renderer. Full upstream parity remains intentionally outside this no-GPU slice where it would require live `pdftext`/PDFium runner parity, Surya/Torch OCR/layout/table models, Texify equation recognition, model downloads, or external rendering/OCR helpers.
