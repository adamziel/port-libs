# Independent Audit - 2026-05-23T06:25:38Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, selected
lane status files needed to check dashboard/status alignment, recent Git
history through `d2ef0ab777f8`, dirty-tree status, active process/test state,
and the PHP shell-out surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - the repo is still an active moving target, so no root result can be accepted.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:253`-`261`, `.tmux-team/tmp/*`,
     `.tmux-team/prompts/*`, `.tmux-team/logs/*`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and `tools/run-tests.php`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:48`, and `goal.md:49` require a practical concurrency cap,
     accurate owner/session state, deliberate integration/cleanup, and honest
     repo-wide verification.
   - Evidence: `progress.md` still declares a launch target of two
     implementation workers plus one auditor and lists every lane as
     `stopped`, while process sampling found watchdog, capacity, dashboard,
     evaluator, auditor, integrator, artifact/capacity shards, and active lane
     agents for Dolt, Pandoc, Gitoxide, Difftastic, rclone, libsqlite,
     LightningCSS, Readability, Quadrable, Syncthing, and markerPDF.
   - Evidence: the latest dirty sample is `763` `git status --short` entries,
     `99` tracked changed files, and
     `99 files changed, 19522 insertions(+), 640 deletions(-)`.
   - Required duplicate-root gate: at 06:25:38Z
     `pgrep -af '^php tools/run-tests\.php( |$)'` returned
     active root `1194670 php tools/run-tests.php`, so I did not start a
     duplicate root harness. Owner evidence from
     `ps -o pid=,user=,etimes=,args= -p 1194670` was
     `1194670 claude 46 php tools/run-tests.php`. The owner-restricted probe
     also saw focused lane PID `1206199 php tools/run-tests.php
     lanes/quadrable/tests`.
   - Audit judgment: freeze active writers and duplicate loops before using any
     root result as an accepted baseline.

2. **High - `porting.html` and `porting-summary.json` are stale and still fail the dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, and `porting-summary.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52` require current dashboard fields for benchmark source,
     upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: the page still advertises generated time
     `2026-05-23 04:57:16 UTC` and snapshot `bda83c6b93d4`, while reviewed
     `HEAD` is `d2ef0ab777f8`. The table still collapses the required
     benchmark source, denominator, mapped tests, and PHP pass/fail fields into
     compound `Benchmark` and `Mapped` columns.
   - Evidence: current manifests disagree with the page: Difftastic is
     `184 / 556` versus dashboard `160 / 417`; Dolt `298 / 613` versus
     `242 / 613`; esbuild `174 / 2567` versus `164 / 2567`; Gitoxide
     `1480 / 2877` versus `1432 / 2877`; libsqlite `171 / 1454` versus
     `149 / 1454`; LightningCSS `843 / 3532` versus `773 / 3532`; markerPDF
     `169 / 78` versus `159 / 78`; Pandoc `503 / 2028` versus `426 / 2028`;
     rclone `329 / 327` versus `291 / 327`; Readability `1147 / 1984` versus
     `1031 / 1984`; Syncthing `261 / 658` versus `235 / 658`.
   - Audit judgment: this dashboard is an old publish snapshot, not the
     current coordination surface.

3. **High - lane status files contradict each other about root verification.**
   - Paths: `lanes/dolt/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/pandoc/lane-status.json:10`-`13`,
     `lanes/quadrable/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`, and
     `progress.md:253`-`261`.
   - Requirement at risk: `goal.md:31`, `goal.md:44`, and
     `goal.md:49` require precise blockers, current status, and honest
     repo-wide failure recording.
   - Evidence: Dolt records a root pass with `186` files and `20050`
     assertions; Gitoxide records a root failure with `17` failures; Pandoc
     records root failures outside its lane in Syncthing; Quadrable records
     root failures in Difftastic; Syncthing records root failures in Quadrable;
     rclone records a skipped root run due PID `1076399`.
   - Audit judgment: root status should be recorded once at repo level for one
     frozen snapshot, then referenced by lanes. Copying lane-local root prose
     creates false green and stale red signals at the same time.

4. **High - manifest denominator and mapped-test percentages remain mathematically indefensible.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`18`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`18`, and
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     explicit slices for huge suites, and dashboard separation of denominator,
     mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric and sometimes
     prose. Units mix files, test functions, helper invocations, BATS cases,
     repository paths, inspected behavior artifacts, benchmark PDF pairs,
     supplied document excerpts, and native behavior boundaries.
   - Evidence: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15` reports
     `mapped=329` against `total=327`, and
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`18` reports
     `mapped=169` against a denominator beginning with `78 tracked upstream
     repository paths` plus benchmark pairs/excerpts. These cannot support
     percent-complete math.
   - Evidence: `runnerStatus` is object-shaped in several lanes,
     string-shaped in Gitoxide/markerPDF/Quadrable, and absent/null in Pandoc.
     Consumers cannot consistently distinguish full runner pass, bounded runner
     evidence, static inventory, oracle fixture evidence, and supplied-boundary
     evidence.
   - Audit judgment: normalize manifest schema before publishing portfolio
     percentages or average progress.

5. **High - the working tree remains a broad aggregate, not small reviewable slices.**
   - Paths: dirty surfaces include `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, many `lanes/*/src/*`,
     many `lanes/*/tests/*`, `lanes/*/examples/*`,
     `lanes/*/fixtures/*`, `lanes/*/notes/*`, `porting.html`,
     `porting-summary.json`, `.tmux-team/prompts/*`, and audit artifacts.
   - Requirement at risk: `goal.md:29`, `goal.md:36`,
     `goal.md:48`, and `goal.md:49` require small reviewable slices,
     cleanup of unrelated changes, verification, and passing repo-wide tests
     before acceptance.
   - Evidence: `git diff --name-only` over lane/status/dashboard surfaces
     still reports `92` changed files, including implementation files for
     Difftastic, esbuild, Gitoxide, libsqlite, LightningCSS, markerPDF, Pandoc,
     Quadrable, rclone, Readability, and Syncthing. Recent history also
     advanced during this audit from `198400b7` to `5f70c87b` to `d2ef0ab7`.
   - Audit judgment: accept or reject one lane batch at a time after active
     writers are stopped and one root harness passes from that frozen state.

6. **Medium - bounded/static evidence is still easy to misread as full upstream parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`16`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require upstream tests as the source of
     truth, meaningful fixture parity, hard-feature blockers, and no credit for
     generated/oracle/bridge work as native progress.
   - Evidence: Gitoxide remains bounded Cargo-package evidence rather than
     full workspace pass parity; Difftastic is cloned static inventory;
     markerPDF cannot execute the full ML/PDF benchmark stack; Pandoc has no
     full Haskell runner parity; rclone/Dolt rely on bounded runner subsets;
     Syncthing lacks full `go test ./...` parity.
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

Latest active result during this audit/edit window:

```text
1194670 php tools/run-tests.php
```

Owner evidence:

```text
1194670 claude        46 php tools/run-tests.php
```

I did not run `php tools/run-tests.php`. The owner-restricted probe also saw
focused lane PID `1206199 php tools/run-tests.php lanes/quadrable/tests`. The
active root harness, active writer/process sample, and broad dirty aggregate
made the tree unstable for an accepted root baseline.

## Recent Git History

Recent commits reviewed:

```text
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
b75cdedf Refresh independent audit status
```

## Recommended Next Intervention

Freeze active writers and duplicate loops, then rerun the exact duplicate-root
gate. If it remains empty, capture one quiesced `php tools/run-tests.php` run
from a single accepted snapshot. After that, accept or reject dirty lane batches
one at a time, regenerate `progress.md`, `porting.html`, `porting-summary.json`,
and lane statuses from the same snapshot, and normalize manifest
denominator/runner-status fields before publishing percentages.
