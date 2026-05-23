# Independent Audit - 2026-05-23T06:08:20Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
lane status files where needed to check dashboard/status alignment, recent Git
history through `5a0bd45ef8f6`, dirty-tree status, and active test/process
state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a duplicate root harness.
Bridge/generated/oracle tooling is treated as non-progress unless the lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - the repo is not stable enough for a trustworthy root baseline, and another root harness was active.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:251`-`259`, `.tmux-team/tmp/*`,
     `.tmux-team/prompts/*`, `.tmux-team/logs/*`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and `tools/run-tests.php`.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:48`, and `goal.md:49` require a practical concurrency cap,
     accurate current owner/session state, deliberate integration, cleanup, and
     honest repo-wide verification.
   - Evidence: `progress.md` still declares a two-implementation-worker plus
     one-auditor launch target and shows every lane as `stopped`, but process
     sampling found active watchdog/capacity/dashboard/evaluator loops plus
     active lane agents for esbuild, readability, quadrable, markerPDF,
     dolt-runner, LightningCSS, libsqlite, syncthing, pandoc, gitoxide,
     difftastic, auditor, integrator, and rclone.
   - Required duplicate-root gate: `pgrep -af '^php tools/run-tests\.php( |$)'`
     returned an active root harness, so I did not start another. One sample
     was `926388 php tools/run-tests.php`; a later active sample was
     `959908 php tools/run-tests.php`. Owner evidence from `ps`: PID
     `959908`, user `claude`, command `php tools/run-tests.php`.
   - Evidence: dirty status sampled `750` `git status --short` entries, `107`
     tracked changed files, and `107 files changed, 20095 insertions(+), 638
     deletions(-)`.
   - Audit judgment: the next accepted checkpoint must freeze writers and run
     one quiesced root harness from a single accepted snapshot.

2. **High - `porting.html` and `porting-summary.json` are stale and still fail the dashboard contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, and `porting-summary.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52` require current dashboard fields for benchmark source,
     upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: the page still advertises generated time
     `2026-05-23 04:57:16 UTC` and snapshot `bda83c6b93d4`, while current
     `HEAD` is `5a0bd45ef8f6`. The table still exposes `Benchmark` and
     `Mapped` compound columns instead of separate benchmark source,
     denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: current manifests disagree with the page: difftastic is
     `179 / 556` versus dashboard `160 / 417`; Dolt `289 / 613` versus
     `242 / 613`; esbuild `171 / 2567` versus `164 / 2567`; Gitoxide
     `1457 / 2877` versus `1432 / 2877`; libsqlite `169 / 1454` versus
     `149 / 1454`; LightningCSS `836 / 3532` versus `773 / 3532`; markerPDF
     `168 / 78` versus `159 / 78`; Pandoc `495 / 2028` versus `426 / 2028`;
     rclone `321 / 327` versus `291 / 327`; Readability `1130 / 1984` versus
     `1031 / 1984`; Syncthing `256 / 658` versus `235 / 658`. Quadrable is the
     only mapped count still aligned at `55 / 55`.
   - Audit judgment: the dashboard is a frozen publish snapshot, not the
     current coordination surface.

3. **High - lane status files contradict each other about the same root-test state.**
   - Paths: `lanes/esbuild/lane-status.json:10`-`13`,
     `lanes/dolt/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/libsqlite/lane-status.json:10`-`13`,
     `lanes/pandoc/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `lanes/readability/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`, and
     `porting.html:54`-`65`.
   - Requirement at risk: `goal.md:31`, `goal.md:44`, `goal.md:48`, and
     `goal.md:49` require precise blockers, current status, verified
     integration, and honest failure recording.
   - Evidence: some lane statuses claim root green, some claim root pending
     behind older active PIDs, `pandoc` still records a root failure in Dolt,
     and `esbuild` still records a root failure in libsqlite. Meanwhile new
     root harness PIDs `926388` and later `959908` were active during this
     audit. The dashboard then
     publishes older commit/status fields such as `pending`, `current`,
     `HEAD St`, and truncated lane-scoped strings.
   - Audit judgment: root status must be a single repo-level fact captured at
     an accepted snapshot, not copied piecemeal into independent lane prose.

4. **High - the working tree remains a broad aggregate, not small reviewable slices.**
   - Paths: representative dirty surfaces include
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`,
     many `lanes/*/src/*`, many `lanes/*/tests/*`, `porting.html`,
     `porting-summary.json`, `.tmux-team/prompts/*`, and audit artifacts.
   - Requirement at risk: `goal.md:29`, `goal.md:36`, `goal.md:48`, and
     `goal.md:49` require small correct slices, cleanup of unrelated changes,
     verification, and passing repo-wide tests before acceptance.
   - Evidence: the current dirty sample is `750` status entries and `107`
     tracked changed files. Recent Git history also contains a cautionary mixed
     commit, `297a8415`, that bundled audit/progress updates with libsqlite
     implementation files before later audit-only corrections.
   - Audit judgment: no lane batch should be accepted from this aggregate until
     writers stop and the supervisor reviews, tests, and commits or rejects one
     lane slice at a time.

5. **High - manifest denominators still mix incompatible units, so portfolio percentages are not defensible.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, with examples at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`15`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`18`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require real upstream benchmark denominators, explicit slices
     for huge suites, and dashboard separation of denominator, mapped tests,
     and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric and sometimes
     prose. Units mix test files, test functions, BATS cases, repository paths,
     golden fixture pairs, inspected behavior artifacts, benchmark PDF pairs,
     and supplied-boundary examples. `runnerStatus` is object-shaped in many
     lanes, string-shaped in Gitoxide/markerPDF/Quadrable, and absent/null in
     Pandoc.
   - Evidence: markerPDF reports `mapped=168` against a denominator total
     described as `78 tracked upstream repository paths` plus other evidence,
     so its percentage is not mathematically meaningful without typed units.
   - Audit judgment: normalize manifest schema before using average progress or
     lane percentages for planning or publication.

6. **Medium - bounded/static evidence is still easy to misread as full upstream parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:218`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:218`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:322`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:72`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:318`.
   - Requirement at risk: `goal.md:30`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require upstream tests as source of truth, meaningful fixture
     parity, hard-feature blockers, and no credit for generated/oracle/bridge
     work as native progress.
   - Evidence: Gitoxide remains bounded Cargo-package evidence, Difftastic is
     static inventory with no full cargo run, markerPDF cannot execute the full
     ML/PDF benchmark stack, Pandoc has no full Haskell runner parity, rclone
     and Dolt have bounded runner evidence but not full provider/full-Go/full-
     BATS parity, and Syncthing lacks full `go test ./...` parity.
   - Audit judgment: add explicit `full-runner`, `bounded-runner`,
     `static-inventory`, `oracle-fixture`, and `supplied-boundary` status
     fields so evidence type cannot be mistaken for upstream acceptance.

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

Required duplicate-root check before any root run found active root harnesses
during this audit:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Result:

```text
959908 php tools/run-tests.php
```

Owner evidence:

```text
959908 claude ... php tools/run-tests.php
```

I did not run `php tools/run-tests.php` because a root harness was already
active and the tree was not quiescent.

## Recent Git History

Recent commits reviewed:

```text
5a0bd45e Refresh independent audit status
b84cdfac Stamp libsqlite large replacement status
cf5fff72 Advance libsqlite large replacement overflow planning
89b251e7 Refresh independent audit status
b75cdedf Refresh independent audit status
0e312c81 Record esbuild verification counts
d54461d5 Stamp libsqlite lane commit
228941de Refresh independent audit status
297a8415 Update libsqlite lane status commit reference
569f1f89 Port esbuild private static assign semantics
006c18a5 Teach run-tests focused path selection
91b9704a Refresh independent audit status
```

## Recommended Next Intervention

Freeze active writers and duplicate loops, let the active root harness finish
or stop it intentionally, then capture one quiesced `php tools/run-tests.php`
run from a single accepted snapshot. After that, accept or reject dirty lane
batches one at a time, regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the same snapshot, and normalize
manifest denominator/runner-status fields before publishing percentages.
