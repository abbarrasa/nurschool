# PROJECT MEMORY

This file tracks the project's architecture, stack decisions, and technical guidelines that must be respected across all sessions.

---

## 1. TECH STACK & ARCHITECTURE

### Frontend & Backend Separation
- **Backend:** Symfony with **API Platform** for a strict REST API.
- **Frontend:** **Twig** and **Vue.js**. Twig provides the page structure and mounts the Vue.js interface, while Vue.js handles interactivity and data loading via API calls.
- **Business Logic:** Centralized in the backend (validation, authorization, and persistence). Do not pass application data directly from backend controllers into Twig templates.

### Frontend Styling
- **Framework:** **Bulma CSS**.
- **Isolation:** Third-party assets and framework-specific styles must be isolated in a presentation theme loaded through one shared entry point. 
- **Decoupling:** Centralize framework classes in reusable Twig presentation helpers. Business templates and Vue.js logic must use semantic presentation helpers and application-owned selectors instead of depending directly on Bulma.

---

## 2. TRANSLATIONS & LITERALS

- **Source of Truth:** Supported locales are strictly defined in `framework.enabled_locales` inside Symfony's `translation.yaml`. Do not hardcode separate lists.
- **User Interface:** All user-visible text (labels, buttons, placeholders, validation, and browser-facing error messages) must use the application's translation mechanism and catalogs.
- **Server-Side Literals:** Internal server strings not exposed to the browser (exceptions, logs, diagnostics) must be written in **English**. Separate translated user-facing messages from internal English messages if an error must be shown.

---

## 3. QUALITY GATES & TESTING

Every feature must implement automated coverage for the changed behavior:
- **Unit Tests:** For isolated domain, service, and validation logic.
- **Functional Tests:** For HTTP endpoints, controllers, forms, authentication, and error responses.
- **Integration Tests:** For persistence, Doctrine, messaging, or external adapters (use fakes/test doubles unless live calls are explicitly authorized).

### Static Analysis & Tooling
- Run **PHPStan** at the project's configured level and resolve any introduced errors. Do not weaken rules, exclude new code, or lower the analysis level to pass.
- If the repository lacks test tooling or PHPStan configuration, add the minimal development dependencies required to run them as part of the feature work.
