# Independent Audit - 2026-05-23T05:08:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
representative `lanes/*/lane-status.json` files needed to verify status drift,
PHP shell-out usage in `lanes/`, `tools/`, and `scripts/`, and recent Git
history through `bda83c6b`.

I did not edit lane implementation files, launch agents or tmux sessions, or
push. I treated bridge/generated/oracle tooling as non-progress unless it was
explicitly marked as temporary fixture or oracle evidence.

## Findings

1. **High - the dashboard is still stale and materially disagrees with the current manifests.**
   - Paths: `porting.html:32`-`36`, `porting.html:54`-`65`,
     `porting-summary.json:2`-`18`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:49` require
     current coordination status, the required dashboard fields, and honest
     repo-wide test recording.
   - Evidence: `porting.html` still reports `Generated: 2026-05-23 03:09:50
     UTC` and source commit `3f4ea3cda693`, while current `HEAD` is
     `bda83c6b93d4` and the worktree has newer dirty lane changes. Current
     manifest mapped counts disagree with the page: difftastic `167` vs `139`,
     Dolt `250` vs `197`, esbuild `165` vs `150`, Gitoxide `1435` vs `1358`,
     libsqlite `155` vs `129`, LightningCSS `794` vs `703`, markerPDF `161`
     vs `81`, Pandoc `461` vs `397`, rclone `294` vs `265`, Readability
     `1044` vs `907`, and Syncthing `236` vs `204`.
   - Audit judgment: `porting.html` and `porting-summary.json` should not be
     treated as the portfolio source of truth until regenerated from one
     accepted, tested snapshot.

2. **High - the root harness is green now, but the aggregate is still not an accepted integration checkpoint.**
   - Paths: worktree-wide; representative dirty paths include
     `tools/run-tests.php`, `porting.html`, `porting-summary.json`,
     `lanes/gitoxide/src/ReferenceStore.php`,
     `lanes/pandoc/src/MarkdownReader.php`,
     `lanes/dolt/src/ConstraintViolationsTable.php`, and
     `lanes/syncthing/src/PullItemUpdater.php`.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, supervisor verification/integration, and
     recorded repo-wide tests.
   - Evidence: the latest exact `php tools/run-tests.php` run exits `0` with
     `183` test files, `18644` assertions, and `0` failures. That is better
     than the previous red audit sample, but the tree remains broad and dirty:
     `git status --short` reports `609` entries, tracked-only status reports
     `100` entries, untracked-all status reports `613` entries, and
     `git diff --shortstat` reports
     `91 files changed, 13220 insertions(+), 709 deletions(-)`.
   - Audit judgment: the green run is useful smoke evidence only. It should
     not be counted as integration progress until dirty lane batches are
     accepted or rejected one lane at a time and status files are regenerated
     from the same snapshot.

3. **High - upstream denominator units are still mixed, so percentages remain non-machine-checkable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`-`20`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`20`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators, meaningful fixture
     parity, explicit slices for huge suites, and separate denominator/mapped
     dashboard fields.
   - Evidence: markerPDF reports `78 tracked upstream repository paths` while
     mapping `161` source/dependency/supplied-document semantics. Quadrable
     maps `55 / 55` tracked paths even though the runner evidence is 34
     upstream scenarios plus assertion counts. Gitoxide uses `2877` upstream
     files while mapped progress is behavior slices and focused runner probes.
     Pandoc uses `2028` test files/artifacts rather than executable test cases.
     Difftastic mixes Rust test attributes, fixture pairs, golden pairs, parser
     corpus files, and targeted source files under one `417` total.
   - Audit judgment: normalize manifests into typed fields such as
     `upstream_test_cases`, `fixture_pairs`, `source_paths`, `runner_passed`,
     `mapped_behavior_checks`, and `php_assertions` before relying on
     percentages.

4. **Medium - lane status files still contradict the current root result and latest-commit requirement.**
   - Paths: `lanes/difftastic/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/quadrable/lane-status.json`,
     and `lanes/syncthing/lane-status.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:31`, `goal.md:44`,
     `goal.md:45`, and `goal.md:49` require precise blockers, current status,
     latest commits, and honest root test state.
   - Evidence: several lane statuses still say root verification is pending
     because an older `php tools/run-tests.php` PID was active; others stamp
     stale green root results with older assertion totals such as `18211` or
     `18316`, not the current `18644`-assertion run. Multiple `latestCommit`
     fields remain prose or pending dirty-batch labels rather than accepted
     SHAs.
   - Audit judgment: lane statuses need one accepted snapshot stamp, not
     copied-forward root-test and dirty-batch prose.

5. **Medium - bounded/static upstream evidence is still easy to overread as full parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:203`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:211`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:290`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:61`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:297`.
   - Requirement at risk: `goal.md:35`-`40` require upstream tests as source
     of truth, meaningful edge-case coverage, and hard features to be marked
     as blockers or future slices.
   - Evidence: Difftastic, Gitoxide, markerPDF, Pandoc, and Syncthing still do
     not have full upstream runner parity. rclone and Dolt have useful bounded
     runner evidence, but provider/mount/live-service/full-BATS/full-Go
     coverage remains outside those passes. The dashboard collapses these
     evidence classes into percentages.
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

Exact latest result for this audit:

```text
exit status: 0
183 test files, 18644 assertions, 0 failures
```

## Recommended Next Intervention

Freeze active writers. Accept or reject dirty lane batches one lane at a time,
stamp accepted SHAs and one root-test result, normalize manifest denominator
and evidence fields, then regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the same accepted green
snapshot.
