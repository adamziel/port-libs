# Independent Audit - 2026-05-22 23:40 UTC

Scope reviewed: `goal.md`, `progress.md`, current `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, bridge/shell-out usage, current dirty worktree state, and recent Git history through `97c6478`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: the root PHP harness result below was captured during this run, but the tree kept moving as concurrent commits and dirty lane edits landed. The latest observed `HEAD` is `97c6478`. `git status --short` has 203 entries, including 51 tracked modified entries plus many untracked audit/evidence files. `git diff --shortstat` reports 51 files changed, 5,056 insertions, and 252 deletions. Treat the test result as diagnostic evidence only, not an accepted integration checkpoint.

## Findings

1. **Critical - the repository is still a moving dirty aggregate, not a reviewable integration checkpoint.**
   - Paths: `lanes/esbuild/src/TypeScriptModuleLowerer.php`, `lanes/gitoxide/src/PackData.php`, `lanes/lightningcss/src/CssMinifier.php`, `lanes/rclone/src/MemoryProvider.php`, `lanes/syncthing/src/FolderIndexState.php`, `porting.html`, `porting-summary.json`, `audits/integration-status.md`.
   - Evidence: the current audit run got a green root result, but the dirty set spans implementation, test, manifest, lane-status, dashboard, and audit/evidence files. Concurrent commits and new dirty/status changes appeared repeatedly during this audit.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests, cleanup of unrelated changes, precise blockers, and latest commit tracking.
   - Audit judgment: freeze or explicitly coordinate writers, then accept or reject one lane batch at a time with a root rerun after each accepted batch.

2. **High - `porting.html` and `porting-summary.json` are stale against current manifests and lane statuses.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:56`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`.
   - Evidence: the dashboard still says average progress is `14.3%` and was generated at `2026-05-22 15:40:20 UTC`. Current lane status estimates average about `50.5%`, with examples including Difftastic `37%` vs dashboard `8%`, Gitoxide `88%` vs `66%`, LightningCSS `51%` vs `14%`, markerPDF `45%` vs `10%`, Pandoc `51%` vs `10%`, Quadrable `60%` vs `8%`, Readability `44%` vs `12%`, and Syncthing `51%` vs `8%`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: do not publish or trust the dashboard until it is regenerated from a single accepted green snapshot.

3. **High - `progress.md` is stale as a coordination document.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:35`, `progress.md:36`, `progress.md:37`, `progress.md:38`, `progress.md:40`, `progress.md:41`, `progress.md:42`, `progress.md:230`.
   - Evidence: the Active Lanes table still carries old estimates such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, Quadrable `8%`, Syncthing `8%`, rclone `9%`, Dolt `5%`, and esbuild `8%`. The current-owner audit line still records the previous root result `148` files / `13340` assertions and `193` status entries, while this audit observed `149` files / `13548` assertions and `203` status entries.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, blockers, next task per lane, percentage estimates, owner/session, and audit status.
   - Audit judgment: I updated only the audit status and next intervention text. The lane table should be refreshed by the supervisor only after dirty lane batches are accepted or rejected.

4. **High - lane blocker and latest-commit fields are not reliable coordination data.**
   - Paths: `lanes/gitoxide/lane-status.json:12`, `lanes/gitoxide/lane-status.json:13`, `lanes/lightningcss/lane-status.json:13`, `lanes/markerpdf/lane-status.json:13`, `lanes/quadrable/lane-status.json:13`, `lanes/readability/lane-status.json:13`, `lanes/syncthing/lane-status.json:13`.
   - Evidence: Gitoxide still says the required root run is red due unrelated libsqlite failures even though this audit's root run was green. LightningCSS, Quadrable, Readability, and Syncthing use `pending`, `current run`, `current batch`, or `pending lane commit` prose instead of accepted commit hashes. Syncthing cites a later green root count of `149` / `13596` that this audit did not run, so lane statuses are unsynchronized with the audit record.
   - Goal requirement at risk: `goal.md` requires precise blockers, honest repo-wide test records, audit status, and latest commit.
   - Audit judgment: normalize lane status after integration. Until then, these fields are useful narrative notes, not machine-readable truth.

5. **High - upstream denominator and mapped-count units remain inconsistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:21`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:376`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:734`.
   - Evidence: Difftastic counts mixed behavior artifacts, Dolt combines executable files, BATS cases, and fixtures, Gitoxide uses `2877` upstream files while mapped counts are focused behavior slices, markerPDF uses repository paths and benchmark pairs with `0` Python test files, Pandoc counts files/artifacts, Readability sets `mapped` to `711` even though lane status says `77` PHP tests and the manifest warning says `711` assertions, and rclone has `mapped: 179` while its warning still says `176` focused lane tests.
   - Goal requirement at risk: `goal.md` requires real upstream benchmark denominators, mapped upstream tests, PHP passing/failing counts, and honest percentage estimates.
   - Audit judgment: split the schema into separate fields for upstream files/artifacts, upstream test cases, mapped upstream cases, native PHP behavior tests, assertions, failures, full-runner parity, bounded-runner evidence, and static inventory.

6. **Medium - high percentages can be misread as upstream parity.**
   - Paths: `lanes/gitoxide/lane-status.json:4`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/dolt/lane-status.json:4`, `lanes/markerpdf/lane-status.json:4`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/pandoc/lane-status.json:4`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/syncthing/lane-status.json:4`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12`, `lanes/difftastic/lane-status.json:4`.
   - Evidence: Gitoxide reports `88%` while full Cargo workspace tests are not run. Pandoc reports `51%` without the full Haskell upstream runner. Syncthing reports `51%` with bounded focused runner evidence but no full `go test ./...`. markerPDF reports `45%` while the full benchmark/conversion runner is not executed. Difftastic and Dolt report `37%` and `36%` without full upstream runner parity.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible, passing tests are not enough, and hard features must be marked as blockers or future slices.
   - Audit judgment: percentages should be labeled as local/native slice progress, not upstream parity.

7. **Medium - markerPDF still risks counting supplied model-boundary scaffolding as native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:24`, `lanes/markerpdf/lane-status.json:5`, `lanes/markerpdf/lane-status.json:12`.
   - Evidence: the denominator is a `78`-path repository inventory with `0` committed Python unit test files, two actual CI PDF/reference pairs, and many supplied-boundary mappings for pdftext, pypdfium, Surya, Texify, and tabled behavior. Those boundaries are useful oracle scaffolding, but they are not the same as native PDF-to-structured-content extraction parity.
   - Goal requirement at risk: `goal.md` requires a native PDF-to-structured-content extraction pipeline and says bridge code, generated fixtures, or shell-outs must not count as progress unless explicitly temporary oracle tooling.
   - Audit judgment: use the acquired benchmark pairs to drive document-level native extraction parity before increasing markerPDF status based on more supplied model/output boundaries.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match is `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Required command: `php tools/run-tests.php`

Exact result for this audit run:

```text
Exit status: 0
149 test files, 13548 assertions, 0 failures
```

This is not worse than the prior recorded green audit state, but it is green on a moving dirty aggregate checkout. Treat it as diagnostic evidence only until lane changes are integrated or rejected one batch at a time.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. Pick one dirty lane batch, verify it in isolation, commit it with a fresh root rerun, and repeat. Before regenerating `porting.html` or `porting-summary.json`, clear stale lane-status blockers that cite older red root runs and normalize the status schema so upstream denominator units, mapped upstream cases, PHP behavior tests, assertions, failures, and runner parity are separate fields.
