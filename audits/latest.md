# Independent Audit - 2026-05-24T00:05Z

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
observed HEAD moved during audit: e803be48 -> bd8e4a851ecd
latest visible commits: bd8e4a85 Record integration hold status; f7d35288 Record integration hold status; e803be48 Refresh independent audit status
commits since 2026-05-23 00:00 UTC: 772
tracked dirty rows: 286
total status rows including untracked: 8792
git diff HEAD --shortstat: 286 files changed, 124622 insertions(+), 12594 deletions(-)
tmux sessions: 141
active repo worker/test-control processes sampled: 34
exact pre-root gate: initially no rows; final handoff sample matched PID 772881
owner evidence: 772881 claude 763950 00:07 Rs php tools/run-tests.php lanes/readability/tests
```

No root run was started. The exact duplicate-root gate was clear at the first
sample, then a final handoff sample matched active `php tools/run-tests.php`
PID `772881` owned by `claude`. The tree was also not stable enough for a
trustworthy aggregate signal: `HEAD` moved during the audit, lane/watchdog/
dashboard/evaluator/integrator/capacity/dependency loops were active,
`progress.md` still reports all lanes stopped, and the worktree remains a
broad dirty aggregate.

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
     `stopped`. Latest samples reported 141 tmux sessions, 34 active repo
     worker/test-control processes, 286 tracked dirty rows, and 8792 total
     status rows after `HEAD` advanced again. `HEAD` moved from `e803be48` to
     `bd8e4a851ecd` while this audit was running. The initial exact root gate
     returned no rows, but a final handoff gate matched active PID `772881`
     owned by `claude`; the stability gate also failed, so
     `php tools/run-tests.php` was not started.

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
     current `HEAD` is `bd8e4a851ecd` and those dashboard artifacts are dirty.
     Current manifests have moved past dashboard rows: Difftastic `378` mapped
     vs dashboard `374`, esbuild `315` vs `311`, Gitoxide `2759` vs `2751`,
     libsqlite `288` vs `286`, markerPDF `282/332` vs `280/330`, Pandoc
     `1075` vs `1061`, rclone `709` vs `698`, and Readability PHP behavior
     tests `205` vs dashboard `204`. `progress.md` still reports old stopped
     lane estimates of 5-66%, while the dashboard reports 92-99% per lane.

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
   - Evidence: every lane status commit field says `pending`, `uncommitted`,
     `not committed`, or dirty-worktree prose. Recent history is dominated by
     audit/status/integration-hold commits, while implementation files remain
     mixed across 281 dirty tracked files and thousands of untracked files.

4. **High - near-complete percentages overstate accepted native upstream
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:388`,
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
     esbuild maps 315/2567, libsqlite maps 288/1589, Pandoc maps 1075/2276
     without Cabal parity, rclone maps 709/1601 with live provider/mount
     suites still excluded, Gitoxide maps 2759/2877 without full Cargo
     workspace pass, Difftastic still lacks full upstream runner parity, and
     markerPDF records `runnerStatus: not-executed` for heavy PDF/model/server
     behavior. These can be useful slices, but they should not read as
     near-finished native ports.

5. **High - essential optional-library coverage remains backlog-only, and some
   dependency candidates are too broad to accept as implementation progress.**
   - Paths: `progress.md:17` through `progress.md:23`,
     `dependency-backlog.json:7` through `dependency-backlog.json:421`,
     `porting.html:71` through `porting.html:90`,
     `porting-summary.json:214` through `porting-summary.json:447`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require
     rich document/runtime behavior, native implementation, real denominators,
     no shell-out progress credit, and explicit hard blockers.
   - Evidence: the backlog has 22 rows, 12 `candidate` and 10 `deferred`, but
     no dependency has a dependency-specific manifest, accepted upstream/spec
     denominator, PHP pass/fail record, owner, commit, or dashboard lane. Rich
     gaps remain for Pandoc ZIP/DOCX/DOC/EPUB/ODT/citation/math, markerPDF PDF
     text/render/OCR/table behavior, esbuild source maps, Syncthing protobuf/
     BEP wire compatibility, Difftastic tree-sitter/encoding behavior, and
     rclone provider metadata/checksum/archive behavior. Cross-lane rows such
     as XML/HTML5 DOM, Unicode repair, charset, checksum, archive/compression,
     tree-sitter, SQL/storage codecs, glob/pathspec, and provider metadata
     normalization are useful shared candidates, but are too broad to count as
     one implementation slice. They need bounded manifests by spec, algorithm,
     provider, or fixture family before progress credit.

6. **Medium - blocker fields still mix slice-local green tests with full-port
   blockers.**
   - Paths: `lanes/difftastic/lane-status.json:12`,
     `lanes/dolt/lane-status.json:12`,
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
     "No focused blocker" while the same fields list unexecuted full upstream
     runners, live provider suites, external model/runtime stacks, broad
     dependency graphs, and pending aggregate root verification. That makes
     full-port blockers read like lane-local success notes.

7. **Medium - manifest and status schemas remain non-normalized and still
   prevent reliable comparison across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2332`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:388`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` alternates between numbers and
     prose; Dolt stores latest-evidence narrative in `total`; runner status
     alternates between object, string, and null; PHP counts mix behavior
     tests, assertions, PASS cases, selected files, and lane-local checks; and
     lane-status fields are mostly prose rather than comparable values.

## Test Gate

I did not run `php tools/run-tests.php`. The required exact duplicate-root gate
initially returned no rows, but a final handoff sample matched active PID
`772881` owned by `claude` (`772881 claude 763950 00:07 Rs php
tools/run-tests.php lanes/readability/tests`). The stability gate also failed:
`HEAD` moved during the audit, 141 tmux sessions and 34 active repo
worker/test-control processes were sampled, and the dirty tree reported 286
tracked rows plus 8792 total status rows. Starting a root harness would not
produce an accepted baseline.

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
and commit schemas, split optional dependency candidates into manifest-backed
bounded ports only behind concrete base-lane blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
one accepted commit, and only then run a quiesced root `php tools/run-tests.php`
if the exact duplicate-root gate remains clear.
