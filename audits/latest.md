# Independent Audit - 2026-05-23T07:37:40Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane-status summaries needed to check status alignment, recent Git history
through `119d9916`, dirty-tree status, active process/test state, and the PHP
shell-out surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - there is still no stable integration snapshot to audit or
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:263`-`271`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `.tmux-team/tmp/*`,
     `.tmux-team/prompts/*`, `.tmux-team/logs/*`, and current dirty
     `lanes/*` worktree files.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:48`, `goal.md:49`, and `goal.md:52` require a practical
     concurrency cap, accurate owner/session state, deliberate integration,
     cleanup, repo-wide verification, and visible current progress.
   - Evidence: `progress.md:25` declares a two-implementation-worker plus
     one-auditor target, and `progress.md:31`-`42` still lists every lane as
     `stopped`, but process sampling showed active watchdog, capacity,
     dashboard, evaluator, integrator, auditor, artifact, verifier, and lane
     agent loops.
   - Evidence: `HEAD` moved during this audit from `efa62b16` through
     `baddfe23`, `52ce98b3`, `3446f9fc`, `25e8b8b8`, and `119d9916`; the
     latest samples report `907` `git status --short` entries, `94` tracked
     changed entries, and `94 files changed, 21134 insertions(+), 636 deletions(-)`.
   - Evidence: the required duplicate-root gate returned an active exact root
     harness: `1558644 php tools/run-tests.php`. Owner evidence from process
     sampling: `1558644 claude 1523087 6 Rs php tools/run-tests.php`.
     A later exact gate was clear, but the active writer/update loops and broad
     dirty tree still made a trustworthy root run invalid. I did not run a
     duplicate root harness.
   - Audit judgment: freeze active writers and root loops before accepting any
     root harness, dashboard, lane-status, manifest percentage, or progress
     estimate.

2. **High - root-test state is contradictory across lane records and cannot be
   treated as a current repo result.**
   - Paths: `lanes/*/lane-status.json`, especially
     `lanes/dolt/lane-status.json:10`-`12`,
     `lanes/readability/lane-status.json:5`-`12`,
     `lanes/gitoxide/lane-status.json:10`-`12`,
     `lanes/libsqlite/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`12`,
     `lanes/rclone/lane-status.json:10`-`12`,
     `lanes/esbuild/lane-status.json:10`-`13`, and
     `lanes/pandoc/lane-status.json:10`-`12`.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices with passing tests, verified integration,
     cleanup, and honest failure records.
   - Evidence: Dolt, Readability, Quadrable, Syncthing, Difftastic, Pandoc, and
     Gitoxide record green root runs around `196` files, but with different
     assertion counts and different snapshots. Libsqlite records a root failure
     outside its lane with `195` files, `21326` assertions, and `1` Dolt
     failure. LightningCSS records an earlier root failure in Readability and
     then an active-root pending state. Rclone and esbuild record root
     publication pending because prior duplicate-root gates found active root
     PIDs.
   - Evidence: current `HEAD` and the dirty tree moved again after those lane
     records, and this audit observed active root PID `1558644`.
   - Audit judgment: collapse root status back to one repo-level integration
     record for a frozen tree, then regenerate lane statuses from that one
     record.

