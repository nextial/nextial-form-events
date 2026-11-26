=== Nextial Form Events ===
Contributors: nextial
Tags: contact form 7, wpforms, chatgpt, mcp, lead management
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

ChatGPT handles every new Contact Form 7, WPForms, Fluent, Gravity, Formidable or Ninja Forms entry: sorts the lead and drafts the reply.

== Description ==

**Before you install, you need:** one of the supported form plugins, a ChatGPT plan with **Work** chats and custom MCP servers (ChatGPT **Plus** or higher), and a site on **HTTPS**. Without Work chats ChatGPT can read your form entries but cannot react to new ones.

Nextial Form Events connects your WordPress forms to ChatGPT with **MCP Events**, the way ChatGPT reacts when something happens in a connected app. Each time a visitor submits a form, your site sends ChatGPT a signed notification and ChatGPT does what you asked it to, in plain language:

* "When someone fills in my contact form, tell me if it is a real lead and draft a reply in my tone."
* "When a quote request arrives, estimate the budget and list what is missing."
* "Ignore messages that look like sales pitches, and tell me about the rest."

ChatGPT subscribes to the event, your site notifies it the moment a form is submitted, and ChatGPT does the rest. No Zapier, no polling, no code.

https://youtu.be/Tr4-CdZGUyI

= Supported form plugins =

* Contact Form 7
* WPForms (Lite and Pro)
* Fluent Forms
* Gravity Forms
* Formidable Forms
* Ninja Forms

You choose which forms notify ChatGPT and which fields are never sent.

= Event =

* **form.submitted** – a visitor submitted a form. ChatGPT can listen to all forms, one form (`form_id`) or one plugin (`plugin`). Free, no limits.

= Use it with Claude =

Claude (claude.ai, Desktop, mobile): Settings → Connectors → "Add custom connector", paste the URL from Settings → Nextial Form Events, sign in and press *Allow*. No client ID or secret needed. Claude can list your forms and recent submissions and read each entry. Claude does not receive live events yet: for alerts, use a Claude scheduled task such as "every hour, list the submissions since the last checked_at and draft a reply to each new lead". Claude Code can connect too (`claude mcp add --transport http`).

= Read-only tools =

ChatGPT can look things up when it needs more context: `list_forms`, `list_recent_submissions` (for example "has this person written before?") and `get_submission`. Only submissions sent to ChatGPT, or kept while an assistant you authorised is connected, are available (the last 50, up to 30 days). With `since`, an assistant without live events (for example a Claude scheduled task) can ask only for what arrived since its last check. Nextial Form Events never changes your site or your form entries.

= Privacy and security =

* **You choose what is sent.** Untick forms you don't want ChatGPT to see, and list fields that must never be sent.
* Password, file-upload, credit-card, payment, CAPTCHA and the form plugins' technical fields are never sent.
* Entries your form plugin marks as spam are never sent.
* **Sign in with OAuth**: ChatGPT opens your site, you log in and press *Allow*. Disconnect at any time from Settings → Nextial Form Events; tokens and subscriptions are removed at once.
* The last 50 submissions (at most 30 days) are kept only while ChatGPT is subscribed or an assistant you authorised is connected, so it can look them up.
* Only administrators can connect the site.
* Every notification is signed (Standard Webhooks, HMAC-SHA256). Callbacks are verified with a challenge before any data is sent.
* Outgoing requests go only to public HTTPS addresses (no private networks, no redirects).
* ChatGPT is told that field values are written by visitors and must be treated as data, not as instructions.

= Lead Desk (optional paid add-on) =

