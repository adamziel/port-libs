# Independent Audit - 2026-05-24T02:44Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, process/test-runner
state, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, plan-only wrappers,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
current UTC samples: 2026-05-24T02:43:06Z, 2026-05-24T02:44:40Z, and 2026-05-24T02:48:30Z
HEAD: cda38725cd53 Refresh independent audit status
recent commits: cda38725 Refresh independent audit status; 0f2bdf1d Record integration hold status; 6ba736f5 Record integration hold status
branch sample: main...origin/main [ahead 659, behind 68]
tracked dirty rows: 300
default status rows including untracked: 11626 -> 11684 during audit sampling
git diff --shortstat: 300 files changed, 147569 insertions(+), 16864 deletions(-)
tmux sessions: 177
active coordination sample: capacity executor/controller, dashboard updater, watchdog, evaluator, Dolt BATS control agents
root run by this audit: not started
external root harness at pre-finish: PID 3481248 owned by claude, `php tools/run-tests.php`
```

Required root-run gate samples:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
initial sample: <no rows>
later sample: 3436695 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ... lanes/syncthing/tests/SystemLogLevelsTest.php
pre-finish sample: 3481248 php tools/run-tests.php; 3481715 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests; 3484179/3484242 focused Syncthing shards
```

The later PID `3436695` was a focused Syncthing PHP shard and exited before
owner sampling. The pre-finish gate then matched an external no-argument root
harness, PID `3481248` owned by `claude` (`php tools/run-tests.php`), plus
focused lane shards. I did not start a duplicate root run.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance or root-verification target.**
   - Paths: `progress.md:39`, `progress.md:63` through `progress.md:80`,
     all current `lanes/*/lane-status.json:5` through
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require small committed slices, verification, cleanup, and
     honest repo-wide test/static-check records.
   - Evidence: `HEAD` stayed at `cda38725cd53` during this sample, but the
     default status surface changed from 11,626 to 11,684 rows while the audit
     was reading. The tree still has 300 tracked dirty rows and 147,569
     insertions in tracked diffs. Recent history is mostly alternating audit
     and integration-hold commits, and every lane still reports pending,
     uncommitted, or shared-dirty handoffs rather than accepted lane commits.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict the current source of truth.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`, `porting.html:73` through
     `porting.html:78`, `porting-summary.json:2` through
     `porting-summary.json:8`, and `dependency-backlog.json:3` through
     `dependency-backlog.json:4`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still says it was generated
     `2026-05-23 23:43:54 UTC` from source commit `79768df0c427`, while the
     checkout is at `cda38725cd53` with newer dirty manifests/status files.
     The dashboard dependency section still reports 22 items, 12 candidates,
     and 10 medium items; `dependency-backlog.json` now reports 23 items, 13
     candidates, and 11 medium items after `pandoc-doctemplates-core`.

3. **High - public dashboard counts disagree with current manifests/statuses
   across most lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:90`,
     `porting-summary.json:96` through `porting-summary.json:205`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:36`, and current
     `lanes/*/lane-status.json:5` through `lanes/*/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45`.
   - Evidence: current manifests/statuses now report Difftastic 790/409 and
     2,556 PHP assertions while the dashboard has 735/374 and 374 pass;
     Dolt PHP 378 while dashboard has 356; Esbuild 333 while dashboard has
     311; Gitoxide 2,877 mapped and 6,108 PHP assertions while dashboard has
     2,751 and 5,634; libsqlite 300 while dashboard has 286; LightningCSS
     1,902 mapped and 2,409 PHP assertions while dashboard has 1,732 and
     2,197; markerPDF 344/295 and 432 PHP tests while dashboard has 330/280
     and 416; Pandoc 1,262 and 301 while dashboard has 1,061 and 278; rclone
     760 while dashboard has 698; Readability 220 while dashboard has 204; and
     Syncthing 5,703 while dashboard has 4,579.

4. **High - focused lane-green evidence is still being recorded before
   supervisor acceptance and aggregate verification.**
   - Paths: all `lanes/*/lane-status.json:10` through
     `lanes/*/lane-status.json:13`, especially
     `lanes/esbuild/lane-status.json:10` through
     `lanes/esbuild/lane-status.json:13`,
     `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:10` through
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49`.
   - Evidence: lane statuses record green focused PHP suites, examples, lints,
     and lane diff checks, but the same records say no-argument root
     verification is pending and latest commits are pending, uncommitted, or
     shared-dirty. These records are intake evidence, not accepted portfolio
     progress.

5. **High - `progress.md` active-lane handoff labels are stale relative to
   current lane-status files.**
   - Paths: `progress.md:67` through `progress.md:78`,
     `lanes/difftastic/lane-status.json:11`,
     `lanes/esbuild/lane-status.json:11`,
     `lanes/gitoxide/lane-status.json:11`,
     `lanes/lightningcss/lane-status.json:11`,
     `lanes/markerpdf/lane-status.json:11`,
     `lanes/pandoc/lane-status.json:11`,
     `lanes/rclone/lane-status.json:11`,
     `lanes/readability/lane-status.json:11`, and
     `lanes/syncthing/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44`.
   - Evidence: `progress.md` still lists older handoffs such as Gitoxide SSH
     config-options, LightningCSS trig/math, markerPDF benchmark
     file-inventory planning, Readability negative-header cleanup, Syncthing
     system log, Difftastic Ada/Apex, rclone VFS Statfs, and Esbuild automatic
     JSX key/spread. Current lane statuses describe newer worktree ignore
     id-mapping, gradient fallback pruning, markerPDF negative split-index,
     code-ancestor cleanup, system browse, Go const/var/iota, WebDAV property
     patch, and private class-expression decorators.

6. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:435`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:445` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:456`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:521` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:533`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:545` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:579`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:925` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:942`, and
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:25`,
     `goal.md:30`, `goal.md:31`, and `goal.md:35`.
   - Evidence: markerPDF has useful native PHP slices, but the manifest/status
     also count benchmark CLI plans, helper-script command plans, OCR/model
     readiness plans, Tesseract/OCRMyPDF/Ghostscript/Texify planning,
     Streamlit/FastAPI/Uvicorn route planning, multiprocessing lifecycle
     planning, Poetry/package metadata, and shell lifecycle planning. Those are
     blockers or optional support-library candidates unless converted into
     bounded native PHP behavior with dependency-specific denominators and
     fixtures.

7. **High - essential optional-library coverage remains backlog-only while
   rich-function lanes already depend on it.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:25` through `dependency-backlog.json:41`,
     `dependency-backlog.json:44` through `dependency-backlog.json:57`,
     `dependency-backlog.json:110` through `dependency-backlog.json:123`,
     `dependency-backlog.json:168` through `dependency-backlog.json:215`,
     `lanes/pandoc/lane-status.json:5` through
     `lanes/pandoc/lane-status.json:14`, and
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library audit requirement.
   - Evidence: Pandoc is mapping DOCX Native image/table handoff, but shared
     ZIP/OpenXML/doctemplates support is still only a backlog candidate rather
     than a support component with its own denominator. markerPDF still needs
     bounded PDF text, layout/OCR-result, table geometry, Unicode repair, and
     renderer-planning cores for real scanned/structured-document parity.
     Backlog rows are necessary but do not count as support-library progress
     without a native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases.

8. **High - lane-local dependency expansion is too broad to count as shared
   support progress.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:23`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1258` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1259`,
     `lanes/rclone/src/VfsZipArchive.php:8` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/VfsZipArchive.php:54` through
     `lanes/rclone/src/VfsZipArchive.php:104`,
     `lanes/rclone/src/VfsServeZipResponse.php:8` through
     `lanes/rclone/src/VfsServeZipResponse.php:10`,
     `lanes/rclone/src/VfsServeZipResponse.php:75` through
     `lanes/rclone/src/VfsServeZipResponse.php:103`, and
     `lanes/rclone/tests/VfsServeZipResponseTest.php:18` through
     `lanes/rclone/tests/VfsServeZipResponseTest.php:39`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library audit requirement.
   - Evidence: rclone's ZIP writer and serve-zip response can be valid
     lane-local VFS behavior, but they are not the shared
     `shared-zip-package-core` item. They have no separate support-library
     manifest, activation gate, ZIP spec/upstream denominator, cross-lane
     mapped fixtures, or corrupt central-directory/CRC/path-safety cases. The
     test uses PHP `ZipArchive` as a local reader oracle, so it supports that
     slice only.

9. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/gitoxide/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`, and
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes. Major parity remains unexecuted, excluded, static-only, or
     pending supervisor acceptance: Gitoxide full Cargo workspace, Pandoc
     Haskell runner, Syncthing full `go test ./...`, markerPDF full
     benchmark/model runner, rclone provider/mount/live remote parity,
     Difftastic full Cargo, Esbuild `make test-all`, and libsqlite all/release
     permutations.

10. **Medium - manifest/status schemas remain non-normalized and make the
    dashboard hard to trust mechanically.**
    - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13` through
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13` through
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:22`,
      `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` through
      `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:38`, and
      `porting-summary.json:10` through `porting-summary.json:18`.
    - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
      `goal.md:45`.
    - Evidence: denominator totals are sometimes numeric, sometimes narrative
      strings, sometimes split across `total` and `totalCount`, and sometimes
      absent in favor of `mapped` plus `latestSlice`. PHP pass/fail values mix
      behavior tests, assertions, PASS cases, and mapped denominator checks.

## Test Gate

I did not run `php tools/run-tests.php`.

The required process gate was checked before considering a root run. It was
initially clear, later matched transient focused Syncthing PID `3436695`, and
the pre-finish gate matched external no-argument root PID `3481248` owned by
`claude` plus focused lane shards. Because another root harness was active at
handoff and the dirty status surface changed during audit sampling, no duplicate
root run was started by this audit.

## Next Intervention

Freeze writers, status publishers, focused PHP loops, broad upstream runners,
and dashboard generation. Confirm the exact PHP harness gate is empty, then
poll `HEAD`, tracked status count, default status count, shortstat, runner
state, and relevant log mtimes twice without movement. Accept exactly one
lane-scoped batch, normalize its manifest/status schema, run focused
verification and `git diff --check`, run one serialized no-argument
`php tools/run-tests.php` from the same snapshot, regenerate `porting.html` and
`porting-summary.json` from the accepted commit, then commit or reject.
