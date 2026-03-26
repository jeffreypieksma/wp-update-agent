# WP Update Agent

Een veilige en betrouwbare WordPress plugin voor het beheren van updates via een REST API.

## Features

- **HMAC-SHA256 Authenticatie**: Alle requests worden geverifieerd met een shared secret en timestamp
- **Plugin Management**: Lijst, update, installeer, activeer en deactiveer plugins
- **Core Updates**: Check en update WordPress core
- **Language Updates**: Update taalbestanden
- **SMTP Testing**: Test de SMTP-configuratie
- **ZIP Install**: Installeer plugins via geüpload ZIP-bestand
- **Concurrent Lock**: Voorkomt gelijktijdige update operaties
- **Logging**: Alle acties worden gelogd

## Installatie

1. Upload de `wp-update-agent` map naar `/wp-content/plugins/`
2. Activeer de plugin via het 'Plugins' menu in WordPress
3. Ga naar Instellingen → WP Update Agent
4. Genereer een nieuwe secret key of stel er een in
5. Kopieer de secret key naar je externe applicatie (bijv. Next.js CMS)

## API Endpoints

### Main Execute Endpoint
```
POST /wp-json/agent/v1/execute
```

### SMTP Test Endpoint
```
POST /wp-json/agent/v1/test-smtp
```

### Status Endpoint
```
POST /wp-json/agent/v1/status
```

## Authenticatie

Alle requests moeten de volgende headers bevatten:

| Header | Beschrijving |
|--------|-------------|
| `X-Agent-Timestamp` | Unix timestamp (seconden) |
| `X-Agent-Signature` | HMAC-SHA256 signature |

### Signature berekening

```javascript
const payload = timestamp + '.' + body;
const signature = crypto.createHmac('sha256', secret)
                        .update(payload)
                        .digest('hex');
```

## Beschikbare Acties

### Plugin Acties

| Actie | Parameters | Beschrijving |
|-------|------------|--------------|
| `plugin_list` | - | Lijst alle geïnstalleerde plugins |
| `update_plugin` | `slug` | Update een specifieke plugin |
| `update_all_plugins` | - | Update alle plugins met beschikbare updates |
| `install_plugin_slug` | `slug`, `activate` (optional) | Installeer plugin van WordPress.org |
| `install_plugin_zip` | `file`, `activate` (optional) | Installeer plugin van ZIP |
| `activate_plugin` | `slug` | Activeer een plugin |
| `deactivate_plugin` | `slug` | Deactiveer een plugin |

### Core Acties

| Actie | Parameters | Beschrijving |
|-------|------------|--------------|
| `core_check` | - | Check voor WordPress core updates |
| `core_update` | `version` (optional) | Update WordPress core |
| `language_update` | - | Update taalbestanden |
| `system_status` | - | Haal systeem status op |

### SMTP Acties

| Actie | Parameters | Beschrijving |
|-------|------------|--------------|
| `smtp_test` | `to` (optional) | Test SMTP configuratie |
| `smtp_info` | - | Haal SMTP configuratie info op |

## Request Voorbeelden

### Plugin List
```json
{
  "action": "plugin_list"
}
```

### Update Plugin
```json
{
  "action": "update_plugin",
  "slug": "akismet"
}
```

### Install Plugin from Slug
```json
{
  "action": "install_plugin_slug",
  "slug": "contact-form-7",
  "activate": true
}
```

### Install Plugin from ZIP
```json
{
  "action": "install_plugin_zip",
  "file": {
    "filename": "my-plugin.zip",
    "content": "BASE64_ENCODED_ZIP_CONTENT"
  },
  "activate": true
}
```

### SMTP Test
```json
{
  "action": "smtp_test",
  "to": "test@example.com"
}
```

## Response Format

Alle responses volgen dit format:

```json
{
  "status": "success|error|partial",
  "action": "plugin_list|update_plugin|...",
  "updated": ["plugin-slug"],
  "failed": ["plugin-slug"],
  "logs": "Beschrijving of foutmeldingen",
  "message": "Optioneel, bij smtp_test of andere acties"
}
```

## JavaScript Client Voorbeeld

```javascript
const crypto = require('crypto');

class WPUpdateAgentClient {
  constructor(baseUrl, secret) {
    this.baseUrl = baseUrl;
    this.secret = secret;
  }

  async request(action, params = {}) {
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const body = JSON.stringify({ action, ...params });
    
    const payload = timestamp + '.' + body;
    const signature = crypto.createHmac('sha256', this.secret)
                            .update(payload)
                            .digest('hex');

    const response = await fetch(`${this.baseUrl}/wp-json/agent/v1/execute`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Agent-Timestamp': timestamp,
        'X-Agent-Signature': signature
      },
      body: body
    });

    return response.json();
  }

  // Plugin methods
  async listPlugins() {
    return this.request('plugin_list');
  }

  async updatePlugin(slug) {
    return this.request('update_plugin', { slug });
  }

  async updateAllPlugins() {
    return this.request('update_all_plugins');
  }

  async installPlugin(slug, activate = false) {
    return this.request('install_plugin_slug', { slug, activate });
  }

  async activatePlugin(slug) {
    return this.request('activate_plugin', { slug });
  }

  async deactivatePlugin(slug) {
    return this.request('deactivate_plugin', { slug });
  }

  // Core methods
  async checkCoreUpdate() {
    return this.request('core_check');
  }

  async updateCore() {
    return this.request('core_update');
  }

  async updateLanguages() {
    return this.request('language_update');
  }

  // SMTP methods
  async testSmtp(to = null) {
    return this.request('smtp_test', to ? { to } : {});
  }

  // Status
  async getStatus() {
    return this.request('system_status');
  }
}

// Usage
const client = new WPUpdateAgentClient('https://mysite.com', 'my-secret-key');
const plugins = await client.listPlugins();
console.log(plugins);
```

## Security

- **Plugin Slug Validatie**: Alleen `[a-z0-9\-]+` is toegestaan
- **ZIP Validatie**: 
  - Alleen `.zip` bestanden
  - Maximaal 10MB
  - Geen path traversal
- **Timestamp Validatie**: Requests ouder dan 5 minuten worden geweigerd
- **Lock Mechanisme**: Slechts één actie tegelijk per site
- **Logging**: Alle acties worden gelogd naar bestand en transient

## Vereisten

- WordPress 5.9+
- PHP 7.4+

## Licentie

GPL v2 or later
