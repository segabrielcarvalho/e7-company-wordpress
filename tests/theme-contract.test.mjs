import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const themeRoot = new URL('../', import.meta.url);
const readThemeFile = (path) => readFile(new URL(path, themeRoot), 'utf8');

test('declares an independent E7 Company WordPress theme', async () => {
  const stylesheet = await readThemeFile('style.css');

  assert.match(stylesheet, /Theme Name:\s*E7 Company/);
  assert.match(stylesheet, /Text Domain:\s*e7-company/);
  assert.match(stylesheet, /Theme URI:\s*https:\/\/e7company\.com/);
});

test('maps the complete Tailwind blue scale to the brand palette', () => {
  const config = require('../tailwind.config.js');

  assert.equal(config.theme.extend.colors.brand['50'], '#eff6ff');
  assert.equal(config.theme.extend.colors.brand['500'], '#3b82f6');
  assert.equal(config.theme.extend.colors.brand['950'], '#172554');
});

test('home template contains the complete Orizon-inspired page flow', async () => {
  const template = await readThemeFile('front-page.php');
  const sections = [
    'services',
    'industries',
    'why-e7',
    'case-studies',
    'technology',
    'approach',
    'testimonials',
    'contact',
  ];

  for (const section of sections) {
    assert.match(template, new RegExp(`id=["']${section}["']`));
  }
});

test('theme loads local compiled styles and scripts', async () => {
  const functions = await readThemeFile('functions.php');

  assert.match(functions, /assets\/css\/app\.css/);
  assert.match(functions, /assets\/js\/app\.js/);
  assert.doesNotMatch(functions, /cdn\.tailwindcss\.com/);
});

test('header keeps a dark backdrop before scrolling', async () => {
  const header = await readThemeFile('header.php');

  assert.match(header, /bg-neutral-950\/75/);
  assert.match(header, /backdrop-blur-xl/);
});

