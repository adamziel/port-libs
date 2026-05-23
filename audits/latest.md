# Independent Audit - 2026-05-23T23:41Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless they are explicitly temporary
oracle tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
observed HEAD: 8dfec3b5530c
latest visible commits: 8dfec3b5 Record integration hold status; 18c3afae Refresh independent audit status; c05ad8c3 Record integration hold status
commits since 2026-05-23 00:00 UTC: 759 total; recent history is still audit/status/integration-hold dominated
tracked dirty rows: 278
total status rows including untracked: 8507
git diff HEAD --shortstat: 278 files changed, 121483 insertions(+), 12482 deletions(-)
tmux sessions: 150
active loops sampled: dashboard updater, evaluator, watchdog, integrator, auditor, dependency-support auditor, capacity controller/executor, focused PHP shards
```

The required exact pre-root gate initially found a no-argument root harness plus
focused shards:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
292564 php tools/run-tests.php
308610 php tools/run-tests.php lanes/quadrable/tests
309149 php tools/run-tests.php lanes/readability/tests
309202 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
310026 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
310035 php tools/run-tests.php lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests
310128 php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests lanes/difftastic/tests lanes/esbuild/tests
```

That no-argument root PID exited before owner sampling. A follow-up exact gate
found only a focused Syncthing shard, with owner evidence:

```text
333440 claude 333064 R+ 00:28 php tools/run-tests.php lanes/syncthing/tests/PullScannerTest.php ...
```

A final handoff gate then found a new active no-argument root harness with
owner evidence:

```text
388845 claude 388748 R+ 00:55 php tools/run-tests.php
389369 claude 389114 R+ 00:54 php tools/run-tests.php lanes/syncthing/tests/PullJobQueueTest.php ...
```

No root run was started.

## Findings

1. **Critical - the repo is still not in a stable integration state, so root
   test results would be a moving-target signal.**
   - Paths: `progress.md:32`, `progress.md:34` through `progress.md:49`,
     `.tmux-team/`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-capacity-executor-queue.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-team-watchdog.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable commits with passing tests, verified integration, cleanup, and
     honest repo-wide verification.
   - Evidence: `progress.md` still says the current launch target is 2
     implementation lanes plus 1 auditor and all 12 active lanes are
     `stopped`, but this audit sampled 150 tmux sessions, active capacity and
     dashboard/evaluator/watchdog/integrator/auditor loops, and a root harness
     during the pre-root gate. The worktree has 278 tracked dirty rows and
     8507 total status rows.

2. **Critical - coordination artifacts are generated from dirty moving state
   while presenting themselves as a commit snapshot.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, `progress.md:34` through `progress.md:49`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require durable, browseable status with current denominator,
     mapped-test, PHP pass/fail, WordPress scenario, phase, audit, current
     work, blocker, and commit fields.
   - Evidence: `porting.html` currently says snapshot `main 8dfec3b5530c`
     and 97.7% average progress, but `porting.html`, `porting-summary.json`,
     `dependency-backlog.json`, all lane manifests, and all lane statuses are
     dirty. The page is not an accepted commit snapshot; it is a dirty
     dashboard rendering. `progress.md` is also stale relative to it: the
     Active Lanes table still reports old 5-66% estimates and all sessions
     stopped while the dashboard reports 90-99% per lane.

3. **High - every lane still reports pending or uncommitted handoff state
   rather than an accepted implementation commit.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small,
     reviewable slices with passing tests, then verification, commit,
     progress update, cleanup, and reassignment.
   - Evidence: lane commit fields still say `pending`, `uncommitted`,
     `not committed`, or dirty-worktree prose. Recent Git history is dominated
     by audit/status/integration-hold commits, while the implementation
     changes remain mixed across 278 tracked dirty files.

