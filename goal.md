/goal Build native standard-PHP ports for the following libraries in `/home/claude/port-libs`, using a supervised tmux workflow with one implementation lane per priority item plus one independent auditor. Do not create wrappers around JS/Rust/Go/C binaries as the main deliverable. Bridge code may exist only as temporary fixture-generation or oracle tooling and must not count as progress.

First inspect the repo, tooling, available PHP version, Composer setup, test runners, and any existing progress files. Then create or update a durable coordination system inspired by `https://orbit-staff-climbing-thousand.trycloudflare.com/porting.html`: track each port by upstream benchmark denominator, mapped upstream tests, PHP passing/failing counts, WordPress-relevant scenarios, phase, audit status, current work, blocker, and latest commit. Maintain `progress.md` for human-readable coordination and a generated `porting.html` dashboard for browsing status.

Port lanes, in priority order:

1. `https://github.com/GitoxideLabs/gitoxide`: Git implementation for PHP with packfiles, refs, commits, object database, protocol v2, sparse/partial clone, push, merge, and server-oriented primitives.
2. LightningCSS: fast CSS parser, transformer, minifier, prefixer, and bundler semantics ported to native PHP.
3. `https://github.com/sddai/markerPDF`: PDF-to-structured-content extraction pipeline suitable for WordPress import, Data Liberation, and document conversion workflows.
4. `libsqlite`: pure PHP SQLite database-file reader/writer and related low-level SQLite primitives for environments where the SQLite extension is unavailable.
5. Production-grade readability/content rewrite engine: Mozilla Readability-class extraction plus migration-aware link/media/page-builder cleanup into clean WordPress blocks.
6. `pandoc`: document conversion kernel with a shared AST plus readers/writers for Markdown, HTML, WXR, EPUB/PDF-oriented intermediate forms, and WordPress block-oriented output.
7. `https://github.com/hoytech/quadrable`: port the core data model, algorithms, and test suite faithfully, then identify WordPress/Playground-relevant uses.
8. `https://github.com/syncthing/syncthing`: sync protocol, block exchange concepts, device/folder model, conflict handling, and resumable file synchronization primitives in PHP.
9. `https://www.devtoolsguide.com/difftastic-structural-diff-tool/`: Difftastic-style structural diffing for code and structured documents, with syntax-aware comparison and useful HTML output.
10. `https://rclone.org/`: cloud/storage sync abstraction for PHP with provider interfaces, copy/sync/check semantics, filters, checksums, and resumability.
11. `dolt`: Git-for-data concepts, SQL database versioning, commits, branches, diffs, merges, table storage, and MySQL-compatible behavior where practical.
12. `esbuild`: native PHP bundler/transpiler for JS/TS/JSX/CSS usable on shared hosting, Playground, PHAR tools, and browser-backed PHP environments without Node/npm.

Create one tmux window/session per lane and keep implementation agents active in parallel, capped at a practical concurrency level if the VM cannot support all 12 at once. Add one auditor agent whose only job is to challenge quality, compare progress against this prompt, identify weak assumptions, and recommend the next best intervention every 20 minutes. Your role is supervisor: keep agents alive, redirect them out of low-value work, restart crashed sessions, integrate useful work, enforce standards, and keep the roadmap honest.

For every lane:

- Identify the best upstream source repo, version/commit, license, architecture, and test suite.
- Build an `UPSTREAM_TEST_MANIFEST.json` that maps the real upstream benchmark denominator. If the upstream runner cannot execute, create a defensible static inventory and mark it clearly as such.
- Implement native PHP incrementally against upstream behavior.
- Add focused WordPress scenarios that explain why the port matters for Playground, Data Liberation, SQLite, Git-backed workflows, migration tools, block editing, local-first sync, document import, or shared-hosting constraints.
- Keep the public API idiomatic PHP while preserving upstream semantics where compatibility matters.
- Commit small, reviewable slices with passing tests.
- Never count generated fixtures, bridge calls, or shell-outs to upstream binaries as native implementation progress.
- Record blockers precisely, including missing tools, failing upstream runners, ambiguous semantics, or unported binary formats.

Quality bar:

- Passing tests are not enough. Each lane needs a real upstream denominator, meaningful fixture parity, edge-case coverage, error behavior, docs/examples, and WordPress-oriented scenarios.
- Prefer small correct slices over broad shallow ports.
- Use upstream tests as the source of truth whenever possible.
- When the upstream suite is huge, map the full denominator first, then work in explicit slices.
- Keep all generated artifacts reproducible.
- Do not silently skip hard features; mark them as blockers or future slices.

Coordination requirements:

- `progress.md` must include the high-level roadmap, active lanes, completed milestones, open blockers, current owner/session, next task per lane, and percentage estimates.
- `porting.html` must show an at-a-glance table with average progress and per-lane columns: library, suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, commit.
- Each lane should have its own directory with source, tests, fixtures, upstream manifest, notes, and examples.
- Restart dead tmux agents unless they completed their assigned slice cleanly.
- If an agent finishes, verify tests, commit its work, update progress, clean up accidental unrelated changes, and assign the next highest-value slice.
- Periodically run repo-wide tests and static checks. Record failures honestly.
- Push useful commits to the remote when available.

Begin by producing the coordination files and lane structure, then launch the tmux team. Keep working until every lane has at least a defensible upstream benchmark manifest, an initial native PHP implementation slice, passing PHP tests, WordPress scenarios, and visible progress in `porting.html`.
