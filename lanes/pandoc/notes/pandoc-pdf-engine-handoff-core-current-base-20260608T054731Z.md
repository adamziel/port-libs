# PDF Engine Handoff Current-Base Optional Content Policy

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T054731Z`
Base: `4cdbc422e45adc25f1ad62ce24e13ad1c7bd277e`

Implemented one bounded native fake-produced PDF handoff behavior: `/OCProperties`
default configuration dictionaries now expose optional-content `/Locked` layer
references and `/RBGroups` radio-button layer groups through
`pdfOptionalContentConfig` and `finalPdfOptionalContentConfig`.

Evidence:

- Baseline focused check before the new probe:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 804 assertions, 0 failures`.
- Red-first probe failed before implementation because the config summary lacked
  `locked` and `radioButtonGroups`: `1 test files, 806 assertions, 1 failures`.
- Final focused check:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 812 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed with `pdf engine handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/PdfEngineHandoff.php`,
  `lanes/pandoc/tests/PdfEngineHandoffTest.php`, and
  `lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`.
- `git diff --check -- lanes/pandoc` passed.

Dependency closure: no new support component is needed. The slice reuses the
existing native `PdfEngineHandoff` PDF object/value parser and fake-runner
diagnostic path. No Pandoc, Cabal/Haskell runner, TeX/PDF engine, Typst,
browser renderer, roff renderer, external PDF validator, online service, live
provider test, or live-service provider test was executed.

Non-overlap: this extends the already bounded PDF optional-content handoff from
groups/config ON/OFF/order and membership dictionaries into lock and radio-group
policy metadata. It does not implement layer rendering, a PDF engine, PDF/A
validation, or external OCG policy evaluation.

Follow-up candidate: optional-content `/AS` usage-application dictionaries and
richer layer UI order validation can be handled as separate native PDF handoff
slices.
