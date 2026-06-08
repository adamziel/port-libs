# markerPDF xref Prev inherited action trailer current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260608T165132Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260608T165132Z`
Base accepted HEAD: `63e2debc141738e27afa8820a6493fd1cbe7d79e`

## Source Truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Upstream delegates searchable-PDF action/link review to native PDF parser dependency behavior before any OCR/layout/model stages. Under the current no-GPU markerPDF scope, xref repair for annotation action graphs is therefore an in-scope native parser fidelity boundary.

## Behavior

Incremental PDFs may finish with a sparse classic xref table whose latest trailer contains `/Prev` but omits `/Root`. The previous trailer still supplies the catalog reference, while same-generation current replacements for the catalog, page, annotation, and action dictionaries can appear between the previous xref section and the latest sparse update.

Before this slice, `PdfActionReviewExtractor` only seeded current-update graph repair from `/Root`, `/Info`, and `/Encrypt` values present in the latest section dictionary/trailer. With `/Root` omitted, action review merged stale previous xref rows and selected stale URI/JavaScript actions even though the current update provided replacement action objects.

`PdfActionReviewExtractor` now inherits omitted trailer graph references through the `/Prev` chain before repairing current update graph entries. Explicit latest values still remain authoritative: direct `/Root`, `/Info`, and `/Encrypt` references are used as before, and explicit non-reference values stop inheritance.

## Verification

Red-first before implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalActionInheritedTrailerCurrentBaseTest.php
```

Failed with `1 test files, 10 assertions, 1 failures`; the action review selected `https://example.com/stale-inherited-action`.

Passing after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalActionInheritedTrailerCurrentBaseTest.php
```

Passed: `1 test files, 21 assertions, 0 failures`.

Adjacent action-review xref Prev-chain family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalActionInheritedTrailerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewForwardPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainCompressedActionRowsCurrentBaseTest.php
```

Passed: `5 test files, 141 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-action-inherited-trailer-currentbase.php
```

Passed with current text selected, current URI promoted into Markdown, current additional action reviewed, stale previous URI/JavaScript excluded, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace:

```bash
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevChainIncrementalActionInheritedTrailerCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-action-inherited-trailer-currentbase.php
git diff --check -- lanes/markerpdf
```

Passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted text/metadata/attachment inherited trailer repair, current trailer `/Root` action-review repair, xref-stream action row repair, indirect `/Prev` action repair, forward `/Prev` action repair, compressed action object repair, free annotation row handling, object-stream carrier repair, encrypted preflight, outline metadata, image review, table/equation handoff, or OCR/model behavior.

The new boundary is specifically a latest sparse classic xref table that omits `/Root`, inherits it through `/Prev`, and must repair current same-generation annotation/action graph rows before WordPress link promotion.

## Dependency Closure

No new support component is needed. This reuses the native PHP xref table/stream parser, trailer `/Prev` walker, direct object scanner, annotation action review extractor, link annotation extractor, and Markdown/WordPress smoke path. GPU/OCR/model parity, live Surya/Texify/Torch execution, pypdfium rendering, action execution, decryption/password validation, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.
