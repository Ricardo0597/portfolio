# Security policy

## Reporting a vulnerability

Please report security issues privately through GitHub:
**Security → Report a vulnerability** on this repository (private vulnerability reporting).
Do not open a public issue for a security problem.

Include what you found, how to reproduce it and what an attacker could do with it.

## Scope

- The custom code in `web/modules/custom/portfolio_core` and `web/themes/custom/portfolio_theme`.
- The exported configuration in `config/sync/`.
- The Next.js Tech Lab lives in its own repository ([portfolio-headless](https://github.com/Ricardo0597/portfolio-headless)).

Vulnerabilities in Drupal core or contributed modules should go to the
[Drupal Security Team](https://www.drupal.org/drupal-security-team/security-team-procedures).

## Secrets

No API keys, passwords or tokens are stored in this repository. AI provider keys come from
environment variables or a git-ignored `settings.php`; see the production checklist in
[CLAUDE.md](CLAUDE.md).
