# PDF Engine Handoff Current-Base Slice

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T054800Z`

Accepted base: `2c84ca27878846c6b3725d422a6af783d4bbe9c7`

## Behavior

This slice adds a bounded native PHP page timing review policy for fake-produced
PDF page `/Dur` and `/Trans` entries. `PdfEngineHandoff::fakeRun()` now exposes
`pdfPageTimingPolicy`, and `fakeRunSequence()` carries it forward as
`finalPdfPageTimingPolicy`.

The policy groups already-parsed page timing metadata into affected pages,
duration pages, transition pages, transition type counts, direction labels,
maximum duration values, and deterministic review issues for auto-advance
durations, transition effects, direction overrides, motion overrides, scale
overrides, and transition background-fill behavior.

## Evidence

Previous focused PDF handoff status recorded:

```sh
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: `1 test files, 1295 assertions, 0 failures`.

Final focused verification:

```sh
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: `1 test files, 1305 assertions, 0 failures`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test
```

Result: `pdf engine handoff self-test ok`.

Status movement: `phpPass +1` (`2399 -> 2400`), mapped denominator `+1`
(`2790 -> 2791`), `pdfEngineHandoffCoreCases 13 -> 14`,
`mappedPdfEngineHandoffCoreCases 13 -> 14`, and
`pdfEngineHandoffCoreAssertions 120 -> 130`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native
`PdfEngineHandoff` fake runner, PDF page-tree traversal, and bounded page
timing parser. It does not shell out to Pandoc, Cabal, Haskell runners, Word,
LibreOffice, zip/unzip, external template engines, TeX/PDF engines, browser
renderers, online services, live provider tests, or live-service provider
tests.

## Non-Overlap

This does not rework accepted PDF output-intent, conformance, associated-file,
page-label, URI base, name-tree, catalog requirement, structure, annotation,
form, signature, DSS, active-action, optional-content, encryption,
stream-filter, viewer-preference, or produced-byte artifact diagnostics. The
new behavior is limited to page timing/transition policy summarization from
fake-produced PDF bytes.
