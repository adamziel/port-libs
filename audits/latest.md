# Independent Audit - 2026-05-23T04:50:37Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, selected
`lanes/*/lane-status.json` files needed to verify status drift, PHP shell-out
usage in `lanes/`, `tools/`, and `scripts/`, and recent Git history observed
through `54a6423f`.

I did not edit lane implementation files, launch agents or tmux sessions, or
push. I treated bridge/generated/oracle tooling as non-progress unless it was
explicitly marked as temporary fixture or oracle evidence.

## Findings

1. **High - the generated dashboard is not a current coordination surface.**
   - Paths: `porting.html:32`-`36`, `porting.html:54`-`65`,
     `porting-summary.json:2`-`8`, `porting-summary.json:15`-`18`.
   - Requirement at risk: `goal.md:3`, `goal.md:44`, `goal.md:45`, and
     `goal.md:49` require current progress/status, a browsable generated
     dashboard, separate denominator/mapped/PHP status, and honest repo-wide
     test recording.
   - Evidence: the dashboard still says it was generated at
     `2026-05-23 03:09:50 UTC` from source commit `3f4ea3cda693`, while this
     audit observed history through `54a6423f`. Current manifest mapped counts
     no longer match the page: difftastic `164` vs dashboard `139`, Dolt `246`
     vs `197`, esbuild `164` vs `150`, Gitoxide `1432` vs `1358`, libsqlite
     `149` vs `129`, LightningCSS `773` vs `703`, markerPDF `159` vs `81`,
     Pandoc `426` vs `397`, rclone `291` vs `265`, Readability `1031` vs
     `907`, and Syncthing `235` vs `204`.
   - Audit judgment: `porting.html` and `porting-summary.json` should not be
     used as the portfolio source of truth until regenerated from one accepted,
     tested snapshot.

2. **High - the green root harness is still a dirty moving-tree sample, not an accepted integration checkpoint.**
   - Paths: worktree-wide; representative dirty paths include
     `tools/run-tests.php:9`-`24`,
     `lanes/dolt/src/ConstraintViolationsTable.php`,
     `lanes/esbuild/src/TypeScriptModuleLowerer.php`,
     `lanes/markerpdf/src/TableRecognizer.php`, `porting.html`, and
     `porting-summary.json`.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, supervisor verification/integration, and
     recorded repo-wide tests.
   - Evidence: `HEAD` advanced from the initially observed `6aa77d14` to
     `54a6423f` during this run. The latest pre-audit-commit status sample
     reports `504` `git status --short` entries, including `43` modified
     tracked files, and `git diff --shortstat` reports
     `43 files changed, 10690 insertions(+), 374 deletions(-)`. The root test
     harness itself is modified and now serializes through
     `.upstream-cache/run-tests.lock`.
   - Audit judgment: the passing test result below is useful smoke evidence,
     but it is not release or integration proof until the supervisor freezes
     writers and accepts or rejects dirty lane batches deliberately.

3. **High - manifest denominator units remain mixed and percentages are not machine-checkable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`-`21`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`19`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`20`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require real upstream benchmark denominators, meaningful
     fixture parity, explicit slices for huge suites, and clear dashboard
     denominator/mapped fields.
   - Evidence: markerPDF has `total` as a prose string starting with `78`
     tracked paths plus dependency/source and benchmark-pair units, while
     `mapped` is `159`. Difftastic mixes Rust test attributes, golden output
     pairs, numbered fixture pairs, parser corpus files, and targeted source
     paths under one `417` total. Gitoxide reports `2877` upstream files while
     mapped progress is behavior slices and focused runner probes. Quadrable
     maps `55 / 55` tracked paths even though the runner evidence is 34
     upstream scenarios plus assertion counts. Pandoc uses upstream artifacts
     rather than executable test cases, while Syncthing uses Go test/benchmark
     entry points plus focused behavior paths.
   - Audit judgment: normalize manifests into typed fields such as
     `upstream_test_cases`, `fixture_pairs`, `source_paths`, `runner_passed`,
     `mapped_behavior_checks`, and `php_assertions` before publishing
     percentages.

4. **Medium - lane status files still carry contradictory root-test and commit state.**
   - Paths: `lanes/difftastic/lane-status.json:10`-`13`,
     `lanes/dolt/lane-status.json:10`-`13`,
     `lanes/esbuild/lane-status.json:10`-`13`,
     `lanes/markerpdf/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`.
   - Requirement at risk: `goal.md:31`, `goal.md:44`, and `goal.md:49`
     require precise blockers, current lane status, latest commits, and honest
     repo-wide test status.
   - Evidence: difftastic says the required root result was left pending
     because another harness was active. Dolt says root is pending for the
     integrator and labels a commit as an uncommitted lane batch. esbuild,
     markerPDF, and Syncthing claim a `18316`-assertion green root result while
     this audit's latest root run is `18405` assertions. Several `latestCommit`
     fields are prose (`pending local...`,
     `lane-scoped...`, `HEAD Stamp...`) instead of accepted SHAs.
   - Audit judgment: root-test status and latest-commit metadata need a single
     accepted snapshot stamp, not copied-forward lane-local prose.

5. **Medium - bounded/static upstream evidence is still easy to overread as full parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`-`20`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`-`18`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:35`-`40` require upstream tests as the source
     of truth and hard features to be marked as blockers or future slices.
   - Evidence: Difftastic, Gitoxide, markerPDF, Pandoc, and Syncthing still do
     not have full upstream runner parity. rclone and Dolt have useful bounded
     runner evidence, but provider/mount/live-service/full-BATS/full-Go
     coverage remains outside those passes. The dashboard converts these
     evidence classes into progress percentages without preserving the
     evidence class.
   - Audit judgment: make evidence class (`full-runner`, `bounded-runner`,
     `static-inventory`, `oracle-fixture`, `supplied-boundary`) first-class in
     manifests, lane statuses, and the dashboard.

## Bridge / Shell-Out Check

Command:

```text
rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane PHP shell-out was found. The only PHP shell-out match is dashboard
coordination tooling.

## Test Run

Required command:

```text
php tools/run-tests.php
```

Exact result for this audit:

```text
exit status: 0
183 test files, 18405 assertions, 0 failures
```

This is a green dirty-worktree sample, not an accepted integration checkpoint,
because the tree moved during the audit and still had `504` status entries in
the latest pre-audit-commit sample.

## Recommended Next Intervention

Freeze active writers. Accept or reject dirty lane batches one lane at a time,
stamp accepted SHAs and one root-test result, normalize manifest denominator
and evidence fields, then regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the same green snapshot.
