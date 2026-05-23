# Independent Audit - 2026-05-23T07:00:46Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane-status summaries needed to check status alignment, recent Git history
through `51c63278`, dirty-tree status, active process/test state, and the PHP
shell-out surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - the repository is still non-quiescent, and an initial duplicate-root gate found active root harnesses.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:257`-`265`, `tools/run-tests.php`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `.tmux-team/tmp/*`,
     `.tmux-team/prompts/*`, and `.tmux-team/logs/*`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:48`, and `goal.md:49` require a practical concurrency cap,
     accurate owner/session state, deliberate integration/cleanup, and honest
     repo-wide verification.
   - Evidence: `progress.md` still declares a two-implementation-worker plus
     one-auditor target and lists every lane as `stopped`, while process
     sampling found watchdog, capacity, dashboard, evaluator, integrator,
     auditor, lane-agent, and capacity/artifact agents active; exact root
     harness samples changed while evidence was being collected.
   - Evidence: `HEAD` moved during the audit from `e5d50bde` through
     `c80373cd` to `51c63278`; the latest tracked-only sample reports `95`
     changed entries and `95 files changed, 19813 insertions(+), 716
     deletions(-)`.
   - Initial required duplicate-root gate:

     ```text
     pgrep -af '^php tools/run-tests\.php( |$)'
     1332636 php tools/run-tests.php
     1332641 php tools/run-tests.php
     ```

   - Owner evidence:

     ```text
     1332636 claude   1294542      10 Rs   php tools/run-tests.php
     1332641 claude   1314316      10 Ss   php tools/run-tests.php
     ```

   - Later exact duplicate-root samples alternated between clear and active;
     one later active sample found root PID `1341411`, with owner evidence
     `1341411 claude 1323958 00:07 Rs php tools/run-tests.php`. The latest
     exact sample was clear, but `HEAD` and the dirty tree had already moved
     again under active writer/update loops.
   - Audit judgment: freeze active writers and duplicate root loops before
     treating any root harness result as an accepted baseline.

2. **High - `porting.html` and `porting-summary.json` are stale and still fail the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:1`-`214`, and
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52` require current dashboard fields for benchmark source,
     upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while the
     reviewed history is at `51c63278`.
   - Evidence: the table still has compound `Benchmark` and `Mapped` columns
     instead of separate `benchmark source`, `upstream denominator`,
     `mapped tests`, and `PHP pass/fail` columns.
   - Evidence: current manifests disagree with the published rows:
     Difftastic `194 / 556-ish` mapped versus dashboard `160 / 417`,
     Dolt `308 / 613` versus `242 / 613`, esbuild `181 / 2567` versus
     `164 / 2567`, Gitoxide `1525 / 2877` versus `1432 / 2877`,
     libsqlite `178 / 1454` versus `149 / 1454`, LightningCSS `851 / 3532`
     versus `773 / 3532`, markerPDF `172 / 233` versus `159 / 78`,
     Pandoc `509 / 2028` versus `426 / 2028`, rclone `350 / 2553` versus
     `291 / 327`, Readability `1179 / 1984` versus `1031 / 1984`, and
     Syncthing `267 / 658` versus `235 / 658`.
   - Audit judgment: the public dashboard is an old publish snapshot, not the
     current coordination surface.

3. **High - `progress.md` is not a reliable owner/session or lane coordination record.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:257`-`265`, and `.tmux-team/tmp/*`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:47`, and `goal.md:48` require capped active lanes, current
     owner/session state, restart/finish decisions, and verified integration.
   - Evidence: the Active Lanes table still reports every lane `stopped`, but
     active process sampling showed running agents for rclone, Dolt, Gitoxide,
     Readability, Difftastic, esbuild, Quadrable, Syncthing, markerPDF,
     LightningCSS, libsqlite, and Pandoc, plus integrator/auditor/artifact/
     capacity/dashboard/evaluator loops.
   - Evidence: the dashboard average is still `68.8%` from an older snapshot,
     while current manifests have advanced mapped counts for every lane except
     Quadrable. The progress file therefore cannot be used to choose the next
     integration target without first freezing writers and regenerating status.
   - Audit judgment: regenerate `progress.md` from one frozen snapshot rather
     than continuing piecemeal edits while lane-local writers are moving.

4. **High - manifest denominator and runner-evidence schema still cannot support trustworthy portfolio percentages.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`18`, and
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     explicit slices for huge suites, and dashboard separation of denominator,
     mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric and sometimes
     prose. Units mix executable files, test functions, BATS cases, repository
     paths, fixture artifacts, helper references, benchmark PDF pairs,
     inspected behavior artifacts, and supplied document excerpts.
   - Evidence: Dolt reports denominator `613` executable files while the same
     field discusses 3,808 BATS cases and 1,420 Go test/benchmark functions;
     Quadrable reports `mapped=55` against prose describing 34 scenarios, 29
     subcases, 136 checks, and 20 throw checks; Pandoc and Difftastic use
     prose totals for file/artifact or behavior-artifact inventories rather
     than a stable numeric test denominator.
   - Evidence: `runnerStatus` is object-shaped in several manifests,
     string-shaped in Gitoxide/markerPDF/Quadrable, and absent/null in Pandoc.
     Consumers cannot consistently distinguish full runner pass, bounded runner
     evidence, static inventory, oracle fixture evidence, and supplied-boundary
     evidence.
   - Audit judgment: normalize manifest schema before publishing average
     progress or comparing lane percentages.

5. **High - lane status commit/root-test fields are not accepted integration evidence.**
   - Paths: `lanes/difftastic/lane-status.json:10`-`13`,
     `lanes/esbuild/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`13`,
     `lanes/markerpdf/lane-status.json:10`-`13`,
     `lanes/pandoc/lane-status.json:10`-`13`,
     `lanes/quadrable/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `lanes/readability/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`, and
     `porting.html:54`-`65`.
   - Requirement at risk: `goal.md:29`, `goal.md:31`,
     `goal.md:48`, and `goal.md:49` require small reviewable slices, precise
     blockers, cleanup, and repo-wide failures recorded honestly.
   - Evidence: several `latestCommit` values are prose or dirty-batch labels,
     including `pending lane batch`, `pending lane changes`, `uncommitted lane
     batch`, `current repository HEAD`, and `not committed because root harness
     is red outside pandoc`.
   - Evidence: lane files copy root-test state locally and now contradict the
     live process state. Examples include Gitoxide/libsqlite/Syncthing green
     root claims, Pandoc/LightningCSS red root claims, Readability/rclone/
     markerPDF root-pending claims, and two exact root harnesses currently
     running in the same shared worktree.
   - Audit judgment: root status should be recorded once at repo level for one
     frozen snapshot, then referenced by lanes. Lane-local copies are stale and
     mutually incompatible.

6. **Medium - bounded, generated, or oracle-backed upstream evidence is still too easy to misread as native parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:227`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:221`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/quadrable/lane-status.json:10`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:345`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:330`.
   - Requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require upstream tests as source of truth,
     meaningful fixture parity, hard-feature blockers, and no credit for
     generated fixtures, bridge calls, or shell-outs as native implementation
     progress.
   - Evidence: Gitoxide remains bounded package evidence rather than full
     workspace cargo parity; Difftastic is static inventory plus bounded probes;
     markerPDF cannot run the full ML/PDF benchmark stack; Pandoc lacks full
     Haskell runner parity; rclone and Dolt rely on bounded runner subsets; and
     Syncthing lacks `go test ./...` parity.
   - Evidence: Quadrable lane status explicitly references generated C++/LMDB
     oracle fixtures, and markerPDF uses supplied conversion callbacks. Those
     can be valuable temporary oracle evidence, but they must not inflate native
     implementation percentages without a separate evidence-type field.
   - Audit judgment: add explicit evidence-type fields and keep generated
     fixtures, bridge calls, shell-backed oracles, and supplied-boundary
     callbacks out of native implementation percentages.

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

Initial required duplicate-root check before any root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
1332636 php tools/run-tests.php
1332641 php tools/run-tests.php
```

Owner evidence:

```text
1332636 claude   1294542      10 Rs   php tools/run-tests.php
1332641 claude   1314316      10 Ss   php tools/run-tests.php
```

A later exact duplicate-root sample returned no process, but the tree remained
non-quiescent due to active writer/update loops and a growing dirty aggregate.
A subsequent active sample returned root PID `1341411`; owner evidence:

```text
1341411 claude   1323958       7 Rs   php tools/run-tests.php
```

The latest exact duplicate-root sample was clear, but `HEAD` had moved through
new lane commits and active writer/update loops remained present, so the tree
was not stable enough for a trustworthy accepted root run.

I did not run `php tools/run-tests.php`.

## Recent Git History

Recent commits reviewed:

```text
51c63278 Map difftastic command resource limits
c80373cd Port esbuild decorated class expression lowering
e5d50bde Refresh independent audit status
b6254802 Record libsqlite lane commit
a4b65435 Advance libsqlite multi-page index write planning
ba222203 Refresh independent audit status
7c328a3f Record Dolt lane implementation commit
65b07e78 Record Syncthing lane implementation commit
951fc895 Add Dolt preview merge conflict projections
4e9d95e0 Port Syncthing scanner block-size hysteresis
b81b54d7 Stamp difftastic lane status
b5127b50 Refresh independent audit status
5c03e2be Stamp esbuild lane status
10cdf74f Advance esbuild decorated class lowering
cc6816bb difftastic map command environment options
51858b39 Stamp rclone lane status
b63b8f34 Advance rclone command operation slices
```

## Recommended Next Intervention

Freeze active writers and duplicate root loops, then rerun the exact
duplicate-root gate. If it remains empty, capture one quiesced
`php tools/run-tests.php` run from a single accepted snapshot. After that,
accept or reject dirty lane batches one at a time, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from the same
snapshot, and normalize manifest denominator/runner-status fields before
publishing percentages.
