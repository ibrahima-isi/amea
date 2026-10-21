# Issue tracker: Local Markdown

Issues, specs and tickets live as markdown files in `.scratch/`, committed to git so every worktree sees the same state. Implementation follows the git workflow in `AGENTS.md`.

## Layout

```
.scratch/
└── <nature>/                    features | bugs | refactors | chores | docs
    └── <issue-slug>/
        ├── spec.md
        ├── completion.md        progress of this issue's tickets
        └── issues/
            ├── 01-<slug>.md
            └── 02-<slug>.md
```

- `<nature>` groups issues by kind of work: `features/`, `bugs/`, `refactors/`, `chores/`, `docs/`. Create the folder on first use.
- `<issue-slug>` is kebab-case and names the issue for its whole life; it is the first half of every worktree and branch name.
- Tickets are one file each at `issues/<NN>-<slug>.md`, numbered from `01`, never a single combined tickets file. `NN` is the second half of the worktree and branch name: ticket `03` of `soft-delete-students` is worked in `soft-delete-students-03`.
- Triage state is a `Status:` line near the top of each ticket (role strings in `triage-labels.md`).
- Comments and conversation history append to the bottom of the file under a `## Comments` heading.

## completion.md

One per issue, next to `spec.md`. It is the progress view of that issue's tickets.

```markdown
# Completion: <issue-slug>

| Ticket | Title | Progress | PR |
| --- | --- | --- | --- |
| 01 | <title> | done | #12 |
| 02 | <title> | in-review | #14 |
| 03 | <title> | in-progress | |
| 04 | <title> | todo | |
```

`Progress` takes one of `todo`, `in-progress`, `in-review`, `done`.

- Creating a ticket adds its row as `todo`.
- The first commit on a ticket's branch sets its row to `in-progress`.
- Opening the PR into `dev` sets `in-review` and fills `PR`.
- The final commit before merge sets `done`, so `dev` carries `done` for exactly the merged tickets.
- The issue is complete when every row is `done`.

## When a skill says "publish to the issue tracker"

Create the files under `.scratch/<nature>/<issue-slug>/` (creating directories as needed): `spec.md`, one `issues/<NN>-<slug>.md` per ticket, and `completion.md` with every ticket as `todo`.

## When a skill says "fetch the relevant ticket"

Read the file at the referenced path. The user will normally pass the path, or the issue slug and ticket number.

## Wayfinding operations

Used by `/wayfinder`. The **map** is a file with one **child** file per ticket.

- **Map**: `.scratch/<nature>/<effort>/map.md` (the Notes / Decisions-so-far / Fog body).
- **Child ticket**: `.scratch/<nature>/<effort>/issues/NN-<slug>.md`, numbered from `01`, with the question in the body. A `Type:` line records the ticket type (`research`/`prototype`/`grilling`/`task`); a `Status:` line records `claimed`/`resolved`.
- **Blocking**: a `Blocked by: NN, NN` line near the top. A ticket is unblocked when every file it lists is `resolved`.
- **Frontier**: scan `.scratch/<nature>/<effort>/issues/` for files that are open, unblocked, and unclaimed; first by number wins.
- **Claim**: set `Status: claimed` and save before any work.
- **Resolve**: append the answer under an `## Answer` heading, set `Status: resolved`, then append a context pointer (gist + link) to the map's Decisions-so-far in `map.md`.
