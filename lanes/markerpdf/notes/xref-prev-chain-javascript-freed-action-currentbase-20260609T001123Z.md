# markerPDF xref Prev chain JavaScript freed action current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260609T001123Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260609T001123Z`
Base accepted HEAD: `2db0e80f0d313cd1b86adb66fbde40c6e33a2164`

## Source Truth

Upstream markerPDF routes searchable PDF parsing through PDF parser-backed text extraction before OCR/model fallback. Under the no-GPU PHP lane, xref-chain object selection and review-only safety metadata are native parser boundaries. A current incremental xref section can free an object inherited through `/Prev`; references to that object must resolve as absent even when the older object bytes still exist in the file.

## Behavior

`PdfJavaScriptActionInspector` now builds its raw object map once, removes object numbers reported by `PdfXrefFreeObjectMap::freeObjectNumbers()`, and parses JavaScript safety review data from that filtered map.

The focused fixture keeps a previous catalog with both:

- `/OpenAction 8 0 R`;
- `/Names << /JavaScript 6 0 R >>` where object `6` names object `8`.

The latest xref table has `/Prev` to the previous table and a current free row for object `8`. The visible page text remains selected through the inherited catalog/page tree, but the freed JavaScript object no longer appears in catalog OpenAction or JavaScript name-tree review output.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfJavaScriptXrefPrevFreedActionCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS suppresses JavaScript actions freed by the current xref Prev chain

1 test files, 11 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfJavaScriptXrefPrevFreedActionCurrentBaseTest.php lanes/markerpdf/tests/PdfJavaScriptActionInspectorTest.php
Focused test run: 2 selected test files (root lock skipped)
PASS reviews catalog JavaScript name tree actions without executing them
PASS reviews catalog open actions, additional actions, page actions, and annotation JavaScript actions
PASS returns an empty safety review for documents without JavaScript actions
PASS reviews cyclic JavaScript action chains once without executing or looping
PASS suppresses JavaScript actions freed by the current xref Prev chain

2 test files, 55 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-javascript-xref-prev-freed-action-currentbase.php
```

Result: emits `free_row_detected=true`, `visible_text_preserved=true`, `freed_javascript_suppressed=true`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfJavaScriptActionInspector.php
php -l lanes/markerpdf/tests/PdfJavaScriptXrefPrevFreedActionCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-javascript-xref-prev-freed-action-currentbase.php
```

All report no syntax errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Link annotation freed-action filtering, markup-annotation free-object suppression, xref-free annotation maps, direct ActionReview xref repair, named-destination xref-stream `/Prev` selection, catalog JavaScript inventory, JavaScript chain-cycle review, or visible text extraction xref repair. The bounded behavior is only `PdfJavaScriptActionInspector` safety review honoring current xref `/Prev` free rows before inspecting inherited catalog OpenAction and JavaScript name-tree references.

## Dependency Closure

No new support component is needed. This reuses the native PHP raw object parser, JavaScript safety inspector, `PdfXrefFreeObjectMap` xref-chain free-row resolver, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, JavaScript execution, external OCR/rendering helpers, online services, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope.
