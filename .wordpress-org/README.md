# WordPress.org listing assets

Files in this directory are pushed to the `assets/` directory of the WordPress.org SVN repository by the deploy workflow. They are not part of the plugin itself.

Add before the first release:

- `icon-128x128.png` and `icon-256x256.png` (or `icon.svg`)
- `banner-772x250.png` and `banner-1544x500.png`
- `screenshot-1.png` … `screenshot-4.png`, matching the `== Screenshots ==` section of `readme.txt`

`blueprints/blueprint.json` powers the "Live Preview" button on the plugin page once it is enabled in the plugin's Advanced view on WordPress.org.
