# Independent Audit - 2026-05-23T22:14Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files for cross-checking, recent Git history, current worktree/process
state, and the required duplicate root-harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

Sampled `HEAD` moved during the audit:

```text
initial: ba7a34a5f12b
final:   c520092f057a
```

Latest sampled worktree/process state:

```text
6307 total git status rows
271 tracked dirty files
271 files changed, 112519 insertions(+), 11882 deletions(-)
121 tmux sessions
69 active repo worker/status/test-control process matches
143 commits since latest sampled non-audit/status implementation commit b75226d1
```

The exact root-harness gate was checked before any possible root run:

```text
2365289 php tools/run-tests.php
2365853 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
2365919 php tools/run-tests.php lanes/syncthing/tests/IgnoreMatcherTest.php ...
2365980 php tools/run-tests.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php ...
```

Owner evidence:

```text
PID      USER    PPID     ELAPSED STAT COMMAND
2365289  claude  2365191  15      R+   php tools/run-tests.php
2365853  claude  2365574  14      R+   php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
2365919  claude  2365702  14      R+   php tools/run-tests.php lanes/syncthing/tests/IgnoreMatcherTest.php ...
2365980  claude  2365754  14      R+   php tools/run-tests.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php ...
```

Because an active no-argument root harness was present, I did not start
`php tools/run-tests.php`.

A post-commit sanity gate still matched active no-argument root harnesses:

```text
2414167 claude  2367559  30  Rs  php tools/run-tests.php
2416261 claude  2416019  22  R+  php tools/run-tests.php
```

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:25`,
     `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `.tmux-team/*`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision,
     reviewable committed slices, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the required pre-root gate found active no-argument root PID
     `2365289` owned by `claude`, plus focused Syncthing shards. The repo is
     not quiescent: `HEAD` moved from `ba7a34a5f12b` to `c520092f057a`, and
     latest samples show `121` tmux sessions, `69` active worker/status/test
     matches, `6307` status rows, and `271` tracked dirty files. This
     contradicts `progress.md:25` documenting a two-worker-plus-auditor target
     and `progress.md:31` through `progress.md:42` reporting every lane as
     `stopped`.

2. **Critical - the dashboard and progress table are materially stale.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54`, `porting.html:57`, `porting.html:60`,
     `porting.html:63`, `porting.html:65`, `porting-summary.json`,
     `progress.md:31` through `progress.md:42`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require durable coordination files and a
     visible dashboard with current denominator, mapped tests, PHP pass/fail,
     phase, audit, blocker, and commit fields.
   - Evidence: `porting.html` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and source snapshot
     `bda83c6b93d4`, while sampled `HEAD` is `c520092f057a`. Current
     manifest/status values disagree with the dashboard: Difftastic is now
     `361 / 727` mapped versus dashboard `160 / 417`, Gitoxide `2712 / 2877`
     versus `1432 / 2877`, markerPDF `274 / 324` versus `159 / 78`, rclone
     `672 / 1601` versus `291 / 327`, and Syncthing `580 / 658` versus
     `235 / 658`. The progress table is also stale: it still shows stopped
     lanes with old estimates such as Gitoxide `66%` and esbuild `8%`, while
     current lane statuses claim `88%` to `99%`.

3. **High - all current lane progress is pending dirty handoff, not accepted
   implementation history.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, and
     `goal.md:48` require small correct slices, meaningful parity, passing
     tests, committed handoff, and integration cleanup.
   - Evidence: every sampled lane `latestCommit` field says `pending`,
     `uncommitted`, `not committed`, or equivalent deferral language. Recent
     Git history is still audit/status/integration-hold dominated: the latest
     sampled non-audit/status implementation commit is `b75226d1`, `143`
     commits behind current `HEAD`.

4. **High - denominator, mapped, and PHP pass-count schemas remain
   non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2241`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:37`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     upstream tests as source of truth, and comparable dashboard fields.
   - Evidence: Difftastic, esbuild, Pandoc, and Quadrable store
     `benchmarkDenominator.total` as prose strings; Dolt's current manifest
     has `mapped` at denominator line 14 and a prose `total` thousands of
     lines later. Lane statuses mix behavior counts and assertion counts:
     Syncthing status reports `phpPass: 4214` while its manifest maps
     `580 / 658`, Gitoxide reports `phpPass: 5526` against `2712 / 2877`,
     markerPDF reports `phpPass: 408` against `274 / 324`, Quadrable reports
     `phpPass: 184` against a 55-path upstream denominator, and Readability
     reports `phpPass: 197` while its manifest claims `1984 / 1984` mapped.
     The dashboard cannot safely compute average progress or compare lane
     coverage from these fields.

5. **Medium - near-complete lane percentages overstate full-port parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/difftastic/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:4`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:4`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/lane-status.json:4`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:4`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, and
     `goal.md:40` require not counting non-native/generated/oracle work as
     progress, meaningful fixture parity, and explicit hard-feature blockers.
   - Evidence: multiple lanes claim `98%` or `99%` while their blockers still
     admit unexecuted full upstream runners, unexecuted root verification,
     blob-filter/no-checkout caches, live provider/service requirements, heavy
     dependency/model downloads, or copied/local oracle fixture coverage.
     MarkerPDF explicitly leaves live benchmark/app/server/model execution
     unrun; Readability's full upstream fixture mapping is mostly copied
     Mozilla fixtures plus local oracle parity; Difftastic has no full upstream
     runner parity; Syncthing and libsqlite still defer broad upstream suites.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched active no-argument root PID `2365289` owned
by `claude`, plus focused Syncthing PHP shards. The tree was also not stable
enough for an accepted aggregate run because active lane agents,
dashboard/evaluator/watchdog/capacity/integrator loops, focused PHP shards,
121 tmux sessions, and a 271-file tracked dirty tree were present.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
