---
name: github-plugin-updater
description: "Add GitHub auto-update functionality to a WordPress plugin. Use when: adding GitHub releases updater, private repo updater, GitHub token management, auto-update from GitHub, plugin self-update via GitHub."
---

# GitHub Plugin Updater

Add private-repo GitHub auto-update support to any WordPress plugin. Uses the GitHub Releases API, encrypted token storage, and hooks into WordPress's native update transient.

## When to Use
- Adding self-update capability to a custom WordPress plugin hosted on GitHub
- Setting up private repository token management (encrypted, stored in DB)
- Integrating GitHub releases with the WordPress plugin update UI

## Architecture

The updater consists of three parts:

1. **PHP Class** (`class-github-updater.php`) — hooks into WordPress update system
2. **REST API endpoints** — token CRUD (GET / POST / DELETE) at `/wp-json/{plugin-slug}/v1/github-token`
3. **Admin JS** — token management UI card embedded in the plugin settings page

## Procedure

### 1. Create the Updater Class

Create `includes/class-github-updater.php` (or equivalent path). The class must:

- Accept `$repo` (owner/repo), `$plugin_file`, and `$basename` in the constructor
- Hook into these WordPress filters/actions:
  - `pre_set_site_transient_update_plugins` → inject update data
  - `plugins_api` → provide "View details" popup info
  - `upgrader_post_install` → fix directory name after ZIP extraction
  - `http_request_args` → add auth header for private repo downloads
  - `rest_api_init` → register token management REST routes

Key constants to define per plugin:
- `{PREFIX}_GITHUB_TOKEN` — wp-config.php fallback constant
- DB option key for encrypted token (e.g. `{prefix}_github_token`)
- Transient cache key (e.g. `{prefix}_github_update`)
- Cache TTL: 43200 seconds (12 hours)

### 2. Token Encryption

Tokens are encrypted with AES-256-CBC using WordPress `AUTH_KEY` as the salt:

```php
private static function get_encryption_key(): string {
    $salt = defined('AUTH_KEY') ? AUTH_KEY : '{plugin-slug}-fallback-key';
    return hash('sha256', $salt, true);
}
```

Token priority: 1) Encrypted DB token, 2) Constant in wp-config.php.

### 3. Wire Up in Main Plugin File

```php
// Require the file (outside is_admin check so REST routes register).
require_once PLUGIN_DIR . 'includes/class-github-updater.php';

// Instantiate (also outside is_admin check).
new GitHub_Updater_Class(
    'owner/repo-name',
    __FILE__,
    plugin_basename(__FILE__)
);
```

### 4. Admin UI (Settings Tab)

Add a card div with a unique ID to the settings page:

```html
<div class="card">
    <h3>GitHub Auto-Updater</h3>
    <div id="{prefix}-github-token-card"></div>
</div>
```

Enqueue the JS file only on the plugin admin page (settings tab):

```php
wp_enqueue_script('{prefix}-github', PLUGIN_URL . 'assets/js/github.js', [], VERSION, true);
wp_localize_script('{prefix}-github', 'pluginVar', [
    'restBase' => esc_url_raw(rest_url('{plugin-slug}/v1')),
    'nonce'    => wp_create_nonce('wp_rest'),
]);
```

### 5. JavaScript Token Management

The JS file should:
- Fetch current token state on load (GET)
- Render status (active/inactive), masked token, source label
- Provide input + save button (POST)
- Provide delete button when DB token exists (DELETE)
- Show inline feedback messages

## GitHub Release Requirements

- Tag releases as `v1.0.0` or `1.0.0` (leading `v`/`V` is stripped)
- The tag version must be higher than the plugin header `Version:` to trigger an update
- Release body text becomes the changelog in "View details"

## Checklist

- [ ] Updater class created with all 5 hooks
- [ ] REST routes registered for GET/POST/DELETE token
- [ ] Token encryption uses AES-256-CBC with AUTH_KEY salt
- [ ] Class required outside `is_admin()` block
- [ ] Instantiated with correct owner/repo
- [ ] Admin card with unique ID added to settings page
- [ ] JS file created and enqueued with localized REST base + nonce
- [ ] `post_install` renames extracted directory to match plugin slug
- [ ] Cache transient cleared when token is saved/deleted
