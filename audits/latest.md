# Independent Audit - 2026-05-24T03:46Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`, root-runner process
state, process-launch scans, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T03:46Z
HEAD observed during audit: 8a1e6b872f08 -> eccd1eeb294b -> 6e732857d475
recent commits: 6e732857 Record integration hold status; eccd1eeb Record integration hold status; 8a1e6b87 Refresh independent audit handoff status
branch sample: main...origin/main [ahead 681, behind 68]
tracked dirty rows: 306
default status rows including untracked: 13175
git diff --shortstat: 306 files changed, 155851 insertions(+), 19383 deletions(-)
root run by this audit: not started
```

Required root-run gate:

```text
initial pgrep -af '^php tools/run-tests\.php( |$)':
30291 php tools/run-tests.php lanes/difftastic/tests
30365 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
30396 php tools/run-tests.php lanes/dolt/tests/DiffSummaryRendererTest.php ...
30404 php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests lanes/difftastic/tests lanes/esbuild/tests

final pgrep -af '^php tools/run-tests\.php( |$)':
40347 php tools/run-tests.php lanes/syncthing/tests

final owner evidence:
40347 claude 01:15 php tools/run-tests.php lanes/syncthing/tests

post-edit validation pgrep -af '^php tools/run-tests\.php( |$)': <no rows>

pre-commit validation pgrep -af '^php tools/run-tests\.php( |$)':
81969 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...

pre-commit owner evidence:
81969 claude 00:50 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
```

I did not start `php tools/run-tests.php`. The required gate matched active
root-harness invocations at the start and still matched a focused Syncthing run
at the final sample. Between those samples, `HEAD`, dirty counts, and shortstat
changed, so this checkout is not a stable snapshot for an audit-owned aggregate
run even when an exact no-argument root process is not visible.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance checkpoint.**
   - Paths: `progress.md:39`, `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`,
     `goal.md:49`, and `goal.md:52`.
   - Evidence: `HEAD` moved during review from `8a1e6b872f08` to
     `6e732857d475`; recent commits are audit/status-only integration-hold
     commits; tracked dirty rows increased to 306; default status rows are
     13,175; and the shortstat moved to 306 files changed with 155,851
     insertions. Lane statuses still describe pending or uncommitted handoffs,
     not accepted supervisor integration slices.

2. **Critical - no coherent root-harness result exists for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:10`, `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:48` and `goal.md:49`.
   - Evidence: the required process gate initially matched four active
     `php tools/run-tests.php ...` invocations and finally matched PID `40347`
     owned by `claude`. Focused lane-green claims in lane statuses explicitly
     say no-argument root verification remains supervisor/integrator-owned.
     That is intake evidence, not accepted aggregate evidence for
     `6e732857d475` plus the current dirty tree.

3. **Critical - `porting.html` and `porting-summary.json` are stale
   coordination artifacts.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting.html:75` through `porting.html:78`, and
     `dependency-backlog.json:1` through `dependency-backlog.json:5`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52`.
   - Evidence: the dashboard still advertises generated time
     `2026-05-23 23:43:54 UTC` and source commit `79768df0c427`; current
     `HEAD` is `6e732857d475` with newer dirty lane manifests/statuses. The
     dashboard says the dependency backlog has 22 items, while
     `dependency-backlog.json` has 23.

4. **High - dashboard, manifest, and lane-status counts disagree across active
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:212`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:10`, and
     `lanes/syncthing/lane-status.json:5` through
     `lanes/syncthing/lane-status.json:10`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: current Difftastic manifest says 809 / 439 while the dashboard
     says 735 / 374; markerPDF status says 348 total, 299 mapped, and 436 PHP
     behavior tests while the dashboard says 330 / 280 and 416; Rclone status
     says 783 pass while the dashboard says 698; Readability status says 224
     tests / 3,128 assertions while the dashboard says 204 pass; Syncthing
     status says 6,023 pass while the dashboard says 4,579.

5. **High - focused lane-green evidence is being recorded before supervisor
   acceptance and aggregate verification.**
   - Paths: `lanes/esbuild/lane-status.json:10` through
     `lanes/esbuild/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:10` through
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:13`, and
     `lanes/readability/lane-status.json:10` through
     `lanes/readability/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49`.
   - Evidence: these statuses report focused tests, examples, upstream probes,
     lint, and `git diff --check` as green, but the same records say root
     aggregate verification and commit acceptance remain pending. The
     acceptance boundary should stay red/blocked until one frozen snapshot has
     focused verification, root verification, dashboard regeneration, and a
     reviewable commit.

6. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native port progress.**
   - Paths: `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:9`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/src/BenchmarkCiWorkflowPlanner.php:49` through
     `lanes/markerpdf/src/BenchmarkCiWorkflowPlanner.php:75`,
     `lanes/markerpdf/src/BatchConverter.php:259` through
     `lanes/markerpdf/src/BatchConverter.php:337`, and
     `lanes/markerpdf/src/MarkerRuntimePlanner.php:33` through
     `lanes/markerpdf/src/MarkerRuntimePlanner.php:56`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:9`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40`.
   - Evidence: markerPDF reports 98% progress, 348 counted units, and 299
     mapped native semantics while the lane still includes GitHub Actions /
     benchmark archive plans, Streamlit/FastAPI/Uvicorn planning,
     multiprocessing/model lifecycle plans, Tesseract/Ghostscript/OCR readiness,
     Texify/model-tokenizer boundaries, and shell lifecycle metadata. Those are
     useful blockers or preflight records, but they should not count as rich
     native PDF extraction parity.

