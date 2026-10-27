# HERMON Group website

WordPress site for [hermongroup.co.mz](https://hermongroup.co.mz), rebuilt clean after the 2026 compromise:
Hello Elementor + Pro Elements, bilingual with Polylang (EN at `/`, PT at `/pt/`).

`HermonGroupWebsite-final/` is the full `public_html`:

- `wp-content/mu-plugins/hermon-site-guard.php`: hardening, e-mail alerts on new admins/plugins, daily backdoor scan, WebP on upload.
- `wp-content/mu-plugins/hermon-multilingual.php`: `[hermon_lang]` switcher, header layout, Service/Organization schema.
- `.htaccess`: https canonical redirect, blocks stray PHP (webshells), security headers, WebP serving, caching.

## Not in this repository

- **`wp-config.php`**: copy `wp-config-sample.php`, fill in the DB credentials and generate new keys at https://api.wordpress.org/secret-key/1.1/salt/. Table prefix is `wpkv_`.
- **Full database dump**: kept privately.

## Public database: `hermongr_wp460_public.sql`

This is the site's content and settings (pages, Elementor layouts, menus, Polylang, Yoast), with everything sensitive removed:

- the admin's e-mail and password hash;
- `secret_key` and the recovery keys;
- the Instagram token;
- license keys;
- Freemius and Elementor Connect data;
- transients.

After importing it, give the admin back an e-mail and password in phpMyAdmin (WordPress rehashes the MD5 on first login):

```sql
UPDATE wpkv_users SET user_email = 'you@example.com', user_pass = MD5('a-strong-password') WHERE ID = 1;
UPDATE wpkv_options SET option_value = 'you@example.com' WHERE option_name IN ('admin_email', 'new_admin_email');
```

Then reconnect the Instagram feed (Smash Balloon) in wp-admin.

## Deploy (DirectAdmin)

1. PHP 8.3 or newer (Contact Form 7 requires it).
2. Create the MySQL database and import the private dump.
3. Upload the contents of `HermonGroupWebsite-final/` to `public_html` and create `wp-config.php`.
4. Issue the Let's Encrypt certificate (the `.htaccess` forces https).
5. In wp-admin: save Permalinks, Elementor → Regenerate files, LiteSpeed Cache → Purge All.
