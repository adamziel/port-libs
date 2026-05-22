# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status/dashboard state, bridge/shell-out usage, the dirty worktree, and recent Git history through `16ae4e5` (`Stamp pandoc code block status`). I did not edit lane implementation files, launch agents, launch tmux sessions, or push.

## Findings

1. **Critical - The repo-wide PHP suite is failing in the current integration snapshot.**
   - Paths: `lanes/syncthing/tests/BepWireTest.php:167`, `lanes/syncthing/tests/BepWireTest.php:174`, `lanes/syncthing/src/BepWire.php:117`, `lanes/syncthing/src/BepWire.php:129`, `lanes/syncthing/src/BepWire.php:190`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:123`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:127`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:137`, `progress.md:15`.
   - Evidence: latest `php tools/run-tests.php` exits 1 with `58 test files, 3164 assertions, 1 failure`. The failing test is `rejects unsupported compressed and malformed post-auth frames`. The test still expects `decodeMessageFrame()` to throw for an LZ4-compressed BEP message, but current `BepWire` can encode and decompress LZ4 frames. The manifest still says compressed frames are rejected in the current slice while also listing LZ4 as the next task, so implementation, tests, and manifest are out of sync.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests and every lane baseline to include passing PHP tests; coordination also requires repo-wide failures to be recorded honestly.
   - Audit judgment: reconcile the Syncthing LZ4/compressed-frame slice by either completing the native parity tests/manifest or reverting the partial behavior before publishing status.

2. **Critical - `porting.html`, `porting-summary.json`, and parts of `progress.md` are stale against the current manifests and recent commits.**
   - Paths: `porting.html:55`, `porting.html:58`, `porting.html:59`, `porting.html:61`, `porting-summary.json:40`, `porting-summary.json:91`, `porting-summary.json:108`, `porting-summary.json:142`, `progress.md:31`, `progress.md:33`, `progress.md:37`, `progress.md:210`, `progress.md:211`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`.
   - Evidence: the dashboard still shows esbuild as `16 / 2,567` mapped with commit `2e1fcb0`, but the manifest says `20` mapped and current history includes multiple later esbuild commits. LightningCSS is `78` mapped on the dashboard but `87` in the manifest. markerPDF is `11` mapped / `23 pass` on the dashboard but `18` mapped / `35` PHP behavior tests in the manifest after `f150123`. Quadrable's dashboard still says the C++ runner fails, while its manifest says `make -r test` passed. `progress.md` also still says esbuild maps 16 tests and Dolt lacks Go/BATS, while the Dolt manifest records bounded Go/BATS runner evidence.
   - Goal requirement at risk: `goal.md` requires the dashboard to track mapped tests, PHP pass/fail, blockers, current work, and commit accurately for each lane.
   - Audit judgment: regenerate `progress.md`, `porting.html`, and `porting-summary.json` from one quiesced state after the PHP suite is green.

3. **High - The audit target is still a moving integration surface.**
   - Paths: current `git status --short`, recent Git history from `8e3ce06` through `b37aeeb`, `progress.md:230`, `progress.md:231`.
   - Evidence: while this audit was running, history advanced from `8e3ce06` through `f150123`, `b37aeeb`, `5778c7a`, `4c57af7`, `a6fb9aa`, `88c4491`, `c30cd41`, `5d5ddbe`, `35da84d`, `5d19e66`, `4e4697b`, `be608f8`, and `16ae4e5`. Test results moved from `57 test files, 2965 assertions, 10 failures` to `58 test files, 3080 assertions, 2 failures`, then `58 test files, 3112 assertions, 5 failures`, and finally `58 test files, 3164 assertions, 1 failure`. The worktree still has many uncommitted lane implementation, manifest, dashboard, and fixture changes.
   - Goal requirement at risk: `goal.md` requires the supervisor to verify finished agent work, commit small passing slices, update progress, and keep dashboard state honest.
   - Audit judgment: quiesce or explicitly coordinate active workers before treating any dashboard or progress numbers as an integration snapshot.

4. **High - Gitoxide remains overstated relative to a machine-checkable upstream denominator.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `porting.html:56`.
   - Evidence: the priority-1 lane reports 66% progress and hundreds of mapped checks, but the manifest still says the Cargo runner was not executed and relies on a very large prose static inventory rather than a normalized list of named upstream suites/fixtures with pass/fail state.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark denominator or a clearly marked defensible static inventory, and requires upstream tests to be the source of truth for large suites.
   - Audit judgment: Gitoxide work may be valuable, but its percentage should remain suspect until fixture IDs and uncovered upstream areas are normalized into machine-checkable counts.

5. **Medium - markerPDF still has no real external upstream benchmark PDF/reference pair.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:16`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:56`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:57`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:78`.
   - Evidence: the manifest reports `mappedBenchmarkPairs: 0`, `mappedBenchmarkSurrogatePairs: 3`, and `runnerStatus: not-executed`. The current code-block and cleaner work improves local behavior, but it still does not prove actual benchmark PDF/reference extraction parity.
   - Goal requirement at risk: `goal.md` scopes markerPDF as a PDF-to-structured-content extraction pipeline for WordPress import/Data Liberation and requires meaningful fixture parity, not only surrogate source-function checks.
   - Audit judgment: after the root PHP suite is green, acquire at least one real benchmark_data PDF/reference pair before broadening more markerPDF layout/OCR behavior.

6. **Medium - Upstream runner passes are still easy to misread as native PHP progress.**
   - Paths: `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:71`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:38`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:47`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:56`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:37`.
   - Evidence: several manifests now contain useful upstream-oracle runner evidence, but native PHP mapped coverage remains narrow relative to the upstream denominators, for example esbuild `20 / 2,567` and Dolt `12 / 613`.
   - Goal requirement at risk: `goal.md` forbids counting JS/Rust/Go/C/C++ execution, bridge calls, generated fixtures, or shell-outs as native implementation progress except temporary oracle tooling.
   - Audit judgment: preserve runner evidence as oracle support, but keep native PHP pass/fail and mapped upstream parity visually and textually separate.

## Bridge / Shell-Out Check

Command searched committed and untracked files under `lanes`, `tools`, and `scripts` for `shell_exec`, `exec(`, `passthru`, `proc_open`, `system(`, and `popen(` using `rg --pcre2`. The only match was copied Mozilla fixture JavaScript `regex.exec(url)` in `lanes/readability/fixtures/mozilla/videos-2/source.html:830`; no PHP process-execution bridge was found.

## Test Run

Command: `php tools/run-tests.php`

Exit status: 1

Exact latest result I ran during this moving snapshot:

```text
58 test files, 3164 assertions, 1 failure
```

Failing area: `lanes/syncthing/tests/BepWireTest.php`, specifically the compressed post-auth frame expectation for LZ4 BEP messages.

## Recommended Next Intervention

Stop the moving target first: quiesce or explicitly coordinate active sessions, then fix or reject the Syncthing LZ4 compressed-frame slice until `php tools/run-tests.php` is green. After that, regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the same committed state, then resume denominator-quality work: acquire a real markerPDF benchmark PDF/reference pair and normalize Gitoxide fixture IDs/coverage.
