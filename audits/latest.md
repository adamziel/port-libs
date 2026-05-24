# Independent Audit - 2026-05-24T04:18Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git history,
root-runner process state, and visible coordination processes.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T04:13:11Z to 2026-05-24T04:18:04Z
HEAD observed during audit: 9fd54394de2d -> 975e85e779cf
recent commits: 975e85e7 Record integration hold status; 9fd54394 Record integration hold status; 351cb699 Refresh independent audit status
branch: main
branch divergence: ahead 691, behind 68 relative to origin/main
tracked dirty rows: 309
default status rows including untracked: 13453
git diff --shortstat: 309 files changed, 156916 insertions(+), 17761 deletions(-)
tmux sessions: 189
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:16Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:17Z:
330587 php tools/run-tests.php

owner evidence:
PID 330587, user claude, elapsed 00:06, args "php tools/run-tests.php"

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:18Z:
<no rows>
```

I did not start `php tools/run-tests.php`. The first process gate was clear,
but the checkout was not stable enough for an audit-owned root run; during
validation an external no-argument root harness appeared as PID 330587 owned by
`claude` and then cleared before the 04:18 sample, so no duplicate was started.
`HEAD` moved during validation, the tree contains 309 tracked dirty rows and
13,453 total status rows, every primary lane still has dirty or pending handoff
state, and active dashboard/evaluator/watchdog,
capacity, integrator, auditor, Dolt runner, and lane-agent processes remain
visible.

## Findings

1. **Critical - the checkout is still a dirty aggregate, not an acceptance
   checkpoint.**
   - Paths: `progress.md:37`, `progress.md:39`, `progress.md:71` through
     `progress.md:90`, current Git status, and recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`.
   - Evidence: `HEAD` moved during validation from `9fd54394de2d` to
     `975e85e779cf`; `main` is `ahead 691, behind 68`; tracked dirty rows are
     309; total status rows are 13,453; and the shortstat is 156,916
     insertions. Recent commits are
     mostly audit/integration-hold status commits while implementation output
     remains broad and unaccepted. This is not the small, reviewable, tested
     slice flow required by the goal.

2. **Critical - no coherent root-harness result exists for this exact
   snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/esbuild/lane-status.json:10` through
     `lanes/esbuild/lane-status.json:13`,
     `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:13`, and
     `lanes/markerpdf/lane-status.json:10` through
     `lanes/markerpdf/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:48` and `goal.md:49`.
   - Evidence: the initial required `pgrep` gate had no rows; intermediate
     validation matched active external no-argument root PID 330587 owned by
     `claude`; and the final 04:18 sample returned no rows only after the tree
     had already moved. Current lane status files explicitly say no-argument
     root verification was not assigned or is pending. Focused lane-green runs
     are not a serialized repo-wide result from a frozen accepted snapshot.

3. **Critical - `porting.html` and `porting-summary.json` are stale and still
   do not satisfy the dashboard contract.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:43` through `porting.html:52`,
     `porting-summary.json:2` through `porting-summary.json:8`, and
     `progress.md:15`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52`.
   - Evidence: both dashboard artifacts still publish generated time
     `2026-05-23 23:43:54 UTC` and source commit `79768df0c427`, while the
     current observed `HEAD` is `975e85e779cf`. The table also collapses the
     required benchmark source, upstream denominator, mapped tests, and PHP
     pass/fail columns into broad `Benchmark` and `Mapped` cells.

4. **High - dashboard, manifest, and lane-status counts disagree, so the
   status surface is not auditable.**
   - Paths: `porting-summary.json:11` through `porting-summary.json:145`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:10`,
     `lanes/rclone/lane-status.json:5` through
     `lanes/rclone/lane-status.json:10`, and
     `lanes/syncthing/lane-status.json:5` through
     `lanes/syncthing/lane-status.json:10`.
   - Goal requirement at risk: `goal.md:25` and `goal.md:45`.
   - Evidence: Difftastic manifest says 815 total / 448 mapped while the
     dashboard says 735 / 374. LightningCSS manifest says 3,532 / 1,912 and
     lane status says 2,448 PHP assertions while the dashboard says 1,732 and
     2,197 pass. markerPDF manifest says 351 / 302, lane status says 350 / 301
     with 438 PHP behavior tests, and the dashboard says 330 / 280 with 416
     pass. Rclone lane status says 790 PHP behavior tests while the dashboard
     says 698. Syncthing lane status says 6,179 assertions while the dashboard
     says 4,579 pass.