7. **High - essential optional-library coverage is still backlog-only while
   rich-function lanes already depend on it.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:23`, `dependency-backlog.json:25` through
     `dependency-backlog.json:43`, `dependency-backlog.json:45` through
     `dependency-backlog.json:124`, `dependency-backlog.json:382` through
     `dependency-backlog.json:399`, and
     `porting-summary.json:215` through `porting-summary.json:258`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library coverage requirement.
   - Evidence: only 12 lane manifests exist under `lanes/*`; there are no
     lane-grade support-library manifests. ZIP/package, XML/HTML5, DOCX/OpenXML,
     legacy DOC/CFB, ODT, doctemplates, PDF text, layout/OCR, table geometry,
     Unicode, source maps, protobuf, archive/compression, glob/pathspec, and
     provider metadata remain candidate/deferred backlog rows without their own
     upstream/spec denominators, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases, or accepted activation gates.

8. **High - lane-local archive/compression work is expanding without a shared
   dependency port.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:23`, `dependency-backlog.json:382` through
     `dependency-backlog.json:399`,
     `lanes/rclone/tests/GzipReaderTest.php:17` through
     `lanes/rclone/tests/GzipReaderTest.php:91`,
     `lanes/rclone/examples/wordpress-vfs-zip-download-preflight.php:9`
     through `lanes/rclone/examples/wordpress-vfs-zip-download-preflight.php:50`,
     and `lanes/markerpdf/tests/BenchmarkArchiveInspectorTest.php:8` through
     `lanes/markerpdf/tests/BenchmarkArchiveInspectorTest.php:57`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library coverage requirement.
   - Evidence: rclone has lane-local ZIP and gzip behavior; markerPDF benchmark
     archive tests create/read ZIPs via PHP `ZipArchive`. Neither has a shared
     dependency manifest, ZIP or compression spec denominator, cross-lane
     Pandoc/markerPDF/rclone fixtures, corrupt central directory/CRC/truncated
     stream/path-safety cases, or dependency-level pass/fail accounting.

9. **High - Gitoxide test subprocesses must remain oracle tooling and not
   native progress.**
   - Paths: `lanes/gitoxide/tests/GitUrlTest.php:70`,
     `lanes/gitoxide/tests/GitUrlTest.php:104`,
     `lanes/gitoxide/tests/FetchResponseTest.php:18`,
     `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1228` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1229`, and
     `lanes/gitoxide/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30`.
   - Evidence: process-launch scan still finds `proc_open()` in Gitoxide tests
     against `.upstream-cache/gitoxide`, while the manifest says shell-outs are
     not allowed for progress. That can be acceptable temporary oracle tooling,
     but any behavior depending on subprocesses must be excluded from native
     implementation progress until materialized as fixtures or parsed natively.

10. **High - near-complete percentages overstate accepted upstream parity.**
    - Paths: `porting.html:32`, `porting.html:56` through
      `porting.html:67`, `lanes/gitoxide/lane-status.json:5` through
      `lanes/gitoxide/lane-status.json:12`,
      `lanes/markerpdf/lane-status.json:5` through
      `lanes/markerpdf/lane-status.json:12`,
      `lanes/rclone/lane-status.json:5` through
      `lanes/rclone/lane-status.json:12`, and
      `lanes/syncthing/lane-status.json:5` through
      `lanes/syncthing/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
      `goal.md:38`, and `goal.md:40`.
    - Evidence: the dashboard advertises 97.7% average progress and most lanes
      at 98-99%, while major parity remains static-only, unexecuted, excluded,
      or pending acceptance: Gitoxide full Cargo workspace, markerPDF live
      benchmark/model runner, rclone live providers/mount/fstest, Syncthing
      full `go test ./...`, Pandoc Haskell runner and rich package
      readers/writers, Difftastic full Cargo runner, esbuild release-extra
      `make test-all`, and SQLite all/release permutations.

11. **Medium - `progress.md` active/current handoff text still lags current
    lane statuses.**
    - Paths: `progress.md:73` through `progress.md:84`,
      `lanes/dolt/lane-status.json:11`,
      `lanes/esbuild/lane-status.json:11`,
      `lanes/rclone/lane-status.json:11`, and
      `lanes/syncthing/lane-status.json:11`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still carries older active-lane summaries while
      current lane statuses describe newer Dolt TIME_TO_SEC/YEARWEEK evidence,
      esbuild class-expression accessor decorators, rclone WebDAV LOCK/UNLOCK,
      and Syncthing `/rest/db/prio` queue promotion work. Coordination readers
      cannot tell which handoff is the current source of truth without opening
      every lane file.

12. **Medium - manifest/status schemas remain too free-form for reliable
    dashboard generation.**
    - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
      `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:23`,
      `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12` through
      `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:32`,
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
- Ran a PHP process-launch scan over lane `src/`, `tests`, and `examples`.
  Actual subprocess launches found in Gitoxide tests via `proc_open()` are
  recorded above; Syncthing `PDO::exec()` matches are SQL calls, not process
  launches.
- Did not run `php tools/run-tests.php`: the required process gate matched
  active `php tools/run-tests.php ...` processes, and the checkout continued to
  move with broad unaccepted dirty lane changes.
