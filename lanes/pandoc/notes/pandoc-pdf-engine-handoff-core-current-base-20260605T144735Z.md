# Pandoc PDF Engine Handoff Optional Content 2026-06-05

## Scope

Micro-slice:
`pandoc-pdf-engine-handoff-core-current-base-20260605T144735Z`.

Accepted base:
`5f01d9e717201ba19e7b761448a3221c1052cf70`.

This slice stays inside the bounded native PDF-output handoff support library.
It does not implement or invoke Pandoc, TeX/PDF engines, Typst, browser
renderers, roff, JavaScript, external PDF validators, online services, or
Haskell runners.

## Implemented Behavior

`PdfEngineHandoff` now inspects fake-runner produced PDF bytes for catalog
optional-content metadata:

- `/OCProperties` dictionaries resolved directly or through an indirect object.
- `/OCGs` group references, including groups discovered by `/Type /OCG`.
- Optional-content group names, `/Intent` names, and `/Usage` metadata for
  view, print, export, creator, language, and zoom bounds.
- Default configuration fields from `/D`: name, creator, base state, list mode,
  on/off group arrays, order references, and order labels.
- Fake-runner diagnostics for optional-content group, intent, config, base
  state, on/off, and order counts.
- Multipass fake-runner sequence handoff through `finalPdfOptionalContentGroups`
  and `finalPdfOptionalContentConfig`.

The WordPress PDF handoff example now includes two fake OCG layers, one default
configuration, and self-test needles for the first and final fake-run payloads.

## Source-Truth Boundary

The local upstream cache does not contain a hydrated Pandoc checkout for this
worktree, so no upstream Haskell runner or golden PDF fixture was executed.
This maps the PDF optional-content contract that Pandoc PDF-producing engines
can hand back through produced bytes, not engine-specific layer generation
parity.

## Non-Overlap

This patch does not repeat the existing PDF-engine support rows for sidecars,
logs, SyncTeX, FLS dependency graphs, output metrics, trailer/xref/object
streams, page tree geometry, page labels/timings, outlines, document info,
language, XMP/PDF-A, output intents, catalog presentation/viewer preferences,
named destinations, tagging/structure trees, annotations, links, embedded
files, AcroForm fields, active actions/JavaScript, or encryption preflight.

Follow-up PDF slices should keep digital signatures, Optional Content
Membership Dictionaries, richer layer usage/page-state policies, and
engine-specific layer generation parity separate.

## Dependency Closure

No new support component is needed. This reuses the existing native
`PdfEngineHandoff` fake-runner/PDF-byte inspection support component and extends
its bounded dictionary parser. Full upstream runner closure remains gated on
hydrating Pandoc at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and recording a
non-mutating Cabal plan for the Haskell Tasty runners.

## Verification

- Rework note check:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print | sort`
  - no matching rework note
- Baseline focused test before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 437 assertions, 0 failures`
- Red-first focused test after adding the optional-content case:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 439 assertions, 1 failures`
  - failure: missing `pdfOptionalContentGroups`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 446 assertions, 0 failures`
  - PASS cases: 41
- Example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - `pdf engine handoff self-test ok`
- Root harness: not run - isolated micro-slice.
