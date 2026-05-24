# Independent Audit - 2026-05-24T02:54Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, process/test-runner state, and recent Git
history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, plan-only wrappers,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
UTC samples: 2026-05-24T02:51:07Z through 2026-05-24T02:58:11Z
HEAD movement observed: a2126fda7fb0 -> 5b3fb14efb9e -> f3fd96ccb6d5
current HEAD: f3fd96ccb6d5 Record integration hold status
recent commits: f3fd96cc Record integration hold status; 5b3fb14e Record integration hold status; a2126fda Refresh independent audit status
branch sample: main...origin/main [ahead 663, behind 68]
tracked dirty rows after audit edits: 302
default status rows including untracked: 11991
git diff --shortstat after audit edits: 302 files changed, 148714 insertions(+), 17030 deletions(-)
tmux sessions: 176
active coordination sample: capacity executor/controller, dashboard updater, watchdog, evaluator, integrator, Dolt runner, lane watchdogs, auditor
root run by this audit: not started
```

Required root-run gate samples:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
2026-05-24T02:51:07Z: 3505076 php tools/run-tests.php lanes/syncthing/tests; 3505674 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...; 3508873 php tools/run-tests.php
owner sample after that gate: PID 3505076 owned by claude; PID 3505674 owned by claude; PID 3508873 had already exited before owner sampling
2026-05-24T02:52:26Z: <no rows>
2026-05-24T02:53:57Z: 3535185 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
owner sample: PID 3535185 owned by claude
2026-05-24T02:56:26Z: 3561059 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
owner sample: PID 3561059 owned by claude
2026-05-24T02:57:11Z: <no rows>
2026-05-24T02:58:11Z: focused lane shards owned by claude via `pgrep -u claude`, including PIDs 3583864, 3584147, 3584231, 3584284, 3584480, 3584870, 3584874, 3584891, and 3586146; no no-argument root row
```

The no-argument root process `3508873` was external to this audit and had
already exited by the time `ps` could sample owner details; current
`audits/integration-status.md` records the same PID in the integration hold
window. I did not start a duplicate root harness. A later exact gate was clear,
then focused Syncthing runners reappeared, then the exact gate cleared again,
then focused lane shards reappeared; the checkout had also moved and remained
a dirty active aggregate, so it was still not a stable audit-owned root-run
target.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance or root-verification target.**
   - Paths: `progress.md:37` through `progress.md:81`,
     `audits/integration-status.md:3` through
     `audits/integration-status.md:72`, and all
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require small committed slices, verification, cleanup, and
     honest repo-wide test/static-check records.
   - Evidence: `HEAD` moved during this audit from `a2126fda7fb0` through
     `5b3fb14efb9e` to `f3fd96ccb6d5`. After the audit edits, the worktree
     still has 302 tracked dirty rows, 11,991 untracked-inclusive status rows,
     and 148,714 tracked insertions.
     Recent history is dominated by audit/status-only commits. Lane statuses
     still describe pending, uncommitted, or shared-dirty handoffs with root
     verification owned by the supervisor/integrator.

2. **Critical - the root harness gate was not safe to consume, and the latest
   dirty-root success remains anecdotal.**
   - Paths: `audits/integration-status.md:36` through
     `audits/integration-status.md:51`, `progress.md:39`, and this audit's
     root-run gate section above.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure records from a coherent snapshot.
   - Evidence: the required gate saw external focused Syncthing runners and a
     no-argument root PID `3508873` at 02:51 UTC. That root exited before owner
     sampling, and a later focused Syncthing PID `3535185` was owned by
     `claude`. The integration hold file records a concurrent dirty-root
     `exit=0 files=341 assertions=45214 failures=0`, but also states status
     counts and log mtimes moved underneath it. That is not an accepted
     integration checkpoint and should not be used to green-light lane commits.

3. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict current source files.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting.html:75` through `porting.html:78`,
     `porting-summary.json`, and `dependency-backlog.json:3` through
     `dependency-backlog.json:5`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still says it was generated
     `2026-05-23 23:43:54 UTC` from source commit `79768df0c427`, while the
     checkout is at `f3fd96ccb6d5` with newer dirty manifests/status files.
     The dashboard dependency section reports 22 items, 12 candidates, and 10
     medium-priority rows; `dependency-backlog.json` now has 23 items, 13
     candidates, and 11 medium-priority rows after `pandoc-doctemplates-core`.

4. **High - public dashboard counts disagree with current manifests/statuses
   across most lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`
     through `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, and current
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45`.
   - Evidence: current manifests/statuses now show Difftastic 794/413 and
     2,572 focused assertions while the dashboard has 735/374 and 374 pass;
     Esbuild 333 while the dashboard has 311; Gitoxide 2,877/2,877 and 6,112
     assertions while the dashboard has 2,751/2,877 and 5,634 pass; libsqlite
     301 while the dashboard has 286; LightningCSS 1,902 while the dashboard
     has 1,732; markerPDF 345/296 while the dashboard has 330/280; Pandoc
     1,290 while the dashboard has 1,061; rclone 766 while the dashboard has
     698; Readability has 221 PHP tests while the dashboard has 204; and
     Syncthing has 5,703 assertions while the dashboard has 4,579 pass.

5. **High - focused lane-green evidence is still being recorded before
   supervisor acceptance and aggregate verification.**
   - Paths: all `lanes/*/lane-status.json`, especially blocker/latestCommit
     fields in `lanes/esbuild/lane-status.json:12` through
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:12` through
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/rclone/lane-status.json:12` through
     `lanes/rclone/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:12` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49`.
   - Evidence: lane statuses report green focused suites, examples, lints, and
     upstream probes, but the same records say root aggregate verification is
     pending and latest commits are pending, uncommitted, or shared-dirty.
     These are intake notes, not accepted portfolio progress.

6. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:436` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:437`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:457` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:459`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:520` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:583`, and
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:920` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:950`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:25`,
     `goal.md:30`, `goal.md:31`, and `goal.md:35`.
   - Evidence: markerPDF has useful native slices, but its denominator/status
     string still counts benchmark CLI plans, helper-script command plans,
     OCR/model readiness, Tesseract/OCRMyPDF/Ghostscript/Texify planning,
     Streamlit/FastAPI/Uvicorn route planning, multiprocessing lifecycle
     planning, Poetry/package metadata, shell lifecycle planning, and remote
     API polling plans. Those are blockers or optional support-library
     candidates until converted into bounded native PHP behavior with their own
     dependency-specific denominator, mapped fixtures, PHP pass/fail evidence,
     and corrupt/malformed cases.

7. **High - essential optional-library coverage remains backlog-only while
   rich-function lanes already depend on it.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:25` through `dependency-backlog.json:41`,
     `dependency-backlog.json:44` through `dependency-backlog.json:57`,
     `dependency-backlog.json:110` through `dependency-backlog.json:123`,
     `dependency-backlog.json:168` through `dependency-backlog.json:215`,
     `lanes/pandoc/lane-status.json`, and
     `lanes/markerpdf/lane-status.json`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library audit requirement.
   - Evidence: the only manifest files found are lane manifests under
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`; there is no support-library
     manifest with its own denominator. Pandoc rich-format work needs ZIP,
     OpenXML/EPUB/ODT/CFB, doctemplates, citation, math, charset, Unicode, and
     HTML/XML cores. markerPDF needs PDF text, page-render planning,
     layout/OCR result, table geometry, Unicode repair, and math/TeX cores.
     Syncthing still has protobuf wire-format risk; esbuild/LightningCSS need
     source-map and shared parser/text support; rclone/Syncthing need provider
     metadata and archive/compression boundaries. Backlog rows are necessary,
     but they do not count as support-library progress without a bounded native
     PHP component, activation gate, dependency-specific upstream/spec
     denominator, mapped fixtures, PHP pass/fail evidence, and malformed or
     corrupt cases where relevant.

