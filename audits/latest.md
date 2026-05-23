# Independent Audit - 2026-05-23

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate dashboard/status drift, bridge/shell-out usage, and recent Git history through latest observed `HEAD` `06f028d` (`Port pandoc HTML reader image slice`). During this audit, `HEAD` advanced from initially observed `ea74f78` through `203f97f`, `32114c1`, `6e1a108`, `6b37511`, `2100b41`, and `06f028d`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: the checkout is still a dirty moving aggregate, not a clean integration checkpoint. Latest observed `git status --short` reported `223` entries, `git status --short --untracked-files=no` reported `38` tracked modified files, and `git diff --shortstat` reported `38 files changed, 6085 insertions(+), 255 deletions(-)`. The latest direct root PHP harness rerun is green, but earlier root runs during the same audit were red while concurrent lane/status changes landed.

## Findings

1. **Critical - the repository is still a moving dirty aggregate, so green root output is not yet a reviewable integration checkpoint.**
   - Paths: `.tmux-team/prompts/dashboard-updater.md`, `audits/integration-status.md`, `lanes/dolt/src/PatchFunctionCall.php`, `lanes/esbuild/src/TypeScriptModuleLowerer.php`, `lanes/gitoxide/src/BlobMerge.php`, `lanes/libsqlite/src/SQLiteDatabase.php`, `lanes/lightningcss/src/CssMinifier.php`, `lanes/quadrable/src/QuadbStore.php`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/lane-status.json`, `porting.html`, `porting-summary.json`, `progress.md`.
   - Evidence: `HEAD` moved multiple times during the audit, and the root harness changed from red (`161` files / `14658` assertions / `2` failures) to green (`161` files / `14699` assertions / `0` failures). There are still 38 tracked modified files and 223 total status entries, including multiple lane implementation files, status files, dashboard artifacts, and untracked evidence/example files.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices with passing tests; `goal.md:48` requires verified finished agent work to be committed and cleaned up; `goal.md:49` requires honest repo-wide verification.
   - Audit judgment: do not accept the aggregate as one batch. Freeze or explicitly coordinate writers, then accept/reject one lane batch at a time from a stable root rerun.

2. **High - `porting.html` and `porting-summary.json` are stale and materially disagree with current manifests and lane statuses.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`-`64`, `porting-summary.json:2`, `porting-summary.json:3`, `porting-summary.json:57`-`64`, `porting-summary.json:91`-`98`, `porting-summary.json:125`-`139`, `porting-summary.json:159`-`183`.
   - Evidence: the dashboard still says `Average progress: 14.3%` and `Generated: 2026-05-22 15:40:20 UTC`. It renders esbuild as `8%`, `16 / 2,567 mapped`, and `16 pass`, while current esbuild status/manifest say `40%`, `128` mapped, and `128` PHP behavior tests (`lanes/esbuild/lane-status.json:4`-`7`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`). It renders rclone as `9%`, `20 / 327 mapped`, and `20 pass`, while current rclone status/manifest say `58%`, `206` mapped/pass (`lanes/rclone/lane-status.json:4`-`7`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`). It renders Pandoc as `10%` while current lane status says `57%`, and Quadrable still shows an old failed-C++ blocker even though its manifest says `make -r test` passes.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current dashboard tracking of denominator, mapped tests, PHP pass/fail, phase, audit, current work, blocker, and commit; `goal.md:52` requires visible progress in `porting.html`.
   - Audit judgment: regenerate dashboard artifacts only after the supervisor selects an accepted green snapshot.

3. **High - `progress.md` still presents stale active-lane estimates and tasks.**
   - Paths: `progress.md:31`-`42`, `progress.md:233`, `progress.md:238`.
   - Evidence: the Active Lanes table still shows Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`, Readability `12%`, Quadrable `8%`, Syncthing `8%`, rclone `9%`, Dolt `5%`, and esbuild `8%`. Current lane statuses are much higher for many lanes, for example Gitoxide `91%`, LightningCSS `55%`, markerPDF `49%`, libsqlite `57%`, Readability `47%`, Quadrable `64%`, Syncthing `56%`, rclone `58%`, Pandoc `57%`, Dolt `41%`, and esbuild `40%`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to include current active lanes, blockers, owner/session, next task per lane, and percentage estimates.
   - Audit judgment: update only the latest audit/next-intervention text now; defer full table refresh until dirty lane batches are accepted or rejected.

4. **High - lane status and manifest root-test claims contradict the latest root rerun.**
   - Paths: `lanes/esbuild/lane-status.json:10`-`13`, `lanes/rclone/lane-status.json:10`-`13`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:200`, `lanes/readability/lane-status.json:5`, `lanes/readability/lane-status.json:10`-`12`, `lanes/quadrable/lane-status.json:10`-`13`, `lanes/gitoxide/lane-status.json:10`-`13`.
   - Evidence: esbuild status says the root suite fails because a Dolt example is missing; rclone status says the root suite fails in esbuild; readability manifest/status says the root suite fails in esbuild; quadrable status still says root is red from an older rclone failure; Gitoxide cites an older `160` file / `14582` assertion root run. The latest direct root rerun in this audit passes with `161` test files, `14699` assertions, and `0` failures.
   - Goal requirement at risk: `goal.md:31` requires precise blockers; `goal.md:44`-`45` require current blocker/audit/latest-commit status; `goal.md:48` requires verified committed slices.
   - Audit judgment: root-test status should be centralized or stamped from one accepted run, not copied as stale prose across lane files.

