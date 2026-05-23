# Independent Audit - 2026-05-23T23:55Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
observed HEAD moved during audit: 43daa240c41c -> 2c61f4c429a9
latest visible commits: 2c61f4c4 Align dependency backlog status; 5cd1ec6b Track expanded support dependency gates; 43daa240 Record integration hold status
commits since 2026-05-23 00:00 UTC: 762 total at sample time; recent history remains audit/status/integration-hold dominated
tracked dirty rows: 280
total status rows including untracked: 8752
git diff HEAD --shortstat: 280 files changed, 122940 insertions(+), 12490 deletions(-)
tmux sessions: 138
active repo worker/test-control processes sampled: 44
```

The required exact pre-root gate initially found an active focused PHP harness:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
427470 php tools/run-tests.php lanes/syncthing/tests
```

That PID exited before owner sampling:

```text
ps -o pid,user,ppid,stat,etime,args -p 427470
PID USER PPID STAT ELAPSED COMMAND
```

Later exact duplicate-root gates returned no rows, but the tree was still not
stable enough for a trustworthy root run: active lane/watchdog/dashboard/
evaluator/integrator/capacity/auditor loops were present, broad Dolt BATS work
was active, `HEAD` moved during the audit, and the worktree remained a large
dirty aggregate. No root run was started.

## Findings

1. **Critical - the repo is still not in a stable integration state, so a root
   test run would be a moving-target signal.**
   - Paths: `progress.md:33`, `progress.md:35` through `progress.md:50`,
     `.tmux-team/`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-capacity-executor-queue.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-team-watchdog.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable commits with passing tests, integration cleanup, and honest
     repo-wide verification.
   - Evidence: `progress.md` still says the current launch target is 2
     implementation lanes plus 1 auditor and all 12 active lanes are
     `stopped`, but this audit sampled 138 tmux sessions, 44 active repo
     worker/test-control processes, broad Dolt BATS activity, and a focused
     PHP harness during the pre-root gate. `HEAD` moved from `43daa240c41c`
     to `2c61f4c429a9`; the worktree has 280 tracked dirty rows and 8752
     total status rows.

2. **Critical - coordination artifacts are stale relative to HEAD while
   presenting themselves as a verified snapshot.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, `progress.md:35` through `progress.md:50`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require durable, browseable status with current denominator,
     mapped-test, PHP pass/fail, WordPress scenario, phase, audit, current
     work, blocker, and commit fields.
   - Evidence: `porting.html` says generated `2026-05-23 23:43:54 UTC`,
     snapshot `main 79768df0c427`, and 97.7% average progress, while current
     `HEAD` is `2c61f4c429a9` and `porting.html`/`porting-summary.json` are
     dirty. `progress.md` is also inconsistent: it still reports old stopped
     lane estimates of 5-66%, while the dashboard reports 92-99% per lane.

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
     reviewable slices with passing tests, then verification, commit, progress
     update, cleanup, and reassignment.
   - Evidence: lane commit fields still say `pending`, `uncommitted`,
     `not committed`, or dirty-worktree prose. Recent history is dominated by
     status/audit/integration-hold commits, while implementation changes remain
     mixed across hundreds of dirty files.

4. **High - near-complete percentages overstate accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:40` require real upstream
     denominators, meaningful fixture parity, upstream tests as source of
     truth, explicit slices, and blockers for hard features.
   - Evidence: the dashboard reports 97.7% average progress, but the manifests
     still show slice evidence rather than full-port proof: Difftastic maps
     374/735 static artifacts without upstream runner parity; esbuild maps
     311/2567; Gitoxide maps 2751/2877 but no full Cargo workspace pass;
     libsqlite maps 286/1589; LightningCSS maps 1732/3532; markerPDF maps
     281/331 while full behavior depends on external model/runtime stacks;
     Pandoc maps 1061/2276 without Cabal parity; rclone maps 698/1601 with
     live provider suites excluded; Readability and Syncthing report complete
     mapped static inventories, but still rely on copied/static/focused
     evidence rather than a frozen accepted root baseline.

5. **High - optional-library coverage is still backlog-only, and several
   dependency candidates are too broad to accept as implementation work.**
   - Paths: `progress.md:17` through `progress.md:23`,
     `dependency-backlog.json`, `porting.html:72` through
     `porting.html:114`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:11`,
     `goal.md:12`, `goal.md:15`, `goal.md:16`, `goal.md:18`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require
     rich document/runtime behavior, real denominators, no shell-out progress
     credit, and explicit hard blockers.
   - Evidence: the backlog now has 22 gated items, but none has its own
     accepted `UPSTREAM_TEST_MANIFEST.json`, upstream/spec denominator, PHP
     pass/fail record, owner, commit, or dashboard lane. Rich-function gaps
     remain real for Pandoc package formats and citations/math, markerPDF PDF
     text/render/OCR/table behavior, esbuild source maps, Syncthing protobuf
     wire compatibility, Difftastic grammar/encoding behavior, and rclone
     provider metadata/checksum behavior. Cross-lane candidates such as
     Unicode, charset encoding, checksum, archive/compression, tree-sitter,
     SQL/storage codecs, glob/pathspec, and provider metadata normalization
     are still too broad until split into bounded, manifest-backed ports.

6. **Medium - markerPDF and Readability still mix strong local evidence with
   non-native, copied-fixture, or oracle-heavy evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/readability/lane-status.json`, `porting.html:62`,
     `porting.html:66`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:11`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require
     native implementation progress, meaningful fixture parity, rich document
     behavior, and explicit blockers for unported binary/model/runtime formats.
   - Evidence: markerPDF's full behavior still depends on pdftext, pypdfium2,
     Surya/Torch, Texify, tabled-pdf, OCRMyPDF/Tesseract/Ghostscript,
     Streamlit, FastAPI/Uvicorn, PIL, benchmark archives, multiprocessing, and
     model downloads. Readability has useful upstream npm and copied-fixture
     evidence, but copied fixtures and JS oracle checks are not accepted native
     parity until the root baseline is frozen and committed.

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
     long prose; Difftastic, Dolt, Pandoc, and Quadrable still rely on prose
     totals in fields the dashboard flattens; PHP counts mix behavior tests,
     assertions, PASS cases, files, and lane-local checks. Comparable status
     cannot be trusted until denominator, mapped, runner, PHP pass/fail, and
     commit fields use one schema.

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
     live provider suites, external model/runtime stacks, broad upstream
     dependency graphs, credential-bearing integrations, and pending root
     verification.

## Test Gate

I did not run `php tools/run-tests.php`. The first exact pre-root gate matched
focused Syncthing PID `427470`, which exited before owner sampling. Later exact
duplicate-root gates returned no rows, but the stability gate still failed:
`HEAD` moved during the audit, 138 tmux sessions and 44 active repo
worker/test-control processes were sampled, broad Dolt BATS was active, and the
dirty tree reported 280 tracked rows plus 8752 total status rows. Starting a
root harness would not produce an accepted baseline.

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
