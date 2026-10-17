const { chromium } = require('playwright');
const { execSync } = require('child_process');
const S = process.argv[2], out = S + '/saas', B = 'http://churchhub.localhost';
const wp = (code) => execSync(`php ${S}/dl/wp-cli.phar --allow-root --path=${S}/wpms eval '${code.replace(/'/g, "'\\''")}'`).toString().trim();
const sql = (q) => execSync(`mysql saas -N -e "${q}"`).toString().trim();
(async () => {
  const b = await chromium.launch({ args: ['--no-proxy-server'] }); const errs = []; const log = (...a) => console.log(...a);
  const watch = (p, tag) => { p.on('pageerror', e => errs.push(tag + ' JS ' + e.message)); p.on('response', r => { if (r.status() >= 500 && r.status() !== 503) errs.push(tag + ' ' + r.status() + ' ' + r.url()); }); p.on('dialog', d => d.accept()); return p; };
  const fresh = async (tag, w = 1366) => watch(await (await b.newContext({ viewport: { width: w, height: 860 } })).newPage(), tag);
  const status = (blog) => sql(`SELECT status FROM wp_ks_subscriptions WHERE blog_id=${blog}`);

  // 1. Second church on Starter.
  let v = await fresh('signup');
  await v.goto(B + '/signup/?plan=starter');
  await v.fill('[data-ks-church]', 'Faith Bible Church'); await v.fill('[data-ks-subdomain]', 'faith'); await v.waitForTimeout(500);
  await v.fill('input[name=name]', 'Deaconess Ngozi'); await v.fill('input[name=email]', 'ngozi@faithbible.test'); await v.fill('input[name=password]', 'faithpass1'); await v.check('input[name=terms]');
  await Promise.all([v.waitForURL(/faith\.churchhub\.localhost\/church-admin/, { timeout: 60000 }), v.click('[data-ks-signup] button')]);
  const owner = v; const faith = +sql("SELECT blog_id FROM wp_ks_subscriptions WHERE subdomain='faith.churchhub.localhost'");
  log('1. Faith created, blog', faith, 'status', status(faith));

  // 2. Super admin: platform dashboard, set Starter limit to 2 partners.
  const sa = await fresh('super');
  await sa.goto(B + '/platform/'); await sa.fill('#user_login', 'super'); await sa.fill('#user_pass', 'superpass1'); await Promise.all([sa.waitForURL(/platform/), sa.click('#wp-submit')]);
  log('2. platform KPIs:', (await sa.$$eval('.ks-kpi', e => e.map(x => x.innerText.replace(/\s+/g, ' ')))).join(' || '));
  await sa.screenshot({ path: out + '/platform-overview.png', fullPage: true });
  await sa.goto(B + '/platform/?tab=plans'); await sa.fill('input[name="plans[starter][max_partners]"]', '2'); await Promise.all([sa.waitForNavigation(), sa.click('.ks-plan-edit > button')]);
  log('   plans saved:', (await sa.textContent('.ks-alert')).trim(), '| starter max =', wp('echo KS_Plans::get("starter")["max_partners"];'));

  // 3. Partner limit.
  log('3. create partners:', wp(`switch_to_blog(${faith}); foreach (array(1,2,3) as $i) { $r = KCS_Partners::create(array("email"=>"p$i@faithbible.test","first_name"=>"P$i","plan_id"=>1,"region"=>"international"), false); echo is_wp_error($r) ? "blocked(".$r->get_error_message().") " : "ok "; }`));
  v = await fresh('visitor');
  await v.goto('http://faith.churchhub.localhost/become-a-partner/');
  await v.fill('input[name=first_name]', 'Visitor'); await v.fill('input[name=email]', 'visitor@x.test'); await v.fill('input[name=password]', 'visitorpass1'); await v.fill('input[name=password2]', 'visitorpass1');
  await Promise.all([v.waitForNavigation(), v.click('.kcs-register button[type=submit]')]);
  log('   public sign-up at limit:', (await v.textContent('.kcs-alert, .kcs-messages').catch(() => '?')).trim());
  await owner.goto('http://faith.churchhub.localhost/church-admin/?view=domain'); log('   starter domain screen:', (await owner.textContent('.ks-upsell h3')).trim());
  await owner.goto('http://faith.churchhub.localhost/church-admin/?view=subscription'); log('   usage KPI:', (await owner.$$eval('.kca-kpi', e => e[2].innerText.replace(/\s+/g, ' '))));

  // 4. Trial ends → payment due → suspended.
  sql(`UPDATE wp_ks_subscriptions SET trial_ends_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE blog_id=${faith}`);
  await sa.goto(B + '/platform/?tab=overview'); await Promise.all([sa.waitForNavigation(), sa.click('button:has-text("Run checks now")')]);
  log('4. run checks:', (await sa.textContent('.ks-alert')).trim(), '| faith =', status(faith));
  await owner.goto('http://faith.churchhub.localhost/church-admin/'); log('   past-due banner:', (await owner.textContent('.ks-banner')).trim());
  log('   visitor during grace:', (await v.goto('http://faith.churchhub.localhost/')).status());
  sql(`UPDATE wp_ks_subscriptions SET trial_ends_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 8 DAY) WHERE blog_id=${faith}`);
  await Promise.all([sa.waitForNavigation(), sa.click('button:has-text("Run checks now")')]);
  log('   run checks:', (await sa.textContent('.ks-alert')).trim(), '| faith =', status(faith));
  const r503 = await v.goto('http://faith.churchhub.localhost/sermons/'); log('   visitor when suspended:', r503.status(), '|', (await v.textContent('h1')).trim());
  await v.screenshot({ path: out + '/suspended.png' });
  log('   visitor REST:', (await v.goto('http://faith.churchhub.localhost/wp-json/kcs/v1/plans')).status());
  await owner.goto('http://faith.churchhub.localhost/church-admin/?view=partners'); log('   owner redirected to:', owner.url().replace(/^https?:\/\/[^/]+/, ''));
  await owner.goto('http://faith.churchhub.localhost/wp-admin/'); log('   owner wp-admin → ', owner.url().replace(/^https?:\/\/[^/]+/, ''));
  // Pay by bank transfer, platform confirms.
  await owner.check('.ks-plan input[value=starter]'); await owner.check('input[name=gateway][value=bank]');
  await Promise.all([owner.waitForNavigation(), owner.click('.ks-checkout button.kcs-btn--lg')]);
  log('   bank instructions:', (await owner.textContent('.ks-bank p')).trim().slice(0, 90));
  await owner.screenshot({ path: out + '/bank-invoice.png' });
  await sa.goto(B + '/platform/?tab=invoices'); await Promise.all([sa.waitForNavigation(), sa.click('button:has-text("Mark paid")')]);
  log('   mark paid:', (await sa.textContent('.ks-alert')).trim(), '| faith =', status(faith), '| visitor now', (await v.goto('http://faith.churchhub.localhost/')).status());

  // 5. Platform actions on Grace.
  const grace = +sql("SELECT blog_id FROM wp_ks_subscriptions WHERE subdomain='grace.churchhub.localhost'");
  await sa.goto(B + '/platform/?tab=churches&s=Grace');
  await Promise.all([sa.waitForNavigation(), sa.click('.ks-church button:has-text("Suspend")')]);
  log('5. suspend grace:', status(grace), '| visitor', (await v.goto('http://gracechapel.test/')).status());
  await Promise.all([sa.waitForNavigation(), sa.click('.ks-church button:has-text("Reactivate")')]);
  log('   reactivate:', status(grace), '| visitor', (await v.goto('http://gracechapel.test/')).status());
  const credit = async () => ((await v.goto('http://gracechapel.test/')) && (await v.$eval('footer', f => /Powered by/.test(f.innerText))));
  log('   credit on Growth:', await credit());
  await sa.selectOption('.ks-church select[name=plan]', 'ministry'); await Promise.all([sa.waitForNavigation(), sa.click('.ks-church button:has-text("Set plan")')]);
  log('   credit on Ministry (white-label):', await credit());
  await sa.selectOption('.ks-church select[name=plan]', 'starter'); await Promise.all([sa.waitForNavigation(), sa.click('.ks-church button:has-text("Set plan")')]);
  const r = await v.goto('http://gracechapel.test/'); log('   downgrade to Starter → gracechapel.test lands on', v.url(), '| domain row:', sql(`SELECT CONCAT(status,'/',is_primary) FROM wp_ks_domains WHERE blog_id=${grace}`));
  await sa.selectOption('.ks-church select[name=plan]', 'growth'); await Promise.all([sa.waitForNavigation(), sa.click('.ks-church button:has-text("Set plan")')]);
  await sa.goto(B + '/platform/?tab=domains'); await Promise.all([sa.waitForNavigation(), sa.click('button:has-text("Check DNS")')]);
  log('   back on Growth + Check DNS:', (await sa.textContent('.ks-alert')).trim(), '| site URL:', wp(`echo get_home_url(${grace});`));
  await sa.goto(B + '/platform/?tab=churches&s=Faith');
  await Promise.all([sa.waitForNavigation(), sa.click('.ks-church button:has-text("Extend days")')]);
  log('   extend faith:', (await sa.textContent('.ks-alert')).trim(), sql(`SELECT current_period_end FROM wp_ks_subscriptions WHERE blog_id=${faith}`));
  await sa.screenshot({ path: out + '/platform-churches.png', fullPage: true });

  // 6. Add a church manually.
  await sa.goto(B + '/platform/?tab=new');
  await sa.fill('input[name=church_name]', 'Living Waters Assembly'); await sa.fill('input[name=subdomain]', 'livingwaters'); await sa.fill('input[name=email]', 'office@livingwaters.test');
  await sa.selectOption('select[name=status]', 'active'); await Promise.all([sa.waitForNavigation(), sa.click('.ks-narrow-form button')]);
  log('6. manual create:', (await sa.textContent('.ks-alert')).trim(), '| status', sql("SELECT CONCAT(status,' until ',current_period_end) FROM wp_ks_subscriptions WHERE subdomain='livingwaters.churchhub.localhost'"));
  log('   new site home:', (await v.goto('http://livingwaters.churchhub.localhost/')).status(), await v.title());
  await sa.goto(B + '/platform/?tab=new'); await sa.fill('input[name=church_name]', 'Dup'); await sa.fill('input[name=subdomain]', 'faith'); await sa.fill('input[name=email]', 'x@y.test'); await Promise.all([sa.waitForNavigation(), sa.click('.ks-narrow-form button')]);
  log('   duplicate subdomain:', (await sa.textContent('.ks-alert')).trim());

  // 7. Isolation: partner tables are per church.
  log('7. partners — faith:', wp(`echo KS_Tenants::partner_count(${faith});`), 'grace:', wp(`echo KS_Tenants::partner_count(${grace});`));
  // 8. Mobile views.
  const m = await fresh('mobile', 390); await m.goto(B + '/'); await m.screenshot({ path: out + '/m-landing.png' });
  const ov = async (pg, n) => { const o = await pg.evaluate(() => document.documentElement.scrollWidth - innerWidth); if (o > 0) errs.push('overflow ' + n + ' ' + o); };
  await ov(m, 'landing'); await m.goto(B + '/signup/'); await ov(m, 'signup'); await m.screenshot({ path: out + '/m-signup.png', fullPage: true });
  const sm = await (await b.newContext({ viewport: { width: 390, height: 844 }, storageState: await sa.context().storageState() })).newPage();
  for (const t of ['overview', 'churches', 'invoices', 'domains', 'plans', 'settings', 'new']) { await sm.goto(B + '/platform/?tab=' + t); await ov(sm, 'platform-' + t); }
  await sm.goto(B + '/platform/?tab=churches'); await sm.screenshot({ path: out + '/m-platform.png' });
  log('issues:', errs.length ? errs : 'none'); await b.close();
})();
