# Independent Audit - 2026-05-23T21:31:30Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files for cross-checking,
recent Git history, current worktree state, process state, and the required
duplicate root-harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle
fixtures, and shell-outs are treated as non-progress unless explicitly
temporary oracle tooling.

`jq empty` passed for every lane manifest and every lane-status file.

## Current Snapshot

Sampled `HEAD` moved during this audit from `e2f3ac7081c5` to
`6267ae67ae99`.

Latest sampled worktree/process state:

```text
4986 total git status rows
264 tracked dirty files
264 files changed, 106261 insertions(+), 11754 deletions(-)
115 tmux sessions
127 commits since last sampled implementation commit b75226d1
latest 20 commits are audit/status/integration-hold records
```

The required exact root-harness gate was checked before any possible root run:

```text
1393005 php tools/run-tests.php
1393242 php tools/run-tests.php lanes/syncthing/tests/IndexHandlerTest.php ...
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
1393005 claude  1392826  27      R+   php tools/run-tests.php
1393242 claude  1393056  27      R+   php tools/run-tests.php lanes/syncthing/tests/IndexHandlerTest.php ...
```

Because an exact no-argument root harness was active, I did not start
`php tools/run-tests.php`.

A post-edit handoff gate found another active no-argument root harness:

```text
1448189 php tools/run-tests.php
1455358 php tools/run-tests.php lanes/syncthing/tests/IndexHandlerTest.php ...
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
1448189 claude  1447901  73      R+   php tools/run-tests.php
1455358 claude  1455146  45      R+   php tools/run-tests.php lanes/syncthing/tests/IndexHandlerTest.php ...
```

A post-commit sanity gate found the root harness had rolled again:

```text
1484178 php tools/run-tests.php
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
1484178 claude  1483777  26      R+   php tools/run-tests.php
```

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:20`, `progress.md:25`,
     `progress.md:31` through `progress.md:42`, `.tmux-team/*`, and
     `scripts/run-php-dirty-root.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision,
     reviewable committed slices, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the duplicate-root gate found active no-argument root PID
     `1393005` owned by `claude`, plus a focused Syncthing PHP shard. The
     tree is not quiescent: `git status` reported `4986` rows, `264`
     tracked dirty files, and `264 files changed, 106261 insertions(+),
     11754 deletions(-)`. `tmux list-sessions` reported `115` sessions
     while `progress.md` still documents a 2-worker plus auditor target and
     every Active Lanes row says `stopped`.

2. **Critical - `porting.html` is stale and materially contradicts the current
   manifests.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current coordination files and a
     visible dashboard with denominator, mapped tests, PHP pass/fail, phase,
     audit, blocker, and commit fields.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 04:57:16 UTC` and snapshot `bda83c6b93d4`, while sampled
     `HEAD` is `6267ae67ae99`. Current manifest rows disagree with the
     dashboard: Difftastic is `354 / 713` while the dashboard is `160 / 417`;
     Dolt is `613 mapped` while the dashboard is `242 / 613`; esbuild is
     `295 / 2567` while the dashboard is `164 / 2567`; Gitoxide is
     `2706 / 2877` while the dashboard is `1432 / 2877`; libsqlite is
     `275 / 1589` while the dashboard is `149 / 1454`; markerPDF is
     `267 / 317` while the dashboard is `159 / 78`; Pandoc is `967 / 2276`
     while the dashboard is `426 / 2028`; rclone is `660 / 1601` while the
     dashboard is `291 / 327`; Readability is `1984 / 1984` while the
     dashboard is `1031 / 1984`; Syncthing is `560 / 658` while the dashboard
     is `235 / 658`.

3. **High - lane progress remains mostly pending dirty handoff, not accepted
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
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`,
     `goal.md:48`, and `goal.md:49` require small correct slices, passing
     tests, verified handoff, and committed work.
   - Evidence: sampled `latestCommit` fields say `pending`, `not
     committed`, `uncommitted`, or dirty-batch prose. The latest 20 commits
     are audit/status/integration-hold records, and `HEAD` is 127 commits past
     the last sampled implementation commit `b75226d1`.

4. **High - manifest denominator schemas are still non-normalized and unsafe
   to average.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` is a number in some lanes and
     prose in Difftastic, Dolt, esbuild, Pandoc, and Quadrable. `mapped` mixes
     executable upstream tests, local PHP behavior tests, static source reads,
     supplied-document excerpts, copied fixture inventories, and selected
     oracle probes. That makes the dashboard average and per-lane percentages
     look numeric without a shared unit.

5. **High - near-complete lane percentages overstate full-port parity.**
   - Paths: `lanes/difftastic/lane-status.json:4` through
     `lanes/difftastic/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4` through
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:13`, and
     `lanes/markerpdf/lane-status.json:4` through
     `lanes/markerpdf/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native ports, prohibit counting
     oracle/bridge evidence as progress, and require explicit hard-feature
     gaps.
   - Evidence: Difftastic, rclone, Readability, and Syncthing report `99%`
     despite uncommitted batches, pending aggregate root verification, and
     full upstream runner/provider/model gaps. markerPDF records `267 / 317`
     mapped static behavior/reference units while full Python/PDF/model,
     Streamlit, FastAPI, multiprocessing, OCR, Texify, tabled, and benchmark
     execution remain not executed.

6. **Medium - blocker language still blurs slice-local green checks with
   full-port blockers.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond local passing tests,
     and explicit hard-feature gaps.
   - Evidence: blocker fields begin with `No ... blocker` while the same
     fields admit pending root verification, uncommitted work, unexecuted full
     upstream runners, excluded live providers/servers/model paths, or broad
     parity gaps. Slice-local green status needs a separate field from
     full-port blocker status.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched active no-argument root PID `1393005` owned
by `claude`, plus a focused Syncthing shard. A post-edit handoff gate then
matched active no-argument root PID `1448189`, also owned by `claude`.
After the audit/progress commit, a sanity gate matched active no-argument root
PID `1484178`, also owned by `claude`.
Starting another aggregate root run would duplicate an existing harness and
would not produce an accepted baseline while the worktree remains broad and
non-quiescent.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
