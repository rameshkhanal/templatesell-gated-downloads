=== Gated Downloads – Lead Capture & Content Locker ===
Contributors: templatesell
Tags: lead generation, lead capture, content locker, gated content, email opt-in
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 1.3.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gate downloads behind a form, download any file after form submission. Capture name, email & phone as leads. Works with any theme.

== Description ==

Gated Downloads turns any download into a lead. Place a download button anywhere with a shortcode or block; when a visitor clicks it, an accessible pop-up form asks for their details. After they submit, the lead is saved to your site and the file is delivered.

In short, it requires a form submission before the file download, so you gate your downloads with lead capture and collect leads before the download, instead of giving files away to anonymous visitors.

Common use cases include ebooks, whitepapers, checklists, brochures, price lists, documents, media kits, and any other file you want to exchange for a contact.

= Key features =

* Gate unlimited downloads of any file type, PDF, Word, Excel, CSV, PowerPoint, ZIP, images, audio, video, and more (upload to the Media Library or link an external URL)
* No file? No problem, leave the file empty to use any form as a simple email opt-in / subscribe form (captures the email, then shows your success message or redirect)
* Content Locker, hide any part of a post or page behind the form with `[tslgd_lock id="123"]your hidden content[/tslgd_lock]`; the content is never present in the page source, unlocks instantly after submission, and stays unlocked for returning visitors
* Automatic placement, add a download button to every post (or only chosen post types and categories) from one setting, before or after the content, with an optional heading; any single post can opt out or offer a different file
* Per-form configuration, every gated file has its own unique shortcode, fields, messages, and notification
* Choose, reorder, and require fields per form, Name, Email, Phone, Company, Job Title, Website, Country (drag to reorder)
* Per-form success message, submit button text, and optional redirect URL after submit
* Lightweight, accessible pop-up form (focus-trapped, keyboard & screen-reader friendly)
* Configurable fields, show/hide and require each field independently
* User-defined categories to organize your files
* Brand colors, match the button, links, and modal header to your site
* Shortcode, Gutenberg block, and a native Elementor widget (with a copy-ready Shortcode column in the files list)
* Inline link trigger, `[tslgd_link id="123"]any wording[/tslgd_link]` turns a phrase or an existing link into the gate, when you don't want a button
* Download counter, count real file deliveries and optionally show "Downloaded 1,204 times" as social proof
* Campaign tracking, every lead records its utm_source, utm_medium, utm_campaign, utm_term, utm_content and referring site, shown on the Leads screen and included in the CSV export
* Leads dashboard with CSV export
* Conversion analytics, see button views, form opens, leads, and the conversion rate for every gated file (privacy-friendly: daily counters only, no cookies, no personal data)
* Admin email notification on each new lead
* Automatic download email, each lead receives their download link by email (write your own subject &amp; message with Pro)
* Built-in honeypot spam protection, invisible to visitors, zero setup (score-based reCAPTCHA v3 in Pro)
* GDPR-friendly: optional consent checkbox, data stays in your database, export/erase support
* Works with any theme, the modal renders at the page root, immune to theme layout conflicts
* Fully translatable (text domain: templatesell-gated-downloads)
* No external services, no tracking, no data sent anywhere

= How to use =

1. Go to Gated Downloads → Add New Gated File.
2. Upload a file (or paste an external file URL) and set the button label.
3. Optionally open the "Form Fields & Behavior" box to pick which fields this form asks for, reorder them, mark which are required, and set the success message, button text, or a redirect URL.
4. Copy the shortcode shown in the files list, for example `[tslgd_download id="123"]`, and paste it into any post or page. The shorter `[tslgd id="123"]` does the same thing, as does the Gutenberg block.
5. To lock text instead of (or as well as) a file, wrap it in the lock shortcode: `[tslgd_lock id="123"]This is only visible after submitting the form.[/tslgd_lock]`. Optional attributes: `message`, `hint`, `button`, and `teaser` (a short blurred preview line).
6. Read the captured leads under Gated Downloads → Leads, and export them as CSV.

