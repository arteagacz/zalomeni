# Zalomení

[![Tests](https://github.com/vyskoczilova/zalomeni/actions/workflows/tests.yml/badge.svg)](https://github.com/vyskoczilova/zalomeni/actions/workflows/tests.yml)

A WordPress plugin that applies Czech typographic rules to your site's output, replacing ordinary spaces with non-breaking spaces wherever Czech typography forbids a line break.

**[Zalomení on WordPress.org →](https://wordpress.org/plugins/zalomeni/)**

Maintained by [Karolína Vyskočilová](https://profiles.wordpress.org/vyskoczilova/#content-plugins). Originally created by Honza Skýpala.

## What it does

Zalomení hooks WordPress output filters and inserts `&nbsp;` where a line break would be wrong:

- **Prepositions and conjunctions** — single letters like *k*, *s*, *v*, *z*, *a*, *i*, *o*, *u* must not end a line
- **Abbreviations** — *např.*, *tj.*, *tzv.*
- **Numbers and units** — *5 kg*, *10 Kč*
- **Number formatting** — phone numbers like *800 123 456* stay together
- **Ordinal numbers** — dates like *1. ledna*
- **Scales and ratios** — *1 : 50 000*
- **Custom terms** — your own multi-word terms

Every list is editable under **Settings → Reading**, so the plugin adapts to Slovak and other closely related languages without any code changes.

Content is rewritten on output only — nothing in the database is modified, and deactivating the plugin removes every inserted non-breaking space.

## Requirements

| | |
|---|---|
| WordPress | 6.0+ |
| PHP | 7.0+ |
| License | GPL-2.0-or-later |

## Extending

The `zalomeni_filtry` filter controls which WordPress hooks Zalomení attaches to:

```php
add_filter( 'zalomeni_filtry', 'remove_title_from_zalomeni' );
function remove_title_from_zalomeni( array $filters ) {
	unset( $filters['the_title'] );
	return $filters;
}
```

By default it applies to `comment_author`, `term_name`, `link_name`, `link_description`, `link_notes`, `bloginfo`, `wp_title`, `widget_title`, `term_description`, `the_title`, `the_content`, `the_excerpt`, `comment_text`, `single_post_title`, and `list_cats`.

## Development

```bash
composer install
php vendor/bin/phpunit
```

This suite uses [WP_Mock](https://github.com/10up/wp_mock) with PHPUnit 9.6 and needs PHP 7.4+; the plugin itself targets PHP 7.0+. It runs without WordPress or Bricks Builder. The original tests check the existing HTML and plain-text behavior; `BricksSupportTest.php` adds registration, decoded-text rules and failure handling. Expected strings are written in the tests. The missing-processor check covers this mock environment; it does not establish behavior on an older WordPress installation.

The separate integration script needs an existing WordPress installation with this checkout active as its Zalomení plugin. It checks the loaded plugin's filename and fails if WordPress loads another checkout. Set `ZALOMENI_WP_LOAD` to that installation's readable `wp-load.php`, then run from this directory (POSIX shell example):

```bash
export ZALOMENI_WP_LOAD=/path/to/wordpress/wp-load.php
php tests/bricks-support.php --group=legacy
php tests/bricks-support.php --group=bricks
php tests/bricks-support.php --group=bricks --context=builder
php tests/bricks-support.php --group=bricks --context=public-rest
php tests/bricks-support.php --group=bricks --context=builder-rest
php tests/bricks-support.php --group=compatibility
```

In PowerShell, set the variable with `$env:ZALOMENI_WP_LOAD = 'C:/path/to/wordpress/wp-load.php'`. Use a fresh PHP process for each command because Bricks Builder caches its request classification.

- `legacy` compares the original compiler arrays and output against literal values captured from the original plugin at `e05c652` on WordPress 7.1.3 / PHP 8.4.24. Upstream commit `c22ec3baf5fc8c1bba05251f30236d8e3117088e` has the same original `zalomeni.php` blob (`b0dd4a7ee5879815365f9636f00a3bfc411ccb42`).
- `bricks` requires active Bricks Builder and the real WordPress HTML text-update API (WordPress 6.7+). It checks specification examples, protected markup, repeated processing, failure fallback, registration and real Bricks Builder rendering for content, headers, footers and popups. The priority-10 marker replacement checks earlier-filter ordering; it is not proof of a real post-dependent dynamic provider. If a suitable published section/content template exists, the script also renders it nested inside a parent; otherwise it reports `UNVERIFIED`. No database fixtures are saved.
- The three Bricks Builder contexts check that builder and builder REST output stays unchanged while public REST output receives typography. These simulate request context in standalone PHP processes; they are not browser or HTTP endpoint tests.
- `compatibility` checks unchanged HTML when the required HTML text-update API is absent. It reports `NOT APPLICABLE` when that API exists. Both `legacy` and `compatibility` can run without Bricks Builder. A run on a current WordPress installation does not establish WordPress 6.0/6.7 or PHP 7.0 compatibility.

Bricks Builder expectations are literal specification values, separate from the captured legacy output. The real renderer checks target Bricks Builder 2.4.3. These integration checks are separate from PHPUnit discovery and CI. The existing GitHub Actions workflow runs on pushes to `main` and pull requests targeting `main`; pushing a feature branch without a pull request does not run it.

See [CLAUDE.md](CLAUDE.md) for the pre-release checklist, the distribution payload, and the automated WordPress.org deploy flow.

## Reporting security issues

Please report security bugs through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/9e5fc6bf-7462-4a23-a890-9bf16e3d30ca) rather than the public issue tracker. Patchstack will handle verification, CVE assignment, and notifying the maintainer.

## Changelog

The full changelog lives in [readme.txt](readme.txt). Highlights:

- **2.0.1** — fixed a stray non-breaking space in English possessives (`America&#8217;s`) and a leading-boundary anchor that never matched, so prepositions opening a paragraph now get their `&nbsp;`
- **2.0.0** — security release under a new maintainer: sanitize callbacks on every setting, regex inputs escaped, admin output escaped, PHPUnit coverage added, license changed from WTFPL to GPL-2.0-or-later
