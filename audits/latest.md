# Independent Audit - 2026-05-24T03:41Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`, root-runner process
state, process-launch scans, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T03:41Z
HEAD observed during audit: 2179fa42df43 -> a6d18cd76696 -> 66b7b91d917c
recent commits: 66b7b91d Record integration hold status; 543d4737 Refresh independent audit status; a6d18cd7 Record integration hold status
branch sample: main...origin/main [ahead 678, behind 68]
tracked dirty rows: 302
default status rows including untracked: 13045
git diff --shortstat: 302 files changed, 154833 insertions(+), 19171 deletions(-)
root run by this audit: not started
```

Required root-run gate:

```text
initial pgrep -af '^php tools/run-tests\.php( |$)':
4143906 php tools/run-tests.php
4144559 php tools/run-tests.php lanes/quadrable/tests
4144887 php tools/run-tests.php lanes/readability/tests
4144964 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
4145021 php tools/run-tests.php lanes/syncthing/tests/ConfigFoldersTest.php ...
4145237 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
4145583 php tools/run-tests.php lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests
4145607 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
4145679 php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests lanes/difftastic/tests lanes/esbuild/tests

owner sampling after the initial gate:
4145237 claude php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
The no-argument root PID 4143906 exited before owner sampling, so owner evidence
could not be recovered without inspecting process environments or external logs.

pre-commit pgrep -af '^php tools/run-tests\.php( |$)': <no rows>

post-commit/before-finish pgrep -af '^php tools/run-tests\.php( |$)':
2447 php tools/run-tests.php
5141 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...

post-commit owner evidence:
2447 claude php tools/run-tests.php
5141 claude php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
```

I did not start a root run. The initial required gate matched another
no-argument root harness, and by the time it cleared `HEAD` had moved to
`a6d18cd76696` while the checkout remained a broad dirty aggregate. Before
finish, `HEAD` moved again to `66b7b91d917c` and an external no-argument root
PID `2447` owned by `claude` was active, so no duplicate was started.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance checkpoint.**
   - Paths: `progress.md:39`, `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/gitoxide/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`,
     `goal.md:49`, and `goal.md:52`.
   - Evidence: `HEAD` moved during this audit from `2179fa42df43` through
     `a6d18cd76696` to `66b7b91d917c`; recent commits are audit/status-only integration-hold
     commits; tracked dirty rows are 302; default status rows are 13,045; and
     shortstat is 302 files changed with 154,833 insertions. Current lane
     statuses still say handoffs are `pending`, `uncommitted`, or not accepted
     by supervisor/integrator ownership.

2. **Critical - no coherent root-harness result exists for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/esbuild/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:48` and `goal.md:49`.
   - Evidence: the initial gate matched root PID `4143906 php
     tools/run-tests.php`; it exited before owner sampling. Later exact root
     gates were briefly clear, but the checkout had moved and all lane
     handoffs remained dirty or pending. Before finish, another external
     no-argument root PID `2447` owned by `claude` was active. Focused
     lane-green records in lane statuses do not establish accepted aggregate
     evidence for `66b7b91d917c`.

3. **Critical - `porting.html` and `porting-summary.json` are stale
   coordination artifacts.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting.html:75` through `porting.html:78`, and
     `dependency-backlog.json:1` through `dependency-backlog.json:5`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52`.
   - Evidence: the dashboard still advertises snapshot `79768df0c427`
     generated on `2026-05-23 23:43:54 UTC`, while current `HEAD` is
     `66b7b91d917c` with newer dirty manifests/statuses. The dashboard reports
     22 dependency items; `dependency-backlog.json` has 23.

4. **High - dashboard, manifest, and lane-status counts disagree across active
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/lane-status.json:4` through
     `lanes/pandoc/lane-status.json:6`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: current Difftastic says 809 / 439 while the dashboard says 735
     / 374; markerPDF says 348 / 299 and 436 PHP pass while the dashboard says
     330 / 280 and 416; rclone says 1601 / 783 while the dashboard says 1601 /
     698; Pandoc status says 1,344 mapped and 306 pass while the dashboard
     says 1,061 and 278; Syncthing status says 6,023 pass while the dashboard
     says 4,579.

5. **High - focused lane-green evidence is being recorded before supervisor
   acceptance and aggregate verification.**
   - Paths: `lanes/esbuild/lane-status.json:12` through
     `lanes/esbuild/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:12` through
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/rclone/lane-status.json:12` through
     `lanes/rclone/lane-status.json:13`, and
     `lanes/readability/lane-status.json:12` through
     `lanes/readability/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49`.
   - Evidence: these files report focused lane tests, examples, upstream
     probes, lint, and diff checks as green, but the same records say root
     aggregate verification and commit acceptance remain pending. That is
     intake evidence, not accepted portfolio progress.

6. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:721`,
     `lanes/markerpdf/lane-status.json:5`, and
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:9`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40`.
   - Evidence: markerPDF reports 348 counted static behavior/reference units,
     299 mapped native PHP semantics, and 98% progress while the manifest
     status and current slice still include benchmark CLI plans, GitHub
     Actions/package workflow planning, server/app route planning,
     Poetry/runtime/model dependency planning, OCR/Tesseract/Ghostscript
     readiness, Texify/model-tokenizer boundaries, Streamlit/FastAPI/Uvicorn
     behavior, multiprocessing planning, and chunk-convert shell lifecycle
     metadata. Those are useful blockers or preflight notes, but they should
     not count as rich native PDF extraction progress.

7. **High - essential optional-library coverage is still backlog-only while
   rich-function lanes already depend on it.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:110` through `dependency-backlog.json:123`,
     `dependency-backlog.json:168`, `dependency-backlog.json:202`,
     `dependency-backlog.json:285`, `dependency-backlog.json:321`,
     `dependency-backlog.json:381`, `dependency-backlog.json:401`, and
     `dependency-backlog.json:421`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library coverage requirement.
   - Evidence: the repository still has only the 12 lane manifests; there are
     no support-library manifests. ZIP/package, XML/HTML5, DOCX/OpenXML,
     legacy DOC/CFB, ODT, doctemplates, PDF text, layout/OCR, source maps,
     protobuf, archive/compression, glob/pathspec, and provider metadata all
     remain candidates/deferred rows without dependency-specific denominators,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases, or
     activation gates recorded in lane-level form.

