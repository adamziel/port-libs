# Independent Audit - 2026-05-23T03:31:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, selected
lane status files needed to verify dashboard/status drift, PHP shell-out usage
in `lanes/`, `tools/`, and `scripts/`, and recent Git history.

Recent history observed: `8c21737`, `81d14bf`, `e4a7484`, `3d44b22`,
`ba89649`, `bb65b26`, `a27fb2e`, `3f4ea3c`, `f53ab46`, `dcf3a7d`,
`af649df`, and `5cc43f3`. I did not edit lane implementation files, launch
agents or tmux sessions, or push.

## Findings

1. **Critical - the current tree is a green but unaccepted dirty aggregate, not a stable integration checkpoint.**
   - Paths: worktree-wide; `progress.md:237`-`245`; `goal.md:29`,
     `goal.md:44`, `goal.md:48`-`49`.
   - Evidence: current `HEAD` is
     `8c21737 Stamp Pandoc grid table span status`. After the required root
     test run and latest status refresh, `git status --short` reported `428`
     entries, tracked-only status reported `75` modified entries, and
     `git diff --shortstat` reported
     `75 files changed, 9983 insertions(+), 415 deletions(-)`.
   - The required harness is green (`php tools/run-tests.php` exited `0` with
     `177` test files, `16997` assertions, and `0` failures), but this is only
     a sample from a broad dirty worktree.
   - Goal requirement at risk: small reviewable slices, passing verified
     commits, honest progress/status, and repo-wide test recording.
   - Audit judgment: do not treat the dirty aggregate as accepted portfolio
     progress until writers are frozen or explicitly coordinated, lane batches
     are accepted/rejected one at a time, and the same snapshot is rerun green.

2. **High - `porting.html` and `porting-summary.json` are stale and contradict the current manifests/status files.**
   - Paths: `porting.html:32`-`36`, `porting.html:54`-`65`,
     `porting-summary.json:2`-`8`, `porting-summary.json:16`-`18`,
     `porting-summary.json:83`-`86`, `porting-summary.json:117`-`120`,
     `porting-summary.json:168`-`171`, `porting-summary.json:185`-`188`,
     `porting-summary.json:202`-`205`.
   - Evidence: the dashboard claims a verified snapshot of source commit
     `3f4ea3cda693`, while current `HEAD` is `8c21737` and the worktree is
     dirty. Stale count examples: Difftastic dashboard `139 / 417` while its
     manifest says `143`; Gitoxide dashboard `1358 / 2877` and `2511 pass`
     while status/manifest say `1361` mapped and `2529 pass`; libsqlite
     dashboard `129 / 1454` and `129 pass` while manifest/status say `133`;
     Esbuild dashboard `150 pass` while status says `156`;
     LightningCSS dashboard `703 / 3532` and `827 pass` while status/manifest
     say `710` mapped and `839 pass`; markerPDF dashboard `254 pass` while
     status says `255`; Pandoc dashboard `397` mapped and `154 pass` while
     status/manifest say `400` and `157`; rclone
     dashboard `265 / 327` while status/manifest say `273`; Readability
     dashboard `907 / 1984` and `100 pass` while manifest/status say `965`
     mapped and `103` PHP tests; Syncthing dashboard `204 / 658` while
     manifest/status say `209`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     generated dashboard to show current denominator, mapped tests, PHP
     pass/fail, current work, blocker, and commit.
   - Audit judgment: regenerate the dashboard only after accepting or rejecting
     the current dirty lane batches from one frozen green snapshot.

