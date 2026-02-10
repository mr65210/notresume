# NotResume.com

An AI-powered one-page profile builder for job seekers.

## Quick Setup

### Requirements
- PHP 8.0+ with curl, zip extensions
- MySQL 5.7+ / MariaDB 10.3+
- Apache with mod_rewrite enabled
- `pdftotext` (optional, for PDF resume parsing — install via `poppler-utils`)

### Installation

1. **Upload all files** to your web root (e.g., `/var/www/html/` or `/public_html/`)

2. **Edit `config.php`** and update:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — your MySQL credentials
   - `SITE_URL` — your domain (e.g., `https://notresume.com`)
   - `RESEND_API_KEY` — get one from [resend.com](https://resend.com)
   - `ANTHROPIC_API_KEY` — get one from [console.anthropic.com](https://console.anthropic.com)
   - `FROM_EMAIL` — your verified sending domain email

3. **Create MySQL database** (the app auto-creates tables on first load):
   ```sql
   CREATE DATABASE notresume DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

4. **Enable Apache mod_rewrite**:
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

5. **Set directory permissions** (Apache needs write access for uploads):
   ```bash
   chmod 755 /path/to/your/webroot
   ```

6. **Configure Apache VirtualHost** to allow .htaccess:
   ```apache
   <Directory /var/www/html>
       AllowOverride All
   </Directory>
   ```

7. **(Optional) Install pdftotext** for PDF resume text extraction:
   ```bash
   sudo apt-get install poppler-utils
   ```

### File Structure

All files are in the root directory (no subfolders):

| File | Purpose |
|------|---------|
| `.htaccess` | URL rewriting, security headers |
| `config.php` | Configuration (DB, API keys) |
| `db.php` | Database connection & schema |
| `auth.php` | Authentication helpers |
| `style.css` | Main stylesheet |
| `app.js` | Frontend JavaScript |
| `index.php` | Landing page |
| `register.php` | User registration |
| `login.php` | User login |
| `verify.php` | Email verification |
| `logout.php` | Logout handler |
| `dashboard.php` | User dashboard |
| `create.php` | Create new page |
| `edit.php` | Edit existing page |
| `delete.php` | Delete a page |
| `page.php` | Public page viewer |
| `api_generate.php` | AI generation API |
| `resend-verification.php` | Resend verification email |

### How It Works

1. User registers with email/password
2. Email verification via Resend API
3. User creates a page by describing themselves or uploading a resume
4. AI (Claude via Anthropic API) generates beautiful HTML
5. HTML is sanitized for security and stored in MySQL
6. Page is accessible at `notresume.com/{slug}`

### Security

- CSRF protection on all forms
- HTML sanitization (removes scripts, iframes, event handlers, etc.)
- Password hashing with bcrypt
- SQL injection prevention via prepared statements
- Reserved slugs to prevent conflicts
- File upload validation

### API Keys Setup

**Resend (Email):**
1. Go to [resend.com](https://resend.com) and create an account
2. Verify your domain
3. Create an API key
4. Add it to `config.php` as `RESEND_API_KEY`

**Anthropic (AI):**
1. Go to [console.anthropic.com](https://console.anthropic.com)
2. Create an API key
3. Add it to `config.php` as `ANTHROPIC_API_KEY`
# notresume
