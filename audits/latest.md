# Independent Audit - 2026-05-23T07:30:44Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane-status summaries needed to check status alignment, recent Git history
through `fcb1c75b`, dirty-tree status, active process/test state, and the PHP
shell-out surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - there is still no stable integration snapshot to audit or
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:261`-`269`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `.tmux-team/tmp/*`,
     `.tmux-team/prompts/*`, `.tmux-team/logs/*`, and current dirty
     `lanes/*` worktree files.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:48`, and `goal.md:49` require a practical concurrency cap,
     accurate owner/session state, deliberate integration/cleanup, and honest
     repo-wide verification.
   - Evidence: `progress.md:25` declares a two-implementation-worker plus
     one-auditor target, and `progress.md:31`-`42` lists every lane as
     `stopped`, but process sampling found active watchdog, capacity,
     dashboard, evaluator, integrator, auditor, artifact, and lane-agent loops
     for most lanes.
   - Evidence: current `HEAD` is `fcb1c75b`; the latest sampled dirty tree has
     `862` default `git status --short` entries, `85` tracked changed entries,
     and `85 files changed, 20133 insertions(+), 466 deletions(-)`.
   - Evidence: the required duplicate-root gate returned an active exact root
     harness: `1531436 php tools/run-tests.php`. Owner evidence:
     `1531436 claude 1501667 00:06 Rs php tools/run-tests.php`. A later exact
     gate returned `1536245 php tools/run-tests.php`; owner evidence:
     `1536245 claude 1470705 00:10 Rs php tools/run-tests.php`. A final exact
     sample was clear, but the active writer loops and broad dirty tree still
     made a trustworthy root run invalid. A post-commit handoff sanity check
     then found another active root harness: `1537589 php tools/run-tests.php`,
     with owner evidence `1537589 claude 1537583 00:36 R php tools/run-tests.php`.
     I did not run a root harness.
   - Audit judgment: freeze active writers and root loops before accepting any
     root harness, dashboard, lane-status, manifest percentage, or progress
     estimate.

2. **High - root-test state is contradictory across lane records and cannot be
   treated as a current repo result.**
   - Paths: `lanes/*/lane-status.json`, especially
     `lanes/dolt/lane-status.json:10`-`13`,
     `lanes/esbuild/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`, and
     `lanes/syncthing/lane-status.json:10`-`13`.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices with passing tests, verified integration,
     cleanup, and honest failure records.
   - Evidence: Dolt records a post-refresh aggregate root failure with `194`
     files, `21073` assertions, and `16` rclone failures in
     `CopyCommandTest.php` because `SyncPlan::destructiveSkipActionCount()` was
     missing. Other lane records still report green root runs from different
     snapshots: Syncthing says `193` files and `21102` assertions passed,
     Readability says `193` files and `21055` assertions passed, and
     LightningCSS says `193` files and `21055` assertions passed.
   - Evidence: Gitoxide records an initial root failure with one unidentified
     failure followed by a filtered rerun passing `194` files and `21191`
     assertions; rclone and esbuild record root acceptance as pending because
     older active root PIDs were already running.
   - Evidence: the current implementation has moved again, and exact root
     harnesses were already active during the audit as PIDs `1531436` and later
     `1536245`; the lane-local green/red anecdotes are not one accepted
     snapshot.
   - Audit judgment: keep root status at one repo-level integration record for
     a frozen tree, then regenerate lane status from that record.

3. **High - `porting.html` and `porting-summary.json` remain stale and still
   fail the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`205`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52` require
     current dashboard fields for benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while
     reviewed `HEAD` is `fcb1c75b`. `porting-summary.json:3`-`4` reports the
     same stale source commit.
   - Evidence: `porting.html:41`-`50` still has compound `Benchmark` and
     `Mapped` columns instead of separate `benchmark source`, `upstream
     denominator`, `mapped tests`, and `PHP pass/fail` columns.
   - Evidence: dashboard rows disagree with current manifests:
     Difftastic `160 / 417` versus `201 / 559`,
     Dolt `242 / 613` versus `324 / 613`,
     esbuild `164 / 2567` versus `186 / 2567`,
     Gitoxide `1432 / 2877` versus `1617 / 2877`,
     libsqlite `149 / 1454` versus `180 / 1454`,
     LightningCSS `773 / 3532` versus `867 / 3532`,
     markerPDF `159 / 78` versus `175 / 234`,
     Pandoc `426 / 2028` versus `513 / 2028`,
     rclone `291 / 327` versus `360 / 2553`,
     Readability `1031 / 1984` versus `1215 / 1984`, and
     Syncthing `235 / 658` versus `273 / 658`.
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
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     and `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`33`.
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
   - Evidence: normalized manifest-level PHP pass/fail fields are absent.
     `phpBehaviorTests` is a bare number in Dolt, esbuild, markerPDF, rclone,
     and Readability, while Difftastic, Gitoxide, libsqlite, LightningCSS,
     Pandoc, Quadrable, and Syncthing rely on lane-status prose.
   - Evidence: denominator units are not comparable: rclone's current manifest
     denominator is `2553` all repository files while its inventory also says
     `327` Go test files; markerPDF moved from the dashboard's `78` tracked
     paths to `234` behavior/reference units; Difftastic counts mixed Rust
     attributes, golden pairs, sample pairs, and parser files in one field.
   - Audit judgment: normalize the manifest/status schema before publishing
     average progress or comparing lane percentages.

5. **Medium - bounded, supplied, generated, or oracle-backed evidence remains
   too easy to misread as native parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:232`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`-`20`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:224`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`-`20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:356`,
     and `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:342`.
   - Requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require upstream tests as source of truth,
     meaningful fixture parity, hard-feature blockers, and no credit for
     generated fixtures, bridge calls, or shell-outs as native implementation
     progress.
   - Evidence: Gitoxide explicitly says full cargo parity is not claimed;
     markerPDF still has the full upstream runner `not-executed`; rclone is a
     bounded provider/mount-excluding runner; Syncthing has no full
     `go test ./...` parity; Quadrable has strong upstream C++ runner evidence
     but also many generated LMDB/raw cursor oracle fixtures and keeps the slow
     sync-fuzzer outside the fast suite.
   - Audit judgment: keep this evidence, but separate it from native
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
1531436 php tools/run-tests.php
```

Owner evidence:

```text
1531436 claude 1501667 00:06 Rs php tools/run-tests.php
```

A later exact duplicate-root gate returned:

```text
1536245 php tools/run-tests.php
```

Owner evidence:

```text
1536245 claude 1470705 00:10 Rs php tools/run-tests.php
```

The final exact duplicate-root sample before committing was clear, but no root
run was started because the tree was still non-quiescent with active
writer/update loops and a broad dirty worktree.

A post-commit handoff sanity check found another active exact root harness:

```text
1537589 php tools/run-tests.php
```

Owner evidence:

```text
1537589 claude 1537583 00:36 R php tools/run-tests.php
```

Latest dirty-tree samples before this update:

```text
git status --short: 862 entries
git status --short --untracked-files=no: 85 entries
git diff --shortstat: 85 files changed, 20133 insertions(+), 466 deletions(-)
```

## Recent Git History

Recent commits reviewed:

```text
fcb1c75b Refresh independent audit status
68f04dbf Record syncthing normalization lane status
8a8bf56e Port syncthing scanner normalization slice
37c9d3bf readability: map additional Mozilla fixtures
9ef2cca7 Port libsqlite index leaf split insert planning
53385c27 Record lightningcss all reset status
a6721493 Record pandoc smart punctuation status
b067aab2 Port lightningcss all reset minifier slice
```
