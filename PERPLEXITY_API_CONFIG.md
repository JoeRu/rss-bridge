# Perplexity Bridge Configuration Guide

The Perplexity Bridge supports API key authentication for accessing the Perplexity Discover feed. This guide shows you how to configure it in different deployment scenarios.

## Getting Your API Key

1. Go to [Perplexity API Settings](https://www.perplexity.ai/settings/api)
2. Sign in to your Perplexity account
3. Generate a new API key
4. Copy the key (it will look like `pplx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`)

## Configuration Methods

### Method 1: Docker Environment Variable (Recommended)

**Using docker-compose.yml:**

Edit your `docker-compose.yml`:

```yaml
services:
  rss-bridge:
    container_name: rss-bridge
    image: rssbridge/rss-bridge:latest
    volumes:
      - ./config:/config
    ports:
      - 3000:80
    environment:
      - PERPLEXITY_API_KEY=pplx-your-api-key-here
    restart: unless-stopped
```

**Using .env file (more secure):**

Create a `.env` file in the same directory as `docker-compose.yml`:

```env
PERPLEXITY_API_KEY=pplx-your-api-key-here
```

Then reference it in `docker-compose.yml`:

```yaml
services:
  rss-bridge:
    environment:
      - PERPLEXITY_API_KEY=${PERPLEXITY_API_KEY}
```

**Using docker run:**

```bash
docker run -d \
  --name rss-bridge \
  -p 3000:80 \
  -v ./config:/config \
  -e PERPLEXITY_API_KEY=pplx-your-api-key-here \
  rssbridge/rss-bridge:latest
```

### Method 2: config.ini.php File

**For Docker deployments:**

Create or edit `./config/config.ini.php`:

```ini
; <?php exit; ?> DO NOT REMOVE THIS LINE

[PerplexityBridge]
api_key = "pplx-your-api-key-here"
```

The Docker container will automatically copy this file to the correct location on startup.

**For non-Docker deployments:**

Create or edit `config.ini.php` in your RSS-Bridge root directory:

```ini
; <?php exit; ?> DO NOT REMOVE THIS LINE

[PerplexityBridge]
api_key = "pplx-your-api-key-here"
```

### Method 3: System Environment Variable

Set the environment variable before starting RSS-Bridge:

```bash
export PERPLEXITY_API_KEY="pplx-your-api-key-here"
php -S localhost:8000
```

## Priority Order

The bridge checks for the API key in this order:

1. **Environment variable** `PERPLEXITY_API_KEY`
2. **Config file** `config.ini.php` under `[PerplexityBridge]` section
3. **Legacy session token** (for backward compatibility)

## Security Best Practices

### For Docker:
- ✅ **DO** use a `.env` file and add it to `.gitignore`
- ✅ **DO** use Docker secrets for production deployments
- ❌ **DON'T** commit API keys to version control
- ❌ **DON'T** hardcode keys directly in docker-compose.yml

### For config.ini.php:
- ✅ **DO** set file permissions to `600` (read/write for owner only)
  ```bash
  chmod 600 config/config.ini.php
  ```
- ✅ **DO** add `config.ini.php` to `.gitignore`
- ❌ **DON'T** share your config file publicly
- ❌ **DON'T** commit it to version control

## Verification

Test your configuration by visiting:
```
http://localhost:3000/?action=display&bridge=PerplexityBridge&format=Json&limit=5
```

You should see JSON feed data with recent articles from Perplexity Discover.

## Troubleshooting

**Error: "API key is required"**
- Check that the environment variable is set correctly
- Verify the config.ini.php file exists and has the correct format
- Ensure the PHP syntax line is present: `; <?php exit; ?> DO NOT REMOVE THIS LINE`

**Empty or unauthorized response:**
- Verify your API key is valid and not expired
- Check that you've copied the full key including the `pplx-` prefix
- Ensure no extra spaces or quotes around the key value

**Docker container doesn't see the environment variable:**
- Restart the container after making changes: `docker-compose restart`
- Check environment variables inside container: `docker exec rss-bridge env | grep PERPLEXITY`

## Legacy Authentication (Session Tokens)

For backward compatibility, the bridge still supports session token authentication with cookies. However, API key authentication is recommended as it's simpler and more reliable.

## Example Usage

Once configured, use the bridge with these parameters:

- **limit**: Number of items (1-100, default: 20)
- **source**: Feed source (default, news, technology, science)
- **language**: Content language (en, de, fr, es, etc.)

Example URL:
```
http://localhost:3000/?action=display&bridge=PerplexityBridge&format=Atom&limit=10&source=technology&language=en
```
