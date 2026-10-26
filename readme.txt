=== Nextial Form Events ===
Contributors: nextial
Tags: chatgpt, contact form 7, wpforms, lead notification, mcp
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

ChatGPT gets every new Contact Form 7, WPForms, Fluent Forms or Gravity Forms entry and handles it by itself: sorts the lead, drafts the reply.

== Description ==

Nextial Form Events connects your WordPress forms to ChatGPT with **MCP Events**, the way ChatGPT reacts when something happens in a connected app. Each time a visitor submits a form, your site sends ChatGPT a signed notification and ChatGPT does what you asked it to, in plain language:

* "When someone fills in my contact form, tell me if it is a real lead and draft a reply in my tone."
* "When a quote request arrives, estimate the budget and list what is missing."
* "Ignore messages that look like sales pitches, and tell me about the rest."

ChatGPT subscribes to the event, your site notifies it the moment a form is submitted, and ChatGPT does the rest. No Zapier, no polling, no code.

= Supported form plugins =

* Contact Form 7
* WPForms (Lite and Pro)
* Fluent Forms
* Gravity Forms

You choose which forms notify ChatGPT and which fields are never sent.

= Event =

* **form.submitted** – a visitor submitted a form. ChatGPT can listen to all forms, one form (`form_id`) or one plugin (`plugin`). Free, no limits.

= Read-only tools =

ChatGPT can look things up when it needs more context: `list_forms`, `list_recent_submissions` (for example "has this person written before?") and `get_submission`. Only submissions already sent to ChatGPT are available (the last 50, up to 30 days). Nextial Form Events never changes your site or your form entries.

= Privacy and security =

* **You choose what is sent.** Untick forms you don't want ChatGPT to see, and list fields that must never be sent.
* Password, file-upload, credit-card, payment, CAPTCHA and the form plugins' technical fields are never sent.
* Entries your form plugin marks as spam are never sent.
* **Sign in with OAuth**: ChatGPT opens your site, you log in and press *Allow*. Disconnect at any time from Settings → Nextial Form Events; tokens and subscriptions are removed at once.
* Only administrators can connect the site.
* Every notification is signed (Standard Webhooks, HMAC-SHA256). Callbacks are verified with a challenge before any data is sent.
* Outgoing requests go only to public HTTPS addresses (no private networks, no redirects).
* ChatGPT is told that field values are written by visitors and must be treated as data, not as instructions.

= Built-in tests =

The settings page has a **self-test** (subscribe → verify → signed delivery → unsubscribe on your own site), a **"listen for real form submissions"** mode, an **OAuth check** and a delivery log.

= Requirements =

* One of the supported form plugins.
* A ChatGPT plan that includes **Work** chats and custom MCP servers (developer mode). On plans without Work chats ChatGPT can use the read-only tools but cannot subscribe to events.
* For OAuth sign-in: pretty permalinks (any setting other than "Plain"). Without them you can still connect with a secret URL.
* Your site must be reachable over HTTPS.

From the makers of [Nextial Order Events for WooCommerce](https://wordpress.org/plugins/nextial-order-events-for-woocommerce/). Both plugins can run on the same site.

== External services ==

Nextial Form Events does not contact any service on its own. It only talks to the AI assistant (MCP client) that you connect and approve:

* **Event notifications.** When ChatGPT – or another MCP client you connected – subscribes to an event, it gives Nextial Form Events a webhook URL (for ChatGPT, on `connectors.api.openai.com`). Each time a form is submitted, Nextial Form Events sends that URL a signed JSON message with: the form plugin, form ID and title, the entry ID (if the form plugin stores entries), the submitted field names, labels and values (except the ones excluded above), the page URL, the date and a link to the entry or form in your admin. Before the first message the URL is verified with a signed challenge.
* **Read-only tools.** When the assistant calls a tool, the response contains your list of forms or submissions that were already sent as events.
* **Client identification.** When an assistant connects with OAuth and identifies itself with a Client ID Metadata Document (ChatGPT uses `https://chatgpt.com/oauth/client.json`), Nextial Form Events downloads that public JSON file to check the app's name and allowed redirect addresses. Nothing about your site is sent in that request.

No data is sent until an administrator connects an assistant and it subscribes to events or calls a tool. Form submissions contain personal data written by your visitors: mention in your privacy policy that form entries may be processed by the AI assistant you use. When you connect ChatGPT, OpenAI's terms and privacy policy apply to the data it receives: [Terms of use](https://openai.com/policies/terms-of-use/), [Privacy policy](https://openai.com/policies/privacy-policy/).

= Trademarks =

ChatGPT is a trademark of OpenAI. Contact Form 7, WPForms, Fluent Forms and Gravity Forms belong to their respective owners. Nextial Form Events is an independent plugin by nextial and is not affiliated with, endorsed or sponsored by any of them.

== Installation ==

1. Install and activate the plugin, then open **Settings → Nextial Form Events**.
2. Check which forms should notify ChatGPT, then copy the connection URL.
3. In ChatGPT open **Plugins → Add → Add custom MCP server**, paste the URL and choose **OAuth** as authentication.
4. ChatGPT opens your site: sign in and press **Allow**.
5. Switch to a **Work** chat, type `@` and pick your site, then ask for example: "Tell me here whenever someone fills in a form on my site, and draft a reply."
6. Submit one of your forms: ChatGPT writes in the chat by itself, usually within a few minutes (your site notifies ChatGPT within seconds; ChatGPT then runs the task).

== Frequently Asked Questions ==

= ChatGPT says the plugin has no events =

ChatGPT keeps a copy of the plugin's tools and events. After installing or updating Nextial Form Events, open ChatGPT Settings → Plugins → your site → **Refresh tools**, then start a new Work chat. If needed, mention the event explicitly: "@YourSite use the plugin's MCP events: tell me here whenever a form is submitted."

= Will spam reach ChatGPT? =

Entries that your form plugin (or its Akismet / CAPTCHA integration) rejects as spam are never sent. Turn on your form plugin's spam protection for best results.

= Which form fields are sent? =

All visible fields of the forms you selected, except password, file-upload, payment, CAPTCHA and technical fields, and any field you list under "Never send these fields". Long values are cut at 2,000 characters and at most 50 fields are sent.

= Can ChatGPT reply to the visitor by itself? =

Nextial Form Events only notifies ChatGPT and lets it read submissions; it cannot send e-mails or change anything on your site. ChatGPT can draft the reply for you, and send it only if you connected an e-mail app to ChatGPT yourself and asked it to.

= Notifications arrive late =

Deliveries are sent in the background with WP-Cron (or Action Scheduler, if another plugin provides it), usually within a minute. On sites with little traffic, consider a real server cron for wp-cron.php.

= The OAuth check fails =

Your server must let WordPress answer `/.well-known/oauth-protected-resource/...` and `/.well-known/oauth-authorization-server/...`. Some hosts reserve `/.well-known/`. Meanwhile you can use the secret URL option.

= Other assistants? =

Nextial Form Events implements the open Model Context Protocol (2026-07-28, with compatibility for 2025 revisions) and MCP Events webhook delivery, so any compatible client can connect.

== Screenshots ==

1. Settings → Nextial Form Events: forms sent to ChatGPT and the connection URL.
2. The consent screen shown when ChatGPT connects to the site.

== Changelog ==

= 0.1.0 =
* First release: form.submitted event for Contact Form 7, WPForms, Fluent Forms and Gravity Forms, per-form switch, excluded fields, read-only tools (list_forms, list_recent_submissions, get_submission), OAuth 2.1 or secret URL, signed webhooks with retries, self-test and delivery log.
