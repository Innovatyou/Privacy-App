const { chromium } = require('playwright');
const { execSync } = require('child_process');
const S = process.argv[2], out = S + '/saas', B = 'http://churchhub.localhost';
const sql = (q) => execSync(`mysql saas -N -e "${q}"`).toString().trim();
const wp = (code) => execSync(`php ${S}/dl/wp-cli.phar --allow-root --path=${S}/wpms eval '${code}'`).toString().trim();
(async () => {
  const b = await chromium.launch({ args: ['--no-proxy-server'] }); const errs = []; const log = (...a) => console.log(...a);
  const sa = await (await b.newContext({ viewport: { width: 1366, height: 860 } })).newPage(); sa.on('dialog', d => d.accept());
  await sa.goto(B + '/platform/'); await sa.fill('#user_login', 'super'); await sa.fill('#user_pass', 'superpass1'); await Promise.all([sa.waitForURL(/platform/), sa.click('#wp-submit')]);
  const v = await (await b.newContext()).newPage();
  const grace = +sql("SELECT blog_id FROM wp_ks_subscriptions WHERE subdomain='grace.churchhub.localhost'");
  const setPlan = async (plan) => { await sa.goto(B + '/platform/?tab=churches&s=Grace'); await sa.selectOption('.ks-church select[name=plan]', plan); await Promise.all([sa.waitForNavigation(), sa.click('.ks-church button:has-text("Set plan")')]); return sa.url(); };
  const credit = async () => { await v.goto('http://gracechapel.test/'); return v.$eval('footer', f => /Powered by/.test(f.innerText)); };
  log('kept filter after action:', (await setPlan('growth')).includes('s=Grace'));
  log('credit on Growth:', await credit());
  await setPlan('ministry'); log('credit on Ministry (white-label):', await credit());
  await setPlan('starter'); await v.goto('http://gracechapel.test/about-us/');
  log('downgrade to Starter: gracechapel.test →', v.url(), '| domain row:', sql(`SELECT CONCAT(status,'/',is_primary,' ',last_error) FROM wp_ks_domains WHERE blog_id=${grace}`));
  await setPlan('growth'); await sa.goto(B + '/platform/?tab=domains'); await Promise.all([sa.waitForNavigation(), sa.click('button:has-text("Check DNS")')]);
  log('back on Growth + Check DNS:', (await sa.textContent('.ks-alert')).trim(), '| home:', wp(`echo get_home_url(${grace});`));
  const m = await (await b.newContext({ viewport: { width: 390, height: 844 }, storageState: await sa.context().storageState() })).newPage();
  for (const t of ['overview', 'churches', 'invoices', 'domains', 'plans', 'settings', 'new']) { await m.goto(B + '/platform/?tab=' + t); const o = await m.evaluate(() => document.documentElement.scrollWidth - innerWidth); if (o > 0) errs.push('overflow ' + t + ' ' + o); }
  await m.goto(B + '/platform/?tab=churches'); await m.screenshot({ path: out + '/m-platform.png' });
  await sa.goto(B + '/platform/?tab=churches'); await sa.screenshot({ path: out + '/platform-churches.png', fullPage: true });
  log('issues:', errs.length ? errs : 'none'); await b.close();
})();
