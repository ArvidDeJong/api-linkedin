# Security policy

This package holds OAuth tokens that can publish on a LinkedIn profile or company page, so security reports are taken seriously.

## Supported versions

Only the latest minor release of 1.x receives security fixes. Upgrade before reporting.

## What counts as a vulnerability

For example:

- a way to complete the OAuth callback without a valid `state`, or to attach someone else's authorization code to the stored connection;
- an access or refresh token ending up unencrypted in the database, in logs or in exception output;
- `LINKEDIN_CLIENT_SECRET` leaking into logs, responses or the authorization URL;
- publishing on a page the token does not carry a scope for being possible after all.

## Reporting a vulnerability

Please do **not** open a public issue. Report it privately instead:

- via [GitHub private vulnerability reporting](https://github.com/ArvidDeJong/api-linkedin/security/advisories/new), or
- by email to info@arvid.nl.

Include the package version, the Laravel version and the steps or request that reproduce it.

You will get a reply within a week. Once a fix is released, the advisory is published and you are credited, unless you prefer not to be.

Problems in LinkedIn's own platform, products or API belong with LinkedIn; this is an independent package.
