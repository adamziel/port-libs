# Independent Audit - 2026-05-23T06:39:19Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status
files needed to check status alignment, recent Git history through
`c236031b6153`, dirty-tree status, active process/test state, and the PHP
shell-out surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - the repo is still non-quiescent, and root testing cannot produce an accepted baseline.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:257`-`263`, `scripts/run-team-watchdog.sh`,
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
     auditor, lane agents, capacity agents, and a root `php tools/run-tests.php`
     process active at the same time.
   - Evidence: latest samples showed `765` default `git status --short`
     entries, `86` tracked changed entries, and `86 files changed, 21137
     insertions(+), 694 deletions(-)`.
   - Required duplicate-root gate: `pgrep -af
     '^php tools/run-tests\.php( |$)'` returned active root PID `1246555`, so I
     did not start a duplicate root harness. A later exact gate was clear, but
     `HEAD` and the dirty tree continued moving under active agents, so the tree
     still was not stable enough for a trustworthy root run.
   - Owner evidence:

     ```text
     1246555 claude   1188838       00:12 Rs   php tools/run-tests.php
     ```

   - Audit judgment: freeze active writers and duplicate loops before treating
     any root harness result as an accepted baseline.

2. **High - `porting.html` and `porting-summary.json` are stale and still fail the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:1`, and `lanes/*/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52` require current dashboard fields for benchmark source,
     upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while
     reviewed `HEAD` is `c236031b6153`.
   - Evidence: the table has compound `Benchmark` and `Mapped` columns instead
     of separate `benchmark source`, `upstream denominator`, `mapped tests`, and
     `PHP pass/fail` columns, so consumers cannot validate denominator math
     independently from PHP pass/fail.
   - Evidence: current manifest samples disagree with the published dashboard:
     Difftastic `188 / 556-ish` versus `160 / 417`, Dolt `303 / 613` versus
     `242 / 613`, esbuild `176 / 2567` versus `164 / 2567`, Gitoxide
     `1515 / 2877` versus `1432 / 2877`, libsqlite `173 / 1454` versus
     `149 / 1454`, LightningCSS `845 / 3532` versus `773 / 3532`,
     markerPDF `170 / 78-ish` versus `159 / 78`, Pandoc `506 / 2028`
     versus `426 / 2028`, rclone `345 / 2553` versus `291 / 327`,
     Readability `1164 / 1984` versus `1031 / 1984`, and Syncthing
     `264 / 658` versus `235 / 658`.
   - Audit judgment: the public dashboard is an old publish snapshot, not the
     current coordination surface.

3. **High - lane status files contradict each other about root verification and commit state.**
   - Paths: `lanes/difftastic/lane-status.json:10`-`13`,
     `lanes/dolt/lane-status.json:10`-`13`,
     `lanes/esbuild/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/quadrable/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `lanes/readability/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`, and
     `progress.md:257`-`263`.
   - Requirement at risk: `goal.md:29`, `goal.md:31`,
     `goal.md:44`, and `goal.md:49` require small verified slices, precise
     blockers, current owner/session state, and honest repo-wide failure
     recording.
   - Evidence: lane files simultaneously claim root green, root red in
     unrelated lanes, root pending because another process was active, and
     uncommitted lane batches. Examples include Difftastic reporting an
     unrelated Gitoxide root failure, Dolt and esbuild reporting a green root
     run, rclone and Syncthing reporting active-root pending state, Readability
     reporting an unrelated Quadrable root failure, and Quadrable claiming a
     green root run while still describing a pending lane batch.
   - Evidence: several `latestCommit` values are not usable commit identifiers:
     examples include `pending lane batch`, `not committed`, `pending
     lane-scoped commit`, and dashboard row values such as `lane-sc`,
     `pending`, `current`, and `HEAD St`.
   - Audit judgment: root status should be recorded once at repo level for one
     frozen snapshot, then referenced by lanes. Lane-local copies are now stale
     and mutually incompatible.

4. **High - manifest denominator and runner-evidence schema still cannot support trustworthy portfolio percentages.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     and `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     explicit slices for huge suites, and dashboard separation of denominator,
     mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric and sometimes
     prose. Units mix executable files, test functions, BATS cases, repository
     paths, fixture artifacts, helper references, benchmark PDF pairs,
     inspected behavior artifacts, and supplied document excerpts.
   - Evidence: markerPDF reports `mapped=170` against a denominator that begins
     with `78 tracked upstream repository paths`; Quadrable reports `mapped=55`
     against a prose denominator that also describes 34 scenarios, 29 subcases,
     136 checks, and 20 throw checks; Dolt reports denominator `613` executable
     files while the same field discusses 3,808 BATS cases and 1,420 Go
     test/benchmark functions.
   - Evidence: runner evidence is object-shaped in several manifests,
     string-shaped in Gitoxide/markerPDF/Quadrable, and absent/null in Pandoc.
     Consumers cannot consistently distinguish full runner pass, bounded runner
     evidence, static inventory, oracle fixture evidence, and supplied-boundary
     evidence.
   - Audit judgment: normalize manifest schema before publishing average
     progress or comparing lane percentages.

