# Dependency Nudge Integrator Audit 2026-05-24T00:18Z

## Inputs Read

- `goal.md`
- `progress.md`
- `dependency-backlog.json`
- `audits/dependency-library-nudge-enforcer-20260524T0012Z.md`
- `.tmux-team/prompts/dependency-library-nudge-enforcer-20260524T0012Z.md`
- `git status --short --branch`
- `git log --oneline --decorate -12`
- Pandoc lane metadata evidence in `lanes/pandoc/lane-status.json`,
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, and
  `lanes/pandoc/notes/upstream-inventory.md`

## Decision

Accepted `pandoc-doctemplates-core` as a bounded support-library tracking item,
not an implementation claim.

The row is coherent because it is scoped to Pandoc writer-template rendering,
has status `candidate`, is gated behind `pandoc-template-writer-next`, names
`jgm/doctemplates` plus Pandoc writer/template evidence, requires a
dependency-specific denominator, mapped fixtures, PHP pass/fail evidence,
malformed/corrupt template cases, and excludes a general templating framework,
external binaries, Haskell runtime embedding, arbitrary plugin execution, and
filesystem includes outside supplied roots.

No duplicate backlog row was found for this support-library boundary.

## Validation

- `jq empty dependency-backlog.json porting-summary.json`: exit 0, no output.
- `php -l tools/generate-dashboard.php`: exit 0,
  `No syntax errors detected in tools/generate-dashboard.php`.
- `git diff --check`: exit 0, no output.
- `git diff --cached --check`: exit 0, no output, with only owned tracking
  artifacts staged.

Root `php tools/run-tests.php` was not run.

## Publication

`porting.html` and `porting-summary.json` were not edited or staged by this
integrator. The live dashboard should remain at the previously published
22-item snapshot until a publisher regenerates it from a clean committed source
snapshot.
