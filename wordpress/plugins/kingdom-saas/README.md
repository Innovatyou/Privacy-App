# Kingdom Cloud — sell church websites as a service

Kingdom Cloud turns the Kingdom Church theme and Kingdom Church Suite plugin into a SaaS you can sell to many churches. Each church gets:

- its own website on `theirchurch.yourplatform.com`, with their own domain available later
- its own partners, payments and Church Admin dashboard
- its own subscription to your platform

It runs on **WordPress Multisite** (subdomain install). Every church is a separate site, so their data never mixes.

## What it does

| | |
|---|---|
| **Landing & pricing page** | Your platform's home page: features, how it works, plans with an International (USD) / Local (e.g. NGN) and Monthly / Yearly switch, FAQ. |
| **Self-service sign-up** (`/signup/`) | The church enters its name and picks a web address (checked live; reserved and taken names refused), website colours, account and plan. Its site is created in about two seconds, with the theme, Church Suite, starter pages, menus and Church Admin. The owner lands signed in on their new site. A free trial starts with no card needed. |
| **Subscription screen** (Church Admin → Subscription) | Plan, trial or renewal date, partner usage, plan picker in USD or local currency (monthly or yearly), checkout, cancel/resume, billing history. A banner on every Church Admin screen shows the trial countdown or overdue notice. |
| **Billing** | Paystack and Stripe hosted checkout, verified server-side. Signed webhooks (Paystack HMAC-SHA512, Stripe signatures with timestamps) are handled. Amounts and currency are checked against the invoice, and each invoice can only be applied once. Bank transfer is supported, confirmed by you. Test mode adds a simulated checkout. |
| **Lifecycle** (twice a day, or "Run checks now") | Trial ends → payment due (site stays up for the grace period) → suspended. Reminder emails go out a few days before the trial ends or the plan renews. "Cancel at period end" leads to cancelled. Paying brings the site back instantly. |
| **Suspension** | Visitors see a polite "taking a short break" page (HTTP 503) and the church's public REST API is closed. Church staff can still log in but only reach the Subscription screen. You (super admin) can always see everything. |
| **Plan limits** | Maximum partners (new sign-ups are blocked politely and the owner is emailed), custom domain, and white-label (removes "Powered by …" from the footer). |
| **Custom domains** (Church Admin → Custom domain, Growth and up) | The church adds `theirchurch.org` and sees the exact DNS records to add. "Check now" verifies the domain and makes it the site's main address. The old subdomain and `www.` redirect to it, and logins work on the new domain. Removing the domain or downgrading the plan moves the site back to its subdomain, and pending domains are rechecked automatically. |
| **HTTPS for custom domains** | `GET /wp-json/ks/v1/tls?domain=` is the "ask" endpoint for Caddy on-demand TLS. Certificates are only issued for domains that belong to a church. |
| **Platform dashboard** (`/platform/`, super admins) | Overview: churches, MRR per currency, revenue in the last 30 days, churches needing attention. Churches: search and filter, open any church's admin (one-time login), change plan, extend days, suspend/reactivate, delete. Add a church manually (for offline sales). Payments: mark bank transfers paid. Domains: check DNS, remove. Plans & pricing editor. Settings: brand, domains, trial and grace days, gateways, test mode. |
| **Emails** | Welcome, trial ending, trial ended, payment due, paused, ended, payment receipt, partner limit, domain connected. |
| **REST** (`ks/v1`) | `GET plans`, `GET subdomain?name=`, `GET billing/return`, `POST webhooks/paystack`, `POST webhooks/stripe`, `GET tls?domain=`. |

Churches still receive their own **tithes, offerings and partnership payments** directly, through their own Paystack, Flutterwave, Stripe or PayPal accounts in Church Suite settings. The platform only bills the church for its subscription.

## Going live

1. **Server**: PHP 8.1+ with PHP-FPM, MySQL 8 or MariaDB 10.6+, and Caddy (recommended) or nginx. Use [`deploy/Caddyfile`](deploy/Caddyfile) or [`deploy/nginx.conf`](deploy/nginx.conf).
2. **DNS**: point `yourplatform.com` **and** `*.yourplatform.com` at the server.
3. **WordPress**: install WordPress, then enable Multisite with subdomains (Tools → Network Setup). Add the lines from [`deploy/wp-config-snippet.php`](deploy/wp-config-snippet.php).
4. **Custom domains**: copy [`deploy/sunrise.php`](deploy/sunrise.php) to `wp-content/sunrise.php` (with `define( 'SUNRISE', true );`).
5. **Activate**: upload `themes/kingdom`, `plugins/kingdom-church-suite` and `plugins/kingdom-saas`. In Network Admin: **Network Enable** the Kingdom theme and **Network Activate** both plugins.
6. **Settings**: visit `https://yourplatform.com/platform/?tab=settings`. Set your platform name, brand colour, support email, CNAME target or server IP, Paystack and Stripe keys, and bank details. Turn **test mode off**.
7. **Webhooks**: add them in Paystack (Settings → API Keys & Webhooks) and in Stripe (event `checkout.session.completed`). The URLs are shown on the Settings tab.
8. **Cron**: add a real cron job (see the snippet) so trials, reminders and suspensions run on time.
9. Edit **Plans & pricing**, then sign up a test church from your landing page.

## Tests

See [`tests/README.md`](tests/README.md). The end-to-end suite covers sign-up, billing, custom domains, limits, the suspension lifecycle, platform actions, gateways and webhooks, and the security checks.
