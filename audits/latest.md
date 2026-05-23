# Independent Audit - 2026-05-23T07:11:49Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane-status summaries needed to check status alignment, recent Git history
through `8d2f62c8`, dirty-tree status, active process/test state, and the PHP
shell-out surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - there is still no stable integration snapshot to audit or
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:261`-`267`, `tools/run-tests.php`,
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
     `stopped`, but process sampling found active watchdog, capacity,
     dashboard, evaluator, auditor, artifact, and lane-agent loops for many
     lanes.
   - Evidence: the dirty tree continued moving during the audit. Samples moved
     from `852` to `858` default `git status --short` entries, and
     `git diff --shortstat` moved from `105 files changed, 20876 insertions(+),
     667 deletions(-)` to `104 files changed, 22876 insertions(+),
     2286 deletions(-)`.
   - Evidence: manifest counts changed while being read. Dolt moved from
     `308 / 613` mapped to `318 / 613`; markerPDF moved from `172 / 233` to
     `173 / 234`.
   - Audit judgment: freeze active writers before accepting any root harness,
     dashboard, lane-status, or percentage evidence.

2. **High - `porting.html` and `porting-summary.json` remain stale and still
   fail the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52` require
     current dashboard fields for benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while
     reviewed `HEAD` is `8d2f62c8`. `porting-summary.json` also reports
     `sourceCommit` `bda83c6b93d4865c7edddaf7a680378f347eb4e6`.
   - Evidence: `porting.html:41`-`50` still has compound `Benchmark` and
     `Mapped` columns instead of separate `benchmark source`, `upstream
     denominator`, `mapped tests`, and `PHP pass/fail` columns.
   - Evidence: dashboard rows disagree with current manifest samples:
     Difftastic `160 / 417` versus `198 / 556`, Dolt `242 / 613` versus
     `318 / 613`, esbuild `164 / 2567` versus `183 / 2567`, Gitoxide
     `1432 / 2877` versus `1554 / 2877`, libsqlite `149 / 1454` versus
     `180 / 1454`, LightningCSS `773 / 3532` versus `853 / 3532`,
     markerPDF `159 / 78` versus `173 / 234`, Pandoc `426 / 2028` versus
     `510 / 2028`, rclone `291 / 327` versus `355 / 2553`, Readability
     `1031 / 1984` versus `1196 / 1984`, and Syncthing `235 / 658` versus
     `267 / 658`.
   - Audit judgment: the public dashboard is still an old publish snapshot, not
     the current coordination surface.

