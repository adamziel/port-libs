# Independent Audit - 2026-05-23T07:25:38Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane-status summaries needed to check status alignment, recent Git history
through `37c9d3bf`, dirty-tree status, active process/test state, and the PHP
shell-out surface. Some manifest/dashboard evidence was first sampled at
`1267695e`; concurrent commits advanced `HEAD` while this audit was being
finalized.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - there is still no stable integration snapshot to audit or
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:260`-`268`, `tools/run-tests.php`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `.tmux-team/tmp/*`,
     `.tmux-team/prompts/*`, `.tmux-team/logs/*`, and current dirty
     `lanes/*` worktree files.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:48`, and `goal.md:49` require a practical concurrency cap,
     accurate owner/session state, deliberate integration/cleanup, and honest
     repo-wide verification.
   - Evidence: `progress.md:25` still declares a two-implementation-worker
     plus one-auditor target and `progress.md:31`-`42` lists every lane as
     `stopped`, but process sampling found watchdog, capacity, dashboard,
     evaluator, integrator, auditor, artifact, and lane-agent loops active for
     many lanes.
   - Evidence: the dirty tree remains broad and non-quiescent at current
     `HEAD` `37c9d3bf`: status samples moved from `855` default entries and
     `94` tracked entries to `846` default entries and `78` tracked entries;
     `git diff --shortstat` moved from
     `94 files changed, 21696 insertions(+), 626 deletions(-)` to
     `78 files changed, 19672 insertions(+), 442 deletions(-)`.
   - Evidence: a mid-audit process sample included
     `1471784 php tools/run-tests.php`; the PID exited before owner sampling.
     A later exact duplicate-root gate returned
     `1495111 php tools/run-tests.php`, with owner evidence
     `1495111 claude 1462628 00:13 Rs php tools/run-tests.php`. Before the
     final commit, another exact gate returned `1506734 php tools/run-tests.php`
     with owner evidence
     `1506734 claude 1472171 00:14 Rs php tools/run-tests.php`. I did not run a
     duplicate root harness.
   - Audit judgment: freeze active writers before accepting any root harness,
     dashboard, lane-status, or percentage evidence.

2. **High - `porting.html` and `porting-summary.json` remain stale and still
   fail the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:3`-`205`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52` require
     current dashboard fields for benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while
     reviewed `HEAD` is `37c9d3bf`. `porting-summary.json:3`-`4` still
     reports `bda83c6b93d4865c7edddaf7a680378f347eb4e6`.
   - Evidence: `porting.html:41`-`50` still has compound `Benchmark` and
     `Mapped` columns instead of separate `benchmark source`, `upstream
     denominator`, `mapped tests`, and `PHP pass/fail` columns.
   - Evidence: dashboard rows disagree with current manifests:
     Difftastic `160 / 417` versus `198 / 556`,
     Dolt `242 / 613` versus `324 / 613`,
     esbuild `164 / 2567` versus `185 / 2567`,
     Gitoxide `1432 / 2877` versus `1561 / 2877`,
     libsqlite `149 / 1454` versus `180 / 1454`,
     LightningCSS `773 / 3532` versus `864 / 3532`,
     markerPDF `159 / 78` versus `175 / 234`,
     Pandoc `426 / 2028` versus `513 / 2028`,
     rclone `291 / 327` versus `360 / 2553`,
     Readability `1031 / 1984` versus `1215 / 1984`, and
     Syncthing `235 / 658` versus `273 / 658`.
   - Audit judgment: the public dashboard is an old publish snapshot, not the
     current coordination surface.

3. **High - `progress.md` and lane statuses are contradictory integration
   records.**
   - Paths: `progress.md:31`-`42`, `progress.md:260`-`268`,
     `lanes/*/lane-status.json`, `porting.html:54`-`65`, and
     `porting-summary.json:9`-`205`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:47`, `goal.md:48`, and `goal.md:49` require capped active
     lanes, current owner/session state, restart/finish decisions, verified
     integration, and honest repo-wide failures.
   - Evidence: lane statuses mix incompatible root-test states from different
     moving snapshots. Examples: Difftastic reports a green root run with
     `193` files and `20898` assertions in
     `lanes/difftastic/lane-status.json:10`; Dolt reports both a green root
     run and later active-work failures in `lanes/dolt/lane-status.json:10`;
     Pandoc reports a red root run in `lanes/pandoc/lane-status.json:10`;
     Rclone reports a red post-status root rerun in
     `lanes/rclone/lane-status.json:10`; Quadrable and Syncthing report green
     root runs in `lanes/quadrable/lane-status.json:10` and
     `lanes/syncthing/lane-status.json:10`.
   - Evidence: lane `latestCommit` fields are not consistently commits:
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`, and
     `lanes/readability/lane-status.json:13` contain pending-batch prose,
     stale bases, or mixed commit-plus-dirty-work descriptions.
   - Audit judgment: record root status once at repo level for one frozen
     snapshot, then reference it from lanes instead of copying mutable root
     anecdotes into lane-local files.

4. **High - manifest denominator, runner-status, and PHP-count schemas still
   cannot support trustworthy portfolio percentages.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     and `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     meaningful fixture parity, explicit slices for huge suites, and dashboard
     separation of denominator, mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is a string in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable, but a number in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `benchmarkDenominator.runnerStatus` mixes object, string, and
     absent/null shapes. Gitoxide, markerPDF, and Quadrable use prose strings;
     Pandoc has no runner status at the denominator level.
   - Evidence: normalized manifest-level PHP pass/fail fields are still absent.
     PHP counts appear only as `nativeImplementation.phpBehaviorTests` in
     Dolt, esbuild, markerPDF, rclone, and Readability, while Difftastic,
     Gitoxide, libsqlite, LightningCSS, Pandoc, Quadrable, and Syncthing lack
     that field and rely on lane-status prose.
   - Audit judgment: normalize the manifest/status schema before publishing
     average progress or comparing lane percentages.