8. **High - lane-local dependency expansion is too broad to count as shared
   support progress.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:23`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1262` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1263`,
     `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/VfsZipArchive.php:52` through
     `lanes/rclone/src/VfsZipArchive.php:104`, and
     `lanes/rclone/tests/VfsServeZipResponseTest.php:10` through
     `lanes/rclone/tests/VfsServeZipResponseTest.php:44`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library audit requirement.
   - Evidence: rclone's ZIP writer and serve-zip response can be valid
     lane-local VFS behavior, but they are not the shared
     `shared-zip-package-core` backlog item. They have no separate support
     manifest, activation gate, ZIP spec/upstream denominator, cross-lane
     mapped fixtures, corrupt central-directory/CRC/path-safety cases, or
     independent PHP pass/fail accounting. The readback test uses PHP
     `ZipArchive` as a local reader oracle, so it only supports the rclone
     slice.

9. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, and blocker fields in `lanes/gitoxide/lane-status.json`,
     `lanes/pandoc/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/syncthing/lane-status.json`, `lanes/esbuild/lane-status.json`,
     and `lanes/markerpdf/lane-status.json`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes. Major parity remains unexecuted, excluded, static-only, or
     pending supervisor acceptance: Gitoxide full Cargo workspace, Pandoc
     Haskell runner, Syncthing full `go test ./...`, markerPDF full
     benchmark/model runner, rclone provider/mount/live remote parity,
     Difftastic full Cargo, Esbuild `make test-all`, and libsqlite all/release
     permutations.

10. **Medium - `progress.md` active-lane handoff labels are stale relative to
    current lane-status files.**
    - Paths: `progress.md:66` through `progress.md:79`,
      `lanes/difftastic/lane-status.json:11`,
      `lanes/esbuild/lane-status.json:11`,
      `lanes/gitoxide/lane-status.json:11`,
      `lanes/markerpdf/lane-status.json:11`,
      `lanes/pandoc/lane-status.json:11`,
      `lanes/rclone/lane-status.json:11`,
      `lanes/readability/lane-status.json:11`, and
      `lanes/syncthing/lane-status.json:11`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still lists older handoffs such as Gitoxide SSH
      config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Syncthing system log, Difftastic Ada/Apex,
      rclone VFS Statfs, and Esbuild automatic JSX key/spread. Current
      lane-status files describe newer worktree-ignore id mapping, gradient
      fallback pruning, markerPDF negative split-index/markdown-to-pdf, system
      browse/service route work, Go generics, WebDAV property patch, and
      derived class-expression decorator work.

11. **Medium - manifest/status schemas remain non-normalized and make the
    dashboard hard to trust mechanically.**
    - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` and
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2404`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13` through
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`, and
      `porting-summary.json`.
    - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
      `goal.md:45`.
    - Evidence: denominator totals are sometimes numeric, sometimes huge prose
      strings, and Dolt currently has `mapped` near the top while a narrative
      `total` appears much later. PHP pass/fail values mix behavior tests,
      assertions, PASS cases, and mapped denominator checks. This schema drift
      is why the generated dashboard can silently publish stale or
      contradictory counts.

## Test Gate

- Ran `jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json`: passed.
- Did not run `php tools/run-tests.php`. The exact runner gate initially saw
  external focused and no-argument PHP runners, then cleared, then saw another
  focused Syncthing runner; the checkout also moved during audit sampling and
  remained a broad dirty active aggregate.

## Required Intervention

Hard-freeze writers, focused/root runners, dashboard/evaluator/watchdog/capacity
updates, and Dolt runners. Take two stable polls of `HEAD`, tracked status
count, untracked-inclusive status count, shortstat, exact PHP runner state,
Dolt runner state, and relevant log mtimes. Accept exactly one quiet lane batch
with normalized manifest/status fields, run focused verification and
`git diff --check`, run one serialized no-argument `php tools/run-tests.php`
from that same accepted snapshot if the exact gate is clear, regenerate
`porting.html`/`porting-summary.json`, then commit or reject the batch.
