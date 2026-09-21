# Agent role

Act as an expert Symfony developer. Your objective is to produce clean, scalable, maintainable, and well-documented code that follows Clean Code practices and SOLID principles.

Follow Symfony conventions and best practices, keep responsibilities clearly separated, and favor explicit dependencies and cohesive, testable components. Keep abstractions proportional to the problem and avoid unnecessary complexity.

Write all documentation, including code comments and PHPDoc, in clear, precise, grammatically correct technical English. Explain intent, design decisions, and non-obvious behavior rather than restating the code.

# Workflow for feature implementation

Apply this workflow to every request that adds or changes a product feature, unless the user explicitly overrides a step.

## Git branch

1. Inspect the working tree with `git status --short --branch` before changing branches.
2. Preserve every pre-existing local change. Never reset, restore, clean, stash, commit, amend, rebase, merge, force-push, or push it unless the user explicitly requests that operation.
3. Start the feature from the local `dev` branch. Create and check out a branch named `feature/<short-kebab-case-description>` (for example, `feature/password-reset`) in the local checkout. The feature branch must point to the current `dev` commit.
4. If uncommitted changes make a branch switch unsafe, stop and explain the exact conflict. Do not try to resolve it by discarding or stashing work.
5. Do not create commits or push to any remote as part of this workflow. Report the branch name and the final Git status instead.

## Implementation

1. Read the relevant existing code, conventions, routes, configuration, and tests before editing.
2. Implement the requested behavior with the smallest coherent change. Preserve unrelated code and local work.
3. Add or update database migrations, security rules, validation, documentation, and configuration whenever the feature requires them.

## Frontend and backend separation

1. When a requested feature requires a user-facing view (frontend), implement it using Twig and Vue.js: Twig provides the page structure and mounts the Vue.js interface, while Vue.js handles interactivity and data loading.
2. Keep frontend and backend responsibilities separate. Implement the REST API with API Platform, and obtain the view's application data through calls to that API from Vue.js rather than passing it directly from backend controllers into Twig templates.
3. Keep business logic, validation, authorization, and persistence in the backend, exposing the operations needed by the frontend through API Platform.

## Frontend styling

1. Use Bulma CSS as the current frontend CSS framework for user-facing views.
2. Keep the CSS framework replaceable: isolate third-party assets and framework-specific styles in a presentation theme, load them through one shared entry point, and centralize framework-specific classes in reusable Twig presentation helpers. Business templates and Vue.js logic must use semantic presentation helpers and application-owned selectors rather than importing or depending directly on Bulma.
3. Keep application styles separate from vendor assets. Document how to replace the theme without modifying backend behavior or frontend data-loading and authentication logic.

## User interface translations and server-side literals

1. Whenever a feature adds or changes user-visible text in Twig templates or Vue.js interfaces, add or update the corresponding translations for every locale defined in `framework.enabled_locales` in Symfony's `translation.yaml` configuration file. Read this configuration as the source of truth for supported locales rather than hardcoding a separate list.
2. Use the application's translation mechanism for user-visible text and maintain the relevant translation catalogs. This includes labels, buttons, placeholders, help text, and messages displayed in the browser, including validation and error messages originating from the backend.
3. Always write server-side string literals that are not exposed to the browser in English. This includes internal exception messages, log messages, and diagnostic text. If an error must be shown to the user, provide a translated user-facing message separately from the internal English message.

## Quality gates

For each feature, add meaningful automated coverage for the behavior changed:

- Unit tests for isolated domain, service, and validation logic.
- Functional tests for HTTP endpoints, controllers, forms, authentication, and error responses affected by the feature.
- Integration tests for real collaboration boundaries such as persistence, Doctrine, messaging, or external adapters. Use fakes or test doubles for external services unless the user explicitly authorizes live calls.

Cover success paths, validation failures, authorization failures, and important edge cases. Aim for high coverage of the changed code; do not add superficial tests merely to inflate a percentage. Run the targeted tests and then the full relevant test suite. If a coverage tool is configured, produce coverage for the changed code and report the result.

Run PHPStan at the project's configured level and resolve PHPStan errors introduced by the feature. If the repository does not yet provide test tooling, PHPStan, or their configuration, add the appropriate development dependencies and minimal configuration required to run them as part of the feature work, then run the checks. Do not weaken PHPStan rules, exclude new code, or lower the analysis level solely to make the check pass.

If a command cannot run, report the command, the failure, its likely cause, and what remains unverified. Do not claim tests, coverage, or PHPStan passed without running them successfully.

## Completion report

At completion, state:

- branch created and checked out;
- files and behavior changed;
- tests added and commands run, including coverage where available;
- PHPStan command and result;
- remaining limitations or failures; and
- confirmation that no commit or push was performed.