3. **High - `porting.html` and `porting-summary.json` remain stale and still
   fail the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`8`, `porting-summary.json:10`-`213`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52` require
     current dashboard fields for benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while
     reviewed `HEAD` is `119d9916`.
   - Evidence: `porting.html:41`-`50` still has compound `Benchmark` and
     `Mapped` columns instead of separate `benchmark source`, `upstream
     denominator`, `mapped tests`, and `PHP pass/fail` columns.
   - Evidence: dashboard rows disagree with current manifests:
     Difftastic `160 / 417` versus `201 / 559`,
     Dolt `242 / 613` versus `330 / 613`,
     esbuild `164 / 2567` versus `186 / 2567`,
     Gitoxide `1432 / 2877` versus `1618 / 2877`,
     libsqlite `149 / 1454` versus `182 / 1454`,
     LightningCSS `773 / 3532` versus `867 / 3532`,
     markerPDF `159 / 78` versus `180 / 239`,
     Pandoc `426 / 2028` versus `514 / 2028`,
     rclone `291 / 327` versus `365 / 2553`,
     Readability `1031 / 1984` versus `1230 / 1984`, and
     Syncthing `235 / 658` versus `275 / 658`.
   - Audit judgment: the public dashboard is an old publish snapshot, not the
     current coordination surface.

4. **High - manifest denominator, runner-status, and PHP-count schemas still
   cannot support trustworthy portfolio percentages.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     and `lanes/readability/UPSTREAM_TEST_MANIFEST.json:368`-`374`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     meaningful fixture parity, explicit slices for huge suites, and dashboard
     separation of denominator, mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable, but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `benchmarkDenominator.runnerStatus` mixes object, string, and
     absent/null shapes. Gitoxide, markerPDF, and Quadrable use prose strings;
     Pandoc has no denominator-level runner status.
   - Evidence: manifest-level PHP pass/fail fields are inconsistent or absent.
     Dolt has `mapped: 330` while its warning says native PHP progress maps
     `214` focused lane tests; LightningCSS has `mapped: 867` while its warning
     says `864` checks; markerPDF has `mapped: 180` while
     `phpBehaviorTests` is `281`; rclone has `mapped: 365` while
     `phpBehaviorTests` is `360`; Readability's warning says `120` local tests
     while `phpBehaviorTests` is `119`.
   - Evidence: denominator units remain mixed: rclone's manifest denominator is
     `2553` repository files while also tracking `327` Go test files;
     markerPDF uses `239` static behavior/reference units; Difftastic mixes Rust
     test attributes, golden pairs, sample pairs, parser corpus files, and
     parser example files in one denominator.
   - Audit judgment: normalize manifest/status schema before publishing average
     progress or comparing lane percentages.

5. **Medium - bounded, supplied, generated, or oracle-backed evidence remains
   too easy to misread as native parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`-`20`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:227`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:408`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`-`20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:361`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:631`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:346`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:870`.
   - Requirement at risk: `goal.md:30`, `goal.md:37`,
     `goal.md:39`, and `goal.md:40` require upstream tests as source of truth,
     reproducible generated artifacts, hard-feature blockers, and no credit for
     generated fixtures, bridge calls, or shell-outs as native implementation
     progress.
   - Evidence: Gitoxide explicitly excludes shell-backed external driver
     execution, live environment inspection, secret-state inspection, and SSH
     process spawning from mapped progress. markerPDF still has the full
     upstream runner `not-executed` and stops at supplied model/output/debug
     boundaries. rclone's clean upstream evidence is bounded and excludes full
     provider/mount parity. Syncthing has no full `go test ./...` parity.
     Quadrable has strong C++ runner evidence but still uses many generated
     LMDB/raw cursor oracle fixtures and keeps full 500-trial sync-fuzzer probes
     outside the fast suite.
   - Audit judgment: keep the evidence, but separate it from native
     implementation progress and aggregate percentages.

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

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root check before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Result:

```text
1558644 php tools/run-tests.php
```

Owner evidence from process sampling:

```text
1558644 claude 1523087 6 Rs php tools/run-tests.php
```

A later exact duplicate-root sample was clear, but no root run was started
because active writer/update loops and a broad dirty worktree made the tree
non-quiescent.

Latest dirty-tree samples before this update:

```text
git status --short: 907 entries
git status --short --untracked-files=no: 94 entries
git diff --shortstat: 94 files changed, 21134 insertions(+), 636 deletions(-)
```

## Recent Git History

Recent commits reviewed:

```text
119d9916 Update Syncthing lane status commit pointer
25e8b8b8 Add Syncthing scanner progress events
3446f9fc pandoc record raw html list status
52ce98b3 pandoc map raw html list item slice
baddfe23 difftastic map guarded json display command
9764081e Refresh independent audit status
fcb1c75b Refresh independent audit status
68f04dbf Record syncthing normalization lane status
8a8bf56e Port syncthing scanner normalization slice
37c9d3bf readability: map additional Mozilla fixtures
9ef2cca7 Port libsqlite index leaf split insert planning
53385c27 Record lightningcss all reset status
a6721493 Record pandoc smart punctuation status
b067aab2 Port lightningcss all reset minifier slice
```