3. **High - lane status root-test/blocker fields conflict with the current exact root result.**
   - Paths: `lanes/esbuild/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:5`,
     `lanes/syncthing/lane-status.json:12`.
   - Evidence: current exact root result is green with `177` files, `16997`
     assertions, and `0` failures. Status files still cite older unrelated red
     roots: Esbuild and LightningCSS blame Difftastic failures, Pandoc blames a
     missing rclone example, markerPDF and rclone blame Syncthing failures, and
     Syncthing blames Pandoc grid-table failures. Readability claims a green
     root too, but with stale assertion count `16922`; Pandoc now also claims
     a green root with stale assertion count `16983`.
   - Goal requirement at risk: `goal.md:44`, `goal.md:45`, and `goal.md:49`
     require current blockers, audit status, and repo-wide test recording.
   - Audit judgment: lane-local results and root-suite samples need separate
     fields, and all root-test fields should be stamped from the same accepted
     snapshot.

4. **High - latest-commit fields are not reliable commit identifiers.**
   - Paths: `porting-summary.json:42`, `porting-summary.json:76`,
     `porting-summary.json:93`, `porting-summary.json:161`,
     `porting-summary.json:195`, `porting-summary.json:212`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Evidence: dashboard commit cells include truncated/prose values such as
     `metadat` and `pending`. Lane status files contain dirty-batch labels such
     as `check-constraint maintenance lane batch`, `uncommitted Gitoxide
     multi-head merge-base slice`, `pending-current-batch`, and `uncommitted
     Syncthing lane worker slice`. Quadrable still says `pending lane commit`
     even though `3d44b22` has landed, and markerPDF records a mixed-lane
     commit: `f53ab46 Record difftastic huge cpp status`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:44`, and `goal.md:45`
     require small reviewable slices and a trustworthy latest commit column.
   - Audit judgment: split accepted SHA, dirty batch label, and mixed-lane
     provenance into separate fields before treating dashboard/status commit
     data as auditable.

5. **High - upstream denominator units remain mixed, and markerPDF still has an impossible mapped count.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
   - Evidence: markerPDF declares `78 tracked upstream repository paths` as the
     denominator while reporting `mapped: 81`. Difftastic combines Rust test
     attributes, golden output pairs, fixture pairs, parser corpus files, and
     source files. Dolt mixes executable test files, BATS case counts, Go test
     function counts, and fixture artifacts. LightningCSS mixes helper
     invocations and Rust/Node tests. Pandoc counts files/artifacts rather than
     executable Tasty cases.
   - Goal requirement at risk: `goal.md:25` and `goal.md:35`-`38` require a
     real upstream denominator, upstream tests as source of truth, and explicit
     slices when the suite is huge.
   - Audit judgment: normalize manifests into separate upstream-file,
     executable-test, behavior-case, mapped-behavior, PHP-test, assertion,
     failure, runner-parity, and static/bounded/full evidence fields.

6. **Medium - several manifests/statuses still imply local PHP progress as upstream parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Evidence: Gitoxide maps `1361 / 2877` without full Cargo workspace
     runner parity; Pandoc maps `400 / 2028` file/artifact inventory without a
     Haskell runner; Syncthing maps `209 / 658` with bounded focused runners
     and static targeted reads; Difftastic still has no upstream runner parity.
   - Goal requirement at risk: `goal.md:35`-`39` says upstream tests are the
     source of truth, static inventories must be clearly marked, and hard
     features cannot be silently skipped.
   - Audit judgment: keep these as bounded/static evidence, not upstream pass
     parity, until the relevant full or deliberately-scoped upstream runners
     are executed and named as such.

## Bridge / Shell-Out Check

Command:

```text
rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane PHP shell-out was found. The only current PHP shell-out match is
dashboard coordination tooling.

## Test Run

Required command:

```text
php tools/run-tests.php
```

Exact result from this audit:

```text
exit status: 0
177 test files, 16997 assertions, 0 failures
```

This is better than the starting audit status recorded in `progress.md`, but it
is still a moving-worktree sample, not an accepted integration checkpoint.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. Accept or reject the dirty lane
batches one lane at a time, rerun focused lane tests and `php tools/run-tests.php`
after each accepted batch, commit accepted slices, then regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same stable green snapshot with normalized denominator, mapped-case,
native-test, runner-parity, blocker, and latest-commit fields.
