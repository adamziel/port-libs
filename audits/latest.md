# Independent Audit - 2026-05-24T00:13Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle
fixtures, and shell-outs are treated as non-progress unless explicitly
temporary oracle tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
observed HEAD movement during audit: d66a2680 -> f3108e404b1a
latest visible commits: f3108e40 Record integration hold status; d66a2680 Refresh independent audit status; bd8e4a85 Record integration hold status
commits since 2026-05-23 00:00 UTC: 774
tracked dirty rows: 284
total status rows including untracked: 8912
git diff HEAD --shortstat: 284 files changed, 125113 insertions(+), 12555 deletions(-)
tmux sessions: 143
active repo worker/test-control processes sampled: 35
exact pre-root gate: PID 833257 matched php tools/run-tests.php with focused Syncthing arguments
owner evidence: 833257 claude 832948 00:54 R+ php tools/run-tests.php lanes/syncthing/tests/PullJobQueueTest.php ...
```

No root run was started. The required exact duplicate-root probe matched an
active focused PHP shard owned by `claude`, and the tree was not stable enough
for a trustworthy aggregate signal: `HEAD` had moved during the audit, primary
lane/watchdog/dashboard/evaluator/integrator/capacity/dependency loops were
active, `progress.md` still reports all lanes stopped, and the worktree remains
a broad dirty aggregate.

## Findings

1. **Critical - the repository is still not in a stable integration state, so
   a root test run would be a moving-target signal.**
   - Paths: `progress.md:33`, `progress.md:35` through `progress.md:50`,
     `.tmux-team/`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-capacity-executor-queue.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable commits with passing tests, integration cleanup, and honest
     repo-wide verification.
   - Evidence: the documented launch target is still 2 implementation lanes
     plus one auditor, and the Active Lanes table says all 12 lanes are
     `stopped`. Current sampling found 143 tmux sessions, 35 active repo
     worker/test-control processes, 284 tracked dirty rows, and 8912 total
     status rows. The required exact pre-root probe matched PID `833257`
     owned by `claude` running a focused Syncthing `php tools/run-tests.php`
     shard, so no root harness was started.

2. **Critical - the dashboard/status snapshot is stale and contradicts current
   manifests while presenting 97.7% average progress.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `progress.md:35` through `progress.md:50`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current denominator, mapped-test, PHP pass/fail,
     WordPress scenario, phase, audit, current work, blocker, and commit
     fields in `progress.md` and `porting.html`.
   - Evidence: `porting.html` and `porting-summary.json` still publish
     generated snapshot `79768df0c427` from `2026-05-23 23:43:54 UTC`, while
     current `HEAD` is `f3108e404b1a` and those generated files are dirty.
     Current manifests have moved past dashboard rows: Difftastic `378` mapped
     vs dashboard `374`, esbuild `315` vs `311`, Gitoxide `2759` vs `2751`,
     libsqlite `288` vs `286`, LightningCSS `1735` vs `1732`, markerPDF
     `283/333` vs `280/330`, Pandoc `1083` vs `1061`, rclone `709` vs `698`,
     and Readability PHP behavior tests have advanced beyond the dashboard's
     `204` pass count. `progress.md` still reports stopped lane estimates of
     5-66%, while the dashboard reports 92-99% per lane.

3. **High - every lane still reports pending or uncommitted handoff state
   instead of accepted implementation commits.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:10` through `porting-summary.json:211`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small
     reviewable slices with passing tests, then verification, commit,
     progress update, cleanup, and reassignment.
   - Evidence: dashboard commit fields remain `pending`, `uncommi`, `not com`,
     or dirty-batch prose. Lane status files still have `latest_commit: null`.
     Recent history is dominated by audit/status/integration-hold commits,
     while implementation files remain mixed across 284 dirty tracked files
     and thousands of untracked files.

