# Independent Audit - 2026-05-23T07:05:34Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane-status summaries needed to check status alignment, recent Git history
through `ce19f538`, dirty-tree status, active process/test state, and the PHP
shell-out surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - the repository is still non-quiescent, and duplicate root
   harnesses were active during the audit.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:261`-`266`, `tools/run-tests.php`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `.tmux-team/tmp/*`,
     `.tmux-team/prompts/*`, and `.tmux-team/logs/*`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:48`, and `goal.md:49` require a practical concurrency cap,
     accurate owner/session state, deliberate integration/cleanup, and honest
     repo-wide verification.
   - Evidence: the first history sample had `HEAD` at `9bc72eb1`; later
     samples had `HEAD` at `ce19f538`. The tracked dirty tree changed while
     evidence was being collected, ending at 104 tracked changed files and
     `104 files changed, 20779 insertions(+), 782 deletions(-)`.
   - Evidence: `progress.md` still declares a two-implementation-worker plus
     one-auditor launch target and lists every lane as `stopped`, while process
     sampling found watchdog, capacity, dashboard, evaluator, integrator,
     auditor, lane-agent, artifact, and focused test processes active.
   - Initial required duplicate-root gate:

     ```text
     pgrep -af '^php tools/run-tests\.php( |$)'
     1360150 php tools/run-tests.php
     ```

     Owner evidence:

     ```text
     1360150 claude 1336902 00:20 Rs php tools/run-tests.php
     ```

   - Later exact samples alternated between clear and active. A later active
     sample found root PID `1368421 php tools/run-tests.php`; it exited before
     owner sampling. Another active sample found root PID `1380355` owned by
     `claude`. The final duplicate-root gate found:

     ```text
     1425928 php tools/run-tests.php
     ```

     Owner evidence:

     ```text
     1425928 claude 1343292 00:15 Rs php tools/run-tests.php
     ```

   - Audit judgment: freeze active writers and duplicate root loops before
     accepting any root harness result or dashboard snapshot.

2. **High - `porting.html` and `porting-summary.json` are stale and still fail
   the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json`, and `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52` require current dashboard fields for benchmark source,
     upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while the
     reviewed `HEAD` moved to `ce19f538`.
   - Evidence: the table still has compound `Benchmark` and `Mapped` columns
     instead of separate `benchmark source`, `upstream denominator`,
     `mapped tests`, and `PHP pass/fail` columns.
   - Evidence: current manifests disagree with the published rows:
     Difftastic `194 / 556` mapped versus dashboard `160 / 417`,
     Dolt `308 / 613` versus `242 / 613`, esbuild `183 / 2567` versus
     `164 / 2567`, Gitoxide `1554 / 2877` versus `1432 / 2877`,
     libsqlite `178 / 1454` versus `149 / 1454`, LightningCSS
     `853 / 3532` versus `773 / 3532`, markerPDF `172 / 233` versus
     `159 / 78`, Pandoc `509 / 2028` versus `426 / 2028`, rclone
     `355 / 2553` versus `291 / 327`, Readability `1179 / 1984` versus
     `1031 / 1984`, and Syncthing `267 / 658` versus `235 / 658`.
   - Audit judgment: the public dashboard is an old publish snapshot, not the
     current coordination surface.

3. **High - `progress.md` and lane statuses are not reliable owner/session or
   integration records.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:261`-`266`, `lanes/*/lane-status.json`, and
     `porting.html:54`-`65`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:47`, `goal.md:48`, and `goal.md:49` require capped active
     lanes, current owner/session state, restart/finish decisions, verified
     integration, and honest repo-wide failures.
   - Evidence: `progress.md` reports all lanes as `stopped`, but active
     process sampling showed lane agents for Readability, Quadrable,
     Gitoxide, rclone, esbuild, LightningCSS, Difftastic, Dolt, libsqlite,
     markerPDF, Pandoc, and Syncthing, plus integrator/auditor/capacity/
     artifact/dashboard/evaluator loops.
   - Evidence: `lanes/rclone/lane-status.json` was observed as a tracked
     deletion while `porting.html:63` still linked to it; a later writer
     recreated it as a modified status file during the same audit. That
     transient broken link is a symptom of active status mutation, not a
     stable accepted coordination state.
   - Evidence: lane status files mix real commit hashes with prose such as
     `current repository HEAD`, `uncommitted lane update`, `pending lane
     batch`, and `ce19f538 + uncommitted rclone move/moveto and copy/copyto
     slices`.
   - Audit judgment: regenerate `progress.md`, lane statuses, and dashboard
     artifacts from one frozen snapshot rather than continuing piecemeal
     status edits while workers are moving.

