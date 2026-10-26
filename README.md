# Nextial Form Events

WordPress plugin: every new Contact Form 7, WPForms, Fluent Forms or Gravity Forms submission is sent to ChatGPT (or any MCP client) as a signed **MCP Events** notification (`form.submitted`), so the assistant can sort the lead and draft the reply by itself.

- Read-only MCP tools: `list_forms`, `list_recent_submissions`, `get_submission`
- OAuth 2.1 sign-in or secret URL, Standard Webhooks signatures, SSRF-safe delivery with retries
- Per-form switch and excluded fields; password, upload, payment and CAPTCHA fields are never sent

Page: https://nextial.it/form-events/ · Sister plugin: [Nextial Order Events for WooCommerce](https://github.com/nextial/nextial-order-events-for-woocommerce)

License: GPL-2.0-or-later