3. **High - `progress.md` and lane statuses are not reliable owner/session or
   integration records.**
   - Paths: `progress.md:31`-`42`, `progress.md:261`-`267`,
     `lanes/*/lane-status.json`, `porting.html:54`-`65`, and
     `porting-summary.json`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:47`, `goal.md:48`, and `goal.md:49` require capped active
     lanes, current owner/session state, restart/finish decisions, verified
     integration, and honest repo-wide failures.
   - Evidence: lane statuses now mix contradictory root-test claims for
     different moving snapshots. Examples: `lanes/rclone/lane-status.json`
     records one green root run, a later red root run with four failures, and a
     final active-root duplicate gate; `lanes/libsqlite/lane-status.json` and
     `lanes/gitoxide/lane-status.json` claim green root runs; `lanes/esbuild`
     and `lanes/lightningcss` record red root runs outside their lanes; Dolt,
     Readability, Pandoc, markerPDF, and Syncthing record root pending or
     blocked behind active PIDs.
   - Evidence: lane `latestCommit` fields include non-commit prose such as
     `pending-this-batch`, `pending lane batch`, `uncommitted lane update
     after 8d2f62c8`, and `ce19f538 + uncommitted rclone move/moveto and
     copy/copyto slices`.
   - Audit judgment: record root status once at repo level for one frozen
     snapshot, then reference it from lanes instead of copying mutable root
     anecdotes into lane-local files.

4. **High - manifest denominator, runner-status, and PHP-count schemas still
   cannot support trustworthy portfolio percentages.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`16`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     meaningful fixture parity, explicit slices for huge suites, and dashboard
     separation of denominator, mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric and
     sometimes prose. Units mix executable test files, test functions, BATS
     cases, repository paths, fixtures, behavior artifacts, benchmark PDF
     pairs, and supplied document excerpts.
   - Evidence: `runnerStatus` is object-shaped in several manifests,
     string-shaped in Gitoxide, markerPDF, and Quadrable, and absent/null in
     Pandoc. Consumers cannot consistently distinguish full runner pass,
     bounded runner evidence, static inventory, oracle fixture evidence, and
     supplied-boundary evidence.
   - Evidence: several manifests expose PHP counts only as
     `nativeImplementation.phpBehaviorTests` or lane-status prose, not as
     normalized manifest-level PHP pass/fail fields. Difftastic, Gitoxide,
     libsqlite, LightningCSS, Pandoc, Quadrable, and Syncthing still lack a
     normalized PHP pass/fail pair in the manifest.
   - Audit judgment: normalize manifest schema before publishing average
     progress or comparing lane percentages.

5. **Medium - bounded, supplied, generated, or oracle-backed upstream evidence
   is still too easy to misread as native parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:16`-`19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`-`20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:351`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:334`.
   - Requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require upstream tests as source of truth,
     meaningful fixture parity, hard-feature blockers, and no credit for
     generated fixtures, bridge calls, or shell-outs as native implementation
     progress.
   - Evidence: Gitoxide explicitly says full cargo parity is not claimed;
     markerPDF still relies on supplied document/model-output boundaries;
     Pandoc is static inventory without the Haskell runner; rclone is a
     bounded provider/mount-excluding runner; Syncthing has no full
     `go test ./...` parity; Quadrable uses many upstream-generated LMDB
     cursor/oracle fixtures while also warning that native PHP parity remains
     partial.
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

Initial required duplicate-root check before any root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Result: no matching root harness process at that initial sample. I still did
not start a root run because the tree was not stable enough: active
writer/update loops were present, dirty status counts changed, and manifest
counts changed while the audit was reading them.

Before finishing, the exact duplicate-root gate returned:

```text
1463163 php tools/run-tests.php
```

Owner evidence:

```text
1463163 claude 1404606 19 Rs php tools/run-tests.php
```

Active process sample included:

```text
216170 bash /home/claude/port-libs/scripts/run-team-watchdog.sh
788795 bash /home/claude/port-libs/scripts/run-capacity-controller-loop.sh
1294155 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-readability ...
1345841 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-difftastic ...
1345896 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-dolt ...
1352753 bash scripts/run-tmux-agent.sh port-libsqlite ...
1370170 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-markerpdf ...
1370291 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-pandoc ...
1370425 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-syncthing ...
1404533 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-quadrable ...
1436397 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-gitoxide ...
1436913 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-esbuild ...
1448765 bash /home/claude/port-libs/scripts/run-tmux-agent.sh port-auditor ...
2399239 bash /home/claude/port-libs/scripts/run-dashboard-updater-loop.sh
2424048 bash /home/claude/port-libs/scripts/run-evaluator-loop.sh
```

## Recent Git History

Recent commits reviewed:

```text
8d2f62c8 Refresh independent audit status
ce19f538 Record syncthing scanner status
9bc72eb1 Port syncthing scanner unchanged shortcut
5c6651e5 Refresh independent audit status
51c63278 Map difftastic command resource limits
c80373cd Port esbuild decorated class expression lowering
e5d50bde Refresh independent audit status
b6254802 Record libsqlite lane commit
a4b65435 Advance libsqlite multi-page index write planning
ba222203 Refresh independent audit status
7c328a3f Record Dolt lane implementation commit
65b07e78 Record Syncthing lane implementation commit
```
