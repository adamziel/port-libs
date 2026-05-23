# Independent Audit - 2026-05-23T22:34Z

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

Sampled repository state:

```text
HEAD: 97076bb69835
6528 total git status rows
271 tracked dirty files
6257 untracked paths
271 files changed, 113773 insertions(+), 11859 deletions(-)
121 tmux sessions
30 sampled worker/status/test-control processes
latest sampled non-audit/status implementation commit: b75226d1
```

The exact required pre-root gate matched an active PHP harness:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
2721631 php tools/run-tests.php lanes/syncthing/tests

ps -o pid,user,ppid,etime,state,comm -p 2721631
2721631 claude 2360785 00:54 R php
```

I did not start `php tools/run-tests.php`: the required gate was not clear, and
the tree is not stable enough for accepted aggregate evidence even aside from
that active focused Syncthing harness.

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:25`,
     `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, and `.tmux-team/*`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, committed
     reviewable slices, integration cleanup, and honest repo-wide verification.
   - Evidence: current samples show `121` tmux sessions, `30`
     worker/status/test-control processes, a matching active PHP harness PID
     `2721631` owned by `claude`, `6528` status rows, `271` tracked dirty
     files, and `271 files changed, 113773 insertions(+), 11859 deletions(-)`.
     This contradicts the documented two-implementation-lane-plus-auditor
     target and the progress table that still reports every lane as `stopped`.

2. **Critical - `progress.md`, `porting.html`, and `porting-summary.json` are
   materially stale relative to the manifests and lane statuses.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`, `porting-summary.json`,
     `progress.md:31` through `progress.md:42`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require durable coordination files and a
     visible dashboard with current denominator, mapped tests, PHP pass/fail,
     phase, audit, blocker, and commit fields.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 04:57:16 UTC` and source snapshot `bda83c6b93d4`, while
     sampled `HEAD` is `97076bb69835`. Current manifest/status values now
     disagree with the dashboard across almost every lane: Difftastic is
     `361 / 727` mapped versus dashboard `160 / 417`; Dolt is `613 / 613`
     versus `242 / 613`; esbuild is `302 / 2567` versus `164 / 2567`;
     libsqlite is `279 / 1589` versus `149 / 1454`; LightningCSS is
     `1725 / 3532` versus `773 / 3532`; markerPDF is `275 / 325` versus
     `159 / 78`; Pandoc is `1018 / 2276` versus `426 / 2028`; rclone is
     `676 / 1601` versus `291 / 327`; Readability is `1984 / 1984` versus
     `1031 / 1984`; Syncthing is `580 / 658` versus `235 / 658`.

3. **High - all current lane progress is still pending dirty handoff, not
   accepted implementation history.**
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
     `goal.md:48` require small correct slices, passing tests, committed
     handoff, and integration cleanup.
   - Evidence: every sampled lane `latestCommit` field is pending,
     uncommitted, not committed, dirty-batch prose, or a non-commit marker such
     as `HEAD ... at status update`. Recent history remains
     audit/status/integration-hold dominated; the latest sampled non-audit/status
     implementation commit is `b75226d1`.

4. **High - near-complete lane percentages overstate full-port parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/difftastic/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/lane-status.json:4`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:4`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, and
     `goal.md:40` require not counting generated/oracle work as native
     progress, meaningful parity beyond passing tests, and explicit hard-feature
     blockers.
   - Evidence: Difftastic claims `99%` while full Cargo runner parity remains
     unavailable; markerPDF claims `98%` while live benchmarks, model workers,
     Streamlit/FastAPI paths, OCR/PDF tooling, and Poetry/model dependencies are
     not executed; Readability claims `99%` and `1984 / 1984` mapped while the
     local native PHP status is only `198` behavior tests and much evidence is
     copied-fixture/upstream-oracle based; Syncthing claims `99%` while full
     `go test ./...` remains unexecuted from a blob-filter/no-checkout cache.

5. **High - manifest denominator, mapped-count, runner, and PHP-pass schemas
   remain non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2247`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:37`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     upstream tests as source of truth, explicit slices, and comparable
     dashboard fields.
   - Evidence: Difftastic stores `benchmarkDenominator.total` as a prose
     string; Pandoc has both a prose `total` and numeric `totalCount`; Dolt has
     `benchmarkDenominator.mapped` at line 14 and a stale prose `total` at line
     2247 that still describes the older `21:56 UTC` slice while the current
     slice at line 16 is `22:13 UTC`. Status files also mix behavior tests and
     assertions: Syncthing reports `phpPass: 4214` against `580 / 658` mapped,
     LightningCSS reports `phpPass: 2164` against `1725 / 3532`, markerPDF
     reports `phpPass: 408` against `275 / 325`, and Readability reports
     `phpPass: 198` while its manifest claims `1984 / 1984`.

6. **Medium - markerPDF and Readability still lean too heavily on static,
   supplied, copied, or oracle evidence for the confidence being reported.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/readability/lane-status.json:5`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, and
     `goal.md:37` require native implementation progress, meaningful fixture
     parity, and upstream tests as the source of truth.
   - Evidence: markerPDF explicitly says native PHP maps supplied/plan-only
     boundaries without Python/model/PDF/OCR execution, yet the lane reports
     `98%`. Readability has a real upstream npm pass, but the local PHP
     confidence combines copied fixture inventory, local JS oracles, and `198`
     PHP behavior tests while presenting complete `1984 / 1984` mapping.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched `2721631 php tools/run-tests.php
lanes/syncthing/tests`, owned by `claude`. The broader stability gate also
failed because active watchdog/capacity/dashboard/evaluator/integrator/auditor
and lane-agent processes persist, there are `121` tmux sessions, and the
worktree remains a large moving dirty aggregate. Starting a fresh root run from
this state would not produce an auditable baseline and could race with ongoing
lane writers/status publishers.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
