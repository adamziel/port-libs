# Independent Audit - 2026-05-24T03:33Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`, root-runner process
state, bounded shell-out scans, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T03:33Z
HEAD observed during audit: 2019e4b0cab0 -> 3e5b77fd295b
recent commits: 3e5b77fd Record integration hold status; 0b3502bf Record integration hold status; 2019e4b0 Refresh independent audit status
branch sample: main...origin/main [ahead 674, behind 68]
tracked dirty rows: 304
default status rows including untracked: 12855
git diff --shortstat: 304 files changed, 154313 insertions(+), 19473 deletions(-)
root run by this audit: not started
```

Required root-run gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
03:29 sample: 4083057 php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
ps -o pid,user,etime,args -p 4083057: process exited before owner sampling
03:34 sample: focused lane runners 4119429, 4120030, 4120373, 4121728
ps owner evidence: 4119429 claude php tools/run-tests.php lanes/syncthing/tests/...; 4120030 claude php tools/run-tests.php lanes/syncthing/tests/...; 4120373 claude php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests; 4121728 claude php tools/run-tests.php lanes/syncthing/tests
exact no-argument root pgrep '^php tools/run-tests\.php$': <no rows>
```

I did not start a root run. The gate briefly matched a focused Pandoc lane
harness, not a no-argument root harness, and it exited before owner sampling.
Later samples matched focused Syncthing/rclone lane runners owned by `claude`,
but exact no-argument root remained clear. `HEAD` had moved twice during review
and the checkout was still a broad dirty aggregate, so an audit-owned aggregate
run would not produce an acceptance result for a frozen snapshot.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance checkpoint.**
   - Paths: `progress.md:37`, `progress.md:39`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`,
     `goal.md:49`, and `goal.md:52`.
   - Evidence: `HEAD` moved during this audit from `2019e4b0cab0` through
     `0b3502bf7589` to `3e5b77fd295b`; recent commits are audit/status-only
     integration-hold commits; tracked dirty rows are 304; default status rows
     are 12,855; shortstat is 304 files changed with 154,313 insertions.
     Current lane
     statuses still say handoffs are `pending`, `uncommitted`, or owned by the
     supervisor/integrator after root verification.

2. **Critical - no coherent root-harness result exists for the current
   snapshot.**
   - Paths: `tools/run-tests.php`,
     `lanes/esbuild/lane-status.json:10` through
     `lanes/esbuild/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:10` through
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:48` and `goal.md:49`.
   - Evidence: current lane statuses publish focused green evidence while
     explicitly leaving no-argument root verification pending. The required
     `pgrep -af '^php tools/run-tests\.php( |$)'` samples matched focused
     Pandoc PID `4083057` and later focused Syncthing/rclone PIDs `4119429`,
     `4120030`, `4120373`, and `4121728` owned by `claude`; exact
     no-argument root remained clear. Because `HEAD` moved during review and
     the tree remained dirty, no new audit-owned root run was started.

3. **Critical - `porting.html` and `porting-summary.json` are stale
   coordination artifacts.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting.html:75`, and `dependency-backlog.json:3` through
     `dependency-backlog.json:5`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52`.
   - Evidence: the dashboard still advertises snapshot `79768df0c427`
     generated on `2026-05-23 23:43:54 UTC`, while current `HEAD` is
     `3e5b77fd295b` with newer dirty manifests/statuses. The dashboard still
     reports 22 dependency items; `dependency-backlog.json` has 23.

4. **High - dashboard, manifest, and lane-status counts now disagree across
   active lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/pandoc/lane-status.json:5` through
     `lanes/pandoc/lane-status.json:7`, and
     `lanes/syncthing/lane-status.json:5` through
     `lanes/syncthing/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: current Difftastic manifest says 806 inspected artifacts / 436
     mapped while the dashboard says 735 / 374; markerPDF says 347 / 298 and
     435 PHP pass while the dashboard says 330 / 280 and 416; rclone says
     1601 / 777 while the dashboard says 1601 / 698; Pandoc status says 1,344
     mapped checks and 306 pass while the dashboard says 1,061 mapped and 278
     pass; Syncthing status says 5,953 pass while the dashboard says 4,579.

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
   - Evidence: these files report focused lane tests, examples, upstream
     probes, lint, and diff checks as green, but the same records state root
     aggregate verification was not assigned or not run, and latest commits
     remain pending/uncommitted. That is intake evidence, not accepted
     portfolio progress.

6. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19` and
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40`.
   - Evidence: markerPDF reports 347 counted static behavior/reference units,
     298 mapped native PHP semantics, and 98% progress while its status/phase
     still includes benchmark CLI plans, GitHub Actions/package workflow
     planning, marker server/app route planning, Poetry/runtime/model
     dependency graph planning, OCR/Tesseract/Ghostscript readiness, Texify
     and model-tokenizer boundaries, Streamlit/FastAPI/Uvicorn behavior, and
     multiprocessing planning. These are useful blocker/oracle/support notes,
     but they should not count as rich native PDF extraction progress.

