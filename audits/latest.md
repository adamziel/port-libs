# Independent Audit - 2026-05-24T03:24Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`, root-runner process
state, tmux session state, bounded shell-out scan, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, and shell-outs are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T03:24Z
HEAD observed during audit: 07451949 -> 1513b31d225d
recent commits: 1513b31d Record integration hold status; 07451949 Refresh independent audit status; b87d0708 Record integration hold status
branch sample: main...origin/main [ahead 670, behind 68]
tracked dirty rows: 305
default status rows including untracked: 12675
git diff --shortstat: 304 files changed, 151125 insertions(+), 17195 deletions(-)
tmux sessions: 184
root run by this audit: not started
```

Required root-run gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
03:20 sample: 3948003 php tools/run-tests.php
03:20 sample: 3951517 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ... lanes/syncthing/tests/SqliteCheckpointStoreTest.php
03:24 sample: 3984519 php tools/run-tests.php lanes/syncthing/tests
03:24 sample: 4002454 php tools/run-tests.php
03:24:56 sample: <no rows>

ps -o pid,user,etime,args -p 3984519,4002454
3984519 claude 01:16 php tools/run-tests.php lanes/syncthing/tests
4002454 claude 00:13 php tools/run-tests.php
```

I did not start a duplicate root run. No-argument root harnesses owned by
`claude` were active in the sampled window; by the final sample the process
gate was clear, but the checkout had already moved and remained unsuitable for
an audit-owned aggregate run.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance checkpoint.**
   - Paths: `progress.md:37`, `progress.md:39`,
     `lanes/*/lane-status.json`, and recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`,
     `goal.md:49`, and `goal.md:52`.
   - Evidence: `HEAD` moved during this audit from `07451949` to
     `1513b31d225d`; tracked dirty rows are now 305; default status rows are
     12,675; shortstat is 304 changed files with 151,125 insertions; tmux has
     184 sessions. Current lane-status files continue to describe pending or
     uncommitted handoffs rather than accepted commits.

2. **Critical - no coherent root-harness result exists for the current
   snapshot, and a duplicate root run would violate the process gate.**
   - Paths: root gate above, `tools/run-tests.php`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:48` and `goal.md:49`.
   - Evidence: `pgrep -af '^php tools/run-tests\.php( |$)'` matched active
     no-argument root PID `3948003` earlier, then active no-argument root PID
     `4002454` owned by `claude` at the pre-commit sample, plus focused
     Syncthing PIDs. A final gate sample was clear only after that active run
     exited; no new audit-owned root run was started because source/status
     counts were still changing and the completed run is not tied to a frozen
     accepted snapshot.

3. **Critical - `porting.html` and `porting-summary.json` are stale
   coordination artifacts.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `dependency-backlog.json:3` through `dependency-backlog.json:5`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52`.
   - Evidence: the dashboard was generated on `2026-05-23 23:43:54 UTC` from
     snapshot `79768df0c427`, while current `HEAD` is `1513b31d225d` with
     newer dirty manifests/statuses. The dashboard still reports 22 dependency
     backlog items; `dependency-backlog.json` has 23.

4. **High - dashboard, manifest, and status counts disagree across active
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:10` through `porting-summary.json:120`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:16`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:18`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: current manifests report Difftastic 803 total / 429 mapped
     while the dashboard says 735 / 374; markerPDF 347 / 298 and 435 PHP pass
     while the dashboard says 330 / 280 and 416; rclone 777 mapped while the
     dashboard says 698; Gitoxide lane status now reports 6,134 PHP assertions
     while the dashboard says 5,634 pass; Syncthing lane status reports 99 lane
     files / 5,849 assertions while the dashboard still shows 4,579 pass.

