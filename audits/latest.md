# Independent Audit - 2026-05-23T06:31:44Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status
files needed to check status alignment, recent Git history through
`b3bf404c22f5`, dirty-tree status, active process/test state, and the PHP
shell-out surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - the repo is still being written by many active loops, so no root result can be accepted.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:254`-`262`, `.tmux-team/tmp/*`,
     `.tmux-team/prompts/*`, `.tmux-team/logs/*`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and `tools/run-tests.php`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:48`, and `goal.md:49` require a practical concurrency cap,
     accurate owner/session state, deliberate integration/cleanup, and honest
     repo-wide verification.
   - Evidence: `progress.md` still declares a two-implementation-worker plus
     one-auditor target and lists every lane as `stopped`, while process
     sampling found watchdog, capacity, dashboard, evaluator, auditor,
     integrator, and lane agents for Dolt, Quadrable, Difftastic, rclone,
     Gitoxide, Syncthing, esbuild, LightningCSS, Readability, markerPDF,
     libsqlite, and Pandoc.
   - Evidence: the latest samples showed `794` default `git status --short`
     entries, `104` tracked changed files, `812` untracked-all entries, and
     `104 files changed, 22117 insertions(+), 847 deletions(-)`.
   - Required duplicate-root gate: `pgrep -af
     '^php tools/run-tests\.php( |$)'` returned active root PID `1231526`
     (`1231526 claude 17 php tools/run-tests.php`) during the audit, so I did
     not start a duplicate root harness. A later process sample showed another
     active root PID `1235480` (`1235480 claude 11 php tools/run-tests.php`).
     The final exact duplicate-root gate was clear, but the active writer
     loops and broad dirty aggregate still made a root run unsuitable.
   - Audit judgment: freeze active writers and duplicate loops before treating
     any root harness result as an accepted baseline.

2. **High - `porting.html` and `porting-summary.json` are stale and still do not meet the dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, and `porting-summary.json:1`-`40`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52` require current dashboard fields for benchmark source,
     upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: the page and summary still advertise generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while
     reviewed `HEAD` is `b3bf404c22f5`.
   - Evidence: the dashboard still collapses required `benchmark source`,
     `upstream denominator`, `mapped tests`, and `PHP pass/fail` into compound
     `Benchmark` and `Mapped` columns, so consumers cannot compare denominator
     units independently from PHP pass/fail.
   - Evidence: current manifests disagree with the page: Difftastic is
     `188 / 556` versus dashboard `160 / 417`; Dolt `298 / 613` versus
     `242 / 613`; esbuild `176 / 2567` versus `164 / 2567`; Gitoxide
     `1480 / 2877` versus `1432 / 2877`; libsqlite `172 / 1454` versus
     `149 / 1454`; LightningCSS `843 / 3532` versus `773 / 3532`; markerPDF
     `169 / 78-ish` versus `159 / 78`; Pandoc `504 / 2028` versus
     `426 / 2028`; rclone `336 / 2553` versus `291 / 327`; Readability
     `1147 / 1984` versus `1031 / 1984`; Syncthing `264 / 658` versus
     `235 / 658`.
   - Audit judgment: this is an old publish snapshot, not the current
     coordination surface.

3. **High - lane status files contradict each other about root verification and commit state.**
   - Paths: `lanes/difftastic/lane-status.json:10`-`13`,
     `lanes/dolt/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/libsqlite/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`13`,
     `lanes/quadrable/lane-status.json:10`-`13`,
     `lanes/readability/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`, and
     `progress.md:254`-`262`.
   - Requirement at risk: `goal.md:31`, `goal.md:44`, and
     `goal.md:49` require precise blockers, current status, and honest
     repo-wide failure recording.
   - Evidence: lane files simultaneously claim root green, root red in
     unrelated lanes, root pending due active PIDs, and uncommitted lane batches.
     Examples include Quadrable claiming a green root run, Syncthing reporting
     Quadrable missing-method root failures, Dolt reporting rclone root
     failures, Difftastic reporting Gitoxide root failures, and Gitoxide
     reporting a 17-failure root run.
   - Evidence: several `latestCommit` fields are prose such as `pending lane
     batch`, `not committed`, or `current repository HEAD`, while
     `goal.md:45` requires a usable commit column.
   - Audit judgment: root status should be recorded once at repo level for one
     frozen snapshot, then referenced by lanes. Lane-local copies are now stale
     and mutually incompatible.

4. **High - manifest denominator and runner-evidence schema remain mathematically unreliable.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`18`, and
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`-`15`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     explicit slices for huge suites, and dashboard separation of denominator,
     mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is sometimes a number and
     sometimes prose. Units mix executable files, test functions, BATS cases,
     repository paths, fixtures, helper references, benchmark PDF pairs,
     inspected behavior artifacts, and supplied document excerpts.
   - Evidence: markerPDF still reports `mapped=169` against a denominator that
     begins with `78 tracked upstream repository paths`, and Quadrable reports
     `mapped=55` against a prose denominator that also contains 34 scenarios,
     29 subcases, 136 checks, and 20 throw checks. Those values cannot support
     portfolio percentage math without separate numeric fields for each unit.
   - Evidence: runner evidence is object-shaped in several manifests,
     string-shaped in Gitoxide/markerPDF/Quadrable, and absent/null in Pandoc.
     Consumers cannot consistently distinguish full runner pass, bounded
     runner evidence, static inventory, oracle fixture evidence, and supplied
     boundary evidence.
   - Audit judgment: normalize manifest schema before publishing average
     progress or comparing lane percentages.

5. **High - the working tree is a broad aggregate, not small reviewable slices.**
   - Paths: dirty surfaces include `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, many `lanes/*/src/*`,
     many `lanes/*/tests/*`, `lanes/*/examples/*`,
     `lanes/*/fixtures/*`, `lanes/*/notes/*`, `porting.html`,
     `porting-summary.json`, `.tmux-team/prompts/*`, and audit artifacts.
   - Requirement at risk: `goal.md:29`, `goal.md:36`,
     `goal.md:48`, and `goal.md:49` require small reviewable slices, cleanup of
     unrelated changes, verification, and passing repo-wide tests before
     acceptance.
   - Evidence: `git diff --name-only` reports `104` tracked changed files,
     including implementation files for Difftastic, esbuild, Gitoxide,
     libsqlite, LightningCSS, markerPDF, Pandoc, Quadrable, rclone,
     Readability, and Syncthing. Recent history also mixed implementation
     commits and audit/status commits immediately before this review.
   - Audit judgment: stop active writers, then accept or reject one lane batch
     at a time from a single frozen root-test result.

6. **Medium - bounded/static upstream evidence is still easy to misread as full parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
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
1231526 php tools/run-tests.php
```

Owner evidence:

```text
1231526 claude        17 php tools/run-tests.php
```

Later active root sample:

```text
1235480 php tools/run-tests.php
1235480 claude        11 php tools/run-tests.php
```

Final exact duplicate-root sample before closing this audit was clear, but the
tree was still not stable enough for a trustworthy root run.

I did not run `php tools/run-tests.php`. The active root harnesses, active
writer/process sample, and broad dirty aggregate made the tree unstable for an
accepted root baseline.

## Recent Git History

Recent commits reviewed:

```text
b3bf404c Refresh independent audit status
d2ef0ab7 Stamp Dolt lane status
5f70c87b Port Dolt merge result rows
198400b7 Refresh independent audit status
df56249e Refresh independent audit status
a8dd5bb3 Record LightningCSS lane status
db0c8fe3 Port Syncthing platform xattr metadata boundaries
4ffd2048 Advance LightningCSS minifier slices
5a0bd45e Refresh independent audit status
b84cdfac Stamp libsqlite large replacement status
cf5fff72 Advance libsqlite large replacement overflow planning
89b251e7 Refresh independent audit status
```

## Recommended Next Intervention

Freeze active writers and duplicate loops, then rerun the exact duplicate-root
gate. If it remains empty, capture one quiesced `php tools/run-tests.php` run
from a single accepted snapshot. After that, accept or reject dirty lane batches
one at a time, regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the same snapshot, and normalize
manifest denominator/runner-status fields before publishing percentages.
