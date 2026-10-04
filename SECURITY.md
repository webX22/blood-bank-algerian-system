# Security Policy

## Supported versions

This repository is an early-stage technical foundation. There are no published release versions or guaranteed security-maintenance window yet. The `main` branch is the current development line; no production support commitment is implied.

## Reporting a vulnerability

**Please do not report exploitable vulnerabilities in public issues, pull requests, screenshots, or discussions.**

Use GitHub's private vulnerability reporting flow for this repository:

<https://github.com/webX22/blood-bank-algerian-system/security/advisories/new>

If private reporting is unavailable, contact the project owner through <https://github.com/webX22> and request a private security-reporting channel. Do not publish exploit details while arranging contact.

When reporting, include only information needed to reproduce and assess the issue: affected component/commit, impact, prerequisites, and safe reproduction steps. Never include real personal data, patient/donor records, credentials, access tokens, or production secrets. Use synthetic accounts and a local test environment.

Please allow maintainers a reasonable opportunity to assess a report and coordinate a fix before public disclosure. There is currently no guaranteed response or remediation SLA.

## Scope and safety

- Test only systems and accounts you own or have explicit authorization to assess. Do not test against a live healthcare service.
- Do not access, alter, retain, or disclose other users' data.
- Do not perform denial-of-service testing, social engineering, or destructive actions.
- Authentication, authorization, audit logging, and CI are technical controls, not an independent security assessment or certification.

For non-security bugs, use the ordinary GitHub issue forms.