4. **High - near-complete percentages overstate accepted native upstream
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:8`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:40` require real upstream
     denominators, meaningful fixture parity, upstream tests as source of
     truth, explicit slices, and blockers for hard features.
   - Evidence: the dashboard reports 97.7% average progress even though many
     lanes remain bounded-slice ports without accepted full-runner parity:
     esbuild maps 315/2567, libsqlite maps 288/1589, Pandoc maps 1083/2276
     without Cabal parity, rclone maps 709/1601 with live provider/mount
     suites still excluded, Gitoxide maps 2759/2877 without full Cargo
     workspace pass, Difftastic still lacks full upstream runner parity, and
     markerPDF records a static behavior denominator because upstream has no
     committed Python test files and heavy PDF/model/server workflows remain
     unexecuted. These are useful slices, but they should not read as
     near-finished native ports.

5. **High - essential optional-library coverage remains backlog-only, and some
   dependency candidates are too broad to accept as implementation progress.**
   - Paths: `progress.md:17` through `progress.md:23`,
     `dependency-backlog.json:7` through `dependency-backlog.json:421`,
     `porting.html:71` through `porting.html:90`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require
     rich document/runtime behavior, native implementation, real denominators,
     no shell-out progress credit, and explicit hard blockers.
   - Evidence: the backlog has 22 rows, 12 `candidate` and 10 `deferred`, but
     no optional dependency has a dependency-specific manifest, accepted
     upstream/spec denominator, PHP pass/fail record, owner, commit, or
     dashboard lane. Rich gaps remain for Pandoc ZIP/DOCX/DOC/EPUB/ODT/
     citation/math, markerPDF PDF text/render/OCR/table behavior, esbuild
     source maps, Syncthing protobuf/BEP wire compatibility, Difftastic
     tree-sitter/encoding behavior, and rclone provider metadata/checksum/
     archive behavior. Cross-lane rows such as XML/HTML5 DOM, Unicode repair,
     charset, checksum, archive/compression, tree-sitter, SQL/storage codecs,
     glob/pathspec, and provider metadata normalization need bounded manifests
     by spec, algorithm, provider, or fixture family before progress credit.

6. **Medium - blocker fields still mix slice-local green tests with full-port
   blockers.**
   - Paths: `lanes/*/lane-status.json`, especially blocker fields that start
     with "No current implementation blocker", "No focused blocker", or
     "No lane-local PHP blocker".
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: blocker fields frequently lead with local green status while
     the same fields list unexecuted full upstream runners, live provider
     suites, external model/runtime stacks, broad dependency graphs, and
     pending aggregate root verification. That makes full-port blockers read
     like lane-local success notes.

7. **Medium - manifest and status schemas remain non-normalized and still
   prevent reliable comparison across lanes.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:25`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` alternates between numbers and
     prose; Dolt stores latest-evidence narrative where a comparable total
     should be; runner status alternates between object, string, and null; PHP
     counts mix behavior tests, assertions, PASS cases, selected files, and
     lane-local checks; and lane-status fields are mostly prose rather than
     comparable values.

## Test Gate

I did not run `php tools/run-tests.php`. The required exact duplicate-root
probe returned active PID `833257` owned by `claude`:

```text
833257 claude 832948 00:54 R+ php tools/run-tests.php lanes/syncthing/tests/PullJobQueueTest.php ...
```

That process is a focused Syncthing shard rather than a no-argument root run,
but the stability gate also failed independently: 143 tmux sessions, 35 active
repo worker/test-control processes, and 8912 total worktree status rows were
sampled. Starting a root harness would not produce an accepted baseline.

Verification performed by this audit:

```text
read goal.md, progress.md, porting.html, porting-summary.json
read every lanes/*/UPSTREAM_TEST_MANIFEST.json and every lanes/*/lane-status.json
read dependency-backlog.json and recent git history
sampled live process/worktree/tmux state without reading process environments
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator/watchdog
loops, capacity jobs, focused PHP shards, broad upstream runners, and duplicate
root harnesses. Then accept or reject dirty lane batches one lane at a time,
normalize manifest/status denominator, mapped, runner, PHP pass/fail, blocker,
and commit schemas, split optional dependency candidates into manifest-backed
bounded ports only behind concrete base-lane blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
one accepted commit, and only then run a quiesced root `php tools/run-tests.php`
if the exact duplicate-root gate remains clear.
