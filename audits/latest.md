# Independent Audit - 2026-05-23T23:56Z

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
observed HEAD moved during audit: bc6589fe1d50 -> 6cb85fbaf28f -> 5cd1bec2c4cd
latest visible commits: 5cd1bec2 Record integration hold status; 6cb85fba Record integration hold status; bc6589fe Refresh independent audit status
commits since 2026-05-23 00:00 UTC: 766
tracked dirty rows: 282
total status rows including untracked: 8851
git diff HEAD --shortstat: 282 files changed, 123715 insertions(+), 12629 deletions(-)
tmux sessions: 139
active repo worker/test-control processes sampled: 33
```

The first required exact pre-root gate returned no rows:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

The final exact gate matched a focused Syncthing PHP harness, so a duplicate
root run would also be blocked at handoff:

```text
616701 php tools/run-tests.php lanes/syncthing/tests
616701 claude 519088 Rs 01:16 php tools/run-tests.php lanes/syncthing/tests
```

No root run was started because the tree was still not stable enough for a
trustworthy aggregate signal: active lane/watchdog/dashboard/evaluator/
integrator/capacity/dependency loops were present, `HEAD` moved during the
audit, `progress.md` still reports all lanes stopped, and the worktree remains
a broad dirty aggregate.

## Findings

1. **Critical - the repo is still not in a stable integration state, so a root
   test run would be a moving-target signal.**
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
   - Evidence: the documented launch target is 2 implementation lanes plus
     one auditor, and the Active Lanes table says all 12 lanes are `stopped`.
     The audit instead sampled 139 tmux sessions, 33 active repo worker/test
     control processes, 282 tracked dirty rows, and 8851 total status rows.
     `HEAD` moved from `bc6589fe1d50` through `6cb85fbaf28f` to
     `5cd1bec2c4cd` while the audit was reading status, and the final exact
     pre-root gate matched focused PHP PID `616701` owned by `claude`.

2. **Critical - the dashboard/status snapshot is stale and contradicts current
   manifests while presenting 97.7% average progress.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting-summary.json:12` through `porting-summary.json:212`,
     `progress.md:35` through `progress.md:50`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current denominator, mapped-test, PHP pass/fail,
     WordPress scenario, phase, audit, current work, blocker, and commit
     fields in `progress.md` and `porting.html`.
   - Evidence: `porting.html` and `porting-summary.json` publish snapshot
     `79768df0c427`, while current `HEAD` is `5cd1bec2c4cd` and the dashboard
     artifacts themselves are dirty. Current manifests have already moved past
     dashboard counts: Difftastic `376` mapped vs dashboard `374`, esbuild
     `313` vs `311`, Gitoxide `2752` vs `2751`, libsqlite `287` vs `286`,
     markerPDF `281/331` vs `280/330`, Pandoc `1067` vs `1061`, and rclone
     `700` vs `698`. `progress.md` still reports old stopped-lane estimates
     of 5-66%, while the dashboard reports 92-99% per lane.

3. **High - every lane still reports pending or uncommitted handoff state
   instead of accepted implementation commits.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require
     small reviewable slices with passing tests, then verification, commit,
     progress update, cleanup, and reassignment.
   - Evidence: lane status commit fields say `pending`, `uncommitted`,
     `not committed`, or dirty-worktree prose. Recent history is dominated by
     audit/status/integration-hold commits, while implementation files remain
     mixed across hundreds of dirty tracked files and thousands of untracked
     files.

4. **High - near-complete percentages overstate accepted native upstream
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:12` through `porting-summary.json:212`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:30`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:40` require real upstream
     denominators, meaningful fixture parity, upstream tests as source of
     truth, explicit slices, and blockers for hard features.
   - Evidence: the dashboard reports 97.7% average progress even though many
     lanes remain bounded-slice ports without accepted full-runner parity:
     Difftastic has no upstream runner parity, esbuild maps 313/2567,
     Gitoxide maps 2752/2877 without full Cargo workspace pass, libsqlite maps
     287/1589, markerPDF maps 281/331 while core behavior depends on external
     model/runtime/PDF stacks, Pandoc maps 1067/2276 without Cabal parity, and
     rclone maps 700/1601 with live provider/mount suites excluded.

5. **High - optional-library coverage remains backlog-only, and several rich
   dependency candidates are too broad to accept as implementation progress.**
   - Paths: `progress.md:17` through `progress.md:23`,
     `dependency-backlog.json:7` through `dependency-backlog.json:120`,
     `porting-summary.json:258` through `porting-summary.json:447`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require
     rich document/runtime behavior, native implementation, real denominators,
     no shell-out progress credit, and explicit hard blockers.
   - Evidence: the backlog has 22 rows, 12 `candidate` and 10 `deferred`, but
     no dependency has a dependency-specific manifest, accepted upstream/spec
     denominator, PHP pass/fail record, owner, commit, or dashboard lane.
     Rich gaps remain for Pandoc package formats, citations, math, DOC/DOCX,
     EPUB and ODT; markerPDF PDF text/render/OCR/table behavior; esbuild
     source maps; Syncthing protobuf/BEP wire compatibility; Difftastic
     grammar/encoding behavior; and rclone provider metadata/checksum/archive
     behavior. Cross-lane rows such as Unicode, charset, checksum,
     archive/compression, tree-sitter, SQL/storage codecs, glob/pathspec, and
     provider metadata normalization must be split into bounded,
     manifest-backed ports before any progress credit.

6. **Medium - blocker fields still mix slice-local green tests with full-port
   blockers.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require
     precise blockers and no silent skipping of hard features.
   - Evidence: many blockers start with "No current implementation blocker" or
     "No focused blocker" but the same fields list unexecuted full upstream
     runners, live provider suites, external model/runtime stacks, broad
     dependency graphs, and pending aggregate root verification. This makes
     full-port blockers look like lane-local success notes.

7. **Medium - manifest and status schemas remain non-normalized and still
   prevent reliable comparison across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2319`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `porting-summary.json:32` through `porting-summary.json:35`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` alternates between numbers and
     long prose; Dolt stores the latest evidence narrative in `total` while
     the dashboard renders the denominator as `inventory`; PHP counts mix
     behavior tests, assertions, PASS cases, files, and lane-local checks; and
     lane-status fields are mostly prose rather than comparable values.

## Test Gate

I did not run `php tools/run-tests.php`. The first exact duplicate-root gate
returned no rows, but the final gate matched focused Syncthing PHP PID `616701`
owned by `claude`. The stability gate also failed: `HEAD` moved during the
audit, 139 tmux sessions and 33 active repo worker/test-control processes were
sampled, and the dirty tree reported 282 tracked rows plus 8851 total status
rows. Starting a root harness would not produce an accepted baseline.

Verification performed by this audit:

```text
read goal.md, progress.md, porting.html, porting-summary.json
read every lanes/*/UPSTREAM_TEST_MANIFEST.json and every lanes/*/lane-status.json
read dependency-backlog.json and recent git history
sampled live process/worktree/tmux state
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator/watchdog
loops, capacity jobs, focused PHP shards, broad upstream runners, and duplicate
root harnesses. Then accept or reject dirty lane batches one lane at a time,
normalize manifest/status denominator, mapped, runner, PHP pass/fail, blocker,
and commit schemas, split optional dependency candidates into
manifest-backed bounded ports only behind concrete base-lane blockers,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from one accepted commit, and only then run a quiesced root
`php tools/run-tests.php` if the exact duplicate-root gate remains clear.