5. **High - manifest/status schemas remain too free-form for reliable
   generation and acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/esbuild/lane-status.json:5` through
     `lanes/esbuild/lane-status.json:14`, and
     `porting-summary.json:11` through `porting-summary.json:145`.
   - Goal requirement at risk: `goal.md:25` and `goal.md:45`.
   - Evidence: denominators mix behavior artifacts, helper invocations,
     source paths, test files, selected upstream runners, PHP assertions, and
     plan-only boundaries. Commit fields still contain prose such as
     `pending`, `uncommitted`, `not committed`, stale hashes, and partial
     strings. The generator cannot distinguish static inventory, accepted
     upstream runner parity, focused PHP checks, and root integration evidence.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `lanes/esbuild/lane-status.json:4` through
     `lanes/esbuild/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:4` through
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/quadrable/lane-status.json:4` through
     `lanes/quadrable/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`, `goal.md:38`, and
     `goal.md:40`.
   - Evidence: the dashboard still advertises 97.7% average progress and most
     lanes at 98-99%, while lane blockers still reserve root verification,
     full Cargo/Go/Haskell/Rust/SQLite/Python runner parity, live providers,
     rich-format dependencies, and other hard features for future slices.

7. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:9`, and
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and
     `goal.md:35`.
   - Evidence: the markerPDF denominator/status includes GitHub Actions,
     Poetry/package metadata, Streamlit/FastAPI/Uvicorn planning,
     `chunk_convert.sh` lifecycle planning, model-runtime graphs,
     OCR/Tesseract/Ghostscript readiness, benchmark archive setup, and
     multiprocessing/model-worker boundaries. Those are blockers, fixture
     generation, or preflight notes unless reduced to bounded native PHP
     behavior with mapped fixtures and pass/fail evidence.

8. **High - essential optional-library coverage remains backlog-only, not
   lane-grade support-library progress.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:25` through `dependency-backlog.json:43`,
     `dependency-backlog.json:111`,
     `dependency-backlog.json:382`,
     `dependency-backlog.json:422`, and `porting.html:75` through
     `porting.html:78`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library granularity requirement.
   - Evidence: only the 12 base lane manifests exist. There is no
     support-library manifest with a bounded native component, activation gate,
     dependency-specific denominator, mapped fixtures, PHP pass/fail evidence,
     or malformed/corrupt-case coverage for ZIP/package, XML/HTML5,
     DOCX/OpenXML, legacy DOC/CFB, doctemplates, PDF text, OCR/layout, table
     geometry, Unicode, source maps, protobuf, compression, glob/pathspec, or
     provider metadata normalization. The dashboard is also stale: it reports
     22 backlog items, while `dependency-backlog.json` has 23.

9. **High - shared dependency work is expanding lane-locally without a bounded
   shared gate.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:43`,
     `dependency-backlog.json:382` through `dependency-backlog.json:440`,
     `lanes/rclone/lane-status.json:5` through
     `lanes/rclone/lane-status.json:12`, and
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's dependency-expansion requirement.
   - Evidence: rclone is growing VFS ZIP/WebDAV/OneDrive mutation and metadata
     surfaces while markerPDF is growing benchmark archive, PDF text, OCR,
     model-runtime, and ZIP/package planning. The backlog says these need
     bounded shared native components with their own evidence; lane-local
     expansion risks duplicate infrastructure and inflated lane progress.

10. **High - `progress.md` Active Lanes are stale relative to current lane
    handoffs.**
    - Paths: `progress.md:71` through `progress.md:88`,
      `lanes/rclone/lane-status.json:9` through
      `lanes/rclone/lane-status.json:14`,
      `lanes/readability/lane-status.json:9` through
      `lanes/readability/lane-status.json:14`, and
      `lanes/syncthing/lane-status.json:9` through
      `lanes/syncthing/lane-status.json:14`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still says rclone is at a VFS Statfs/usage
      handoff, readability at negative-header cleanup, and Syncthing at system
      log route work. Current lane statuses report rclone WebDAV mutation
      handlers, readability paragraph sibling thresholds, and Syncthing folder
      versions. The human coordination surface is no longer the current
      owner/session/next-task source.

11. **Medium - process/shell boundaries still need explicit non-progress
    treatment.**
    - Paths: `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/markerpdf/src/ChunkConversionPlanner.php:142`,
      `lanes/markerpdf/tests/ChunkConversionPlannerTest.php:49`, and
      `tools/generate-dashboard.php:197`.
    - Goal requirement at risk: `goal.md:1` and `goal.md:30`.
    - Evidence: Gitoxide tests still use process launch for local oracle
      checks, markerPDF records shell lifecycle metadata, and dashboard
      generation shells out for Git metadata. These may be acceptable as
      explicit oracle/tooling boundaries, but runtime behavior depending on
      process launch must not count as native implementation progress.

## Required Intervention

Keep the integration hold. Freeze writers, runners, dashboard/status updaters,
and lane agents long enough for two stable polls of `HEAD`, `git status`,
`git diff --shortstat`, and `pgrep -af '^php tools/run-tests\.php( |$)'`.
Accept exactly one lane batch only after schema/count normalization, focused
lane verification, and `git diff --check`. If the process gate is empty, run
one serialized no-argument `php tools/run-tests.php` from that exact frozen
snapshot. Regenerate `porting.html`/`porting-summary.json` from the accepted
commit only, then commit or reject the batch. Do not credit support-library
work until a bounded shared component has its own manifest, activation gate,
denominator, fixtures, PHP evidence, and malformed/corrupt coverage.
