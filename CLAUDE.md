# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

**examis** — an online final-exam management system for Thai government schools. Students take exams on their phone or computer; teachers author questions; supervisors, managers, deputies, and admins each have their own views. Primary goals: eliminate paper exam sheets that must be shredded, support scanned-PDF exams paired with a virtual answer sheet.

Served via XAMPP at `http://localhost/examis/`.

## Running the app

No build step. Open `http://localhost/examis/` with XAMPP running (Apache only — no database needed for the current prototype).

## Architecture

The entire frontend is a **single-file React prototype** (`index.html`) powered by a bundled runtime (`support.js`).

### Runtime (`support.js`)

A compiled TypeScript runtime (~50 KB) that provides:
- A custom component DSL parsed from the HTML (`<x-dc>`, `<sc-if>`, `<sc-for>`, `<helmet>`)
- A `DCLogic` base class that `Component` extends — exposes `state`, `setState`, and `renderVals()`
- Template binding with `{{ expr }}` interpolation and `style-hover` pseudo-prop
- React is bundled inside `support.js`; there is no separate React import

### App logic (`index.html` — the `<script type="text/x-dc">` block)

All application state and logic lives in a single `Component extends DCLogic` class:

- **`state`** — flat object: `screen` (routing), `pin`, `answers` (object keyed by question number), `timeLeft`, `currentPage`, `mask`/`peekRow` (answer privacy), `perm` (permission approvals), `importText`, etc.
- **`go(screen)`** — all navigation; `screen` is a string key (`'home'`, `'student-login'`, `'student-exam'`, `'student-done'`, `'supervisor'`, `'deputy'`, `'manager'`, `'permission'`, `'teacher-list'`, `'build'`, `'import'`, `'admin'`)
- **`renderVals()`** — single method that derives all template bindings from state; returns a flat object consumed by the DSL

### Template (`index.html` — the `<x-dc>` block)

Screens are toggled via `<sc-if value="{{ isXxx }}">`. The layout splits into:
- Student flow: Home → Student Login → Student Exam → Student Done (dark teal full-screen pages)
- Staff shell: sidebar + main content, shared for Supervisor / Deputy / Manager / Permission / Teacher-* / Admin

### Design tokens

- Font: **Sarabun** (Google Fonts), weights 300–800
- Primary: `#0f766e` (teal-700), gradients to `#134e4a`
- Background: `#f4f7f7`; cards: `#ffffff` with `border: 1px solid #e6edec`
- Border-radius conventions: cards 16–18 px, buttons 10–13 px, pills 999 px
- Animations: `examisPulse`, `examisFade`, `examisPop` (defined in `<helmet>`)

## Extending the prototype

When adding a new screen:
1. Add a `screen` key string.
2. Add an `isXxx` boolean to `renderVals()` return.
3. Add `<sc-if value="{{ isXxx }}">` block in the template.
4. Add a nav entry or role card that calls `this.go('new-screen')`.

The `parseImport()` method handles text-format question import (lines starting with a number, choices ก–ง, `*` prefix marks the correct answer).
