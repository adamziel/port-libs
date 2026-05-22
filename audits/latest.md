# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status/test/source surface, recent Git history through `acb1f24`, current worktree status, and tmux/log state. Concurrent worker changes appeared during the audit in `lanes/dolt/*`, `lanes/quadrable/*`, and `tools/seed-lane-metadata.php`; those changes were not made by this audit and are not staged here.

## Findings

1. **Critical - No committed lane has the required upstream benchmark denominator.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `porting.html`, `progress.md`
   - Evidence: at audited HEAD, every manifest still has `benchmarkDenominator.status` set to `static-seed` and `benchmarkDenominator.total` set to `pending full upstream inventory`. Mapped counts are only 1-3 per lane. A concurrent uncommitted quadrable change now adds a static inventory, but it is not committed, not regenerated into `porting.html`, and its upstream runner is still not executed.
   - Goal requirement at risk: "Build an `UPSTREAM_TEST_MANIFEST.json` that maps the real upstream benchmark denominator" and "Use upstream tests as the source of truth whenever possible."
   - Audit judgment: the manifests are useful scaffolding, but they are not defensible parity baselines. No lane should claim implementation progress beyond seed exploration until the upstream test inventory is counted and committed.

2. **High - PHP pass/fail and dashboard progress can be misread as upstream parity.**
   - Paths: `porting.html`, `lanes/*/lane-status.json`, `lanes/*/tests/*Test.php`, `progress.md`
   - Evidence: the dashboard reports `PHP Pass / Fail` values such as `3 / 0`, `2 / 0`, and `1 / 0`, while the harness only runs 12 local seed test files with 58 total assertions. The tests are hand-written micro-cases and are not mapped to upstream fixture IDs or denominators.
   - Goal requirement at risk: "Passing tests are not enough. Each lane needs a real upstream denominator, meaningful fixture parity, edge-case coverage, error behavior, docs/examples, and WordPress-oriented scenarios."
   - Audit judgment: the numbers should remain labeled as seed-local tests until manifests contain upstream denominators and each PHP case references upstream provenance.

3. **High - Current implementations are broad toy slices, not meaningful native ports yet.**
   - Paths: `lanes/gitoxide/src/*`, `lanes/lightningcss/src/*`, `lanes/markerpdf/src/*`, `lanes/libsqlite/src/*`, `lanes/readability/src/*`, `lanes/pandoc/src/*`, `lanes/quadrable/src/*`, `lanes/syncthing/src/*`, `lanes/difftastic/src/*`, `lanes/rclone/src/*`, `lanes/dolt/src/*`, `lanes/esbuild/src/*`
   - Evidence: examples include gitoxide only covering object header/loose blob storage, LightningCSS using a small comment/whitespace minifier, markerPDF extracting text via stream regexes, libsqlite parsing only the database header/varint, and esbuild exposing only a lexer. Similar minimal slices exist in all lower-priority lanes.
   - Goal requirement at risk: the requested ports include packfiles/refs/protocol, CSS parser/transformer/prefixer/bundler semantics, PDF structured extraction, SQLite reader/writer primitives, document conversion kernels, sync protocols, structural diffing, cloud sync, data versioning, and JS/TS/CSS bundling.
   - Audit judgment: this is acceptable as a bootstrap slice only. The next intervention should narrow to upstream-manifest inventory and one high-priority lane slice instead of spreading new toy APIs across all lanes.

4. **Medium - Coordination status is stale against the actual tmux state.**
   - Paths: `progress.md`, `.tmux-team/logs/*`, `scripts/start-tmux-team.sh`, `scripts/run-tmux-agent.sh`
   - Evidence: `progress.md` says the auditor and worker sessions are pending, but `tmux list-sessions` shows `port-auditor`, `port-gitoxide`, `port-lightningcss`, and `port-markerpdf`. Earlier `004759Z` logs show the launch failed with `error: unexpected argument '-a' found`; later `004819Z` logs show the helper-script relaunch is active. The helper script is currently untracked and `scripts/start-tmux-team.sh` is modified outside this audit.
   - Goal requirement at risk: "keep agents alive, redirect them out of low-value work, restart crashed sessions, integrate useful work" and "`progress.md` must include ... current owner/session."
   - Audit judgment: the supervisor needs to reconcile versioned progress with actual session state and either commit/fix the launch helper separately or mark the launch path as unstable.

5. **Medium - License/source verification remains incomplete in most manifests.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`
   - Evidence: most manifests still say `license: pending verification from upstream checkout`; only `libsqlite` and `quadrable` include partial license notes, and even those request verification.
   - Goal requirement at risk: "Identify the best upstream source repo, version/commit, license, architecture, and test suite."
   - Audit judgment: license verification is a cheap prerequisite and should be completed alongside upstream test inventory before deeper porting.

6. **Low - WordPress scenarios are descriptive but not executable.**
   - Paths: `lanes/*/notes/wordpress-scenarios.md`, `lanes/*/tests/*Test.php`, `porting.html`
   - Evidence: every lane has a scenario string/notes file, but the test suite does not encode WordPress-specific workflows beyond small synthetic examples.
   - Goal requirement at risk: "Add focused WordPress scenarios that explain why the port matters for Playground, Data Liberation, SQLite, Git-backed workflows, migration tools, block editing, local-first sync, document import, or shared-hosting constraints."
   - Audit judgment: the notes satisfy the "explain why" part, but the baseline is not met until at least one executable WordPress scenario exists per lane.

## Test Run

Command: `php tools/run-tests.php`

Initial audited result before concurrent lane changes:

```text
12 test files, 58 assertions, 0 failures
```

Latest result after concurrent uncommitted quadrable changes appeared:

```text
13 test files, 75 assertions, 0 failures
```

Both commands exited with status 0.

## Recommended Next Intervention

Stop adding implementation breadth until the top-priority lanes have committed upstream denominators. Start with `gitoxide`, `lightningcss`, and `markerPDF`: verify license, clone/count the real upstream suite or a static inventory when the runner cannot execute, update each manifest with total/mapped/failing counts, and relabel dashboard pass/fail numbers so seed-local tests cannot be mistaken for upstream parity.
