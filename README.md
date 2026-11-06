# My Store Locator – Joomla module (`mod_mystorelocator`)

Site module for **Joomla 5** (also tested on **Joomla 4.4**) that shows a [My Store Locator](https://my-store-locator.com/?utm_source=joomla&utm_medium=integration&utm_campaign=joomla-plugin)
map (store finder / location map) in any module position. Thin connector: paste the map token (or embed code / map link) → the map appears.
Locations, look and template are managed in the app at `app.my-store-locator.com`; nothing is copied into Joomla.

Licence: GPL-2.0-or-later (`mod_mystorelocator/LICENSE.txt`). Operator: Internet am Dornbusch, Sajjad Hanifa (SH Software Solution).

## Folder layout

```
joomla-my-store-locator/
├── mod_mystorelocator/                 module source = contents of the install zip
│   ├── mod_mystorelocator.xml          manifest (namespace Shss\Module\MyStoreLocator, params, update server)
│   ├── services/provider.php           DI service provider (dispatcher + helper factory)
│   ├── src/Dispatcher/Dispatcher.php   layout data; adds msl-consent.js (consent on) or widget.js (consent off) once per page
│   ├── src/Helper/MyStoreLocatorHelper.php  token extraction, height/language normalisation, attributes
│   ├── src/Field/MsltokenField.php     form field "msltoken": token input + Connect button + sign-up link
│   ├── src/Rule/MsltokenRule.php       validation rule: value must contain a valid token
│   ├── tmpl/default.php                output (one <div>, or an HTML comment when no valid token)
│   ├── language/{en-GB,de-DE}/         mod_mystorelocator.ini + .sys.ini
│   └── media/                          → /media/mod_mystorelocator/
│       ├── connect-return.html         return page of the connect flow
│       ├── js/connect.js               admin field script (token check, connect popup)
│       └── js/msl-consent.js           strict two-click consent (copy of tools/msl-consent.js)
├── bin/build-release.sh                builds dist/mod_mystorelocator-<version>.zip (+ .sha256, updates.xml in sync)
├── updates.xml                         Joomla update server file (published at my-store-locator.com/downloads/joomla-updates.xml)
├── docs/screenshots/                   test screenshots
└── store/                              logo
```

## Install

1. Download `mod_mystorelocator-<version>.zip` (or build it, see below).
2. Joomla administrator → **System → Install → Extensions → Upload Package File** → drop the zip.
   CLI alternative: `php cli/joomla.php extension:install --path=/absolute/path/mod_mystorelocator-1.0.0.zip`
   (the zip must be in a writable folder – Joomla unpacks next to it).
3. **Content → Site Modules → New → My Store Locator**.
4. Paste the map token, choose a **position** (e.g. `main-top` in Cassiopeia), set **Menu Assignment** (all pages or selected pages), publish.

No account yet? [Create a free account](https://app.my-store-locator.com/#/register?utm_source=joomla), add your locations, open the map → Embed and copy the token.

## Configuration

| Field | Default | Output |
|---|---|---|
| Map token | – (required) | `data-token`. Accepts the raw token, the iframe or widget embed code, or the public map link `…/#/m/<token>`. Extracted server-side on save (form filter) and again on render; must match `^[A-Za-z0-9_-]{6,64}$`. Invalid → validation error in the form; on the site only `<!-- My Store Locator: no valid map token configured -->`. |
| Height | `auto` | `data-height`. `auto` (map grows with its content) or `640`, `640px`, `80vh`, `40rem` … – anything else falls back to `auto`. |
| Map language | Automatic | `data-lang`: `auto` follows the current Joomla language (`de*` → `de`, else `en`), or fixed `de` / `en`. |
| Consent mode (click to load) | Yes | Strict two-click (GDPR): server-rendered placeholder, nothing external is loaded before the click – see below. |
| Lazy loading | Yes | `data-lazy="true"` |
| Layout | Default | `data-layout="split"` (list + sticky map, Finder template). Only shown/used with height `auto` (showon). |
| Sticky header offset (px) | 0 | `data-offset="N"` when > 0. Only shown/used with height `auto`. |

Advanced tab: alternative layout and module class (standard Joomla). Module caching is intentionally not offered – the output is one static `<div>`.

Output per module – **consent off**:

```html
<div class="msl-map" id="msl-map-<moduleId>-<n>" data-mystorelocator data-token="…" data-height="auto" data-lang="de" data-lazy="true"></div>
<!-- once per page, via WebAssetManager asset "mod_mystorelocator.widget": -->
<script src="https://app.my-store-locator.com/widget.js" async id="msl-widget-js"></script>
```

`data-mystorelocator` is required by the widget that is live today (it mounts `#mystorelocator,[data-mystorelocator]`); `class="msl-map"` is written for the
upcoming class support. Several modules on one page work (unique ids, one script).

**Consent on** (default, strict): the module renders the placeholder itself and loads only the local script
`/media/mod_mystorelocator/js/msl-consent.js` (asset `mod_mystorelocator.consent`, once per page). The container has no widget markers
(no `msl-map`, no `data-mystorelocator`, no `data-consent`), so a widget loaded for another map on the same page does not mount it:

```html
<div class="msl-map-consent" data-msl-gate id="msl-map-<moduleId>-<n>" data-token="…" data-height="auto" data-lang="en" data-lazy="true">
  <div class="msl-consent" style="height:760px;…"><p …>This map is loaded from my-store-locator.com (own map server in Germany). Your IP address is
  transferred to this server.</p><button type="button" class="msl-consent__load" …>Load map</button></div>
</div>
```

Placeholder height = 760px for `auto`, else the configured height. Text and button come from the module language files
(`MOD_MYSTORELOCATOR_CONSENT_TEXT` / `_BUTTON`) in the **map language** (de → de-DE, en → en-GB, built-in fallback).
On click, every gated map on the page becomes a normal widget container and `widget.js` is injected.

### Connect button

The token field has a **Connect with My Store Locator** button. It opens a popup
`https://app.my-store-locator.com/connect?platform=joomla&return_url=<site>/media/mod_mystorelocator/connect-return.html&state=<random>`.
After login and map selection the app redirects to the return page with `?msl_token=…&msl_map=…&state=…`; the page hands the token to the
module form (same origin only), the form checks `state` (sessionStorage) and fills the field. The user then saves the module.

- Joomla sends `Cross-Origin-Opener-Policy: same-origin` (System – HTTP Headers plugin), which removes `window.opener` once the popup has visited
  the app. The return page therefore tries `window.opener.postMessage` first and falls back to a same-origin `BroadcastChannel('msl-connect')`
  with an ack from the form; without an ack it shows the token for manual copy. (Our copy of `tools/connect-return.html` contains this fallback.)
- The button is disabled on non-HTTPS sites (the contract requires an https `return_url`).
- Pasting the token manually always works as an alternative to the Connect button.
- Map tokens are public (they are in every embed), no secret is exchanged.

## Privacy

- External service: the map is loaded from **app.my-store-locator.com** (operator Internet am Dornbusch, Sajjad Hanifa, Frankfurt am Main; hosted in Germany).
- When the map loads, the visitor's browser connects to that server, which receives the **visitor's IP address** and the usual request data.
  Map tiles and address search go through the same server (visitors do not contact OpenStreetMap directly).
- **Consent mode (default on)** is strict two-click: before the visitor clicks "Load map" / "Karte laden" the page loads **nothing** from
  app.my-store-locator.com (not even `widget.js`; only the local `msl-consent.js` from the Joomla site). Verified with a full network log.
- The module itself sets no cookies, stores nothing about visitors, has no tracking and makes **no server-side requests** (no phoning home).
  The admin form only contacts app.my-store-locator.com when the user clicks the Connect button or the sign-up link.
- Privacy policy: <https://my-store-locator.com/datenschutz/> (DE) · <https://my-store-locator.com/en/privacy/> (EN). A data processing agreement (Art. 28 GDPR) is available in the app.
- Site owners should mention the service in their own privacy policy.

## Development & testing

Build:

```bash
bin/build-release.sh        # → dist/mod_mystorelocator-1.0.0.zip + .sha256, updates.xml version/sha256 updated
```

`dist/` is gitignored. The zip contains only runtime files (manifest, licence, services, src, tmpl, language, media). Uses `zip` if installed,
otherwise a Python fallback; runs `php -l` when PHP is available.

Docker test (what was done on 2026-10-25; ports 58120-58129, bind 127.0.0.1 only, all removed afterwards):

```bash
docker network create msl-test-joomla-net
docker run -d --name msl-test-joomla-db --network msl-test-joomla-net -e MARIADB_ROOT_PASSWORD=<random> -e MARIADB_DATABASE=joomla mariadb:11
docker run -d --name msl-test-joomla-web --network msl-test-joomla-net -p 127.0.0.1:58120:80 \
  -e JOOMLA_DB_HOST=msl-test-joomla-db -e JOOMLA_DB_USER=root -e JOOMLA_DB_PASSWORD=<random> -e JOOMLA_DB_NAME=joomla \
  -e JOOMLA_SITE_NAME="MSL Joomla Test" -e JOOMLA_ADMIN_USER="Test Admin" -e JOOMLA_ADMIN_USERNAME=msladmin \
  -e JOOMLA_ADMIN_PASSWORD=<random, 13+ chars> -e JOOMLA_ADMIN_EMAIL=test@example.com \
  -v "$PWD/dist:/mslpkg:ro" joomla:5-php8.3-apache          # auto-install via the JOOMLA_* variables
docker exec -u www-data msl-test-joomla-web sh -c 'cp /mslpkg/mod_mystorelocator-1.0.0.zip tmp/ && \
  php cli/joomla.php extension:install --path=/var/www/html/tmp/mod_mystorelocator-1.0.0.zip'
# module instance on all pages (prefix from configuration.php, here joom_):
#   INSERT INTO joom_modules (title,note,content,ordering,position,published,module,access,showtitle,params,client_id,language)
#   VALUES ('Find a store','','',1,'main-top',1,'mod_mystorelocator',1,1,'{"token":"gf5jjebq0fae210403","consent":1,"lazy":1}',0,'*');
#   INSERT INTO joom_modules_menu (moduleid,menuid) VALUES (LAST_INSERT_ID(),0);
# for admin automation disable the guided-tour autostart: UPDATE joom_extensions SET enabled=0 WHERE folder='system' AND element IN ('guidedtours','stats');
docker rm -fv msl-test-joomla-web msl-test-joomla-db && docker network rm msl-test-joomla-net
```

Tested (Playwright, headless Chromium) on **Joomla 5.4.10 / PHP 8.3** and **Joomla 4.4.14 / PHP 8.2**, MariaDB 11, template Cassiopeia/Atum, demo token `gf5jjebq0fae210403`:

- CLI install and upgrade-install of the zip; namespace registered in `autoload_psr4.php`.
- Frontend, consent on (network log of all requests, 2026-10-25): **0 requests** to `*.my-store-locator.com` before the click; after the click
  46 (1 × `widget.js`, 45 × map document, assets, API, tiles) and the iframe `https://app.my-store-locator.com/#/m/<token>?lang=en&h=auto` renders the map.
- Consent off: `widget.js` and the map load directly with the page (46 requests, iframe present without click).
- One consent-on map (language de on an English site → German placeholder) and one consent-off map on the same page: before the click only the
  consent-off map loads; after the click both iframes are there, no gate left.
- Consent off + fixed height `500px` + language `de`: iframe appears directly (`…?lang=de`, height 500px); `split`/`offset` are dropped for fixed heights.
- Split layout + offset: two frames (`msl-split-list`, `msl-split-panel`) after the click.
- Map link as token input (`https://app.my-store-locator.com/#/m/<token>?lang=de`) → token extracted; invalid token → only the HTML comment.
- Three modules on one page → unique ids, `widget.js` included once.
- Admin form: pasted widget embed code → "Map token recognised", saved value = extracted token; invalid input → validation error, not saved;
  showon hides Layout/Offset for fixed heights; Connect button disabled on http; simulated connect return (popup to `connect-return.html`)
  fills the field via the BroadcastChannel fallback; wrong `state` is ignored.
- Helper checks (token extraction from iframe/widget code/URL, height normalisation, language mapping, attribute escaping).
- No PHP warnings/notices in the logs.

Screenshots: `docs/screenshots/frontend-consent.png`, `docs/screenshots/frontend-map.png`, `docs/screenshots/admin-module-form.png`.

Known limitations:
- Not tested on Joomla 6 yet.
- Update server: https://my-store-locator.com/downloads/joomla-updates.xml, zip: https://my-store-locator.com/downloads/mod_mystorelocator-<version>.zip (website repo, theme assets/downloads/).

## Release checklist

1. Bump `<version>` in `mod_mystorelocator/mod_mystorelocator.xml`, run `bin/build-release.sh`.
2. Check the zip for secrets (it only contains the module source): `unzip -l dist/*.zip`.
3. Copy the zip and `updates.xml` (as joomla-updates.xml) into the website repo (theme assets/downloads/) and deploy both together.
4. Create a GitHub release with the zip and update the JED listing.

Support: info@my-store-locator.com · Website: https://my-store-locator.com/
