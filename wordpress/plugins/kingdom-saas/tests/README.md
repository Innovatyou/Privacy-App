# Kingdom Cloud — end-to-end tests

These tests drive a real WordPress Multisite network (MariaDB/MySQL, subdomain install)
in Chromium with Playwright. They were used to confirm every SaaS flow before release.

## Set up a test network

```bash
# 1. WordPress multisite on http://churchhub.localhost (port 80; *.localhost resolves to 127.0.0.1)
wp core multisite-install --url=http://churchhub.localhost --subdomains --title=ChurchHub \
   --admin_user=super --admin_password=superpass1 --admin_email=super@churchhub.localhost
wp plugin activate kingdom-church-suite kingdom-saas --network
wp theme enable kingdom --network
wp rewrite structure '/%postname%/'
cp wp-content/plugins/kingdom-saas/deploy/sunrise.php wp-content/sunrise.php   # + define('SUNRISE', true)
cp wp-content/plugins/kingdom-saas/tests/mu-plugins/*.php wp-content/mu-plugins/ # mail log + gateway mocks (tests only!)
wp eval 'ks_set(array("test_mode"=>"1","https"=>"0","bank_details"=>"Test Bank 0123"));'
echo "127.0.0.1 gracechapel.test www.gracechapel.test" >> /etc/hosts               # fake custom domain

# 2. Serve it (several workers: sign-up makes a request back to the new site)
PHP_CLI_SERVER_WORKERS=8 php -S 0.0.0.0:80 wp-content/plugins/kingdom-saas/tests/router.php
```

## Run

`SCRATCH` is a folder holding `wp-cli.phar` in `dl/`, the WordPress install in `wpms/`, and an output folder `saas/` for screenshots.

```bash
node e2e-signup-billing.js $SCRATCH/saas   # landing, pricing toggles, sign-up, auto-login, trial, test payment
node e2e-custom-domain.js "<login link>" $SCRATCH/saas   # add/verify/remove/re-add domain, redirects
node e2e-platform.js $SCRATCH     # plan limits, trial → payment due → suspended → paid, platform actions
node e2e-plan-changes.js $SCRATCH # white-label, downgrade disconnects domain, upgrade reconnects
wp eval-file gateways.php         # Paystack/Stripe checkout, webhooks (signatures), tampering, cancel, reminders
```

`mu-plugins/gateway-mock.php` fakes Paystack and Stripe API replies so payments can be tested
offline. **Never install it on a live site.**