5. **High - focused lane-green evidence is being recorded before supervisor
   acceptance and aggregate verification.**
   - Paths: `lanes/gitoxide/lane-status.json:10` through
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:10` through
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:17` through
     `lanes/syncthing/lane-status.json:18`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49`.
   - Evidence: these statuses publish focused PHP, focused upstream, lint,
     example, or diff-check passes, but the same handoffs say root aggregate
     verification was not assigned, root PHP was not run by the lane worker,
     or commit acceptance remains supervisor/integrator owned. That is intake
     evidence, not accepted portfolio progress.

6. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/lane-status.json:5`, and
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:25`,
     `goal.md:30`, `goal.md:35`, and `goal.md:40`.
   - Evidence: the counted status slug and source text still include benchmark
     CLI plans, chunk-convert shell lifecycle planning, marker_server and
     marker_app route/runtime planning, Poetry/package/workflow planning,
     OCRMyPDF/Tesseract/Ghostscript readiness, Texify/model/tokenizer
     boundaries, Streamlit/FastAPI/Uvicorn behavior, and Python
     multiprocessing boundaries. Those may be blockers, oracle notes, or
     gated support-library candidates, but they are not native PHP progress.

7. **High - essential optional-library coverage remains backlog-only, while
   rich-function lanes already depend on those libraries.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:22`, `dependency-backlog.json:25` through
     `dependency-backlog.json:40`, `dependency-backlog.json:44` through
     `dependency-backlog.json:58`, `dependency-backlog.json:110` through
     `dependency-backlog.json:120`, and the repository manifest list from
     `find . -maxdepth 4 -name UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library coverage requirement.
   - Evidence: the only manifests are the 12 lane manifests; there are no
     support-library manifests. The backlog contains 23 gated items such as
     ZIP/package, XML/HTML, DOCX/OpenXML, legacy DOC/CFB, EPUB, doctemplates,
     PDF text, layout/OCR, table geometry, source maps, protobuf, checksums,
     archive/compression, glob/pathspec, and provider metadata. Pandoc rich
     formats, markerPDF OCR/layout/PDF text, Readability serialization, and
     rclone archives all need bounded native support libraries with their own
     activation gates, denominators, mapped fixtures, malformed/corrupt cases,
     and PHP pass/fail evidence.

8. **High - rclone ZIP work is lane-local dependency expansion, not shared
   support-library progress.**
   - Paths: `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/VfsZipArchive.php:52` through
     `lanes/rclone/src/VfsZipArchive.php:104`,
     `lanes/rclone/tests/VfsServeZipResponseTest.php:10` through
     `lanes/rclone/tests/VfsServeZipResponseTest.php:44`, and
     `lanes/rclone/lane-status.json:8` through
     `lanes/rclone/lane-status.json:9`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library coverage requirement.
   - Evidence: `VfsZipArchive` is a minimal ZIP32 writer for rclone VFS
     behavior, and its readback test uses PHP `ZipArchive` as the oracle. It
     lacks a shared activation gate, ZIP spec/upstream denominator, cross-lane
     Pandoc/markerPDF/rclone fixtures, corrupt central-directory/CRC/path
     safety cases, and independent dependency-level pass/fail accounting.

9. **High - Gitoxide test evidence now relies on `git` subprocesses and must
   be labeled oracle-only.**
   - Paths: `lanes/gitoxide/tests/GitUrlTest.php:68` through
     `lanes/gitoxide/tests/GitUrlTest.php:83`,
     `lanes/gitoxide/tests/GitUrlTest.php:101` through
     `lanes/gitoxide/tests/GitUrlTest.php:115`,
     `lanes/gitoxide/tests/FetchResponseTest.php:15` through
     `lanes/gitoxide/tests/FetchResponseTest.php:30`,
     `lanes/gitoxide/tests/FetchV2SessionTest.php:10` through
     `lanes/gitoxide/tests/FetchV2SessionTest.php:25`, and
     `lanes/gitoxide/lane-status.json:8`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:39`.
   - Evidence: bounded scan found `proc_open()` calls that invoke `git` for
     diagnostics and upstream fixture reads. That is acceptable only as
     explicit oracle/fixture tooling. It should not be counted as native
     implementation progress, and pass counts should distinguish subprocess
     oracle tests from pure PHP fixture tests.

10. **High - near-complete percentages overstate accepted upstream parity.**
    - Paths: `porting.html:32`, `porting.html:56` through
      `porting.html:67`, `lanes/gitoxide/lane-status.json:12`,
      `lanes/markerpdf/lane-status.json:12`,
      `lanes/pandoc/lane-status.json:12`,
      `lanes/rclone/lane-status.json:12`, and
      `lanes/syncthing/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
      `goal.md:38`, and `goal.md:40`.
    - Evidence: the dashboard advertises 97.7% average progress and most lanes
      at 98-99%, while major parity remains unexecuted, static-only, excluded,
      or pending acceptance: Gitoxide full Cargo workspace, Pandoc full Haskell
      runner, Syncthing full `go test ./...`, markerPDF benchmark/model
      runner, rclone live provider/mount/fstest breadth, Difftastic full Cargo,
      esbuild release-extra `make test-all`, and libsqlite all/release
      permutations.

11. **Medium - `progress.md` active-lane handoff labels lag current lane
    statuses.**
    - Paths: `progress.md:69` through `progress.md:82`,
      `lanes/dolt/lane-status.json:11`,
      `lanes/esbuild/lane-status.json:11`,
      `lanes/gitoxide/lane-status.json:11`,
      `lanes/rclone/lane-status.json:11`, and
      `lanes/syncthing/lane-status.json:18`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still names older handoffs such as Gitoxide SSH
      config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Syncthing system log, Difftastic Ada/Apex,
      rclone VFS Statfs, and esbuild automatic JSX key/spread. Current status
      files describe newer work including Gitoxide gix-index v4, rclone WebDAV
      PROPFIND, Syncthing pause/resume, Dolt DAYNAME/date functions, and
      esbuild computed class-expression accessors.

12. **Medium - manifest/status schemas remain too free-form for reliable
    dashboard generation.**
    - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
      `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:16`,
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:18`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12` through
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:22`,
      `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12` through
      `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:16`, and
      `porting-summary.json:10` through `porting-summary.json:120`.
    - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
      `goal.md:45`.
    - Evidence: denominator and status fields mix integers, long prose strings,
      current-batch status slugs, inventory labels, and separate count fields.
      The dashboard then truncates or collapses denominator, mapped, and PHP
      pass/fail data, making automated acceptance and stale-artifact detection
      fragile.

## Tests

- Ran `jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json`: pass.
- Ran bounded shell-out scan under lane `src/`, `tests/`, and `examples/`.
  Findings: SQL `PDO::exec()` in `lanes/syncthing/src/SqliteCheckpointStore.php`
  is not a shell-out; Gitoxide tests contain `proc_open()` calls to `git` and
  are recorded above as oracle-only risk.
- Did not run `php tools/run-tests.php`: active `claude`-owned no-argument
  root PID `3948003` was already running at the first gate sample; pre-commit
  sampling later showed active no-argument root PID `4002454` and focused
  Syncthing PID `3984519`; a final sample was clear only after the tree had
  moved and remained unstable.

## Next Intervention

Hard-freeze writer/runner/status churn, then require two stable samples of
`HEAD`, tracked status count, untracked-inclusive status count, shortstat,
exact root-runner state, and dashboard source commit. Accept one lane batch
only after schema/count normalization and focused verification; then run one
serialized no-argument root harness from that exact frozen snapshot, or reuse a
completed external root result only if it demonstrably matches the frozen
snapshot. Regenerate `porting.html`/`porting-summary.json`, then commit or
reject.
