# Bubba Hub database configuration

The standalone APIs use a private, per-environment PHP configuration file outside the public web root. This keeps live and staging credentials separate and prevents Git deployments from overwriting them.

## Required files

Create these files in the hosting account's home directory (the parent directory containing `public_html` and `staging_html`):

- `database-config-public_html.php` for live
- `database-config-staging_html.php` for staging

Each file must return an array. Use this structure:

```php
<?php
return [
    'mysql_host' => 'YOUR_DATABASE_HOST',
    'mysql_database' => 'YOUR_DATABASE_NAME',
    'mysql_username' => 'YOUR_DATABASE_USERNAME',
    'mysql_password' => 'YOUR_DATABASE_PASSWORD',
    'mysql_charset' => 'utf8',
];
```

Use the live database credentials only in the live file, and the staging database credentials only in the staging file. Do not commit either private file to GitHub and do not paste passwords into chat.

The existing `github-deploy-config.php` remains available for deployment and admin settings. Its values are used as a fallback, while the environment-specific database file takes precedence.

## Safety

- Do not copy the live password into the staging file.
- Do not put either file inside `public_html` or `staging_html`.
- Do not add credentials to `wp-config.php` in GitHub or to any repository file.
- After creating the files, test staging first, then live.
