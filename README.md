# Nextial Form Events

**ChatGPT handles every new form entry on your WordPress site.** Contact Form 7, WPForms, Fluent Forms, Gravity Forms, Formidable and Ninja Forms: each new submission is sent to ChatGPT (or another MCP client) as a signed **MCP Events** notification (`form.submitted`), so the assistant sorts the lead, drafts the reply or flags spam on its own — following instructions you write in plain language.

Free on WordPress.org: **https://wordpress.org/plugins/nextial-form-events/**
2-minute setup video: **https://youtu.be/Tr4-CdZGUyI**

```
"When someone fills in my contact form, tell me if it is a real lead and draft a reply in my tone."
"When a quote request arrives, estimate the budget and list what is missing."
```

## What it does

- **Real time**: your site notifies the assistant the moment a form is submitted. No Zapier, no polling, no code.
- **MCP tools** (read-only in the free plugin): `list_forms`, `list_recent_submissions` (with `since`, so a client without events can ask "what's new since last time"), `get_submission`.
- **Any MCP client**: ChatGPT for the automatic events; Claude, Claude Code, VS Code and Cursor connect too and ask for what is new.
- **Privacy by design**: per-form switch and excluded fields; password, upload, payment and CAPTCHA fields are never sent. Entries are only kept while a client is connected.
- **Safe delivery**: OAuth 2.1 sign-in or secret URL, Standard Webhooks signatures, SSRF-safe delivery with retries.

## Before you install

One of the supported form plugins, a ChatGPT plan with **Work** chats and custom MCP servers (Plus or higher) for the automatic events, and a site on **HTTPS**.

## Pro: Lead Desk

[Nextial Form Events Pro – Lead Desk](https://nextial.it/form-events/) adds the actions: reply to a lead from your site, mark handled or spam, add notes, a `lead.unanswered` event after X hours, and the lead source (UTM, gclid, landing page, referrer).

## Other Nextial plugins

- [Nextial Order Events for WooCommerce](https://github.com/nextial/nextial-order-events-for-woocommerce) — new orders, stock and refunds to ChatGPT in real time ([WordPress.org](https://wordpress.org/plugins/nextial-order-events-for-woocommerce/))
- [Nextial Order Archive](https://github.com/nextial/nextial-order-archive) — archive old WooCommerce orders and keep the Orders screen fast ([WordPress.org](https://wordpress.org/plugins/nextial-order-archive/))

License: GPL-2.0-or-later