= Gated Downloads Pro =

A separate paid add-on, Gated Downloads Pro, is available from [templatesell.net](https://templatesell.net/gated-downloads/). It adds custom field types, email-marketing integrations (Mailchimp, Brevo), webhooks, autoresponders, protected and expiring download links, and reCAPTCHA v3 spam protection. The free plugin here is fully functional on its own and does not require the add-on.

== Installation ==

1. Upload the `templatesell-gated-downloads` folder to `/wp-content/plugins/`, or install via Plugins → Add New.
2. Activate the plugin through the Plugins menu.
3. Go to Gated Downloads → Add New Gated File and add your first file (upload or URL) and category.
4. Copy the shortcode (or insert the Gated Downloads block) into any post or page.
5. View captured leads under Gated Downloads → Leads.

== Frequently Asked Questions ==

= Can visitors download any file after form submission? =

Yes, that is exactly what Gated Downloads does. The file stays locked behind the opt-in form; the moment a visitor submits their details, the download is delivered. You can gate any file type, PDF, spreadsheet, ZIP, image, audio, or video.

= Does the visitor also get the file by email? =

Yes. Besides the instant in-browser delivery, each lead automatically receives a clean email with their download link (you can switch this off under Settings → Notifications). Gated Downloads Pro lets you write your own subject line and message with placeholders.

= Does it block spam submissions? =

Yes, every capture form ships with an invisible honeypot trap that silently blocks most bots, with zero configuration. For sites under heavier attack, Gated Downloads Pro adds score-based reCAPTCHA v3.

= How do I require form submission before a file download? =

Create a gated file, add your download, and drop its shortcode on any page. The file stays locked until the form is submitted, so every download requires a completed form first. This lets you capture leads before file downloads and secure your downloads with a lead form, no code required.

= Does it work with Elementor? =

Yes, natively. Search for "Gated Downloads" in the Elementor editor and drop the widget anywhere; pick the gated file, optionally override the button label, and set the alignment. The shortcode also works inside any Elementor text or shortcode widget if you prefer.

= Can I use a text link instead of a button? =

Yes. Wrap any wording in the link shortcode: `[tslgd_link id="123"]Download the price list[/tslgd_link]`. It opens the same capture form and is counted in analytics exactly like the button, but inherits your theme's link styling so it fits inside a sentence.

= Can I show how many times a file has been downloaded? =

Yes. The plugin counts real file deliveries (not just form submissions). Turn on "Show download count" under Settings and visitors see "Downloaded 1,204 times" beneath the button, a proven bit of social proof. The count stays hidden until a file has at least one download, so a new file never advertises a zero. All-time downloads also appear on the Gated Files list.

= Can I show the download button on every post without shortcodes? =

Yes. Turn on Settings → Automatic Placement, pick the file, and choose whether the button goes before or after the content. You can limit it to specific post types and categories, add a heading above it, and exclude individual posts by ID. Every post also gets a small "Gated Downloads" box in the sidebar to use the site default, hide the button on that post, or offer a different file there. Posts that already contain the same file's shortcode or block are skipped automatically, so no post ever shows a duplicate button.

= Can I lock text or other page content instead of a file? =

Yes, that is the Content Locker. Wrap the content in the lock shortcode and point it at any gated file: `[tslgd_lock id="123"]hidden content[/tslgd_lock]`. Visitors see a blurred placeholder with an "Unlock content" button that opens the same capture form; the moment they submit, the content appears in place, no page reload. The locked content is never present in the page's HTML source (it is fetched only after a verified submission), so it cannot be read by viewing the source or by caching plugins, and returning visitors who already submitted see it unlocked automatically. You can customize the placeholder with the `message`, `hint`, `button`, and `teaser` attributes.

= Can I use it as a simple email subscribe form without a file? =

Yes. Leave the file empty on a gated file and the form becomes a plain email opt-in, the visitor's details are captured and a success message (or your redirect URL) shows after they submit, with no download involved. Rename the button to "Subscribe", show just the email field, and you have a lightweight subscribe form.

= What file types are supported? =

Every file type is supported, there is no restriction. That includes PDF, Word (DOC/DOCX), Excel (XLS/XLSX), CSV, PowerPoint (PPT/PPTX), ZIP and other archives, images (PNG, JPG, GIF, SVG, WebP), audio (MP3, WAV), and video (MP4). You can either upload the file to the WordPress Media Library, or paste any direct file URL (for example a file hosted on cloud storage). If a browser can download it, you can gate it.

= Where is lead data stored? =

In a custom table in your WordPress database. Nothing is sent to any third party.

= Can I export my leads? =

Yes, Gated Downloads → Leads includes a CSV export.

= Can each form ask for different fields? =

Yes. Open the "Form Fields & Behavior" box on any gated file to choose which fields that form shows, drag them into the order you want, and mark each one required or optional. Forms you do not configure use the global defaults from the settings page.

= Will it work with my theme? =

Yes. The pop-up is rendered at the root of the page, so theme wrappers with overflow or z-index rules cannot break it.

= Is it accessible? =

Yes. The modal uses proper ARIA roles, traps focus while open, closes on Escape, and returns focus to the trigger.

== Screenshots ==

1. The download button on the front end
2. The lead-capture pop-up form
3. Managing gated files in the admin
4. The leads dashboard with CSV export

== Changelog ==

= 1.3.0 =
* Added: campaign tracking (UTM parameters and referrer) on every lead and in the CSV export.
* Added: `tslgd:lead` and `tslgd:download` front-end events.
* Added: Elementor widget.
* Added: inline link trigger, `[tslgd_link]`.
* Added: download counter, with an optional public count.
* Added: automatic placement of the button on posts.
* Added: Content Locker, `[tslgd_lock]`.

= 1.2.0 =
* Added: conversion analytics (views, opens, leads) per gated file.
* Added: download link email sent to each lead.
* Added: built-in honeypot spam protection.
* Changed: clearer guidance on the gated file edit screen.
* Changed: admin notices appear less often.

= 1.1.1 =
* Added: the file is optional, so a form can work as a plain email opt-in.
* Changed: plugin renamed to "Gated Downloads – Lead Capture & Content Locker".

= 1.1.0 =
* Added: welcome prompt, demo content and setup checklist.
* Added: Leads stats and "filter by form" dropdown.
* Added: one-click duplicate for gated files.
* Added: Automation screen previewing Pro integrations.
* Added: 7-day free trial button for Pro.
* Added: optional review prompt and upgrade suggestion.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.3.0 =
Adds Content Locker, automatic placement, Elementor widget, link trigger, download counter and UTM tracking. New options stay off until enabled.

= 1.2.0 =
Adds conversion analytics. No settings changes.

= 1.1.1 =
Plugin renamed. Settings are unchanged.

= 1.1.0 =
Adds guided setup, leads stats, file duplication and a 7-day Pro trial.

= 1.0.0 =
Initial release.

== Privacy ==

This plugin collects the information a visitor enters in the capture form (such as name, email address, and phone number) and stores it in your site's database. This data is not transmitted to any third party. As the site owner you are responsible for disclosing this collection to your visitors in accordance with applicable laws (e.g. GDPR). The plugin registers WordPress personal-data exporter and eraser hooks so you can fulfill data requests.

Campaign attribution (utm_* parameters and the referring site) is read from the visitor's own URL and browser and held in their browser's session storage until they submit a form; it is then stored with their lead in your database. No identifier is created, nothing is shared with any third party, and the data disappears when the visitor closes the tab without submitting.

If you use the Content Locker, a functional first-party cookie (`tslgd_unlock_{id}`) is set in the visitor's browser after they submit the form, so previously unlocked content stays visible on return visits. It contains only an anonymous signed token (no personal data) and expires after 30 days.
