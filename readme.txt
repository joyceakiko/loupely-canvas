=== Loupely Canvas ===
Contributors: loupely
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 2.12.0
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
* Gives each page its own settings: hide the title, render full width with no wrapper, add a body class, set noindex, skip the site-wide head and body code, and add head or body end code for that page alone. The things you would otherwise hand-code on every page.
* Renders posts, archives, and search through your own markup, using simple tokens, so the blog stays as passthrough as your pages.
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

= 2.13.0 =
* Switching from Loupely Canvas Lite now carries your header, footer, and per page hide title, full width, and body class settings into the full theme. Lite stores these under its own keys, so before this they read blank after switching. The carry over runs once on activation and never overwrites a setting you have already made in the full theme.

= 2.12.0 =
* The per page Header and footer controls can now offer modes beyond the built-in Global, Custom, and None, and the front end honors them. The render falls back to the global header and footer when nothing supplies the added mode, so the theme works the same on its own. Canvas Pro uses this to add a Use a set choice to each control.
* The Page settings panel can now be mounted by another editor with no change to its markup or save, so the Canvas Pro page editor reaches the same per page settings as the classic editor.

= 2.11.3 =
* Added a per page Hide the archive header option. It shows when you edit the page assigned as your Posts page, and turns off the archive header on the blog index so only the content above the post list shows there.

= 2.11.2 =
* Per page settings now apply on the blog index when a static Posts page is assigned. The Posts page is the home query, not a singular page, so its per page head code, body end code, noindex, body class, full width, and skip-global setting were not taking effect there. They now read from the assigned Posts page. True archives like category, tag, and search still have no single page to attach settings to, so they stay untouched.

= 2.11.1 =
* Hide title now removes the title rather than only adding a hook class. On a post it blanks the title token and hides the default .lc-post-title element, so checking the box hides the title on its own. On a page it adds the lc-hide-title body class, since the theme prints no page title to remove.
* Added a per post option to hide the previous and next post links, for single posts that should show only your own markup.

= 2.11.0 =
* Added a Page settings meta box that gathers the per page controls a hand-coder would otherwise repeat by hand: hide the title (adds the lc-hide-title body class), render full width with no theme wrapper, set a noindex robots tag, skip the site-wide head and body code on this page, add a free body class, and add head and body end code for this page alone.
* The per page head code prints after the site-wide Head box so it can build on or override it, and the body end code prints after the site-wide Body end box.
* Folded the existing header and footer override into the same Page settings box. Code boxes carry the find and replace bar, and code-bearing fields are re-slashed on save so a backslash or a JS hex escape in head code is kept intact.

= 2.10.4 =
* Colored the Loupely Canvas Pro link in the Starter Kit panel to the Canvas sage palette, matching the other links on the settings screen. Moved the panel's styles from the render code into the settings stylesheet.

= 2.10.3 =
* Colored the Starter Kit link in the example helper to the Canvas sage palette, so it matches the sage Starter Kit button in the panel above instead of the default blue.

= 2.10.2 =
* In the example helper, linked the words Starter Kit to the configurator page on the website and removed the word above, so the reference reads as a link rather than a position on the screen.

= 2.10.1 =
* Reworded the example helper on the settings screen so it explains that it loads a sample header, footer, and page draft into the empty boxes, and sets it apart from the Starter Kit configurator on the website. Restyled it to the Canvas sage palette and added a dismiss link that hides it for the current user.

= 2.10.0 =
* Added a Site basics section to the settings screen that gathers the logo, the favicon, and the menus in one place, with the current state shown and a link straight to each control. The logo and favicon are stored by WordPress, so they stay in sync with the Customizer.
* Added logo support and a {logo} token for the header and footer, so you can place the site logo without hardcoding an image tag. It renders with the class custom-logo, yours to size with CSS.
* The favicon is printed by WordPress once set, with no code or token needed.

