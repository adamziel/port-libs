# DOCX upstream runner evidence slice

Date: 2026-06-30T20:24:03Z

Branch base: PR head `01cd5f5078cc647cefd04d610e00493d79a62897`.

Scope:

- Inspected `DocxUpstreamRunnerPlan`, the focused DOCX CI workflow, local cache/source blockers, and the targeted result artifact gate.
- Did not run the full suite.
- Did not execute Cabal, Tasty, Pandoc, or any upstream DOCX runner.

Local runner status:

- `php tools/pandoc-docx-upstream-runner-plan.php --validate-result-artifacts` reports `blocked-missing-docx-upstream-source`.
- `runnerExecuted=false`, `resultRecorded=false`, and no DOCX parity claim is asserted.
- Required pinned upstream source root `.upstream-cache/pandoc-current` is absent, so required files `cabal.project`, `pandoc.cabal`, `test/test-pandoc.hs`, `test/Tests/Readers/Docx.hs`, `test/Tests/Writers/Docx.hs`, and `data/default.docx` are missing, as are `test/docx` and `test/docx/golden`.
- Pinned source evidence is `not-checked-upstream-root-missing`; no observed upstream commit is available.
- `cabal` and `ghc` are not on PATH in this worktree.
- Local free space is `250298368` bytes, below the targeted-runner workspace floor of `1073741824` bytes.
- Result artifact gate remains `blocked-missing-targeted-runner-result-artifacts`; dependency dry-run, list-tests, targeted-run, selected inventory, and `result.json` artifacts are not admitted as a result bundle.

Change made:

- Selected DOCX inventory artifacts now carry observed source-commit evidence, and the result artifact gate rejects inventories whose source commit is not verified as the pinned Pandoc commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- The focused DOCX CI workflow now writes `preflight-plan.json` and `result-artifact-gate.json` under `.port-libs/pandoc-runner/artifacts/docx-targeted-run/` and uploads those JSON files plus any runner transcripts as the `pandoc-docx-runner-evidence` artifact.

Validation:

- `php -l lanes/pandoc/src/DocxUpstreamRunnerPlan.php`
- `php -l lanes/pandoc/tests/DocxUpstreamRunnerPlanTest.php`
- `php -l tools/pandoc-docx-upstream-runner-plan.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxUpstreamRunnerPlanTest.php` passed: 1 file, 121 assertions, 0 failures.
- Workflow YAML parsed with Ruby `YAML.load_file`.