test('home includes an accessible partner strip and search description', async () => {
  const template = await readThemeFile('front-page.php');
  const footer = await readThemeFile('footer.php');
  const functions = await readThemeFile('functions.php');

  assert.match(template, /logo-cloud-track/);
  assert.match(template, /text-neutral-500/);
  assert.doesNotMatch(template + footer, /text-white\/40/);
  assert.match(functions, /name="description"/);
  assert.match(functions, /add_action\('wp_head'/);
});

test('shows the real client companies in testimonial cards', async () => {
  const template = await readThemeFile('front-page.php');

  for (const company of ['VoteMe', 'FIA', 'Assessoria Alpha', 'Asaas']) {
    assert.match(template, new RegExp(`'company'\\s*=>\\s*'${company}'`));
  }

  assert.doesNotMatch(template, /CEO, Layers|Founder, Circle/);
});

test('desktop hero centers its content without the dashboard visual', async () => {
  const template = await readThemeFile('front-page.php');

  assert.match(template, /lg:min-h-\[min\(820px,100svh\)\]/);
  assert.match(template, /lg:items-center/);
  assert.doesNotMatch(template, /max-w-\[25rem\]/);
  assert.doesNotMatch(template, /E7 \/ Digital systems/);
  assert.doesNotMatch(template, /Think<br>Build<br>Scale/);
  assert.doesNotMatch(template, /lg:min-h-\[900px\]/);
});

test('replaces the static capability strip with a moving logo cloud', async () => {
  const template = await readThemeFile('front-page.php');
  const stylesheet = await readThemeFile('src/input.css');

  assert.match(template, /logo-cloud-track/);
  assert.match(template, /aria-label="Technology partners"/);
  assert.match(template, /React/);
  assert.match(template, /Cloudflare/);
  assert.doesNotMatch(template, /<span>Technology<\/span>/);
  assert.match(stylesheet, /@keyframes logo-cloud/);
});

test('mobile hero uses compact vertical spacing', async () => {
  const template = await readThemeFile('front-page.php');

  assert.match(template, /min-h-\[650px\]/);
  assert.match(template, /items-start/);
  assert.match(template, /pb-8/);
  assert.match(template, /pt-24/);
  assert.match(template, /mobile-hero-details/);
  assert.doesNotMatch(template, /min-h-\[760px\]/);
});

test('technology stack follows the compact Orizon layout', async () => {
  const template = await readThemeFile('front-page.php');
  const script = await readThemeFile('assets/js/app.js');

  assert.match(template, /technology-stack-nav/);
  assert.match(template, /Web Platform/);
  assert.match(template, /Cloud &(?:amp;)? DevOps/);
  assert.match(template, /Database/);
  assert.match(template, /Mobile Apps/);
  assert.match(template, /technology-card/);
  assert.match(template, /data-technology-tab/);
  assert.match(template, /data-technology-panel/);
  assert.match(template, /lg:grid-cols-\[17rem_1fr\]/);
  assert.match(template, /border-neutral-200 bg-neutral-50/);
  assert.match(script, /data-technology-tab/);
  assert.doesNotMatch(template, /technology-card[^"\n]*bg-neutral-950/);
  assert.doesNotMatch(template, /brightness-0 invert/);
  assert.doesNotMatch(template, /The right tools for the right challenge/);
});

test('approach section follows the Orizon navigation and illustration layout', async () => {
  const template = await readThemeFile('front-page.php');
  const stylesheet = await readThemeFile('src/input.css');
  const script = await readThemeFile('assets/js/app.js');

  assert.match(template, /approach-tabs/);
  assert.match(template, /approach-illustration/);
  assert.match(template, /data-approach-tab/);
  assert.match(template, /data-approach-panel/);
  assert.match(template, /UI\/UX Design/);
  assert.match(stylesheet, /\.approach-tab\.is-active/);
  assert.match(script, /data-approach-tab/);
  assert.doesNotMatch(template, /Clarity from idea to impact/);
});

test('uses the supplied E7 brand and exposes favicon assets', async () => {
  const header = await readThemeFile('header.php');
  const footer = await readThemeFile('footer.php');
  const functions = await readThemeFile('functions.php');

  assert.match(header, /brand\/e7-company-logo-transparent-256\.webp/);
  assert.match(footer, /brand\/e7-company-logo-transparent-256\.webp/);
  assert.match(functions, /brand\/favicon\.ico/);
  assert.match(functions, /brand\/e7-icon-512\.png/);
  assert.match(functions, /brand\/apple-touch-icon\.png/);
  assert.match(functions, /add_action\('wp_head', 'e7_company_favicons'/);
});

test('publishes a branded 1200x630 social sharing preview', async () => {
  const functions = await readThemeFile('functions.php');
  await readThemeFile('assets/brand/e7-company-social-preview.jpg');

  assert.match(functions, /property="og:type" content="website"/);
  assert.match(functions, /property="og:title"/);
  assert.match(functions, /property="og:description"/);
  assert.match(functions, /property="og:url"/);
  assert.match(functions, /property="og:image"/);
  assert.match(functions, /property="og:image:width" content="1200"/);
  assert.match(functions, /property="og:image:height" content="630"/);
  assert.match(functions, /name="twitter:card" content="summary_large_image"/);
  assert.match(functions, /brand\/e7-company-social-preview\.jpg/);
});

test('uses a process metric instead of an unverified years claim', async () => {
  const template = await readThemeFile('front-page.php');

  assert.match(template, /\['04', 'Delivery stages'\]/);
  assert.doesNotMatch(template, /\['15\+', 'Years experience'\]/);
});

test('offers English by default with a PT-BR language switcher', async () => {
  const header = await readThemeFile('header.php');
  const functions = await readThemeFile('functions.php');

  assert.match(header, /e7_company_language_switcher\(\)/);
  assert.match(functions, /function e7_company_language_switcher/);
  assert.match(functions, /'default_language'\s*=>\s*'en'/);
  assert.match(functions, /'incl_langs'\s*=>\s*\['en', 'pt'\]/);
  assert.match(functions, /gtranslate_wrapper/);
  assert.match(functions, /do_shortcode\('\[gtranslate\]'\)/);
});

test('replaces the team gallery with a dark partnership section', async () => {
  const template = await readThemeFile('front-page.php');

  assert.match(template, /id="partnership"/);
  assert.match(template, /A technical partner for your next big move\./);
  assert.match(template, /bg-neutral-950/);
  assert.doesNotMatch(template, /People behind the work/);
  assert.doesNotMatch(template, /Our team/);
  assert.doesNotMatch(template, /\$team\s*=/);
});

test('hero uses a dark canvas dot distortion background', async () => {
  const template = await readThemeFile('front-page.php');
  const stylesheet = await readThemeFile('src/input.css');
  const script = await readThemeFile('assets/js/app.js');

  assert.match(template, /data-hero-dot-canvas/);
  assert.match(template, /hero-dot-distortion/);
  assert.match(stylesheet, /\.hero-dot-distortion/);
  assert.match(script, /data-hero-dot-canvas/);
  assert.match(script, /ResizeObserver/);
  assert.match(script, /requestAnimationFrame/);
  assert.doesNotMatch(template, /data-hero-light/);
  assert.doesNotMatch(template, /hero-orb/);
  assert.doesNotMatch(stylesheet, /\.hero-orb/);
  assert.doesNotMatch(template, /shadow-\[0_0_30px_8px_rgba\(96,165,250/);
});

test('hero distortion dots use E7 blue and react to pointer velocity', async () => {
  const stylesheet = await readThemeFile('src/input.css');
  const script = await readThemeFile('assets/js/app.js');

  assert.match(stylesheet, /rgba\(23, 37, 84/);
  assert.match(script, /#3b82f6/);
  assert.match(script, /pointermove/);
  assert.match(script, /mouseVelocity/);
  assert.match(script, /prefers-reduced-motion/);
});

test('serves self-hosted variable fonts without a render-blocking Google Fonts request', async () => {
  const functions = await readThemeFile('functions.php');
  const stylesheet = await readThemeFile('src/input.css');

  assert.doesNotMatch(functions, /fonts\.googleapis\.com/);
  assert.match(stylesheet, /font-family:\s*['"]Inter['"]/);
  assert.match(stylesheet, /fonts\/inter-latin\.woff2/);
  assert.match(stylesheet, /font-family:\s*['"]Inter Tight['"]/);
  assert.match(stylesheet, /fonts\/inter-tight-latin\.woff2/);
  assert.match(stylesheet, /font-display:\s*swap/);
});

test('uses a right-sized WebP brand logo in the header and footer', async () => {
  const header = await readThemeFile('header.php');
  const footer = await readThemeFile('footer.php');

  await readThemeFile('assets/brand/e7-company-logo-transparent-256.webp');
  assert.match(header, /e7-company-logo-transparent-256\.webp/);
  assert.match(footer, /e7-company-logo-transparent-256\.webp/);
  assert.match(header + footer, /width="256" height="119"/);
  assert.doesNotMatch(header + footer, /e7-company-logo-transparent\.png/);
});

test('keeps approach tabs and panels accessible to people and agents', async () => {
  const template = await readThemeFile('front-page.php');
  const stylesheet = await readThemeFile('src/input.css');

  assert.doesNotMatch(template, /<article\s+id="approach-panel-[^"]+"[\s\S]*?role="tabpanel"/);
  assert.match(template, /<div\s+id="approach-panel-[^"]+"[\s\S]*?role="tabpanel"/);
  assert.match(stylesheet, /\.approach-tab\s*\{[\s\S]*?rgba\(255, 255, 255, 0\.62\)/);
  assert.match(stylesheet, /\.approach-tab span\s*\{[\s\S]*?#60a5fa/);
});

test('all project contact entry points open the owner WhatsApp', async () => {
  const template = await readThemeFile('front-page.php');
  const header = await readThemeFile('header.php');
  const footer = await readThemeFile('footer.php');
  const functions = await readThemeFile('functions.php');

  assert.match(functions, /function e7_company_whatsapp_url/);
  assert.match(functions, /5562995506531/);
  assert.match(functions, /https:\/\/wa\.me\//);
  assert.doesNotMatch(template + header + footer, /href=["']#contact["']/);
  assert.match(template + header + footer, /e7_company_whatsapp_url\(/);
});

test('contact form validates the fields and redirects with a prefilled WhatsApp message', async () => {
  const template = await readThemeFile('front-page.php');
  const functions = await readThemeFile('functions.php');

  assert.match(template, /admin-post\.php/);
  assert.match(template, /wp_nonce_field\('e7_company_contact'/);
  assert.match(template, /name="name"[^>]+autocomplete="name"/);
  assert.match(template, /name="email"[^>]+autocomplete="email"/);
  assert.match(functions, /admin_post_nopriv_e7_company_contact/);
  assert.match(functions, /check_admin_referer\('e7_company_contact'/);
  assert.match(functions, /Project details:/);
  assert.match(functions, /website\. Name:/);
  assert.match(functions, /e7_company_whatsapp_url\(\$body\)/);
  assert.doesNotMatch(functions, /wp_mail\(/);
});

test('removes network render blockers and applies long-lived caching to theme assets', async () => {
  const functions = await readThemeFile('functions.php');
  const cacheRules = await readThemeFile('.htaccess');

  assert.match(functions, /wp_add_inline_style\('e7-company'/);
  assert.match(functions, /'strategy'\s*=>\s*'defer'/);
  assert.match(cacheRules, /max-age=31536000/);
  assert.match(cacheRules, /ExpiresActive On/);
  assert.match(cacheRules, /ExpiresByType text\/javascript/);
});

test('applies long-lived caching to GTranslate assets during the Dokploy sync', async () => {
  const compose = await readThemeFile('docker-compose.dokploy.yml');

  assert.match(compose, /deploy\/gtranslate-cache\.htaccess/);
  assert.match(compose, /wp-content\/plugins\/gtranslate\/\.htaccess/);

  const cacheRules = await readThemeFile('deploy/gtranslate-cache.htaccess');
  assert.match(cacheRules, /max-age=31536000/);
  assert.match(cacheRules, /ExpiresByType application\/javascript/);
  assert.match(cacheRules, /ExpiresByType image\/svg\+xml/);
});

test('provisions the Ross Motorcycles multisite clone idempotently', async () => {
  const compose = await readThemeFile('docker-compose.dokploy.yml');

  assert.equal((compose.match(/WORDPRESS_CONFIG_EXTRA/g) ?? []).length, 2);
  assert.match(compose, /github\.com\/segabrielcarvalho\/ross-motorcycles-cork\.git/);
  assert.match(compose, /ross-motorcycles\.e7company\.com/);
  assert.match(compose, /wp site create/);
  assert.match(compose, /wp theme enable ross-motorcycles-cork --network/);
  assert.match(compose, /wp theme activate ross-motorcycles-cork/);
  assert.match(compose, /wp ross catalogue import --prune/);
  assert.match(compose, /condition: service_completed_successfully/);
});

test('serves right-sized industry images with explicit dimensions', async () => {
  const template = await readThemeFile('front-page.php');

  for (const image of ['orizonjpg-01-480.webp', 'orizonjpg-02-480.webp', 'orizonjpg-03-480.webp']) {
    await readThemeFile(`assets/images/${image}`);
    assert.match(template, new RegExp(image.replace('.', '\\.') ));
  }

  assert.match(template, /width="480" height="232" loading="lazy"/);
});

test('serves right-sized collaboration, case study and testimonial images', async () => {
  const template = await readThemeFile('front-page.php');

  await readThemeFile('assets/images/orizonjpg-010-400.webp');
  await readThemeFile('assets/images/orizonjpg-010-320.webp');
  assert.match(template, /orizonjpg-010-400\.webp/);
  assert.match(template, /orizonjpg-010-320\.webp/);
  assert.match(template, /srcset=/);
  assert.match(template, /width="400" height="570"/);

  for (const image of ['Porto-06', 'Porto-07', 'Porto-08', 'Porto-09', 'Porto-010', 'Porto-011']) {
    await readThemeFile(`assets/images/${image}-400.webp`);
    assert.match(template, new RegExp(`${image}-400\\.webp`));
  }
  assert.match(template, /width="400" height="266" loading="lazy"/);

  for (const image of ['testimonial-img-01', 'testimonial-img-02', 'testimonial-img-03', 'testimonial-img-04']) {
    await readThemeFile(`assets/images/${image}-96.webp`);
    assert.match(template, new RegExp(`${image}-96\\.webp`));
  }
  assert.match(template, /width="96" height="96" loading="lazy"/);
});

test('renders a static hero canvas on touch devices and limits desktop animation work', async () => {
  const script = await readThemeFile('assets/js/app.js');

  assert.match(script, /hover:\s*hover/);
  assert.match(script, /frameInterval\s*=\s*1000\s*\/\s*30/);
  assert.match(script, /interactiveDots/);
});

test('avoids redundant header layout invalidation while the page remains at the top', async () => {
  const script = await readThemeFile('assets/js/app.js');

  assert.match(script, /let headerScrolled = siteHeader\?\.classList\.contains\('is-scrolled'\)/);
  assert.match(script, /if \(shouldBeScrolled === headerScrolled\) return;/);
  assert.match(script, /requestAnimationFrame\(updateHeader\)/);
});

test('delegates HTTPS redirects to the reverse proxy', async () => {
  const functions = await readThemeFile('functions.php');

  assert.doesNotMatch(functions, /function e7_company_force_https/);
  assert.doesNotMatch(functions, /add_action\('template_redirect', 'e7_company_force_https'/);
});
