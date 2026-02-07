# Security Policy

## Supported Versions

The following versions of the plugin are currently supported with security updates:

| Version | Supported |
|--------|-----------|
| 0.2.x  | ✅ Yes     |
| < 0.2  | ❌ No     |

Only the latest minor release of the current version line receives security updates.

---

## Reporting a Vulnerability

If you believe you have found a security vulnerability in this plugin, **please do not open a public issue**.

Instead, report it responsibly using one of the following methods:

- **Email:** pkalantzis@gmail.com
- **GitHub (private):** Use GitHub Security Advisories, if available

Please include:
- Plugin version
- WordPress version
- PHP version
- Clear steps to reproduce the issue
- Proof of concept (if available)

You can expect:
- Acknowledgement within **72 hours**
- A fix or mitigation plan as soon as reasonably possible
- Credit in the changelog if you wish (optional)

---

## Security Considerations

This plugin was designed with the following security principles:

- Uses the **WordPress HTTP API** for all external requests
- Communicates only with the **official ENISA EUVD API**
- Does **not** accept or process user-submitted data
- Does **not** store personal data
- Does **not** require API keys or authentication tokens
- Escapes all frontend output
- Sanitizes all admin and widget inputs
- Protects admin functionality with capability checks
- Uses WordPress transients for caching external responses

---

## External Services

This plugin retrieves publicly available vulnerability data from:

- **European Union Vulnerability Database (EUVD)**  
  https://euvd.enisa.europa.eu/

No personal data is transmitted to or received from the EUVD API.

---

## Disclosure Process

When a vulnerability is confirmed:

1. The issue will be reproduced and assessed
2. A fix will be developed and tested
3. A patched version will be released
4. The changelog will note the security fix

---

## Acknowledgements

We appreciate the efforts of security researchers and community members who help keep the WordPress ecosystem safe.