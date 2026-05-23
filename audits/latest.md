# Independent Audit - 2026-05-23

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files needed to validate dashboard/status drift, bridge/shell-out usage, and recent Git history through audit-sampled `HEAD` `eeec618` (`Stamp markerPDF finalizer code status`). I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: this is still a dirty, moving checkout rather than an accepted integration snapshot. During this audit, `HEAD` moved from `6b5a4c8`/`92cef32` through `3c22585` to `eeec618` while tests and audit notes were being collected. Latest sampled status reported `230` `git status --short` entries, `34` tracked modified entries, and `git diff --shortstat` reported `34 files changed, 6190 insertions(+), 286 deletions(-)`. Dirty tracked files include lane implementations/tests plus dashboard artifacts: `lanes/esbuild/src/TypeScriptModuleLowerer.php`, `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`, `lanes/gitoxide/src/BlobMerge.php`, `lanes/gitoxide/tests/BlobMergeTest.php`, `lanes/quadrable/src/QuadbStore.php`, `lanes/readability/src/ArticleExtractor.php`, `porting.html`, `porting-summary.json`, and `progress.md`.

## Findings

1. **Critical - the required root PHP harness is red at the latest sampled head.**
   - Paths: `lanes/esbuild/tests/TypeScriptModuleLowererTest.php:1476`, `lanes/esbuild/src/TypeScriptModuleLowerer.php`, `lanes/esbuild/lane-status.json:10`-`13`.
   - Evidence: the latest required run at `HEAD` `eeec618` exited `1` with `163 test files, 15015 assertions, 1 failures`. The failing case is `lowers wordpress local using asset controller without trapping exports`; it expected `var PreviewBlockController = class _PreviewBlockController {` but the output serialized `class _PreviewBlockController{...}` without the expected space. `lanes/esbuild/lane-status.json` still claims esbuild lane PHP is green and says the root blocker is unrelated Quadrable failures.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices with passing tests; `goal.md:48` requires finished work to be verified before integration; `goal.md:49` requires repo-wide failures to be recorded honestly; `goal.md:52` requires every lane to have passing PHP tests.
   - Audit judgment: do not integrate or publish the current aggregate. Fix or reject the dirty esbuild TypeScript lowering batch first, then rerun the exact root harness from a quiesced tree.

2. **Critical - the repository is still a moving dirty aggregate, so test results are not stable integration evidence.**
   - Paths: `progress.md`, `audits/latest.md`, `.tmux-team/prompts/dashboard-updater.md`, `lanes/*/lane-status.json`, `porting.html`, `porting-summary.json`.
   - Evidence: while this audit ran, `HEAD` advanced from the initially observed rclone/syncthing state through LightningCSS, Pandoc, Syncthing, and markerPDF commits. Earlier same-audit root runs failed with `9` Gitoxide merge failures at `92cef32`; the latest run failed with `1` esbuild failure at `eeec618`. Status also changed from `31` to `34` tracked modified files during the audit. Several files are staged or dirty outside this audit, so an audit-only commit would be unsafe unless the supervisor first freezes writers and isolates the audit files.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices; `goal.md:48` requires verification, cleanup, and commit of finished slices; `goal.md:49` requires periodic repo-wide checks to be recorded honestly.
   - Audit judgment: treat every green/red root result collected during this run as diagnostic for a moving tree, not as an accepted checkpoint.

3. **High - `porting.html` and `porting-summary.json` are stale and materially disagree with manifests/status.**
   - Paths: `porting.html:30`-`32`, `porting.html:53`-`64`, `porting-summary.json`.
   - Evidence: the dashboard still shows `Average progress: 14.3%` and `Generated: 2026-05-22 15:40:20 UTC`. It renders LightningCSS as `14.0%`, `141 pass`, and `78 / 312 mapped`, while current LightningCSS status/manifest report `57%`, `688` PHP assertions, and a `3,532` behavior-check denominator. It renders Pandoc as `10.0%` and `19 / 1979 mapped`, while current status/manifest report `59%` and `328` mapped checks. It still renders Quadrable with an old failed-C++ blocker even though the manifest now says `make -r test` passes all 34 upstream scenarios.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current dashboard tracking of denominator, mapped tests, PHP pass/fail, phase, audit, current work, blocker, and commit; `goal.md:52` requires visible current progress in `porting.html`.
   - Audit judgment: regenerate dashboard artifacts only after the red root suite is fixed and the supervisor chooses an accepted green snapshot.

