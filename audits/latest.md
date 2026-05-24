# Independent Audit - 2026-05-24T02:40Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, current process state,
and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, plan-only wrappers,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
current UTC sample: 2026-05-24T02:40:15Z
HEAD: 0f2bdf1d93a4 Record integration hold status
HEAD movement observed during audit: 6ba736f5836e -> 0f2bdf1d93a4
recent commits: 0f2bdf1d Record integration hold status; 6ba736f5 Record integration hold status; 089f7294 Refresh independent audit status
branch sample: main...origin/main [ahead 658, behind 68]
tracked dirty rows: 302
default status rows including untracked: 11625
git diff --shortstat sample: 302 files changed, 148442 insertions(+), 17971 deletions(-)
tmux sessions: 176
coordination/test-control sample: dashboard updater, evaluator, watchdog, capacity controller/executor, lane agents, and Dolt BATS recover/selector agents active
root run by this audit: not started
pre-root gate: pgrep -af '^php tools/run-tests\.php( |$)' returned no rows
```

Active non-root evidence included capacity executor PIDs `1464482`/`1464486`,
dashboard updater PID `2222131`, watchdog PID `2347911`, evaluator PID
`2424048`, capacity controller PID `2938141`, Dolt BATS recover agent PID
`2445807`, and Dolt BATS next-selector agent PID `3093713`.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance or root-verification target.**
   - Paths: `progress.md:39`, `progress.md:63` through `progress.md:79`,
     `lanes/difftastic/lane-status.json:11` through
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:11` through
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:11` through
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:11` through
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:11` through
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:11` through
     `lanes/markerpdf/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:11` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require small committed slices, verification, cleanup, and
     honest repo-wide test/static-check records.
   - Evidence: `HEAD` moved during this audit from `6ba736f5836e` to
     `0f2bdf1d93a4`. Branch state is `ahead 658, behind 68`, tracked dirty
     rows are 302, the default status surface is 11,625 rows, and shortstat is
     302 files with 148,442 insertions. Recent history remains alternating
     audit/integration-hold commits, while every lane still reports pending,
     uncommitted, or supervisor-owned aggregate verification.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict the current source of truth.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting.html:75` through `porting.html:78`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `dependency-backlog.json:3`, and
     `dependency-backlog.json:110` through `dependency-backlog.json:123`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a
     current dashboard with denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still says it was generated
     `2026-05-23 23:43:54 UTC` from `79768df0c427`, while the repo is at
     `0f2bdf1d93a4` with newer dirty manifests/status files. The dashboard
     dependency section still reports 22 items, 12 candidates, and 10 medium
     items; `dependency-backlog.json` has 23 items, 13 candidates, and 11
     medium items after `pandoc-doctemplates-core`.

3. **High - public counts disagree with current manifests and lane status in
   multiple lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:38`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/lane-status.json:5` through
     `lanes/readability/lane-status.json:7`, and
     `lanes/syncthing/lane-status.json:5` through
     `lanes/syncthing/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: dashboard rows still show Difftastic 735/374 while the
     manifest says 789/407, Esbuild 311 while the manifest/status say 332,
     Gitoxide 2,751 mapped while the manifest says 2,877, libsqlite 286 while
     the manifest/status say 299-300, LightningCSS 1,732 mapped while the
     manifest says 1,902, markerPDF 330/280 while the manifest says 343/294,
     Pandoc 1,061 mapped while the manifest says 1,262, rclone 698 while the
     manifest/status say 752, Readability 204 pass while status says 218, and
     Syncthing 4,579 pass while status says 5,644.

4. **High - focused lane-green evidence is still being recorded before
   supervisor acceptance and aggregate verification.**
   - Paths: all current `lanes/*/lane-status.json:5` through
     `lanes/*/lane-status.json:13`, especially
     `lanes/esbuild/lane-status.json:10` through
     `lanes/esbuild/lane-status.json:13`,
     `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49`.
   - Evidence: lane statuses record green focused PHP, lints, examples, and
     lane diff checks, but the same records say root aggregate verification is
     pending and latest commits are pending/uncommitted/shared-dirty. These
     records are review inputs, not accepted portfolio progress.

5. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:432`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:707`,
     `lanes/markerpdf/lane-status.json:5`, and
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:25`,
     `goal.md:30`, `goal.md:31`, and `goal.md:35`.
   - Evidence: markerPDF has useful native PHP slices, but its manifest/status
     also count benchmark CLI plans, CI workflow plans, OCR/model readiness,
     Tesseract/OCRMyPDF/Ghostscript/Texify readiness, Streamlit/FastAPI/Uvicorn
     route planning, Poetry/package metadata, multiprocessing plans, and
     chunk-convert shell lifecycle planning. Those are blockers or optional
     support-library candidates unless converted into bounded native PHP
     behavior with dependency-specific denominators and fixtures.

6. **High - essential optional-library coverage remains backlog-only while
   rich-function lanes already depend on it.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:44` through `dependency-backlog.json:57`,
     `dependency-backlog.json:110` through `dependency-backlog.json:123`,
     `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:14`,
     `lanes/markerpdf/lane-status.json:12`, and
     `lanes/readability/lane-status.json:8`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library audit requirement.
   - Evidence: Pandoc is now mapping DOCX Native image/table handoff but shared
     ZIP/OpenXML support is still a candidate gate, not a support component
     with its own denominator. markerPDF continues to need bounded PDF text,
     layout/OCR-result, table geometry, and Unicode repair cores for real
     scanned/structured-document parity. Readability's media/table/math cleanup
     is still lane-local instead of backed by activated shared XML/HTML,
     table, charset, and media helpers. Backlog rows are necessary but do not
     count as support-library progress without a native PHP component,
     activation gate, dependency-specific upstream/spec denominator, mapped
     fixtures, PHP pass/fail evidence, and malformed/corrupt cases.

