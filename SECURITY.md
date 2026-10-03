# Security policy

Where this file is silent, the [laranail security policy](https://github.com/laranail/.github/blob/HEAD/SECURITY.md) applies.

## Reporting a vulnerability

Please do not open a public issue for a security report. Two channels, in order of preference:

1. **GitHub private vulnerability reporting**, from this repository's Security tab. The report arrives
   attached to the repository with a draft advisory and a CVE request path already in place.
2. **Email `security@simtabi.com`**, if you would rather not use GitHub or do not have an account.

Either way, include a description of the issue, the affected version, and steps to reproduce.

You will receive an acknowledgement within three working days. We aim to ship a fix, or a documented
mitigation, within 30 days of confirming the report, and will credit you in the release notes unless
you ask otherwise.

## What is in scope

This package turns untrusted text into HTML (`toHtml()`, `Mode::Image`, the Blade component and directive)
and parses text a user controls. Reports about escaping, attribute injection through custom emoji or image
set URLs, denial of service through crafted input, or anything that lets input reach a log or exception
message are especially welcome.

## Supported versions

While the package is pre-1.0, only the latest tag receives security fixes.