4. **High - near-complete percentages overstate accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40` require meaningful fixture parity,
     upstream tests as source of truth, explicit slices, and blockers for hard
     features.
   - Evidence: the dashboard now reports 97.7% average progress, with most
     lanes at 98-99%, but the manifests still show slice evidence rather than
     full-port proof: Difftastic maps 374 static artifacts against a 735
     artifact inventory with no upstream runner pass; Gitoxide maps 2750/2877
     with no full cargo workspace pass; markerPDF maps 280/330 static units
     with 0 upstream Python test files and heavy model/runtime blockers;
     Pandoc maps 1061/2276 without Cabal test parity; rclone maps 698/1601
     with live provider suites excluded; Syncthing maps 658/658 through
     static/focused evidence but not `go test ./...`. Root PHP verification is
     also pending in a dirty, active tree.

5. **High - optional dependency coverage has expanded, but it is still not
   manifest-backed dependency-port work and several items are too broad to
   accept as-is.**
   - Paths: `progress.md:17` through `progress.md:22`,
     `dependency-backlog.json`, `porting.html:72` through
     `porting.html:114`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:11`,
     `goal.md:12`, `goal.md:15`, `goal.md:16`, `goal.md:18`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require
     rich document/runtime behavior, real denominators, no shell-out progress
     credit, and explicit hard blockers.
   - Evidence: the dirty dependency backlog has grown to 22 items
     (`candidate:12`, `deferred:10`) including ZIP/package, XML/HTML5 DOM,
     DOCX/OpenXML, legacy DOC/CFB, EPUB, ODF, citations/CSL, math/TeX,
     PDF text dictionaries, PDF render planning, OCR/layout results, table
     geometry, Unicode/encoding, source maps, tree-sitter subsets, protobuf,
     checksums, SQL storage codecs, archives, glob/pathspec, and provider
     metadata normalization. None has its own accepted
     `UPSTREAM_TEST_MANIFEST.json`, upstream/spec denominator, PHP pass/fail
     record, owner, commit, or dashboard lane. Cross-lane items such as
     Unicode, encoding, checksum, archive, and provider metadata are especially
     broad and should remain gated until split into bounded, testable ports.

6. **Medium - markerPDF and Readability still mix strong local evidence with
   non-native or fixture/oracle-heavy evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/readability/lane-status.json`, `porting.html:62`,
     `porting.html:66`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:11`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require
     native implementation progress, meaningful fixture parity, rich document
     behavior, and explicit blockers for unported binary/model/runtime
     formats.
   - Evidence: markerPDF's full behavior still depends on pdftext, pypdfium2,
     Surya/Torch, Texify, tabled-pdf, OCRMyPDF/Tesseract/Ghostscript,
     Streamlit, FastAPI/Uvicorn, PIL, benchmark archives, multiprocessing, and
     model downloads. Readability has useful upstream npm and copied-fixture
     evidence, but local copied fixtures and JS oracle checks are not the same
     as accepted native parity until the root baseline is frozen and committed.

7. **Medium - manifest and status schemas remain non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` alternates between numbers and
     long prose; Dolt renders the denominator as `inventory`; Difftastic,
     Pandoc, and Quadrable still rely on prose totals; `runnerStatus` varies
     between string and object shapes; PHP counts mix behavior tests,
     assertions, PASS cases, files, and lane-local checks.

8. **Medium - blocker fields still conflate slice-local green tests with
   full-port blockers.**
   - Paths: `lanes/dolt/lane-status.json`,
     `lanes/esbuild/lane-status.json`, `lanes/gitoxide/lane-status.json`,
     `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: many blockers say "No current implementation blocker" or
     "No focused blocker" while the same files list unexecuted full runners,
     live provider suites, external model/runtime stacks, credential-bearing
     integrations, broad upstream dependency graphs, and pending root
     verification.

## Test Gate

I did not run `php tools/run-tests.php`. The first exact pre-root gate matched
active no-argument root PID `292564` plus focused lane harnesses; that root
process exited before owner sampling. A later exact gate found only focused
Syncthing PID `333440`, owned by `claude`. The final handoff gate then matched
active no-argument root PID `388845`, owned by `claude`, plus focused
Syncthing PID `389369`, also owned by `claude`. Starting another root harness
would have violated the duplicate-root rule, and the tree also had active
writers, 150 tmux sessions, 278 tracked dirty rows, and 8507 total status rows.

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
root harnesses. Then validate manifests from the frozen tree, accept or reject
dirty lane batches one lane at a time, normalize denominator/mapped/PHP/
runner/blocker/commit schemas, split optional dependency candidates into
manifest-backed bounded ports only behind concrete base-lane blockers,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from one accepted commit, and only then run a quiesced root
`php tools/run-tests.php` if the exact duplicate-root gate remains clear.