8. **High - lane-local archive/package work should be split into shared
   dependency ports before it is counted broadly.**
   - Paths: `lanes/rclone/src/VfsZipArchive.php:10` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/tests/VfsVirtualTreeTest.php:22`,
     `lanes/rclone/tests/VfsServeZipResponseTest.php:18`,
     `lanes/markerpdf/tests/BenchmarkArchiveInspectorTest.php:8` through
     `lanes/markerpdf/tests/BenchmarkArchiveInspectorTest.php:10`, and
     `dependency-backlog.json:7` through `dependency-backlog.json:23`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library coverage requirement.
   - Evidence: rclone has a lane-local ZIP32 writer and validates readback via
     PHP `ZipArchive`; markerPDF benchmark archive tests create/read ZIPs via
     PHP `ZipArchive`. Neither has a shared dependency manifest, ZIP spec
     denominator, cross-lane Pandoc/markerPDF/rclone fixtures, corrupt central
     directory/CRC/path-safety cases, or dependency-level pass/fail accounting.

9. **High - Gitoxide tests now shell out to `git`; this must remain oracle
   tooling and not native progress.**
   - Paths: `lanes/gitoxide/tests/GitUrlTest.php:68` through
     `lanes/gitoxide/tests/GitUrlTest.php:82`,
     `lanes/gitoxide/tests/GitUrlTest.php:101` through
     `lanes/gitoxide/tests/GitUrlTest.php:115`,
     `lanes/gitoxide/tests/FetchResponseTest.php:15` through
     `lanes/gitoxide/tests/FetchResponseTest.php:30`, and
     `lanes/gitoxide/tests/FetchV2SessionTest.php:10` through
     `lanes/gitoxide/tests/FetchV2SessionTest.php:25`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30`.
   - Evidence: these tests use `proc_open()` to invoke `git cat-file` or
     diagnostic helpers against `.upstream-cache/gitoxide`. That can be
     acceptable temporary oracle/fixture tooling, but any behavior depending on
     this subprocess must be explicitly excluded from native implementation
     progress until fixtures are materialized or the PHP code parses the needed
     objects itself.

10. **High - near-complete percentages overstate accepted upstream parity.**
    - Paths: `porting.html:32`, `porting.html:56` through
      `porting.html:67`, `lanes/gitoxide/lane-status.json:5` through
      `lanes/gitoxide/lane-status.json:12`,
      `lanes/pandoc/lane-status.json:5` through
      `lanes/pandoc/lane-status.json:12`,
      `lanes/rclone/lane-status.json:5` through
      `lanes/rclone/lane-status.json:12`, and
      `lanes/syncthing/lane-status.json:5` through
      `lanes/syncthing/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
      `goal.md:38`, and `goal.md:40`.
    - Evidence: the dashboard advertises 97.7% average progress and most lanes
      at 98-99%, while major parity remains static-only, excluded, unexecuted,
      or pending acceptance: Gitoxide full Cargo workspace, Pandoc Haskell
      runner and rich package readers/writers, Syncthing full `go test ./...`,
      markerPDF live benchmark/model runner, Difftastic full Cargo runner,
      rclone live providers/mount/fstest, esbuild release-extra `make
      test-all`, and SQLite all/release permutations.

11. **Medium - `progress.md` active-lane handoffs lag current lane statuses.**
    - Paths: `progress.md:73` through `progress.md:84`,
      `lanes/gitoxide/lane-status.json:11`,
      `lanes/rclone/lane-status.json:11`,
      `lanes/syncthing/lane-status.json:11`, and
      `lanes/lightningcss/lane-status.json:11`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still lists older handoffs such as Gitoxide SSH
      config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Syncthing system log, rclone VFS Statfs, and
      esbuild automatic JSX key/spread. Current lane statuses describe newer
      Gitoxide gix-index EOIE/IEOT, rclone WebDAV PROPFIND/PROPPATCH,
      Syncthing DB prio, and LightningCSS advanced-color gradient slices.

12. **Medium - manifest/status schemas remain too free-form for reliable
    dashboard generation.**
    - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
      `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
      `porting-summary.json:10` through `porting-summary.json:26`, and
      `porting.html:56` through `porting.html:67`.
    - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
      `goal.md:45`.
    - Evidence: denominator/status fields mix integers, long prose strings,
      current-batch slugs, inventory labels, runner notes, behavior checks,
      assertion counts, and pending/prose commit labels. The dashboard then
      truncates or collapses denominator, mapped-test, PHP pass/fail, and
      commit fields, making stale-artifact detection and acceptance gating
      fragile.

## Tests

- Ran `jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json`: pass.
- Ran a PHP process-launch scan over lane `src/`, `tests/`, and `examples`.
  Actual subprocess launches found in Gitoxide tests via `proc_open()` are
  recorded above; `PDO::exec()` matches in Syncthing are SQL calls, not process
  launches.
- Ran `git diff --check -- audits/latest.md progress.md`: pass after edits.
- Did not run `php tools/run-tests.php`: the initial required process gate
  matched another no-argument root harness, and after it cleared the checkout
  had moved and remained too dirty for a stable audit-owned aggregate run.
