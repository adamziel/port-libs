# Pandoc Status Replay Unblock Note

Date: 2026-06-13T03:02:09Z

Issue: `plib-591ss`

Current local `origin/main`: `6dfacd7bba` (`fix: keep fenced div section references scoped (plib-yaih5)`).

## Scope

This is a bounded status-file replay decision for the reducer snapshot cited by
`plib-591ss`. It does not implement parser behavior and does not replay stale
aggregate status counters into `progress.md`, `PANDOC_STATUS.md`,
`lanes/pandoc/lane-status.json`, or
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

The seed note named by the bead,
`lanes/pandoc/notes/pandoc-status-file-conflict-reducer-20260613T0246Z.md`, is
not present on current `origin/main`, so this note reconstructs the current
state from Beads, git history, and the merge queue.

## Current Evidence

- `gt mq list port_libs --verify` shows the relevant pending branches as
  ready/OK, including `plib-pg7cz` and `plib-j5tip`.
- `gt refinery blocked port_libs` reports no blocked MRs.
- Current Pandoc status files already report 3,320 PHP passes / 0 failures and
  3,279 mapped upstream cases, with the latest landed status text covering the
  Markdown fenced Div section-reference slice.

## Replay Decisions

| Bead | Current state | Replay decision |
| --- | --- | --- |
| `plib-gljei` | Closed as merged, but its source branch is not an ancestor of current `origin/main` and its status-only diff is stale against the current 3,320 / 0 counters. | Do not replay the old status-only branch. Treat its landed-vs-pending intent as superseded by current main status text. |
| `plib-pg7cz` | Ready/OK pending MR with a status-only diff limited to `progress.md` and `PANDOC_STATUS.md`; its text is based on the older `17c91bad52` / 3,314-pass queue snapshot. | Do not replay onto current main as-is. If this MR is rejected, recover with a fresh current-main status note or a narrow landed-vs-pending edit using current counters, not the older 25-MR snapshot. |
| `plib-yaih5` | Current `origin/main` contains the rebased implementation as `6dfacd7bba`; the original source branch is not an ancestor because the refinery rebased it. | No implementation-owner review is needed before status replay. The implementation is already landed; use current-main evidence, not the old source-branch commit. |
| `plib-j5tip` | Ready/OK pending implementation MR. Its diff touches `PandocFormatRegistry.php`, `PandocFormatRegistryTest.php`, `lane-status.json`, `UPSTREAM_TEST_MANIFEST.json`, `progress.md`, and `PANDOC_STATUS.md`. | Implementation-owner/refinery review is required before any status replay. Do not status-replay the text-markup unsupported counters separately from the code and tests. If rejected, recover by rebasing the full implementation branch. |

## Unblock Action

Leave the live aggregate status files unchanged on `plib-591ss`. The safe action
is this note-only handoff:

- status-only candidates `plib-gljei` and `plib-pg7cz` are stale for current
  main and should not be replayed verbatim;
- `plib-yaih5` is landed, so current status can rely on current-main evidence;
- `plib-j5tip` remains implementation-coupled and should not be split into a
  status-only replay.

## Validation

Commands run for this note:

```sh
jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/pandoc/notes/pandoc-status-replay-unblock-20260613T030209Z.md
gt mq list port_libs --verify
gt refinery blocked port_libs
```

No Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, TeX or
office tooling, online service, live provider test, or external validator was
invoked.
