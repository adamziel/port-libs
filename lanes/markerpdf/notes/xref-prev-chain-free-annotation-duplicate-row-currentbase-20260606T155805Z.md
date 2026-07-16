# xref Prev Chain Free Annotation Duplicate Row Current Base

Slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260606T155805Z`
Base: `f275e7ef84bb0d1552526667c009b35d687cc13a`

## Source Truth

Native markerPDF xref-stream parsing already treats a current xref section as ordered source truth before walking `/Prev`. `PdfTextExtractor::xrefEntriesFromStreamObject()` preserves the first row for an object number inside one decoded xref stream. The lightweight `PdfXrefFreeObjectMap` used by annotation/link preflight had the inverse gap: duplicate `/Index` ranges inside the current xref stream could let a later stale in-use row overwrite an earlier current free row for the same annotation object.

This patch aligns the lightweight free-object map with the current-section precedence boundary so a freed annotation in the latest update cannot be revived into WordPress link or annotation review metadata by a later duplicate row in the same xref stream.

## Change

- `PdfXrefFreeObjectMap::xrefStreamRows()` now keeps the first decoded xref-stream row per object number and skips later duplicate rows in the same stream.
- Added a focused fixture where the previous xref table contains a stale `/Subtype /Link` annotation and the latest xref stream has `/Prev` plus duplicate `/Index` rows for object `7`: first free, later stale in-use.
- Added a WordPress smoke that emits only the current paragraph and confirms the stale link/annotation payload remains suppressed without PDF action execution, Python/models, OCR, or external PDF tools.

## Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationDuplicateRowCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps first current xref-stream free annotation row before duplicate stale row
1 test files, 1 assertions, 1 failures
```

Focused passing run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationDuplicateRowCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps first current xref-stream free annotation row before duplicate stale row
1 test files, 9 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-free-annotation-duplicate-row-currentbase.php
```

The smoke reports `duplicate_free_row_preserved=true`, `stale_link_suppressed=true`, `stale_payload_in_visible_text=false`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This is not the accepted action-review duplicate-row slice where current in-use rows must beat later duplicate free rows. It is the inverse lightweight free-object-map boundary for annotation/link suppression: current free row first, duplicate stale in-use row later, with a `/Prev` chain that still contains the stale annotation object.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP xref stream, `/Prev`, annotation, and link extractors already present in `lanes/markerpdf/src`; no GPU/model/OCR, pypdfium, Python, PDF renderer, or external PDF tool execution is required.

## Next

Continue with non-overlapping native PDF xref repair coverage, especially malformed xref-stream `/Index` and `/Prev` interactions that affect metadata, annotations, forms, embedded files, or object-stream member ownership.