4. **High - manifest denominator and runner-evidence schema still cannot
   support trustworthy portfolio percentages.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`15`, and
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     meaningful fixture parity, explicit slices for huge suites, and dashboard
     separation of denominator, mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric and
     sometimes prose. Units mix executable test files, test functions, BATS
     cases, repository paths, fixtures, behavior artifacts, benchmark PDF
     pairs, and supplied document excerpts.
   - Evidence: `runnerStatus` is object-shaped in several manifests,
     string-shaped in Gitoxide, markerPDF, and Quadrable, and null/absent in
     Pandoc. Consumers cannot consistently distinguish full runner pass,
     bounded runner evidence, static inventory, oracle fixture evidence, and
     supplied-boundary evidence.
   - Evidence: several manifests expose PHP counts only as
     `nativeImplementation.phpBehaviorTests` or lane-status prose rather than
     normalized manifest fields for PHP pass/fail. Difftastic, Gitoxide,
     libsqlite, LightningCSS, Pandoc, Quadrable, and Syncthing still lack a
     normalized PHP pass/fail field inside the manifest.
   - Audit judgment: normalize manifest schema before publishing average
     progress or comparing lane percentages.

5. **High - root-test state copied into lane statuses is not accepted
   integration evidence.**
   - Paths: `lanes/difftastic/lane-status.json:10`-`13`,
     `lanes/esbuild/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/libsqlite/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`13`,
     `lanes/markerpdf/lane-status.json:10`-`13`,
     `lanes/pandoc/lane-status.json:10`-`13`,
     `lanes/quadrable/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `lanes/readability/lane-status.json:10`-`13`, and
     `lanes/syncthing/lane-status.json:10`-`13`.
   - Requirement at risk: `goal.md:29`, `goal.md:31`,
     `goal.md:48`, and `goal.md:49` require small reviewable slices, precise
     blockers, cleanup, and repo-wide failures recorded honestly.
   - Evidence: some lane statuses claim a green root harness for older moving
     snapshots, others say root is pending behind active PIDs, and at least one
     exact root harness was active during this audit. These statements cannot
     all describe one accepted repository state.
   - Evidence: rclone status says a root run passed with `191` files and
     `20759` assertions, then also says a later duplicate-root sample saw PID
     `1368421`; Syncthing status references a final captured green root in a
     log file; LightningCSS, markerPDF, Pandoc, Dolt, and Readability keep
     root-pending or duplicate-root prose. This duplicates repo-level evidence
     into mutable lane-local files.
   - Audit judgment: record root status once at repo level for one frozen
     snapshot, then reference it from lanes.

6. **Medium - bounded, generated, or oracle-backed upstream evidence is still
   too easy to misread as native parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:227`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:351`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:334`.
   - Requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require upstream tests as source of truth,
     meaningful fixture parity, hard-feature blockers, and no credit for
     generated fixtures, bridge calls, or shell-outs as native implementation
     progress.
   - Evidence: Gitoxide remains bounded package/static source evidence rather
     than full workspace cargo parity; Difftastic is static inventory plus
     offline no-run failure; markerPDF cannot run the full ML/PDF benchmark
     stack; Pandoc lacks full Haskell runner parity; rclone and Dolt rely on
     bounded runner subsets; and Syncthing lacks `go test ./...` parity.
   - Evidence: Quadrable and markerPDF still use generated/supplied oracle
     fixtures in their evidence trail. Those can be useful temporary oracle
     evidence, but they must not inflate native implementation percentages
     without a separate evidence-type field.

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
1360150 php tools/run-tests.php
```

Owner evidence:

```text
1360150 claude 1336902 00:20 Rs php tools/run-tests.php
```

Later exact samples alternated between clear and active. One later active
sample found root PID `1368421`, which exited before owner sampling. Another
active sample found root PID `1380355` owned by `claude`. The final duplicate
root sample before this audit write found:

```text
1425928 php tools/run-tests.php
```

Owner evidence:

```text
1425928 claude 1343292 00:15 Rs php tools/run-tests.php
```

The tree was also not stable enough for an accepted root run because active
writer/update loops moved `HEAD`, lane statuses, and the dirty tree during the
audit.

## Recent Git History

Recent commits reviewed:

```text
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
951fc895 Add Dolt preview merge conflict projections
```
