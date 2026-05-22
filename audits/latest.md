# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files, representative tests/examples, upstream cache state, `tmux list-sessions`, bridge/shell-out search, and recent Git history through `3504c40` (`Mark esbuild session stopped`). The worktree was clean at audit start. No lane implementation files were edited.

## Findings

1. **Critical - No lane has upstream runner parity, and the required portfolio baseline is still unmet.**
   - Paths: `goal.md:22-44`, `progress.md:11-15`, `progress.md:68-79`, `porting.html:48-59`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12-17`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:47-70`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:38-57`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:44-64`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:32-50`.
   - Evidence: every non-Dolt lane is still a static or cloned-static inventory with the upstream runner not executed. Dolt remains a `static-seed` manifest with `total: pending full upstream inventory`.
   - Goal requirement at risk: build a real upstream benchmark denominator, use upstream tests as the source of truth where possible, and reach the required baseline for every lane.
   - Audit judgment: local PHP tests are useful smoke tests, but none of the lanes can claim upstream parity or baseline completion.

2. **High - Coordination is wrong, and an active Dolt worker conflicts with the explicit deferral.**
   - Paths: `progress.md:41`, `progress.md:86`, `progress.md:88-96`, `.tmux-team/tmp/port-dolt.md:1-22`, `.tmux-team/prompts/auditor.md:1-20`.
   - Evidence: `tmux list-sessions` reports `port-auditor` and `port-dolt`, while `progress.md` reported the auditor stopped and no active workers. The Dolt prompt instructs a worker to replace the Dolt denominator and implement a slice, but `progress.md` and the manifest both say Dolt is deferred until other lanes reach baseline.
   - Goal requirement at risk: `progress.md` must include current owner/session, the supervisor must keep the roadmap honest, and the portfolio is priority ordered with Dolt explicitly deferred by current direction.
   - Audit action: updated `progress.md` to record the active sessions and make resolving `port-dolt` the next intervention.

3. **High - Several upstream caches are not reproducible working trees, yet manifests cite them as evidence.**
   - Paths: `.upstream-cache/dolt`, `.upstream-cache/esbuild`, `.upstream-cache/pandoc`, `.upstream-cache/syncthing`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:16`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:16-17`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:16`, `progress.md:82-83`.
   - Evidence: `git status --short | wc -l` reports 2,387 deletions in `.upstream-cache/dolt`, 349 in `.upstream-cache/esbuild`, 2,781 in `.upstream-cache/pandoc`, and 940 in `.upstream-cache/syncthing`.
   - Goal requirement at risk: keep generated artifacts reproducible and record blockers precisely.
   - Audit judgment: `git ls-tree` inventories can still be defensible static counts, but these caches are not runner-ready and should not be used for cache-local targeted reads until restored, recloned, or explicitly documented as no-checkout object stores.

4. **High - The dashboard still makes local PHP micro-tests easy to misread as upstream pass counts.**
   - Paths: `porting.html:36-38`, `porting.html:49-59`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-37`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:61-70`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-17`.
   - Evidence: markerPDF shows `Mapped Tests` 0 but PHP `5 / 0`; Pandoc shows 1,979 upstream artifacts with only 5 mapped local tests; esbuild shows 2,567 counted upstream entries with 6 local lexer tests. The dashboard header says `PHP Pass / Fail` without labeling these as local lane tests.
   - Goal requirement at risk: `porting.html` must show honest suite progress, upstream denominator, mapped tests, and PHP pass/fail without implying that passing tests are enough.
   - Audit judgment: until each PHP pass/fail count is tied to upstream fixture IDs or test names, the dashboard should explicitly label the column as local PHP pass/fail.

5. **Medium - markerPDF remains weak for a priority-3 lane because no real benchmark PDF/reference pair is mapped.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-37`, `lanes/markerpdf/tests/PdfTextExtractorTest.php`, `porting.html:54`.
   - Evidence: the manifest identifies six benchmark documents and two CI score thresholds but records `mapped: 0`. The PHP tests exercise tiny synthetic/local PDFs, not an upstream benchmark document, reference Markdown, or score threshold.
   - Goal requirement at risk: the markerPDF lane is supposed to port a PDF-to-structured-content extraction pipeline with meaningful fixture parity.
   - Audit judgment: the next markerPDF slice should map one real benchmark/reference pair or a documented upstream-derived surrogate before broadening extraction behavior.

6. **Medium - Recent implementation slices are still shallow relative to the named port scopes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:44-52`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:36-42`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:48-57`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:54-64`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:61-70`.
   - Evidence: Difftastic is token normalization rather than recursive syntax-tree diffing; Pandoc has hand-written Markdown snippets rather than golden `.native` parity; Readability has not mapped a Mozilla `test-pages` source/expected/metadata fixture; Rclone tests an in-memory provider and filters but not upstream provider contract behavior; Esbuild is still lexer-only.
   - Goal requirement at risk: each lane needs meaningful fixture parity, edge-case coverage, error behavior, docs/examples, and WordPress-oriented scenarios, not broad shallow ports.
   - Audit judgment: future implementation commits should map directly to manifest items or close an explicit blocker.

## Bridge / Shell-Out Check

Searched `lanes`, `tools`, `scripts`, and `.tmux-team` for `shell_exec`, `exec(`, `passthru`, `proc_open`, and `system(`. No matches were found, so I did not find committed bridge code or shell-outs being counted as native implementation progress.

## Test Run

Command: `php tools/run-tests.php`

Exact result:

```text
14 test files, 220 assertions, 0 failures
```

Exit status: 0.

## Recommended Next Intervention

Stop/retire `port-dolt` or explicitly reauthorize it only after the non-Dolt baseline is reached. Then use implementation capacity on the highest-priority gaps: a targeted Gitoxide object/ref denominator slice or one real markerPDF benchmark/reference mapping. Keep dashboard PHP pass/fail values labeled as local until tied to upstream fixture IDs.
