# Independent Audit - 2026-05-24T03:13Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
current `lanes/*/lane-status.json`, `dependency-backlog.json`, exact root-runner
process state, tmux session state, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, plan-only wrappers,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every current lane-status file,
`porting-summary.json`, and `dependency-backlog.json`. A bounded shell-out scan
found no `proc_open`, `shell_exec`, `exec(...)`, `passthru`, `system`, or
`popen` calls under lane `src/`, `tests/`, or `examples/`.

## Current Snapshot

```text
UTC samples: 2026-05-24T03:10Z through 2026-05-24T03:13Z
current HEAD: 8a31e4871e69 Record integration hold status
recent commits: 8a31e487 Record integration hold status; b357145b Record integration hold status; 7ee74d4a Record integration hold status
branch sample: main...origin/main [ahead 667, behind 68]
tracked dirty rows: 300
default status rows including untracked: 12418
git diff --shortstat samples: 300 files changed, 149382 insertions(+), 16493 deletions(-) -> 300 files changed, 149666 insertions(+), 16609 deletions(-)
tmux sessions: 183
active coordination sample: capacity, dashboard, evaluator, integrator, auditor, Dolt runner, lane, reseed, dependency/support-library, verifier, and watchdog sessions
root run by this audit: not started
```

Required root-run gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
2026-05-24T03:10Z sample: 3753255 php tools/run-tests.php
owner sample for PID 3753255: process exited before `ps -o pid,user,etime,args -p 3753255` could identify owner
2026-05-24T03:12Z owner-filtered sample: claude-owned focused lane runners 3808118, 3808460, 3808518, 3808615, 3808810, 3809588, 3809616, and 3809701
2026-05-24T03:13Z owner-filtered sample: claude-owned focused Syncthing runner 3808810
2026-05-24T03:13Z exact no-argument sample `pgrep -af '^php tools/run-tests\.php$'`: <no rows>
```

I did not start a duplicate no-argument root run. The initial no-argument root
PID exited before owner sampling; later samples still matched active focused
lane harnesses, and the checkout was moving during the audit.

## Findings

1. **Critical - the checkout remains a moving dirty aggregate, not an
   acceptance checkpoint.**
   - Paths: `progress.md:37` through `progress.md:83`, all
     `lanes/*/lane-status.json`, and recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`,
     `goal.md:49`, and `goal.md:52`.
   - Evidence: current `HEAD` is `8a31e4871e69`, the branch is
     `main...origin/main [ahead 667, behind 68]`, tracked dirty rows remain
     at 300, default status rows are 12,418, and shortstat moved from
     149,382 to 149,666 insertions during audit sampling. Lane status files
     still say their handoffs are pending/uncommitted or supervisor-owned; see
     `lanes/difftastic/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.

2. **Critical - no coherent no-argument root harness result exists for the
   current source/status snapshot.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and the root-run gate above.
   - Goal requirement at risk: `goal.md:48` and `goal.md:49`.
   - Evidence: the first gate matched no-argument root PID `3753255`, but it
     exited before owner sampling. Later `pgrep -u claude -af` samples matched
     focused lane harnesses, and exact no-argument root was clear only after
     the tree had already moved. Focused lane greens cannot substitute for one
     serialized root run from a frozen accepted snapshot.

3. **Critical - `porting.html` and `porting-summary.json` are stale and should
   not be used as current coordination truth.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:78`,
     `porting-summary.json:1` through `porting-summary.json:8`, and
     `dependency-backlog.json:1` through `dependency-backlog.json:5`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52`.
   - Evidence: the dashboard says it was generated on
     `2026-05-23 23:43:54 UTC` from snapshot `79768df0c427`, while current
     `HEAD` is `8a31e4871e69` with newer dirty manifests/statuses. The
     dashboard dependency section still reports 22 items, but
     `dependency-backlog.json` contains 23 items.

4. **High - dashboard, manifest, and status counts disagree across active
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:10` through `porting-summary.json:80`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/syncthing/lane-status.json:5`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: current Difftastic status/manifest reports 803 inspected units,
     429 mapped, and 2,605 assertions, while the dashboard still says 735/374.
     markerPDF reports 346 total / 297 mapped / 434 PHP behavior tests while
     the dashboard still says 330 / 280 / 416. rclone reports 770 mapped
     behavior tests while the dashboard says 698. Syncthing status reports
     99 lane files / 5,849 assertions for its latest focused pass, while the
     dashboard still shows the older 4,579-pass row.

5. **High - focused lane-green evidence is being recorded before supervisor
   acceptance and aggregate verification.**
   - Paths: `lanes/dolt/lane-status.json:5`,
     `lanes/dolt/lane-status.json:12` through
     `lanes/dolt/lane-status.json:13`,
     `lanes/readability/lane-status.json:5`,
     `lanes/readability/lane-status.json:12` through
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:12` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49`.
   - Evidence: the statuses publish focused PHP passes, focused upstream probes,
     examples, and lints, but the same files say the batches are uncommitted,
     root verification was not assigned, or commit selection is left to the
     supervisor/integrator. Those are intake notes, not accepted portfolio
     progress.

6. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:920` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:973`,
     `lanes/markerpdf/lane-status.json:5`, and
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:25`,
     `goal.md:30`, `goal.md:35`, and `goal.md:40`.
   - Evidence: markerPDF has useful native slices, but its counted denominator
     and mapped semantics still include benchmark CLI plans, shell lifecycle
     planning, multiprocessing setup, Streamlit/FastAPI/Uvicorn route planning,
     OCRMyPDF/Tesseract/Ghostscript installer plans, Texify/tokenizer/model
     preflights, Poetry/package metadata, Pandoc/XeLaTeX proof-PDF command
     planning, and other non-executed external runtime boundaries. Those should
     remain blockers, fixture-oracle metadata, or separately gated support
     libraries until they have bounded native PHP implementations and their own
     denominators.

7. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:22`, `dependency-backlog.json:25` through
     `dependency-backlog.json:40`, `dependency-backlog.json:44` through
     `dependency-backlog.json:58`, and
     `dependency-backlog.json:383` through `dependency-backlog.json:398`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this audit run's support-library requirement.
   - Evidence: `find . -maxdepth 3 -name UPSTREAM_TEST_MANIFEST.json` shows
     only the 12 lane manifests, with no support-library manifests. The backlog
     now has 23 candidate/deferred items, including ZIP/package, XML/HTML,
     DOCX/OpenXML, CFB/DOC, EPUB, doctemplates, math/TeX, PDF text,
     layout/OCR result, table geometry, source maps, protobuf wire, checksum,
     archive/compression, glob/pathspec, and provider metadata rows. Backlog
     rows are routing, not implementation progress.

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
     `goal.md:35`, and this audit run's support-library requirement.
   - Evidence: the VFS ZIP writer is valid rclone behavior, but it is not the
     shared `shared-zip-package-core`. It lacks an activation gate, ZIP
     spec/upstream denominator, cross-lane Pandoc/markerPDF/rclone fixtures,
     corrupt central-directory/CRC/path-safety cases, and independent PHP
     pass/fail accounting. The current readback uses PHP `ZipArchive` as a
     local reader oracle for the lane slice.

9. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/gitoxide/lane-status.json:5`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4` through
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40`.
   - Evidence: the dashboard advertises 97.7% average progress and most lanes
     at 98-99%. Major parity remains unexecuted, excluded, static-only, or
     pending acceptance: Gitoxide full Cargo workspace, Pandoc Haskell runner,
     Syncthing full `go test ./...`, markerPDF full benchmark/model runner,
     rclone provider/mount/live remote parity, Difftastic full Cargo,
     Esbuild `make test-all`, and libsqlite all/release permutations.

10. **Medium - `progress.md` active-lane handoff labels lag current lane
    status files.**
    - Paths: `progress.md:68` through `progress.md:81`,
      `lanes/difftastic/lane-status.json:11`,
      `lanes/dolt/lane-status.json:11`,
      `lanes/esbuild/lane-status.json:11`,
      `lanes/rclone/lane-status.json:11`, and
      `lanes/syncthing/lane-status.json:11`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still lists older handoffs such as Gitoxide SSH
      config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Syncthing system log route, Difftastic Ada/Apex,
      rclone VFS Statfs, and Esbuild automatic JSX key/spread. Current
      lane-status files describe newer work such as Difftastic Swift failable
      initializer paths, Dolt DAYNAME, Esbuild computed class-expression
      accessors, rclone WebDAV PROPPATCH response behavior, and Syncthing
      pause/resume side effects.

11. **Medium - manifest/status schemas remain too free-form for reliable
    dashboard generation.**
    - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
      `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13` through
      `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:18`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13` through
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:22`,
      `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
      `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:16`, and
      `porting-summary.json:10` through `porting-summary.json:80`.
    - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
      `goal.md:45`.
    - Evidence: denominators and status fields mix integers, prose strings,
      giant status slugs, inventory labels, and separate `totalCount` fields.
      The dashboard then truncates or collapses denominator, mapped, and PHP
      pass/fail data. The JSON is valid, but it is not a durable coordination
      contract.

## Tests

- Ran `jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json`: pass.
- Ran a bounded shell-out scan under lane `src/`, `tests/`, and `examples/`: no PHP shell-out function matches.
- Did not run `php tools/run-tests.php`. The required gate initially matched
  no-argument root PID `3753255`, which exited before owner sampling; later
  samples matched active `claude`-owned focused lane harnesses, and the checkout
  was still moving.

## Next Intervention

Hard-freeze writer/runner/status churn, then require two stable samples of
`HEAD`, tracked status count, untracked-inclusive status count, shortstat, exact
PHP runner state, Dolt runner state, and relevant log mtimes. Accept one quiet
lane-scoped batch, normalize manifest/status counts for that batch, run focused
verification plus `git diff --check`, run one serialized no-argument
`php tools/run-tests.php` from the same snapshot if the exact gate is empty,
regenerate dashboard artifacts from the accepted commit, then commit or reject.
