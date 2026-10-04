# Contributing to Rifaq

Thank you for considering a contribution. Read this guide and the [Code of Conduct](CODE_OF_CONDUCT.md) before opening an issue or pull request.

## Before you begin

- Search existing issues and pull requests for related work.
- For a substantial change, open an issue first to agree on scope.
- Use synthetic data only. Do not submit personal, patient, donor, facility-confidential, or production data.
- Do not add credentials, `.env` files, local databases, tokens, or other secrets.
- Do not describe the application as clinically validated, legally approved, or authorized for real healthcare use.
- The repository currently has no selected software license. Until the project owner adds one, public visibility does not grant reuse or contribution licensing rights. Please discuss the licensing status with the maintainer before contributing code intended for reuse.

## Local development

Follow the [Windows local setup](README.md#run-locally-on-windows) or install PHP 8.3+, Composer 2, Node.js 22+, and npm on your platform. Install backend and frontend dependencies from their respective folders.

Run relevant checks before submitting:

```powershell
Set-Location backend
php artisan test
vendor\bin\pint --test

Set-Location ..\frontend
npm run lint
npm run build
```

The CI workflow also exercises PostgreSQL migrations/tests and frontend checks. Report any check you could not run rather than marking it as passing.

## Contribution expectations

- Keep changes focused and consistent with the existing Laravel API and Next.js/TypeScript patterns.
- Update tests and user-facing documentation when behavior, routes, setup, or limitations change.
- Preserve backend/frontend contract consistency and add authorization/validation tests when touching protected workflows.
- Treat blood matching as a non-clinical screening aid. Do not add automatic transfusion or eligibility decisions.
- Do not add unverified medical guidance, official-data claims, integrations, or regulatory claims without authoritative project evidence and appropriate review.
- Make interfaces usable across Arabic RTL, French, and English when changing visible copy or layout.

## Pull requests

1. Use a focused branch and make small, reviewable commits.
2. Open a pull request against `main` and complete the repository pull-request template.
3. Explain the problem, change, user impact, and any limitations or migrations.
4. Link relevant issues; add screenshots for UI changes using synthetic data.
5. Confirm tests/checks and disclose any that were not run.
6. Wait for review; do not claim medical or security approval from an automated check.

Maintainers may request revisions or decline work that falls outside the project scope or safety boundaries.
