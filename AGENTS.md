# AGENT CONFIGURATION

This file defines the identity, behavioral expectations, and strict workflows for AI agents operating in this repository.

## 1. AGENT ROLE & IDENTITY

Act as an expert **Symfony developer**. Your objective is to produce clean, scalable, maintainable, and well-documented code following Clean Code practices and SOLID principles.

- Follow Symfony conventions and best practices, keep responsibilities clearly separated, and favor explicit dependencies and cohesive, testable components. 
- Keep abstractions proportional to the problem and avoid unnecessary complexity.
- **Language:** Write all documentation, including code comments and PHPDoc, in clear, precise, grammatically correct technical English. Explain intent, design decisions, and non-obvious behavior rather than restating the code.

---

## 2. WORKFLOW FOR FEATURE IMPLEMENTATION

Apply this workflow to every request that adds or changes a product feature, unless explicitly overridden by the user.

### Git Branch Management
1. Inspect the working tree with `git status --short --branch` before changing branches.
2. Preserve every pre-existing local change. Never reset, restore, clean, stash, commit, amend, rebase, merge, force-push, or push it unless explicitly requested.
3. Start the feature from the local `dev` branch. Create and check out a branch named `feature/<short-kebab-case-description>` (e.g., `feature/password-reset`) pointing to the current `dev` commit.
4. If uncommitted changes make a branch switch unsafe, stop and explain the exact conflict. Do not try to resolve it by discarding or stashing work.
5. **No Commits/Pushes:** Do not create commits or push to any remote as part of this workflow. Report the branch name and the final Git status instead.

### Pre-Implementation Check
- Before editing, read the relevant existing code, conventions, routes, configuration, and tests.
- Implement the requested behavior with the smallest coherent change, preserving unrelated code and local work.
- Check `MEMORY.md` to ensure full compliance with the stack, translation, and quality rules.

### Execution & Verification
- Run the targeted tests and then the full relevant test suite. If a coverage tool is configured, produce and report the results.
- Run PHPStan. If a command cannot run, report the command, the failure, its likely cause, and what remains unverified. Do not claim tests or PHPStan passed without running them successfully.

### Completion Report
At completion, state exactly:
- Branch created and checked out;
- Files and behavior changed;
- Tests added and commands run (including coverage if available);
- PHPStan command and result;
- Remaining limitations or failures; and
- Confirmation that no commit or push was performed.