5. **High - upstream denominator and mapped-count units remain inconsistent across manifests.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`15`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`-`16`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`18`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`18`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`16`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`16`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`16`.
   - Evidence: Difftastic counts inspected behavior artifacts; Dolt mixes executable files, BATS cases, Go tests, and fixtures; esbuild counts upstream test entry points; Gitoxide counts upstream files; LightningCSS counts behavior checks/helper invocations; markerPDF counts repository paths plus benchmark pairs and supplied-boundary semantics; Pandoc counts files/artifacts; Quadrable maps tracked paths/C++ scenarios; rclone counts Go test files; Readability counts Mocha tests while `mapped` is local assertions; Syncthing counts Go test/benchmark entry points.
   - Goal requirement at risk: `goal.md:25` requires real upstream benchmark denominators; `goal.md:35`-`38` require meaningful fixture parity and upstream tests as source of truth; `goal.md:45` requires comparable dashboard denominator/mapped fields.
   - Audit judgment: split schema fields into upstream files/artifacts, executable upstream tests, upstream behavior cases, mapped upstream cases, native PHP behavior tests, assertions, failures, full-runner parity, bounded-runner evidence, and static inventory.

6. **Medium - markerPDF still risks counting supplied model-boundary scaffolding as native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:430`-`445`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:448`-`449`.
   - Evidence: the manifest records `0 committed Python unit test files found`, only `2` actual CI benchmark PDF/reference pairs, and extensive supplied boundaries: Surya layout/OCR, Texify, pdftext/pypdfium, tabled recognition, image rendering, FastAPI/Uvicorn/requests, callback-driven conversion/server paths, PIL drawing, and model loading. Much of the large current slice validates plans or supplied outputs rather than native PDF-to-structured-content extraction.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting bridge/shell-out/generated-oracle work as native progress; `goal.md:35` requires meaningful fixture parity beyond passing local tests.
   - Audit judgment: keep markerPDF progress conservative until document-level native extraction parity expands against the acquired benchmark pairs.

7. **Medium - high progress percentages obscure missing full upstream runner parity.**
   - Paths: `lanes/gitoxide/lane-status.json:4`-`12`, `lanes/rclone/lane-status.json:4`-`12`, `lanes/syncthing/lane-status.json:4`-`12`, `lanes/pandoc/lane-status.json:4`-`12`, `lanes/markerpdf/lane-status.json:4`-`12`.
   - Evidence: Gitoxide is marked `91%` while full Cargo workspace tests remain unexecuted; rclone is `58%` with live provider/mount/Docker/fstest provider parity excluded; Syncthing is `56%` with full `go test ./...` unexecuted; Pandoc is `57%` with no Haskell upstream runner; markerPDF is `49%` with no full benchmark/model runner. These are useful bounded slices, but the percentages can read like port maturity.
   - Goal requirement at risk: `goal.md:35` says passing tests are not enough; `goal.md:37` says upstream tests are the source of truth; `goal.md:40` requires hard features to be marked as blockers or future slices.
   - Audit judgment: progress percentages should be displayed with a parity class so bounded/static evidence cannot be mistaken for upstream parity.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane implementation process-execution bridge calls were found. The remaining match is dashboard coordination tooling reading Git metadata; it must not be counted as native port progress.

## Test Run

Required command: `php tools/run-tests.php`

Direct required runs during this audit:

```text
Earlier direct run exit status: 1
161 test files, 14658 assertions, 2 failures

Final direct run exit status: 0
161 test files, 14699 assertions, 0 failures
```

Diagnostic follow-up after the red run filtered failure names and reported `161 test files, 14661 assertions, 2 failures` in `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`; a focused rerun of that esbuild test file later passed with `541` assertions and `0` failures, and the final direct root rerun passed. Treat this as evidence that the worktree moved during verification.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. From the current dirty set, pick one lane batch at a time, verify it in isolation, run the root harness from a stable tree, then commit or reject that lane before moving to the next. After the first accepted green snapshot, regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane status files from the same state, clearing stale root-test strings and pending/prose latest-commit fields. Normalize the manifest/status schema so upstream denominator units, mapped upstream cases, native tests/checks/assertions, failures, runner parity, and latest accepted commit are separate values.
