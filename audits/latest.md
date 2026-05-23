# Independent Audit - 2026-05-23T06:43:27Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane-status summaries needed to check status alignment, recent Git history
through `b63b8f3459dc`, dirty-tree status, active process/test state, and the
PHP shell-out surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - the repo is still non-quiescent, and duplicate root harnesses are active.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:258`-`264`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `.tmux-team/tmp/*`,
     `.tmux-team/prompts/*`, `.tmux-team/logs/*`, and `tools/run-tests.php`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:48`, and `goal.md:49` require a practical concurrency cap,
     accurate owner/session state, deliberate integration/cleanup, and honest
     repo-wide verification.
   - Evidence: `progress.md` still declares a two-implementation-worker plus
     one-auditor target and lists every lane as `stopped`, while process
     sampling found watchdog, capacity, dashboard, evaluator, integrator,
     auditor, lane-agent, capacity/artifact agents, two exact root harnesses,
     and a focused markerPDF test process active at the same time.
   - Evidence: `HEAD` moved during this audit from `cddabe76` to
     `b63b8f3459dc`; latest samples showed `767` default
     `git status --short` entries, `88` tracked changed entries, and
     `88 files changed, 19403 insertions(+), 599 deletions(-)`.
   - Required duplicate-root gate: `pgrep -af
     '^php tools/run-tests\.php( |$)'` returned active root PIDs `1281798`
     and `1281936`, so I did not start a duplicate root harness.
   - Owner evidence:

     ```text
     1281798 claude   1225389       00:13 Rs   php tools/run-tests.php
     1281936 claude   1253900       00:10 Ss   php tools/run-tests.php
     ```

   - Audit judgment: freeze active writers and duplicate loops before treating
     any root harness result as an accepted baseline.

2. **High - `porting.html` and `porting-summary.json` are stale and still fail the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:1`, and `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52` require current dashboard fields for benchmark source,
     upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while
     reviewed `HEAD` is `b63b8f3459dc`.
   - Evidence: the table has compound `Benchmark` and `Mapped` columns instead
     of separate `benchmark source`, `upstream denominator`, `mapped tests`,
     and `PHP pass/fail` columns, so consumers cannot validate denominator
     math independently from PHP pass/fail.
   - Evidence: current manifests disagree with the published rows: Difftastic
     `191 / 556-ish` mapped versus dashboard `160 / 417`, Dolt `303 / 613`
     versus `242 / 613`, esbuild `178 / 2567` versus `164 / 2567`,
     Gitoxide `1516 / 2877` versus `1432 / 2877`, libsqlite `173 / 1454`
     versus `149 / 1454`, LightningCSS `845 / 3532` versus `773 / 3532`,
     markerPDF `170 / 78-ish` versus `159 / 78`, Pandoc `506 / 2028`
     versus `426 / 2028`, rclone `345 / 2553` versus `291 / 327`,
     Readability `1164 / 1984` versus `1031 / 1984`, and Syncthing
     `264 / 658` versus `235 / 658`.
   - Audit judgment: the public dashboard is an old publish snapshot, not the
     current coordination surface.

3. **High - `progress.md` is no longer a reliable owner/session or lane coordination record.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:258`-`264`, and `.tmux-team/tmp/*`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:47`, and `goal.md:48` require capped active lanes, current
     owner/session state, restart/finish decisions, and verified integration.
   - Evidence: the Active Lanes table still reports every lane `stopped` with
     early-phase estimates and old next tasks, while active process sampling
     showed running agents for Dolt, rclone, Quadrable, Difftastic, esbuild,
     Gitoxide, Syncthing, LightningCSS, Readability, markerPDF, libsqlite,
     Pandoc, plus integrator/auditor/artifact/capacity loops.
   - Evidence: the dashboard average is `68.8%`, current manifests have much
     larger mapped counts than the Active Lanes estimates imply, and the
     latest `progress.md` audit paragraph still describes prior PID `1246555`
     instead of the current duplicate-root PIDs.
   - Audit judgment: `progress.md` should be regenerated from one frozen
     snapshot after active writers stop, not patched piecemeal from moving
     lane-local status.

4. **High - manifest denominator and runner-evidence schema still cannot support trustworthy portfolio percentages.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     explicit slices for huge suites, and dashboard separation of denominator,
     mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric and sometimes
     prose. Units mix executable files, test functions, BATS cases, repository
     paths, fixture artifacts, helper references, benchmark PDF pairs,
     inspected behavior artifacts, and supplied document excerpts.
   - Evidence: markerPDF reports `mapped=170` against a denominator that
     begins with `78 tracked upstream repository paths`; Quadrable reports
     `mapped=55` against prose also describing 34 scenarios, 29 subcases,
     136 checks, and 20 throw checks; Dolt reports denominator `613`
     executable files while the same field discusses 3,808 BATS cases and
     1,420 Go test/benchmark functions.
   - Evidence: `runnerStatus` is object-shaped in several manifests,
     string-shaped in Gitoxide/markerPDF/Quadrable, and absent/null in Pandoc.
     Consumers cannot consistently distinguish full runner pass, bounded
     runner evidence, static inventory, oracle fixture evidence, and supplied
     boundary evidence.
   - Audit judgment: normalize manifest schema before publishing average
     progress or comparing lane percentages.

5. **High - lane status commit/root-test fields are not reviewable integration evidence.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/markerpdf/lane-status.json`,
     `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`, and `porting.html:54`-`65`.
   - Requirement at risk: `goal.md:29`, `goal.md:31`,
     `goal.md:48`, and `goal.md:49` require small reviewable slices, precise
     blockers, cleanup, and repo-wide failures recorded honestly.
   - Evidence: several `latestCommit` values are prose or dirty-batch labels,
     including `pending local display-option env command-runner slice`,
     `uncommitted lane batch`, `pending lane batch`, `not committed`, and
     dashboard commit cells such as `lane-sc`, `pending`, `current`, and
     `HEAD St`.
   - Evidence: lane files copy root-test state locally and now contradict the
     live process state; examples include lane-local green root claims while
     exact duplicate root harnesses are currently active and the tree is still
     moving.
   - Audit judgment: root status should be recorded once at repo level for one
     frozen snapshot, then referenced by lanes. Lane-local copies are now stale
     and mutually incompatible.

6. **Medium - bounded/static upstream evidence is still easy to misread as full parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require upstream tests as source of truth,
     meaningful fixture parity, hard-feature blockers, and no credit for
     generated/oracle/bridge work as native implementation progress.
   - Evidence: Gitoxide remains bounded Cargo-package evidence rather than
     full workspace pass parity; Difftastic is cloned static inventory;
     markerPDF cannot run the full ML/PDF benchmark stack; Pandoc has no full
     Haskell runner parity; rclone and Dolt rely on bounded runner subsets; and
     Syncthing lacks `go test ./...` parity.
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

Required duplicate-root check before any root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Active result during this audit:

```text
1281798 php tools/run-tests.php
1281936 php tools/run-tests.php
```

Owner evidence:

```text
1281798 claude   1225389       00:13 Rs   php tools/run-tests.php
1281936 claude   1253900       00:10 Ss   php tools/run-tests.php
```

A later exact duplicate-root sample was clear, but active writer/process
sampling and `HEAD` movement still made the tree unstable for an accepted root
baseline.

I did not run `php tools/run-tests.php`.

## Recent Git History

Recent commits reviewed:

```text
b63b8f34 Advance rclone command operation slices
cddabe76 Refresh independent audit status
c236031b Stamp libsqlite lane status
957d3ab8 Advance libsqlite indexed wp_options write plans
0055fd29 Record LightningCSS lane status
1ae44f9f Stamp readability lane status
625f9cdf Advance LightningCSS light-dark fallbacks
e800c009 Advance readability publisher fixture parity
4f2d1199 Record esbuild lane status
cefc9ad3 Advance esbuild TypeScript class lowering
5bdb07ef Refresh independent audit status
b3bf404c Refresh independent audit status
```

## Recommended Next Intervention

Freeze active writers and duplicate loops, then rerun the exact duplicate-root
gate. If it remains empty, capture one quiesced `php tools/run-tests.php` run
from a single accepted snapshot. After that, accept or reject dirty lane batches
one at a time, regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the same snapshot, and normalize
manifest denominator/runner-status fields before publishing percentages.