7. **High - lane-local dependency expansion is too broad to count as shared
   support progress.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:96` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:103`,
     `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/VfsZipArchive.php:52` through
     `lanes/rclone/src/VfsZipArchive.php:104`,
     `lanes/rclone/src/VfsServeZipResponse.php:7` through
     `lanes/rclone/src/VfsServeZipResponse.php:10`, and
     `lanes/rclone/tests/VfsServeZipResponseTest.php:10` through
     `lanes/rclone/tests/VfsServeZipResponseTest.php:39`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library audit requirement.
   - Evidence: rclone's ZIP writer/serve-zip response can be valid lane-local
     VFS behavior, but it is not the shared `shared-zip-package-core` backlog
     item. It has no separate support-library manifest, no ZIP spec/upstream
     denominator, no activation gate, no cross-lane mapped fixtures, and no
     corrupt central-directory/CRC/path-safety cases. Its test uses PHP
     `ZipArchive` as a local reader oracle, so it is evidence for this slice
     only, not shared standard-PHP package-core coverage.

8. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     and `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes. Major parity remains unexecuted, excluded, static-only, or
     pending supervisor acceptance: Gitoxide full Cargo workspace, Pandoc
     Haskell runner, Syncthing full `go test ./...`, markerPDF full
     benchmark/model runner, rclone provider/mount/live remote parity,
     Difftastic full Cargo, esbuild `make test-all`, and libsqlite all/release
     permutations.

9. **Medium - manifest/status schemas remain non-normalized and make the
   dashboard hard to trust mechanically.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2389`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:38`, and
     `porting-summary.json:10` through `porting-summary.json:18`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is sometimes numeric, sometimes a
     narrative string, sometimes missing until a late `total` field, and
     sometimes paired with separate mapped counts. PHP pass/fail values mix
     behavior tests, assertions, PASS cases, and mapped denominator checks.

10. **Medium - Syncthing manifest/status handoff is internally stale.**
    - Paths: `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1596` through
      `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1599` and
      `lanes/syncthing/lane-status.json:5` through
      `lanes/syncthing/lane-status.json:14`.
    - Goal requirement at risk: `goal.md:3`, `goal.md:31`, and
      `goal.md:44`.
    - Evidence: the manifest `currentSlice` omits the newer
      `/rest/system/connections` and `/rest/system/discovery` work and still
      says the next task is `/rest/system/connections`. `lane-status.json`
      says both connections and discovery are already mapped and that the next
      task is `/rest/system/browse`.

11. **Medium - `progress.md` active-lane handoffs lag current lane-status
    files.**
    - Paths: `progress.md:63` through `progress.md:77`,
      `lanes/difftastic/lane-status.json:11`,
      `lanes/esbuild/lane-status.json:11`,
      `lanes/gitoxide/lane-status.json:11`,
      `lanes/rclone/lane-status.json:11`,
      `lanes/readability/lane-status.json:11`, and
      `lanes/syncthing/lane-status.json:11`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still lists older handoffs such as Difftastic
      Ada/Apex, Esbuild automatic JSX key/spread, Gitoxide SSH config options,
      rclone VFS Statfs/usage, Readability negative-header cleanup, and
      Syncthing system log. Current lane-status files describe newer Go
      syntax-list, static private class-expression decorator, worktree
      ignore-stack, HTTP favicon, data-table descendant cleanup, and system
      discovery work.

## Test Gate

I did not run `php tools/run-tests.php`.

The required process gate was checked before considering a root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
<no rows>
```

Even with no exact PHP root harness at that sample, the checkout was not stable
enough for an audit-owned no-argument root run. `HEAD` moved during the audit,
active coordination/lane processes were present, the dirty surface is enormous,
the dashboard is stale, lane handoffs are unaccepted, and manifest/status
artifacts disagree. A root run here would be another moving-snapshot anecdote
rather than the accepted verification record required by the goal.

## Next Intervention

Freeze writers, status publishers, focused PHP loops, broad upstream runners,
and dashboard generation. Confirm the exact PHP harness gate is empty, then
poll `HEAD`, tracked status count, shortstat, runner state, and relevant log
mtimes twice without movement. Accept exactly one lane-scoped batch, normalize
its manifest/status schema, run focused verification and `git diff --check`,
run one serialized no-argument `php tools/run-tests.php` from the same
snapshot, regenerate `porting.html` and `porting-summary.json` from the
accepted commit, then commit or reject.
