# Pandoc PDF Engine Handoff AcroForm Submit Policy

Session: `port-dev-pandoc-pdf-handoff-20260609T052245Z`
Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T052245Z`
Base accepted HEAD: `aeac7627505caef0c7f45b74c533b70ec36e1807`
Lane: `pandoc`

## Scope

Implemented one bounded native fake-runner diagnostic for produced PDF bytes:
AcroForm submit/import/reset policy review metadata.

`PdfEngineHandoff` now derives `pdfFormFieldActionPolicy` and
`finalPdfFormFieldActionPolicy` from already parsed form field actions and
target lists. The policy reports action type, action target scheme, remote
target status, field-selection scope, flags, selected field count, review
status, and issue diagnostics for:

- remote `SubmitForm` targets;
- `SubmitForm` actions that submit the full PDF payload;
- all-field and exclude-listed submit/reset behavior;
- `ImportData` actions and import targets.

This does not execute Pandoc, a PDF engine, JavaScript, form submission, import
data loading, browser rendering, or an external PDF validator.

## Evidence

Rework notes checked first:

- No current non-stale `port-pandoc-*.needs-lane-rework.md` note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates` for this slice.
  Existing matches were under the `stale/` subtree and did not name this
  current-base PDF handoff session.

Baseline focused test before adding the new case:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 1267 assertions, 0 failures`

Red-first focused test after adding the AcroForm submit/export policy case:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 1269 assertions, 1 failures`
- Failure: missing `pdfFormFieldActionPolicy` key on fake-runner output.

Final focused tests after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 1283 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
- Result: `pdf engine handoff self-test ok`

Syntax, JSON, and diff hygiene:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- Result: no syntax errors detected
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: no syntax errors detected
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- Result: no syntax errors detected
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
- Result: `lane-status json ok`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'`
- Result: `manifest json ok`
- `git diff --check -- lanes/pandoc`
- Result: clean, no output

Root harness:

- not run - isolated micro-slice

## Status Delta

- Adds 1 focused PHP PASS case.
- Focused PDF handoff assertions move from `1267` baseline to `1283`
  assertions after implementation.
- `lanes/pandoc/lane-status.json` `phpPass`: `2368 -> 2369`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2762 -> 2763`.
- PDF engine inventory moves from `12` mapped cases and `108` recorded
  assertions to `13` mapped cases and `124` recorded assertions.

## Non-Overlap

This slice does not repeat accepted PDF engine work for engine planning,
source/resource artifacts, recorder files, SyncTeX/source maps, logs, output
metrics, xref/object streams, page trees, outlines, page boxes, page labels,
document info, XMP/PDF-A/PDF-UA/PDF-X, output intents, catalog/viewer policy,
name trees, destinations, tagging, structure parent/id tree policy, annotations,
annotation appearance streams, stream filters, rich media, embedded files,
associated files, marked content, optional content, encryption preflight,
signature ByteRange policy, signature revision mapping, signature seed/lock
policy, signature appearance policy, document security store policy, active
action extraction, page lifecycle actions, JavaScript name-tree extraction, or
raw AcroForm field/action/target extraction.

The new behavior is only the policy summary for AcroForm SubmitForm,
ImportData, and ResetForm actions using the existing native PDF byte parser.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`PdfEngineHandoff` object parsing, AcroForm action extraction, AcroForm target
list extraction, fake-runner sequence reporting, focused PHP tests, and the
lane-local WordPress PDF handoff example.

Full upstream Pandoc/Haskell runner parity, real TeX/PDF engine rendering,
Typst/browser/roff rendering, JavaScript execution, form submission, FDF import,
and external PDF validation remain out of scope for this isolated lane.

## Follow-Up

Next non-overlapping PDF engine handoff work should target one bounded
produced-PDF review gap such as annotation appearance policy, richer
active-content action policy, XFA packet review, or PDF/A form conformance
handoff without running external PDF engines, validators, browser renderers, or
online services.
