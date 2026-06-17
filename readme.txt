=== Loupely Canvas ===
Contributors: loupely
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 2.2.11
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: one-column, full-width-template, translation-ready, custom-colors, editor-style

A minimal passthrough theme for people who build in raw HTML and CSS. Paste your markup and it renders full width, with nothing in the way.

== Description ==

Loupely Canvas is for people who would rather write HTML and CSS than wrestle a page builder. You paste your markup into a Custom HTML block and the theme renders it full width, with no container max widths, no injected block styles, and no framework deciding things for you.

What it does:

* Renders Custom HTML blocks full width with zero interference.
* Lets you set a global header and footer once, applied to every page.
* Injects head code (analytics, fonts, favicons, meta tags) and body end code (chat widgets, late scripts) site wide, without editing theme files.
* Lets any single page override the header and footer, or hide them entirely.
* Adds a find and replace bar to every HTML box. Press Ctrl+F or Cmd+F inside a box to search its contents, the thing the editor never let you do. It supports case sensitivity, whole word, and regular expressions.
* Ships an optional one click example header, footer, and page so you are not staring at a blank box.

No page builder. No build step. No bundled libraries.

== Installation ==

1. Upload the theme through Appearance, Themes, Add New, Upload Theme, or unzip it into wp-content/themes.
2. Activate it.
3. Go to Appearance, Loupely Canvas to set your header, footer, and head and body code. New here? Use the Create starter content button for a working example.
4. Create a page, add a Custom HTML block, paste your markup, and publish.

== Frequently Asked Questions ==

= My scripts or styles are being removed when I save. Why? =

WordPress only lets accounts with the unfiltered_html capability save raw script and style tags. That is administrators on a normal single site install. On multisite or locked down hosts, other roles will have their script and style tags stripped. The settings screen shows a notice when this applies to you.

= Does the find and replace tool change my live site? =

No. It runs only in the admin while you are editing, never on the public site. Replacements are normal edits you can undo with Ctrl+Z.

= Where do the header and footer come from? =

From Appearance, Loupely Canvas. A single page can override them under the Header and footer box in the page editor.

= How does this version update? =

This full edition is distributed through GitHub and loupelycanvas.com. It includes a built in update checker that watches the project's GitHub releases, so update notices appear in wp-admin and you can update in one click. The Loupely Canvas Lite edition on WordPress.org updates through the directory instead.

== Changelog ==

= 2.2.11 =
* Settings screen panel: removed the audience descriptor from the Pro text, leaving the feature list to speak for itself.
* Settings screen panel: switched the section label to the Canvas tag marker style, replacing the old uppercase eyebrow.
* Moved the per page override toggle script out of inline PHP into an enqueued assets file.
* Tested up to WordPress 7.0.

= 2.2.10 =
* Settings screen panel: described what Loupely Canvas Pro adds (a full page code editor with syntax coloring and error finding, snippets and templates, multiple header and footer sets, version history, and more), with a link to the Pro page.
* Settings screen panel: now hidden automatically when Loupely Canvas Pro is active, so it no longer points to an upgrade you already have.

= 2.2.9 =
* Added a Starter Kit panel at the top of the settings screen, linking to the free kit at loupelycanvas.com/starter-kit. Admin only, nothing is added to the front end.
* Updated the Theme URI, Author URI, and translation bug report URL to loupelycanvas.com.
* Pointed the bundled update checker at the Loupely organization repository.

= 2.2.1 - 2.2.5 =
* Fixed find and replace so Replace All works across the whole HTML box.
* Fixed the undo hotkey so it reverts a replace or replace all made with find and replace.

= 2.2.0 =
* Added a GitHub based update checker so self-hosted installs receive update notices in wp-admin and can update in one click, without the WordPress.org directory.

= 2.1.1 =
* Refreshed the theme screenshot to the new Loupely Canvas branding (Forest palette, Lora and Inter, the paste to preview hero).

= 2.1.0 =
* Added an optional setting to hide the Patterns and Fonts menus under Appearance, since this classic theme does not use them. Off by default, hides the menu links only.

= 2.0.0 =
* Added global head and body end code injection.
* Added per page header and footer overrides (global, custom, or none).
* Added one click starter content.
* Find and replace now supports regular expressions and whole word, and works in the block editor code view and classic editor.
* Find and replace accessibility pass: labels, focus styles, and live match announcements.
* Added a clear notice when content is sanitized for accounts without unfiltered_html.
* Translation ready, GPL licensed, organized into modules.

= 1.2.0 =
* Find and replace now works in the header and footer settings boxes.

= 1.1.0 =
* Added find and replace for Custom HTML blocks.

= 1.0.0 =
* Initial release.

== Copyright ==

Loupely Canvas is distributed under the terms of the GNU GPL version 2 or later.