The free plugin is complete on its own. [Lead Desk](https://checkout.freemius.com/plugin/43089/plan/75030/?billing_cycle=annual) (7-day free trial) adds:

* **No lead lost when e-mail fails**: every submission is kept; if your site cannot send the form's notification e-mail (broken mail or SMTP), you are alerted on Slack, Discord or ChatGPT, and you resend that e-mail in one click once mail works.
* **No lead lost to the captcha**: when Cloudflare Turnstile, reCAPTCHA or hCaptcha wrongly blocks a real visitor, Contact Form 7 keeps no copy. Lead Desk keeps these submissions for 30 days so you can mark them "Not spam" in one click.
* **Know when a form goes quiet**: if a form that usually gets submissions receives none for much longer than usual (for example after an update breaks it in the browser), you get an alert and a notice in WordPress.
* Replies from your site written by ChatGPT (or drafts you approve), a reminder for leads nobody answered, and where each lead came from (UTM, Google Ads, landing page).

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
* **Client identification.** When an assistant connects with OAuth and identifies itself with a Client ID Metadata Document (ChatGPT uses `https://chatgpt.com/oauth/client.json`, Claude uses `https://claude.ai/oauth/mcp-oauth-client-metadata`), Nextial Form Events downloads that public JSON file to check the app's name and allowed redirect addresses. Nothing about your site is sent in that request.

No data is sent until an administrator connects an assistant and it subscribes to events or calls a tool. Form submissions contain personal data written by your visitors: mention in your privacy policy that form entries may be processed by the AI assistant you use. When you connect ChatGPT, OpenAI's terms and privacy policy apply to the data it receives: [Terms of use](https://openai.com/policies/terms-of-use/), [Privacy policy](https://openai.com/policies/privacy-policy/). When you connect Claude, Anthropic's terms and privacy policy apply: [Consumer terms](https://www.anthropic.com/legal/consumer-terms), [Privacy policy](https://www.anthropic.com/legal/privacy).

= Trademarks =

ChatGPT is a trademark of OpenAI. Claude is a trademark of Anthropic. Contact Form 7, WPForms, Fluent Forms, Gravity Forms, Formidable Forms and Ninja Forms belong to their respective owners. Nextial Form Events is an independent plugin by nextial and is not affiliated with, endorsed or sponsored by any of them.

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

Deliveries are sent in the background with WP-Cron (or Action Scheduler, if another plugin provides it), usually within a minute. On sites with little traffic, consider a real server cron for wp-cron.php. If notifications wait for more than 30 minutes (for example because WP-Cron is turned off with DISABLE_WP_CRON and no server cron replaces it), a warning appears on the Dashboard and on the plugin page, with a link to the past-due actions when Action Scheduler is present. Nothing is lost: waiting notifications are sent as soon as background tasks run again. The 30 minutes can be changed with the `nextial_fe_late_after` filter (seconds).

= My host does not have PHP 8.3, so I stay on Contact Form 7 6.1. Does it work? =

Yes. Contact Form 7 6.2 needs PHP 8.3; version 6.1 still runs on PHP 7.4 and later, and so does Nextial Form Events. We tested Contact Form 7 6.1.7 on PHP 7.4 and PHP 8.2 (WordPress 7.1.5): real submissions reach ChatGPT and Claude with their fields (no hidden or captcha fields), the OAuth sign-in works, and the optional Lead Desk add-on saves every lead, keeps submissions blocked by Cloudflare Turnstile and warns you when a form goes quiet. Contact Form 7 6.2 is tested on PHP 8.3. To check your PHP version: Tools → Site Health → Info → Server. To keep Contact Form 7 on 6.1, turn off auto-updates for that plugin only (Plugins → "Disable auto-updates").

= Can an update break my form without anyone noticing? =

Yes. In a real case on the Contact Form 7 support forum, a child theme still used a class that Contact Form 7 had removed: after the update the Send button kept spinning and the server returned an error, while the page looked normal. When a submission never reaches WordPress, no plugin on the server can see it. The optional Lead Desk add-on learns each form's usual rhythm (from the last 28 days) and warns you when a busy form stays silent for much longer than usual: automatically 4 times the usual gap and at least 2 days, or a number of days you choose. After any update, send one test message from each form.

= ChatGPT or Claude worked, then asks to reconnect (invalid_grant) =

Update to the latest version. Some assistants send back an older refresh token (a previous copy, or a second session on the same connection); since 0.1.5 this is accepted for 7 days and the connection stays up. If it still happens, open the settings page: "Recent sign-in problems" shows the exact reason, and the OAuth check tells you whether your server blocks the sign-in addresses.

= Sign-in works, but the assistant finds no tools ("action discovery failed") =

The sign-in page runs in your browser, but the tools are fetched by the assistant's own servers (OpenAI, Anthropic). If something in front of WordPress blocks those servers, sign-in succeeds and the tool list never arrives. Check Cloudflare or CDN bot rules, your host's firewall and security plugins, and allow POST requests to `/wp-json/nextial-form-events/v1/mcp`.

= The OAuth check fails =

Your server must let WordPress answer `/.well-known/oauth-protected-resource/...` and `/.well-known/oauth-authorization-server/...`. Some hosts reserve `/.well-known/`. Meanwhile you can use the secret URL option.

= Other assistants? =

Nextial Form Events implements the open Model Context Protocol (2026-07-28, with compatibility for 2025 revisions) and MCP Events webhook delivery, so any compatible client can connect. VS Code (GitHub Copilot agent mode) and Cursor connect with the same URL and OAuth sign-in: add it as an HTTP MCP server and sign in when asked (both tested in a lab with their real sign-in settings). VS Code is recognised on the consent screen; Cursor registers itself, so the screen shows its self-declared name with a warning: continue only if you started the connection.

== Screenshots ==

1. Settings → Nextial Form Events: choose the forms sent to ChatGPT, fields that are never sent, and the connection URL.
2. The consent screen shown when ChatGPT connects to the site.
3. Built-in self-test and test receiver: a real Contact Form 7 quote request delivered as a signed form.submitted event, with field labels.

== Changelog ==

= 0.1.6 =
* Warning when form notifications are stuck: if background tasks stop (WP-Cron turned off without a server cron, or a site with no visits), notifications waited silently in the queue and nobody knew. Now the Dashboard and the plugin page say how many are waiting and since when, what to check, and link to the past-due actions in Tools → Scheduled Actions when Action Scheduler is present. Waiting notifications are kept and sent once background tasks run again. Retries are counted from their own due time, so a normal retry does not trigger the warning.

= 0.1.5 =
* Sign-in: the connection no longer drops when ChatGPT or another assistant sends back an older refresh token (it keeps a previous copy, or runs several sessions on one connection, as ChatGPT Desktop does). Before, the store answered "invalid_grant" and the assistant had to be connected again. A refresh token that was already replaced is still accepted for 7 days and gets its own new tokens; the other sessions keep working. "Disconnect" still removes every token at once. Tested in a lab with Claude, VS Code and Cursor sign-in settings.

= 0.1.4 =
* Sign-in: VS Code (its published identity at vscode.dev) is now recognised on the consent screen, like ChatGPT and Claude. VS Code and Cursor tested end to end in a lab (sign-in, tokens, refresh, tools); no other change was needed.

= 0.1.3 =
* Settings page and readme: how to connect Claude, next to ChatGPT. The full Claude sign-in (Claude's published client identity, consent, tokens, refresh) and the tools were tested end to end in a lab: no code change was needed.

= 0.1.2 =
* Works with assistants that have no live events (Claude, Claude Code, VS Code): while one you authorised is connected, recent submissions are kept so it can list them.
* "What's new since last time": list_recent_submissions accepts since and returns checked_at and has_more, for example for a Claude scheduled task that checks every hour.

= 0.1.1 =
* Sign-in: native MCP clients that listen on a random local port (such as Claude Code) can now connect. Loopback redirect URIs are matched without the port (RFC 8252); everything else still needs an exact match.
* The consent screen recognises Claude as well as ChatGPT.

= 0.1.0 =
* First release: form.submitted event for Contact Form 7, WPForms, Fluent Forms, Gravity Forms, Formidable Forms and Ninja Forms, per-form switch, excluded fields, read-only tools (list_forms, list_recent_submissions, get_submission), OAuth 2.1 or secret URL, signed webhooks with retries, self-test and delivery log.
