=== SuperQuest ===
Contributors: jcodigital
Tags: quiz, gamification, learning, survey, poll
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed SuperQuest quests, quizzes and polls in your content with a block, or show them site-wide as a popup.

== Description ==

SuperQuest is a service for building gamified quests: quizzes, surveys, polls, ratings and other interactive content. This plugin connects a WordPress site to your SuperQuest organisation so its quests can be placed anywhere in your content.

A SuperQuest account is required. Quests are created and edited in the SuperQuest dashboard, not in WordPress.

= Features =

* **SuperQuest block** – pick any quest of your organisation from a searchable list and place it in a post, page, template or pattern.
* **Popup quests** – show one or more quests above the footer on every page, with a list of pages to leave out.
* **Fast loading** – the SuperQuest loader is only added to pages that need it, and the app bundle is hinted for early download so quests appear as soon as the page does.
* **Usage overview** – see every page, post, template and synced pattern that holds a quest.
* **Multilingual** – the language of popup quests follows Polylang automatically; other plugins can hook the `superquest_locale` filter.

= External services =

This plugin connects to SuperQuest, a service operated by J&Co Digital Oy under the domain superquest.fi. The plugin does not work without it.

**Quest list (api.jquest.fi)**
When an administrator saves the Organisation ID or refreshes the quest list, and when an editor presses "Refresh quests" in the block, the plugin sends the Organisation ID to `https://api.jquest.fi` and stores the returned list of quest titles and IDs on your site. No visitor data is involved.

**Quest player (files.jquest.fi)**
On pages that hold a quest block, an active popup quest, or when "Always load" is enabled, the visitor's browser loads the SuperQuest loader script and the quest application from `https://files.jquest.fi`. As with any resource served by a third party, the visitor's IP address, browser information and the page address are sent to that server. Which quest the visitor sees, and their answers, are processed by SuperQuest.

The loader is loaded after cookie consent by default when a consent manager blocks it. Site owners can opt in to marking it as essential for Cookiebot, OneTrust and CookieYes on the Popup screen.

**Dashboard link (dashboard.jquest.fi)**
The block offers editors a link to open the selected quest in the SuperQuest dashboard. Following the link is up to the editor; nothing is sent automatically.

Terms of use: https://superquest.staging.bojaco.com/en/terms-of-use/
Privacy policy: https://superquest.staging.bojaco.com/en/privacy-policy/

= Source code =

The block's JavaScript is built with `@wordpress/scripts`. Its readable source is published at https://github.com/JCO-Digital/superquest-wordpress-plugin.

== Installation ==

1. Install the plugin from the Plugins screen, or upload the plugin folder to `/wp-content/plugins/`.
2. Activate SuperQuest.
3. Open **SuperQuest → General** and enter your Organisation ID. The quest list is fetched right away.
4. Add the **SuperQuest** block to a post or page and pick a quest, or configure site-wide quests under **SuperQuest → Popup**.

== Frequently Asked Questions ==

= Where do I find my Organisation ID? =

In the SuperQuest dashboard, in your organisation's settings.

= The block says no quests were found =

Press **Refresh quests** in the block or on the General screen. If the list stays empty, check the Organisation ID and the message shown above the quest table.

= My quest sits in a template part and does not render =

It does: when a quest is rendered from a template, pattern or Query Loop, the loader is added at the end of the page instead of in the head. Only the early-download hints are skipped.

= How do I set the popup language with WPML or another multilingual plugin? =

Return the language slug from the `superquest_locale` filter:

`add_filter( 'superquest_locale', fn() => apply_filters( 'wpml_current_language', '' ) );`

= Does the plugin set cookies? =

The plugin itself does not. The SuperQuest application loaded from files.jquest.fi may store data in the visitor's browser; see the SuperQuest privacy policy.

== Screenshots ==

1. The SuperQuest block with a quest selected.
2. General settings with the organisation's quests.
3. Popup settings.
4. Usage overview.

== Changelog ==

= 1.0.0 =
* First release on WordPress.org.
