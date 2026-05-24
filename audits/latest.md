# Independent Audit - 2026-05-24T03:05Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
current `lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, exact root-runner process state, tmux session
state, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, plan-only wrappers,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every current lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
UTC samples: 2026-05-24T03:05:17Z through 2026-05-24T03:08:13Z
current HEAD: 7ee74d4ac045 Record integration hold status
recent commits: 7ee74d4a Record integration hold status; 1a970cc5 Refresh independent audit status; f3fd96cc Record integration hold status
branch sample: main...origin/main [ahead 665, behind 68]
tracked dirty rows: 300
default status rows including untracked: 12175
git diff --shortstat samples: 300 files changed, 148748 insertions(+), 16470 deletions(-) -> 300 files changed, 148791 insertions(+), 16470 deletions(-)
tmux sessions: 178
active coordination sample: capacity, dashboard, evaluator, integrator, auditor, Dolt runner, lane, reseed, and support-library sessions
root run by this audit: not started
```

Required root-run gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
2026-05-24T03:04Z sample: <no rows>
2026-05-24T03:05Z sample: <no rows>
2026-05-24T03:07Z validation sample: 3683835 php tools/run-tests.php
owner sample: PID 3683835 owned by claude, elapsed 00:20, command `php tools/run-tests.php`
2026-05-24T03:08Z validation sample: focused lane runners 3699872, 3704163, 3704655, 3704716, 3704791, 3705942; no no-argument root row
owner sample: PIDs 3699872, 3704163, and 3704716 still owned by claude when sampled; the others had exited before owner sampling
```

The exact root-run gate was clear during the initial audit samples but an
external no-argument root harness appeared during post-write validation. I did
not start a duplicate. A later validation sample showed only focused lane
runners. The checkout was also not stable enough for an audit-owned
no-argument root run: `git diff --shortstat` changed during the audit sample,
all lanes remain dirty or pending, and 178 tmux sessions are present,
including active lane/control sessions.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance or root-verification target.**
   - Paths: `progress.md:37` through `progress.md:82`,
     `audits/integration-status.md:3` through
     `audits/integration-status.md:87`, and all current
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`,
     `goal.md:49`, and `goal.md:52`.
   - Evidence: recent history is dominated by audit/status holds, and the
     branch is still `ahead 665, behind 68`. The audit samples show 300 tracked
     dirty rows, 12,175 untracked-inclusive status rows, and a shortstat that
     moved from 148,748 to 148,791 insertions during review. Current
     lane-status files repeatedly say root verification and commit acceptance
     are pending, for example `lanes/esbuild/lane-status.json:10` through
     `lanes/esbuild/lane-status.json:13`,
     `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:13`.

2. **Critical - no coherent no-argument root harness result exists for the
   current source/status snapshot.**
   - Paths: `audits/integration-status.md:42` through
     `audits/integration-status.md:53`, `progress.md:39`, and the root-run
     gate section above.
   - Goal requirement at risk: `goal.md:48` and `goal.md:49`.
   - Evidence: this audit's exact gate was clear during initial samples, then
     post-write validation matched external no-argument root PID `3683835`
     owned by `claude`; a later validation sample showed focused lane runners
     and no no-argument root row. The tree also changed during sampling, and
     the integration hold records focused runner activity from moving
     intervals. Current lane statuses publish focused greens while also saying
     aggregate root verification is pending. I did not start a root run because
     it would duplicate an active root process during the validation window and
     would not verify a frozen accepted snapshot.

3. **Critical - `porting.html` and `porting-summary.json` are stale and no
   longer describe the checkout.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:78`,
     `porting-summary.json:2` through `porting-summary.json:8`, and
     `porting-summary.json:215` through `porting-summary.json:220`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45`.
   - Evidence: the dashboard says it was generated on
     `2026-05-23 23:43:54 UTC` from snapshot `main 79768df0c427`, but current
     `HEAD` is `7ee74d4ac045` with newer dirty manifests/statuses. The
     dashboard dependency section still reports 22 items, 12 candidates, and
     10 medium-priority rows, while `dependency-backlog.json:3` through
     `dependency-backlog.json:5` now contains 23 items including
     `pandoc-doctemplates-core`.

4. **High - dashboard, manifest, and status counts disagree across most
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:10` through `porting-summary.json:213`,
     and every `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: current manifests/statuses now report newer figures than the
     dashboard, including Difftastic 800/421 mapped and 2,590 PHP assertions
     versus dashboard 735/374; Esbuild 334 behavior tests versus 311;
     Gitoxide 2,877/2,877 mapped and 6,118 assertions versus dashboard
     2,751/2,877 and 5,634 pass; libsqlite 302 versus 286; LightningCSS
     1,902 mapped and 2,421 pass versus 1,732 and 2,197; markerPDF 345/296
     and 433 pass versus 330/280 and 416; Pandoc 1,304 mapped and 303 pass
     versus 1,061 and 278; rclone 766 pass versus 698; Readability 222 pass
     versus 204; and Syncthing 5,767 pass versus 4,579. The public dashboard
     cannot be treated as current coordination evidence.

5. **High - focused lane-green evidence is still being recorded before
   supervisor acceptance and aggregate verification.**
   - Paths: `lanes/dolt/lane-status.json:10` through
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:10` through
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:10` through
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/readability/lane-status.json:10` through
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49`.
   - Evidence: the status files report focused suite passes, upstream probes,
     examples, and lints, but their `latestCommit`/`blocker` fields say the
     batches are uncommitted, pending, or supervisor-owned for root
     verification. These are intake notes, not accepted portfolio progress.

6. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:436` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:459`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:520` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:583`, and
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:920` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:970`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:25`,
     `goal.md:30`, `goal.md:31`, and `goal.md:35`.
   - Evidence: markerPDF has useful native PHP slices, but its denominator
     still counts benchmark CLI plans, shell-helper command planning,
     OCR/model readiness, Streamlit/FastAPI/Uvicorn route planning,
     multiprocessing lifecycle planning, Poetry/package metadata, Texify
     tokenization plans, OCRMyPDF/Tesseract/Ghostscript installer plans, and
     `subprocess.run shell=True` launcher boundaries. Those are blockers or
     support-library candidates unless converted into bounded native PHP
     behavior with a dependency-specific denominator, mapped fixtures, PHP
     pass/fail evidence, and corrupt/malformed cases where relevant.

7. **High - essential optional-library coverage remains backlog-only while
   rich-function lanes already depend on it.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:110` through `dependency-backlog.json:123`,
     `dependency-backlog.json:168` through `dependency-backlog.json:215`, and
     `dependency-backlog.json:236` through `dependency-backlog.json:258`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library requirement.
   - Evidence: the only `UPSTREAM_TEST_MANIFEST.json` files in the repo are
     lane manifests under `lanes/*`; there is no support-library manifest with
     its own denominator. Pandoc rich-format work needs ZIP, OpenXML/EPUB/ODT,
     doctemplates, citation, math/TeX, charset, Unicode, and HTML/XML cores.
     markerPDF needs PDF text, page-render planning, layout/OCR result, table
     geometry, Unicode repair, and math/TeX cores. Syncthing still has
     protobuf wire-format risk; esbuild/LightningCSS need source-map and
     parser/text support; rclone/Syncthing need provider metadata and
     archive/compression boundaries. Backlog rows are useful routing, but they
     do not count as support-library progress.

8. **High - lane-local dependency expansion is too broad to count as shared
   support progress.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:23`,
     `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/VfsZipArchive.php:52` through
     `lanes/rclone/src/VfsZipArchive.php:104`, and
     `lanes/rclone/tests/VfsServeZipResponseTest.php:10` through
     `lanes/rclone/tests/VfsServeZipResponseTest.php:44`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library requirement.
   - Evidence: rclone's ZIP writer can be valid VFS lane behavior, but it is
     not the shared `shared-zip-package-core` support library. It has no
     separate activation gate, ZIP spec/upstream denominator, cross-lane
     mapped fixtures, corrupt central-directory/CRC/path-safety cases, or
     independent PHP pass/fail accounting. The readback test uses PHP
     `ZipArchive` only as a local reader oracle for the rclone slice.

9. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/esbuild/lane-status.json:4` through
     `lanes/esbuild/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4` through
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes. Major parity remains unexecuted, excluded, static-only, or
     pending acceptance: Gitoxide full Cargo workspace, Pandoc Haskell runner,
     Syncthing full `go test ./...`, markerPDF full benchmark/model runner,
     rclone provider/mount/live remote parity, Difftastic full Cargo,
     Esbuild `make test-all`, and libsqlite all/release permutations.

10. **Medium - `progress.md` active-lane handoff labels lag current lane
    status files.**
    - Paths: `progress.md:67` through `progress.md:80`,
      `lanes/esbuild/lane-status.json:11`,
      `lanes/gitoxide/lane-status.json:11`,
      `lanes/rclone/lane-status.json:11`,
      `lanes/readability/lane-status.json:11`, and
      `lanes/syncthing/lane-status.json:11`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still lists older handoffs such as Gitoxide SSH
      config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Syncthing system log, Difftastic Ada/Apex,
      rclone VFS Statfs, and Esbuild automatic JSX key/spread. Current status
      files describe newer gix-worktree ignore id mapping, computed
      class-expression decorators, WebDAV PROPPATCH XML, Readability template
      inert content, Syncthing cluster pending routes, and other newer work.

11. **Medium - manifest/status schemas remain too free-form for reliable
    dashboard generation.**
    - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
      `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13` through
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
      `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
      `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
      `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13` through
      `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:15`, and
      `porting-summary.json:10` through `porting-summary.json:213`.
    - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
      `goal.md:45`.
    - Evidence: denominators mix integers, prose strings, inventory labels,
      and expanded status slugs; dashboard fields then truncate or collapse
      denominator/mapped/PHP pass-fail data. The result is valid JSON, but not
      a durable coordination contract.

## Tests

- Ran `jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json`: pass.
- Did not run `php tools/run-tests.php`. The exact root-run gate was clear in
  two initial samples, then post-write validation matched external
  no-argument root PID `3683835` owned by `claude`; a later validation sample
  showed focused lane runners and no no-argument root row. The checkout was
  also still moving and active enough that an audit-owned root result would
  not verify an accepted snapshot.

## Next Intervention

Hard freeze writer/runner/status churn, then require two stable samples of
`HEAD`, tracked status count, untracked-inclusive status count, shortstat,
exact PHP runner state, Dolt runner state, and relevant log mtimes. Accept one
quiet lane-scoped batch, normalize manifest/status counts for that batch, run
focused verification plus `git diff --check`, run one serialized
no-argument `php tools/run-tests.php` from the same snapshot if the exact gate
is empty, regenerate dashboard artifacts from the accepted commit, then commit
or reject.