= 2.9.0 =
* The header and footer boxes now accept tokens. {site_title}, {tagline}, {home_url}, and {year} fill in site values, so a footer copyright line can read the current year on its own.
* Added navigation menu tokens for the header and footer. {menu:header} and {menu:footer} render the menus assigned to the new Header and Footer locations under Appearance, Menus, and {menu:a-menu-slug} renders any menu by its slug or name. A menu prints as a plain ul with the class lc-menu, which you style from your Head code box.
* The settings screen lists the new tokens under the Header and Footer boxes.

= 2.8.1 =
* Added a {search_form} token for the Archive header and 404 boxes, so you can drop a search box into either one.
* The search results page now shows a search form when nothing matched, so a visitor can try again without leaving the page.

= 2.8.0 =
* Added blog tokens for richer post cards and single posts. {post_class} outputs the post's own CSS classes, so a card can be styled by category, tag, or sticky state. {comment_count} and {comments_link} show the number of comments and link to them. {author_avatar}, {author_bio}, and {author_url} pull author details from the profile.
* The settings token reference and the Post card and Single post token lists now include the new tokens.

= 2.7.2 =
* Reply links on threaded comments now work. The core comment reply script loads on single posts when threaded comments are turned on, so a Reply moves the form under the comment it answers.
* Added responsive embeds support, so a video or other embed inside a post scales to its container instead of overflowing.
* Added the pingback link in the head on single posts that accept pings, so other sites can register a pingback.

= 2.7.1 =
* Single posts split with the Page Break block now show within-post page links, matching the Lite theme. Single-page posts are unaffected.

= 2.7.0 =
* Blog templates settings rewritten to be clear: a plain explanation of how pages and posts differ, what Post card, Single post, and Archive header each mean, and a built in token reference that explains every token and where to use it.
* Each blog box now lists the tokens you can use in it, and has its own jump link in the sticky nav, including the 404 page.
* Single posts now show comments and previous and next post links. Comments use standard markup so you style them from your Head code box.
* Fixed the settings screen checkboxes so they show in the Canvas sage color.
* Added automatic feed links.

= 2.6.0 =
* The editor preview now reflects your design. The CSS and fonts from your Head code box load into the block editor and the Custom HTML block preview, so it looks like the front end instead of plain markup.
* Added an Editor preview styling toggle in Theme Settings, on by default, to turn that behavior off if your head CSS interferes with the editor.

= 2.5.0 =
* The find and replace bar is now a light panel matching the Pro editor, white with a sage focus ring and a sage active state, instead of the dark bar.
* Added a setting to turn the find and replace bar on or off across every editor.
* Renamed the Editor menus section to Theme Settings, now holding both the find and replace toggle and the editor menus toggle, with a matching Theme Settings button on the sticky nav.

= 2.4.1 =
* Recolored the find and replace bar and its match highlights to the Canvas sage and forest ink palette, replacing the off-brand blue accent.

= 2.4.0 =
* Added a sticky section nav to the settings screen that follows as you scroll, with jump links to each section and a Save button always in reach.
* Recolored the settings screen buttons and checkboxes to the Canvas sage palette.
* The sticky nav highlights the section you are currently viewing.

= 2.3.0 =
* Added blog support that stays passthrough: posts, archives, and search now render through your own markup instead of bare post bodies.
* New Blog templates section on the settings screen: Post card, Single post, Archive header, and 404 boxes, each accepting raw HTML with simple tokens ({title}, {permalink}, {date}, {content}, {excerpt}, {thumbnail}, {categories}, and more).
* The page you assign as the Posts page under Settings, Reading now renders its content above the post list, so a pasted intro or hero shows instead of being discarded.
* Added home.php, single.php, archive.php, search.php, and 404.php. A missing URL now shows a real 404 instead of a blank page.
* Empty template boxes fall back to a minimal default so a fresh install is never blank, with no imposed styling to undo.

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
