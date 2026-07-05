<div align="center">

# 🛠️ WP Arzo — Maintenance & Administration Suite

**The all‑in‑one administration + site‑enhancement suite for WordPress.**
One dark, modern dashboard to run, secure, optimize, and rescue your site — plus a
break‑glass power‑tools console and a standalone emergency recovery tool for when
WordPress won't even load.

![Version](https://img.shields.io/badge/version-6.152.0-16e791)
![WordPress](https://img.shields.io/badge/WordPress-%E2%89%A5%205.0-21759b)
![PHP](https://img.shields.io/badge/PHP-%E2%89%A5%207.2-777bb4)
![Admin only](https://img.shields.io/badge/access-administrators%20only-ff4d4f)
![Features](https://img.shields.io/badge/features%20%26%20tools-80%2B-16e791)

</div>

---

## ✨ What is WP Arzo?

WP Arzo replaces a drawer full of single‑purpose plugins with **one cohesive suite**. It's
built around three surfaces:

| Surface | What it's for |
|---|---|
| 🎛️ **Feature Manager** | A native, full‑dark dashboard: a searchable, category‑filtered **toggle grid** of ~50 site‑enhancement features, each with schema‑driven settings — enable only what you need. |
| 🧰 **Advanced Tools console** | A standalone dark console of hands‑on power tools: Site Info, Users, Plugins, Themes, Debug, Site Modes, Extra Options, Temporary Logins — plus a full **File Manager** and **Database manager** with [WP Arzo Pro](https://wparzo.com/). |
| 🚑 **Emergency Recovery** | A self‑contained recovery script that works even when WordPress is fully down (WSOD, fatal plugin conflict) — deactivate plugins, switch themes, create an admin, fix URLs, and more. |

Everything is **administrators‑only** and authenticates with your existing WordPress
session. The optional **WP Arzo Pro** add‑on registers premium modules into the same
dashboard (shown as locked **PRO** cards until unlocked).

- **Current version:** `6.152.0` — see [CHANGELOG.md](CHANGELOG.md)
- **Requires:** WordPress ≥ 5.0 · PHP ≥ 7.2
- **Highlights:** a built‑in **cookieless analytics** engine (no Site Kit needed), multi‑provider
  SMTP with fallback **+ auto‑retry queue**, a CodeMirror **Code Snippets** editor with **smart
  conditional logic**, local **+ scheduled backups** with file snapshots & diff, **REST API
  authentication**, a **role/capability editor**, and dozens of performance, security, and admin
  refinements. Heavy power‑tools (File Manager, Database manager) ship with **WP Arzo Pro** to keep
  the free core lean.

---

## 🎛️ Feature Manager & Setup Wizard

The native **WP Arzo** admin menu is the modern home base:

- A grouped, searchable **toggle grid** — flip features on/off with modern switches; each
  carries its own schema‑driven settings panel and a real SVG icon.
- A **left sidebar** for page navigation (Dashboard, Email, Backups, Snippets, Media
  Cleanup, Activity Log, Content Types, Login Security, Advanced Tools…) and a live
  **category filter** that scopes the grid.
- A **⌘/Ctrl‑K command palette** (WordPress‑native) with every WP Arzo destination.
- A **light / dark theme** toggle (dark by default), WCAG 2.2 AA throughout.
- A **Setup Wizard** that applies curated presets in one click (it only *enables* — it
  never disables what you've set):
  **Essentials · Velocity · Fortress · Creator · Growth · Command Center · The Works.**

---

## 📦 Complete feature list (free core)

> ~50 toggleable features, organized by group. Enable only what you need; everything is off
> until you turn it on.

### 🧑‍💼 Utilities & Admin
| Feature | What it does |
|---|---|
| **Hide Admin Bar** | Hide the front‑end toolbar for a chosen audience |
| **Disable Dashboard Widgets** | Remove default dashboard widgets for a cleaner home screen |
| **Last Login Column** | Record and show each user's last login in the Users list |
| **Login / Logout Redirects** | Send users to a custom URL after login or logout |
| **Clean Up Admin Bar** | Strip WordPress/branding clutter from the toolbar |
| **Enhance List Tables** | Featured‑image column, IDs, and other list‑table quality‑of‑life |
| **Page & Post Duplication** | One‑click duplicate any post/page/CPT |
| **Content Order (drag & drop)** | Manually order posts/pages/CPTs by dragging |

### ⚙️ Core Controls
| Feature | What it does |
|---|---|
| **Disable Comments** | Turn off comments site‑wide (or per type) |
| **Disable Gutenberg** | Restore the Classic Editor |
| **Disable RSS Feeds** | Disable all RSS/Atom feeds |
| **Disable Embeds** | Remove oEmbed discovery & the embed script |
| **Disable Emojis** | Drop the emoji script/styles for speed |
| **Disable Self Pingbacks** | Stop self‑ping notifications |
| **Disable XML‑RPC** | Close the XML‑RPC attack surface |
| **Disable Application Passwords** | Turn off WP application passwords |
| **Disable All Updates** | Freeze core/plugin/theme auto‑updates |
| **Disable Archives** | Disable author/date/tag archives |
| **Disable Front Dashicons** | Stop loading Dashicons for logged‑out visitors |
| **Remove jQuery Migrate** | Drop the legacy jQuery Migrate script |
| **Revisions Control** | Cap or disable post revisions |
| **Heartbeat Control** | Throttle or disable the WordPress Heartbeat API |

### 🔒 Security & Access
| Feature | What it does |
|---|---|
| **Limit Login Attempts** | Lock out an IP after repeated failed logins, with a live lockouts dashboard |
| **Custom Login URL** | Move `wp-login.php` to a secret slug (ASE‑style, warning‑free) |
| **Custom Login Page** | Brand every login screen — logo, colors, background, custom CSS |
| **Block User Enumeration** | Stop `?author=N` and REST user enumeration |
| **Restrict REST API** | Require auth for REST endpoints |
| **REST API Authentication** | Issue/revoke API keys (Bearer / X‑API‑Key / Basic) with **per‑key read‑only scope**, **auto‑expiry**, and last‑used tracking |
| **Role Manager** | Edit role capabilities (grouped by category with a live filter + per‑group toggle‑all); add, clone, and delete roles |
| **Disable Theme/Plugin Editor** | Remove the in‑admin file editors |

### ✍️ Content & Developer
| Feature | What it does |
|---|---|
| **Code Snippets** | A CodeMirror editor for PHP/CSS/JS/HTML snippets, fatal‑guarded (auto‑disables a snippet that errors), with **Smart Conditional Logic** (run only on chosen roles / post types / page types / URLs / devices / schedules) and Import/Export |
| **Custom CSS** | Site‑wide custom CSS without touching the theme |
| **Header / Body / Footer Code** | Inject scripts/markup into `<head>`, after `<body>`, or the footer |
| **Custom Body Class** | Add custom classes to `<body>` |

### 🖼️ Media
| Feature | What it does |
|---|---|
| **Auto WebP / WebM Conversion** | Convert uploads to WebP (and video to WebM via ffmpeg) on upload |
| **SVG Upload** | Safely allow sanitized SVG uploads |
| **Image SEO** | Auto‑fill alt/title from filenames |
| **Prevent Duplicate Uploads** | Detect and block duplicate media by hash |
| **Media Cleanup** | Scan for unreferenced attachments and reclaim space (batched, with a reclaimable‑space summary and confirmed delete) |
| **Missed Schedule Fix** | Publish posts that missed their scheduled time |

### 📈 Marketing & SEO
| Feature | What it does |
|---|---|
| **Manage robots.txt** | Edit a virtual `robots.txt` from the dashboard |
| **Manage ads.txt** | Edit a virtual `ads.txt` |
| **Site Verification** | Add Google/Bing/Pinterest/Yandex/Baidu verification tags |
| **Crawl Optimizations** | Trim crawl bloat (shortlinks, RSD, WLW, generator, etc.) |

### 📊 Analytics
| Feature | What it does |
|---|---|
| **Built‑in Analytics** _(cookieless)_ | Privacy‑first, first‑party website analytics recorded **entirely in your own database** — no cookies, no external services, no personal data at rest (GDPR/CCPA‑friendly). A **WP Arzo → Analytics** dashboard shows pageviews, unique visitors, sessions, bounce rate, avg. visit and views/session, a **traffic chart**, and reports for **Top pages, Referrers, Countries, Devices/Browsers/OS, Landing & Exit pages, 404s and on‑site Search terms**, over Today / 7 / 30 / 90‑day ranges with **CSV export**. Plus a **dashboard widget**, an **admin‑bar “today” peek**, and a **Views column** in the Posts list. Bot filtering, admin/role/IP exclusion, Do‑Not‑Track respect, and automatic data retention. _No Site Kit or Independent Analytics needed._ |
| **Google Analytics 4 / Tag Manager / Ads tags** | Insert Google's tags without another plugin — GA4 (gtag.js + IP anonymization), GTM (container + body noscript), and Google Ads — managed from one **Google** tab, each with an “exclude signed‑in admins” option. |

### ✉️ Email
| Feature | What it does |
|---|---|
| **Email Delivery (SMTP & API)** | A SureMail‑style **connections manager**: pick a provider (Custom SMTP, Gmail, Outlook, Zoho, Yahoo, Fastmail, Amazon SES, Mailjet, **SMTP2GO, SparkPost, MailerSend, Elastic Email**, SendGrid, Mailgun, Postmark, Brevo — 16 providers), configure multiple named connections with a **primary + ordered fallback**, per‑connection test send, and an **auto‑retry queue** that re‑sends failed messages with backoff |
| **Email Log** | Every sent/failed email with recipient, subject, connection, resend, CSV export, and a deliverability meter — plus a **Stats** tab (7/30/60‑day per‑connection volume + deliverability trend chart) and an **Engagement** column (opens/clicks) when Pro Email Tracking is active |

### 💾 Backup & Config
| Feature | What it does |
|---|---|
| **Local Backups** | Streaming database snapshots (options or full DB) with one‑click restore (safety‑snapshot‑first), plus **file snapshots** (uploads/plugins/themes/config) with a **Compare/diff** drawer and **file restore** |
| **Scheduled Backups** | Automatic daily/weekly/monthly snapshots with scope + retention |
| **Automated Snapshots** | Take a safety snapshot automatically before any feature toggle |
| **Config Import / Export** | Move your whole WP Arzo setup (features + settings + snippets) between sites as versioned JSON, safety‑snapshot before import |

### 📋 Operations
| Feature | What it does |
|---|---|
| **Activity Log** | A full audit trail — logins & password resets, user/role/profile changes, content edits/deletes, media, comments, plugin/theme/core installs & updates, and settings changes |

---

## 🧰 Advanced Tools console

A standalone, dark‑themed console for hands‑on maintenance (each tab can be enabled/disabled
individually from the dashboard):

| Tab | What it does |
|---|---|
| **Site Info** | WordPress / PHP / MySQL / server / disk usage at a glance |
| **Users** | Paginated user list; create, delete, and log in as any user |
| **Database** | Full database manager (**AdminNeo**) — browse/edit rows, run SQL, export/import. **Requires WP Arzo Pro** (moved out of the free core in 6.146 to keep it lean); the free tab shows an upgrade panel |
| **Files** | Full file manager (**elFinder**) rooted at the WordPress install. **Requires WP Arzo Pro** (moved out of the free core in 6.146); the free tab shows an upgrade panel |
| **Plugins** | Activate / deactivate via toggle; upload a plugin ZIP |
| **Themes** | Switch the active theme; upload a theme ZIP |
| **Debug** | Toggle `WP_DEBUG` / `WP_DEBUG_LOG` / `WP_DEBUG_DISPLAY` / `SCRIPT_DEBUG` / `SAVEQUERIES`; a **live AJAX debug‑log console** (tail depth, auto‑refresh, severity filter, download, clear); and a read‑only **wp‑config.php / .htaccess viewer** with secrets masked |
| **Site Modes** | Maintenance (503) / Coming Soon (200 + noindex) / Payment Required (402) pages with social contacts, plus the Emergency Recovery toggle |
| **Extra Options** | Set PHP limits via `wp-config.php`, `.htaccess`, or `php.ini` |
| **Temporary Logins** | Passwordless, time‑limited, revocable login links for support/clients/devs |

## 🚑 Emergency Recovery

A standalone script (`/wp-arzo/emergency/` — bookmarkable, and works via a direct file URL
even when rewrites are down) that runs **without loading WordPress**. Password‑protected and
throttled. It can: deactivate all plugins (safe mode), toggle individual plugins, switch/
upload themes, create an administrator, reset any user's password, fix the site URL, restore
a default `.htaccess`, and clear transients — enough to bring a white‑screened site back.

---

## 💎 WP Arzo Pro

The optional Pro add‑on registers these modules into the same dashboard:

| Group | Pro modules |
|---|---|
| **Marketing & Tracking** | Meta (Facebook) Pixel · TikTok Pixel · LinkedIn Insight Tag · Pinterest Tag · Snapchat Pixel · X (Twitter) Pixel · Microsoft/Bing UET _(GA4 · Tag Manager · Google Ads are now **free** — see Analytics)_ |
| **Analytics** | Analytics Pro — a **UTM Campaigns** report and a live **Real-time** visitor view on top of the free built-in engine _(more coming: click/event/form tracking, email reports, eCommerce, GA4 in‑dashboard reporting)_ |
| **Content Modeling** | Content Types (CPT/CCT builder) · Custom Fields (meta‑box builder) |
| **Media** | Media Folders (nestable library folders + filters) |
| **Branding** | Admin Branding & Custom Dashboard · Text Replacement (white‑label) |
| **Security** | Two‑Factor Authentication (TOTP + recovery codes) |
| **Ops & Monitoring** | Redirects & 404 Monitor · Advanced Cron Manager · Advanced Audit Log (DB‑backed) · Notifications (Slack/Discord/n8n/webhook) · Site Health Monitor (checks + uptime + status endpoint) · AI / MCP Server |
| **Off‑site Backups** | FTP · Google Drive · pCloud (auto‑upload + retention + remote restore) |

---

## 🚀 Installation

1. Upload the plugin folder to `/wp-content/plugins/` (or install the `wp-arzo.zip` release).
2. Activate **WP Arzo** from the *Plugins* screen.
3. Open **WP Arzo** in the admin menu — run the **Setup Wizard**, or start flipping features on.
4. Reach the power tools from **WP Arzo → Advanced Tools**.

Updates are delivered in‑plugin (from GitHub releases) like any normal WordPress update.

## 🔐 Security model

WP Arzo can edit files, run SQL, switch plugins/themes, create admins, and rewrite server
config — so every entry point is gated to `manage_options`, every state‑changing action is
**CSRF‑protected with a WordPress nonce**, input is sanitized early and output escaped late,
file‑manager paths are confined to the WordPress install, and all SQL is prepared. The
Emergency tool adds its own password + brute‑force throttle + strict CSP.

## 📄 License

Proprietary. See [LICENSE](LICENSE). WP Arzo is developed by
[Yasir Shabbir](https://yasirshabbir.com).
