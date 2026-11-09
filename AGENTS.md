# Repository Guidelines

## Project Overview

This project is a local-development MVP for a Municipal Employee Attendance and Workforce Analytics System. It uses React with Tailwind CSS for the frontend, Lavalust PHP as the sole application backend, MySQL for persistence, and Python with scikit-learn for department-level attendance forecasting. The required product behavior and acceptance criteria are defined in the owner-provided `specs.md`; read it before implementation and treat it as the scope baseline.

The MVP covers Employee registration and Admin approval, departments, simulated weekday time-in/time-out, personal attendance history, Admin monitoring and CSV reports, and five-working-day department forecasts. It does not connect to biometric hardware or include payroll, leave, official absence/late classification, or production deployment. Keep business rules and authorization in Lavalust; React is not a security boundary. Do not invent additional roles, rules, or features.

## Project Structure

The workspace has a separate `frontend/` React app and `Lavalust/` PHP backend. Within `Lavalust/`, application code lives in `app/`: controllers, models, views, configuration, custom libraries, commands, and database migrations. Framework internals are in `Lavalust/scheme/`; treat them as core and avoid changes when an application-level extension is sufficient. `Lavalust/public/index.php` is the web entry point, `Lavalust/console/cli.php` implements CLI commands, and `Lavalust/runtime/` holds writable runtime data. Put schema changes in `Lavalust/app/migrations/`.

## Development Commands

Requires PHP 8.2 or newer and a configured web server with URL rewriting.

- `php lava serve` starts the built-in development server with Lavalust's front-controller router; add `--port=8080` to choose a port.
- `php lava` lists available CLI commands.
- `php lava route:list` displays configured routes.
- `php lava migration status` checks migration state; `php lava migration run` applies pending migrations.
- `php -l path/to/file.php` checks an individual PHP file for syntax errors.
- From the workspace root, `cd frontend; npm install; npm run dev` starts the separate React frontend. Run `cd Lavalust; php lava serve --port=3000` for its backend.
- From the workspace root, `cd frontend; npm run build` creates the frontend bundle in `frontend/dist/`.
- Provision the initial Admin with `php lava admin:create admin@example.local` after setting `ADMIN_SETUP_PASSWORD` in the shell; clear it afterward. Real employee accounts enter through registration and Admin approval.
- Set up forecasting from `ml/` with `python -m venv .venv`, install `requirements.txt`, then run `.venv/Scripts/python.exe seed_attendance.py`, `train_model.py`, and `generate_forecasts.py` in order. Schedule the training/forecast checks on Sundays at 2:00 AM Asia/Manila.
- Migrations `005` and `006` add generic departments and 15 clearly marked sample employees; their random passwords are discarded, so those fixtures are for analytics only. `seed_attendance.py` generates repeatable `SIMULATED` records for them.

Configure local settings in `.env` or the relevant `app/config/` files. Never commit secrets or local `.env` changes.

## Style and Naming

Follow nearby PHP conventions: four-space indentation, classes in PascalCase (for example, `Welcome`), and filenames matching existing framework patterns (for example, `User_model.php`). Keep controllers, models, and views in their corresponding `app/` directories. Use descriptive, lowercase snake_case names for migration files with the framework's numeric prefix, such as `003_add_profile_fields.php`.

## Testing

No automated test framework or test suite is configured in this repository. For PHP changes, run `php -l` on modified files and exercise affected routes or CLI commands locally. For schema work, use the native migration system and check the resulting migration status.

## Commits and Pull Requests

Recent history uses short, direct subjects, sometimes with a release/version prefix (for example, `fixing` or `Version 4.7.2 Emergency Patch...`). Use a concise imperative summary that explains the change. PRs should describe behavior and rationale, link related issues when available, list relevant verification, and include screenshots for visible UI changes.

## Token Efficiency and Communication Rules

- Keep communication concise, direct, and technical; report key findings, decisions, blockers, and completion only. Avoid repeated summaries, basic explanations, and unnecessary plans or questions.
- Inspect only relevant files and reuse repository guidance already read. Read `specs.md` and relevant `AGENTS.md` sections before implementation when present.
- Make the smallest complete change, following existing conventions. Avoid unrelated work, duplicate documentation, unnecessary abstractions, and unrequested features; never trade correctness, security, or maintainability for brevity.
- Diagnose root causes before fixes. Verify changes with the smallest relevant check and never claim unverified behavior.
- Follow LavaLust architecture and migration conventions. Use its native migration system for all schema changes; do not invent business rules or exceed approved MVP scope.
- When blocked, state the exact blocker and ask only the necessary question.
