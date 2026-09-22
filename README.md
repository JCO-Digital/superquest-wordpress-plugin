# SuperQuest for WordPress

Embed [SuperQuest](https://jquest.fi) quests, quizzes and polls in WordPress with a block, a shortcode or an Elementor widget, or show them site-wide as a popup. This is the development repository; the plugin is distributed through the [WordPress.org plugin directory](https://wordpress.org/plugins/superquest/).

See `readme.txt` for the user-facing description, external-service disclosure and changelog.

## Development

Requirements: PHP 8.0+, Composer, Node 22, pnpm 11, WP-CLI (for `make-pot`).

```sh
pnpm install
composer install

pnpm build        # build the block into build/
pnpm start        # rebuild on change
pnpm check        # eslint, stylelint, phpcs
pnpm make-pot     # regenerate languages/superquest.pot (run after build)
pnpm plugin-zip   # zip the distributable files listed in package.json "files"
pnpm playground   # local WordPress Playground at http://127.0.0.1:8881 with Plugin Check installed
```

### Layout

| Path | Purpose |
| --- | --- |
| `superquest.php` | Plugin header, constants, autoloader, boot |
| `includes/` | PHP classes in the `SuperQuest` namespace, autoloaded from `class-*.php` files |
| `src/quest/` | Block source (built to `build/quest/`, which ships; `src/` does not) |
| `views/admin/` | Admin screen templates |
| `assets/` | Admin stylesheet and logo |
| `languages/` | POT file (shipped) and fi/sv_SE PO files for translate.wordpress.org (not shipped) |
| `.wordpress-org/` | Directory listing assets and the Live Preview blueprint |

### Loader contract

The class `jquest-app`, the `data-org-id`, `data-game-id`, `data-version`, `data-locale` and `data-jq-load` attributes, the `window.__JQUEST_VERSION` global and the `files.jquest.fi` URLs are read by the external SuperQuest loader. They are not this plugin's to rename. `data-jq-load` takes `eager`, `hover` or `click`; the loader logs and ignores anything else.

### Hooks

- `superquest_loader_attributes` – filter the extra attributes on the loader script tags.
- `superquest_locale` – filter the language slug written to popup quests.

## Release

1. Update `Version` in `superquest.php`, `Stable tag` and the changelog in `readme.txt`, and `version` in `package.json` and `src/quest/block.json`.
2. Tag `vX.Y.Z` and push. The deploy workflow builds the block and pushes to WordPress.org SVN (needs the `SVN_USERNAME` and `SVN_PASSWORD` repository secrets).