5. **Medium - bounded, supplied, generated, or oracle-backed evidence is still
   too easy to misread as native parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:224`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`-`33`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:356`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:338`, and
     `lanes/markerpdf/lane-status.json:12`.
   - Requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require upstream tests as source of truth,
     meaningful fixture parity, hard-feature blockers, and no credit for
     generated fixtures, bridge calls, or shell-outs as native implementation
     progress.
   - Evidence: Gitoxide explicitly says full cargo parity is not claimed;
     markerPDF still relies on supplied document/model-output boundaries and
     marks the full upstream runner `not-executed`; rclone is a bounded
     provider/mount-excluding runner; Syncthing has no full `go test ./...`
     parity; Quadrable uses many upstream-generated LMDB cursor/oracle
     fixtures while also warning that slow sync-fuzzer probes remain outside
     the fast suite.
   - Audit judgment: keep these evidence types, but separate them from native
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

Initial required duplicate-root check before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Result: no matching root harness process at that initial sample.

Mid-audit process sample included an active root harness:

```text
1471784 php tools/run-tests.php
```

Immediate owner sampling for that PID returned only the header because the
process had already exited.

Before finishing, the exact duplicate-root gate returned another active root
harness:

```text
1495111 php tools/run-tests.php
```

Owner evidence:

```text
1495111 claude 1462628 00:13 Rs php tools/run-tests.php
```

No duplicate root run was started.

After that PID exited, a later exact duplicate-root gate returned another
active root harness:

```text
1506734 php tools/run-tests.php
```

Owner evidence:

```text
1506734 claude 1472171 00:14 Rs php tools/run-tests.php
```

No duplicate root run was started.

Latest dirty-tree samples before this update:

```text
git status --short: 846 entries
git status --short --untracked-files=no: 78 entries
git diff --shortstat: 78 files changed, 19672 insertions(+), 442 deletions(-)
```

## Recent Git History

Recent commits reviewed:

```text
37c9d3bf readability: map additional Mozilla fixtures
9ef2cca7 Port libsqlite index leaf split insert planning
53385c27 Record lightningcss all reset status
a6721493 Record pandoc smart punctuation status
b067aab2 Port lightningcss all reset minifier slice
d50f586f Refresh independent audit status
c8d138c1 Port pandoc smart punctuation edge cases
1267695e Record Syncthing ignore perms commit
553a0226 Port Syncthing scanner ignore perms window
c758725c difftastic map display control env options
845f00b6 Refresh independent audit status
4a1406b9 libsqlite: add automatic index writes and utf16 records
```