5. **High - the working tree is a broad aggregate, not small reviewable slices.**
   - Paths: dirty surfaces include `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, many `lanes/*/src/*`,
     many `lanes/*/tests/*`, many `lanes/*/examples/*`,
     many `lanes/*/fixtures/*`, `lanes/*/notes/*`, `porting.html`,
     `porting-summary.json`, `.tmux-team/prompts/*`, and audit artifacts.
   - Requirement at risk: `goal.md:29`, `goal.md:36`,
     `goal.md:48`, and `goal.md:49` require small reviewable slices, cleanup of
     unrelated changes, verification, and passing repo-wide tests before
     acceptance.
   - Evidence: recent history is still interleaving implementation commits,
     one-line lane-status commits, and audit/status commits. The latest commits
     reviewed include `c236031b Stamp libsqlite lane status`, `957d3ab8
     Advance libsqlite indexed wp_options write plans`, `0055fd29 Record
     LightningCSS lane status`, `1ae44f9f Stamp readability lane status`,
     `625f9cdf Advance LightningCSS light-dark fallbacks`, and `e800c009
     Advance readability publisher fixture parity`.
   - Evidence: the current dirty tracked set spans implementation files for
     Difftastic, Gitoxide, libsqlite, LightningCSS, markerPDF, Pandoc,
     Quadrable, rclone, Readability, and Syncthing, plus generated/status
     surfaces.
   - Audit judgment: stop active writers, then accept or reject one lane batch
     at a time from a single frozen root-test result.

6. **Medium - bounded/static upstream evidence is still easy to misread as full parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`16`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
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
1246555 php tools/run-tests.php
```

Owner evidence:

```text
1246555 claude   1188838       00:12 Rs   php tools/run-tests.php
```

Final exact duplicate-root sample before closing this audit was clear, but
active writer/process sampling and continued `HEAD` movement still made the tree
unstable for an accepted root baseline.

I did not run `php tools/run-tests.php`.

## Recent Git History

Recent commits reviewed:

```text
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
d2ef0ab7 Stamp Dolt lane status
5f70c87b Port Dolt merge result rows
198400b7 Refresh independent audit status
df56249e Refresh independent audit status
a8dd5bb3 Record LightningCSS lane status
db0c8fe3 Port Syncthing platform xattr metadata boundaries
4ffd2048 Advance LightningCSS minifier slices
5a0bd45e Refresh independent audit status
```

## Recommended Next Intervention

Freeze active writers and duplicate loops, then rerun the exact duplicate-root
gate. If it remains empty, capture one quiesced `php tools/run-tests.php` run
from a single accepted snapshot. After that, accept or reject dirty lane batches
one at a time, regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the same snapshot, and normalize
manifest denominator/runner-status fields before publishing percentages.