7. **High - essential optional-library coverage is still backlog-only while
   rich-function lanes already depend on it.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:23`, `dependency-backlog.json:25` through
     `dependency-backlog.json:41`, `dependency-backlog.json:44` through
     `dependency-backlog.json:58`, `dependency-backlog.json:76` through
     `dependency-backlog.json:89`, and `dependency-backlog.json:101` through
     `dependency-backlog.json:112`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library coverage requirement.
   - Evidence: the repository still has only the 12 lane manifests; there are
     no support-library manifests. Backlog items such as
     `shared-zip-package-core`, `xml-html5-dom-core`, DOCX/OpenXML, legacy
     DOC/CFB, EPUB, PDF text, layout/OCR, table geometry, source maps,
     protobuf, checksums, archives, glob/pathspec, and provider metadata need
     bounded native PHP components with activation gates, dependency-specific
     denominators, mapped fixtures, PHP pass/fail evidence, malformed/corrupt
     cases where relevant, and explicit no-shell-out rules before they receive
     progress credit.

8. **High - lane-local archive/package work should be split into shared
   dependency ports before it is counted broadly.**
   - Paths: `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/tests/VfsVirtualTreeTest.php:14` through
     `lanes/rclone/tests/VfsVirtualTreeTest.php:46`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9` through
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:39`,
     `dependency-backlog.json:7` through `dependency-backlog.json:20`, and
     `dependency-backlog.json:380` through `dependency-backlog.json:398`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library coverage requirement.
   - Evidence: rclone has a minimal ZIP32 writer and readback uses PHP
     `ZipArchive` as the oracle; markerPDF inspects benchmark archives through
     PHP `ZipArchive`. Neither has a shared dependency-level manifest, ZIP spec
     denominator, cross-lane Pandoc/markerPDF/rclone fixtures, corrupt central
     directory/CRC/path-safety cases, or dependency-level pass/fail accounting.

9. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/gitoxide/lane-status.json:5` through
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:5` through
     `lanes/pandoc/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:5` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40`.
   - Evidence: the dashboard advertises 97.7% average progress and most lanes
     at 98-99%, while major parity remains static-only, excluded, unexecuted,
     or pending acceptance: Gitoxide full Cargo workspace, Pandoc Haskell
     runner and rich package readers/writers, Syncthing full `go test ./...`,
     markerPDF live benchmark/model runner, Difftastic full Cargo runner,
     rclone live provider/mount/fstest breadth, esbuild release-extra
     `make test-all`, and libsqlite all/release permutations.

10. **Medium - `progress.md` active-lane handoffs lag current lane statuses.**
    - Paths: `progress.md:70` through `progress.md:83`,
      `lanes/gitoxide/lane-status.json:9` through
      `lanes/gitoxide/lane-status.json:14`,
      `lanes/rclone/lane-status.json:9` through
      `lanes/rclone/lane-status.json:14`, and
      `lanes/syncthing/lane-status.json:9` through
      `lanes/syncthing/lane-status.json:14`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still lists older handoffs such as Gitoxide SSH
      config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Syncthing system log, rclone VFS Statfs, and
      esbuild automatic JSX key/spread. Current lane statuses describe newer
      work including Gitoxide gix-index v4 ignore mappings, rclone WebDAV
      PROPFIND, Syncthing DB override/revert, Pandoc ODT image captions, and
      Readability top-candidate cluster promotion.

11. **Medium - manifest/status schemas remain too free-form for reliable
    dashboard generation.**
    - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
      `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
      `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12` through
      `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:16`, and
      `porting.html:56` through `porting.html:67`.
    - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
      `goal.md:45`.
    - Evidence: denominator/status fields mix integers, long prose strings,
      current-batch status slugs, inventory labels, runner notes, and separate
      assertion/pass counts. The dashboard then truncates or collapses
      denominator, mapped-test, PHP pass/fail, and commit fields, which makes
      automated stale-artifact detection and acceptance gating fragile.

12. **Medium - shell-out scans are currently clean, but shell-backed
    integration gaps remain hidden under high progress numbers.**
    - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1198` through
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1203` and
      `lanes/gitoxide/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
      `goal.md:35`, and `goal.md:39`.
    - Evidence: bounded scans of lane `src/`, `tests/`, and `examples/`
      found no PHP `proc_open`, `shell_exec`, `passthru`, `popen`,
      `system`, `exec`, or backtick shell execution. However, Gitoxide still
      records shell-backed filter/askpass/SSH process-launch integration as
      caller-supplied or planned, and it remains a future gap rather than
      accepted native behavior.

## Tests

- Ran `jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json`: pass.
- Ran bounded shell-out scans for PHP process-launch functions and backticks
  under lane `src/`, `tests/`, and `examples`: no matches.
- Did not run `php tools/run-tests.php`: the checkout moved during review and
  remained a broad dirty aggregate; the process gate showed focused
  Pandoc/Syncthing/rclone lane runs but no stable no-argument root snapshot to
  verify.
