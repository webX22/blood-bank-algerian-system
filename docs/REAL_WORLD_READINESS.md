# Real-world readiness and limitations

This repository provides application code and development checks only. It has not been authorized for real patient care, blood collection, donor recruitment, allocation, or transfusion operations.

## Required before any live use

- Obtain review and written approval from qualified Algerian transfusion-medicine professionals and the responsible blood-service operators. Validate every workflow, blood component rule, terminology, and escalation process against current clinical protocols.
- Obtain Algerian legal, privacy, and regulatory review for health data, consent, retention, cross-border hosting, incident response, and the responsibilities of each operator.
- Conduct an independent security assessment, threat model, penetration test, access-control review, and privacy impact assessment. Configure production secrets, TLS, secure headers, rate limits, monitoring, backups, recovery tests, retention/deletion, and incident response.
- Connect only to authorized identity, facility, and notification systems. Email, SMS, WhatsApp, national health systems, and clinical laboratory integrations are not implemented; in-app notifications are database records only.
- Provision verified staff/admin access through a controlled operator process. Public registration intentionally cannot assign privileged roles.
- First-run privileged accounts can be provisioned on the application server with the interactive `php artisan app:create-operator` command; the password is entered via a hidden prompt. Facility creator/approver separation requires a second administrator for facility activation.
- Establish a verified, maintained geographic data source and correction process. The current seeded wilaya labels are provisional conventional labels, have not been checked against an official current registry, and are not a substitute for authoritative administrative data. Commune-level records are absent; users can currently select a wilaya only.
- Validate data provenance, translations, accessibility, and usability with Algerian Arabic, French, and English speakers and representative users.
- Build and validate deployment artifacts. This project currently has no Docker deployment definition; infrastructure, database backup, monitoring, and disaster recovery are not supplied here.

## Medical and data safeguards in this implementation

- Donor-submitted blood group is not independently verified. Donors start unverified and unavailable; staff review fields are operational flags, not proof of medical screening.
- Matching uses only simple red-cell ABO/Rh candidate rules, consent, wilaya, and staff-maintained flags. It does not account for antibody screening, crossmatching, component-specific protocols, clinical eligibility, inventory quarantine, or any other laboratory/clinical criterion. Plasma and platelet auto-matching are deliberately rejected.
- The automatic fulfillment workflow is a technical stock ledger for unexpired red-cell units. It does not authorize allocation, release, transport, transfusion, or clinical use.
- Requests are held for staff review. This repository does not provide a verified-facility onboarding process, on-call coverage, emergency dispatch, or a confirmed human review SLA.
- Phone numbers and donor contact details are exposed only to authenticated staff through consent-filtered matching results; public reference endpoints do not expose donor records. Audit metadata avoids request notes and contact details.
- The 58 seeded wilaya labels and conventional translations are convenience data, not official or validated geographic data. No communes are seeded.
- Token authentication, role middleware, route throttles, and audit entries are technical controls, not a completed cybersecurity or regulatory review. Tokens are held in frontend memory and are cleared when the page reloads.
- Email verification and password-reset flows are not implemented. Provision operators only after identity checks through an approved out-of-band process.

## Validation scope

The repository tests exercise application workflows on SQLite. PostgreSQL support is configured and a PostgreSQL-backed CI job is defined, but no production PostgreSQL instance or deployment environment has been certified by this project. Automated test success is not medical, legal, regulatory, or operational approval.