4. **High - `progress.md` still presents stale active-lane estimates and tasks.**
   - Paths: `progress.md:29`-`42`, `progress.md:230`-`238`.
   - Evidence: the Active Lanes table still lists Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`, Pandoc `10%`, Syncthing `8%`, rclone `9%`, Dolt `5%`, and esbuild `8%`, while current lane statuses report much larger bounded slices such as Gitoxide `91%`, LightningCSS `57%`, Pandoc `59%`, Syncthing `58%`, rclone `60%`, and Quadrable `65%`. This audit updates only the audit status/next intervention; the table remains stale until a stable snapshot exists.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to include current active lanes, blockers, owner/session, next task per lane, and percentage estimates.
   - Audit judgment: defer the full table refresh until after the root suite is green and lane batches are accepted or rejected one at a time.

5. **High - lane status files copy stale root-test claims and blockers.**
   - Paths: `lanes/esbuild/lane-status.json:10`-`13`, `lanes/lightningcss/lane-status.json:10`-`13`, `lanes/pandoc/lane-status.json:10`-`13`, `lanes/markerpdf/lane-status.json:10`-`13`, `lanes/syncthing/lane-status.json:10`-`13`, `lanes/gitoxide/lane-status.json:10`-`13`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`.
   - Evidence: multiple lanes claim `php tools/run-tests.php` passes with older counts such as `163 test files, 14952 assertions, 0 failures`, `163 files, 14919 assertions, 0 failures`, or `163 test files, 14966 assertions, 0 failures`. The latest audit run is red with `163 test files, 15015 assertions, 1 failures`. Esbuild status is particularly misleading because the current failure is in esbuild, while its blocker still says root is blocked by unrelated Quadrable failures.
   - Goal requirement at risk: `goal.md:31` requires precise blockers; `goal.md:45` requires current audit/blocker/commit fields; `goal.md:49` requires failures to be recorded honestly.
   - Audit judgment: root-test status should be centralized or stamped from one accepted run, not copied as optimistic prose across lane files while the shared checkout is dirty.

6. **High - upstream denominator and mapped-count units remain inconsistent across manifests.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Evidence: Difftastic counts inspected behavior artifacts; Dolt mixes executable files, Go tests, BATS cases, benchmarks, fixtures, and local patched-copy BATS evidence; esbuild counts upstream test entry points; Gitoxide counts upstream files plus targeted static inventories; LightningCSS counts helper invocations/behavior checks; markerPDF counts repository paths plus two benchmark pairs and supplied-boundary semantics; Pandoc counts files/artifacts while `mapped` is focused checks; Readability counts upstream Mocha tests while mapped/native progress is local assertions; Syncthing counts Go test/benchmark entry points plus focused static/probe evidence.
   - Goal requirement at risk: `goal.md:25` requires a real upstream benchmark denominator; `goal.md:35`-`38` require meaningful fixture parity and upstream tests as source of truth; `goal.md:45` requires comparable dashboard denominator/mapped fields.
   - Audit judgment: split schema fields into upstream files/artifacts, executable upstream tests, upstream behavior cases, mapped upstream cases, native PHP behavior tests, assertions, failures, full-runner parity, bounded-runner evidence, and static inventory.

7. **Medium - high percentages can be mistaken for upstream parity.**
   - Paths: `lanes/gitoxide/lane-status.json`, `lanes/pandoc/lane-status.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`.
   - Evidence: Gitoxide is at `91%` while full Cargo workspace tests remain unexecuted; Pandoc is at `59%` with no Haskell upstream runner parity; rclone has a strong bounded 299-package runner but excludes provider integration, mount/FUSE, Docker-backed serve/docker, and full provider fstest parity; Syncthing uses focused package runners/static reads rather than full `go test ./...`; markerPDF is at `50%` while the full benchmark/model pipeline remains unexecuted.
   - Goal requirement at risk: `goal.md:35` says passing tests are not enough; `goal.md:37` says upstream tests are the source of truth; `goal.md:40` requires hard features to be marked as blockers or future slices.
   - Audit judgment: show a parity class beside each percentage so bounded/static evidence cannot read as port maturity.

8. **Medium - markerPDF still risks counting supplied model-boundary scaffolding as native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/src/ConversionFinalizer.php`, `lanes/markerpdf/tests/ConversionFinalizerTest.php`.
   - Evidence: markerPDF has useful native post-processing, scoring, debug, and conversion-finalization work, but its upstream pipeline depends on heavy model/PDF dependencies and several slices stop at supplied OCR/layout/table/debug-render/model outputs. The manifest maps only `2` actual CI benchmark PDF/reference pairs plus `4` surrogate pairs; current dirty/committed finalizer work is not a substitute for broader native document-level extraction parity against those real benchmark pairs.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting bridge/shell-out/generated-oracle work as native implementation progress; `goal.md:35` requires meaningful fixture parity beyond local scaffolding tests.
   - Audit judgment: keep markerPDF progress conservative until native document-level extraction parity broadens against acquired benchmark pairs.

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

Latest exact result for this audit, run at `HEAD` `eeec618`:

```text
exit status: 1
FAIL lowers wordpress local using asset controller without trapping exports (lanes/esbuild/tests/TypeScriptModuleLowererTest.php)
163 test files, 15015 assertions, 1 failures
```

Earlier same-audit diagnostic result before later commits landed:

```text
exit status: 1
9 Gitoxide merge failures in BlobMergeTest.php and TreeMergeTest.php
163 test files, 14894 assertions, 9 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. Fix or reject the dirty esbuild TypeScriptModuleLowerer batch first because it is the current root harness blocker, then rerun `php tools/run-tests.php` from a stable tree. After the first accepted green snapshot, commit or reject the remaining dirty lane batches one at a time, regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that same snapshot, clear stale root-test strings and pending/prose latest-commit fields, and normalize dashboard/status fields for upstream denominator units, mapped upstream cases, native tests/checks/assertions, failures, runner parity, and latest accepted commit.
