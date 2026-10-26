# Spec: Resume file

**Status:** ready-for-agent

## Problem Statement

Where to resume work is only ever stated in chat replies. A new session, a different agent, or the user coming back after days has no durable place to read the current state, what is next, and which risks are open.

## Solution

A `resume.md` file at the repository root always holds the current resume point. `AGENTS.md` tells every agent to read it first and to rewrite it whenever a resume point is stated, so the project's standing survives between sessions.

## User Stories

1. As the maintainer, I want one file that says where to resume, so that I can pick the project up without replaying a conversation.
2. As an agent starting a session, I want to read the resume point first, so that I continue from the right place.
3. As an agent finishing a task, I want a rule that tells me to update the resume point, so that the next session is not working from stale state.
4. As the maintainer, I want open risks listed next to the next steps, so that nothing known gets lost between sessions.
5. As the maintainer, I want the file to hold the current state only, with history left to git, so that it stays short enough to read in a minute.

## Implementation Decisions

- `resume.md` lives at the repository root and is tracked in git, so every worktree and every session sees the same file.
- Structure: last-updated line, repository state, "Resume from here" (ordered next steps), open items and risks.
- `AGENTS.md` gets a "Resume point" subsection at the top of the Mandatory workflow and a pointer in its opening summary: read `resume.md` first; rewrite it whenever a resume point is stated and as the last step of every task; ship the update in the task's PR (a docs-only PR when no task is open).
- A dated Change log row records the rule and what applied before.
- The file holds current state only; history stays in git and the Change log.

## Testing Decisions

- A documentation change: the observable behaviours are that the file exists with the agreed structure, that `AGENTS.md` instructs agents to read and update it, and that the `CLAUDE.md` to `AGENTS.md` to `resume.md` reading chain is intact.
- Single seam: the CI check on this ticket's own pull request stays green (no application code changes), plus a read-through that the three facts above hold.
- Prior art: the `chores/agents-md-promotion-rules` ticket, another `AGENTS.md` rule change.

## Out of Scope

- Automating updates to `resume.md` (hooks, scheduled tasks).
- Changing `GEMINI.md`.

## Further Notes

- Concurrent tickets that both edit `resume.md` can conflict on merge; the later PR resolves it by rewriting the current state.
