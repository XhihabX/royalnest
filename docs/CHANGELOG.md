# Changelog — Royal Nest Restaurant ERP

All notable changes to this project will be documented in this file.

## [2026-11-11] — Phase 0: Project Initialization, Repository Isolation & Architecture Baseline

**What:**
1. Created dedicated subfolder `royal-nest/` with independent Git repository (`git init`).
2. Configured parent `.gitignore` to strictly ignore `/royal-nest/`, guaranteeing zero git pollution on Saba POS repository.
3. Established initial architecture baseline `docs/ARCHITECTURE.md` and `docs/CHANGELOG.md` aligned with PRD Version 2.0.
4. Scaffolded application structure for Laravel + Inertia.js + Vue 3 + MySQL foundation.

**Why:** Establish a clean, dedicated repository foundation for Royal Nest Restaurant ERP without technical debt or coupling to retail SaaS structures.
**How:** Created subfolder, initialized git, configured `.gitignore`, and authored baseline architecture documentation.
**Where:** `royal-nest/`, `royal-nest/docs/*`, `../.gitignore`.
**Impact:** Zero impact on Saba POS. Independent development environment ready for Royal Nest.

