# Database configuration (live and staging)

The standalone PHP APIs obtain their MySQL credentials from server-side files. Never commit credentials or paste passwords into GitHub issues or chat.

## Preferred environment-specific files

The API derives the environment from the document-root folder name and first checks for a private file one directory above that web root:

- Live: `database-config-public_html.php`
- Staging: `database-config-staging_html.php`

For the hosting layout where `public_html` and `staging_html` are siblings, both files belong in their shared parent (the hosting account's private home directory), not inside either public web root.

Each file should return an array using either supported shape.

Flat keys:

```php
<?php
return [
    'mysql_host' => 'YOUR_DATABASE_HOST',
    'mysql_database' => 'YOUR_DATABASE_NAME',
    'mysql_username' => 'YOUR_DATABASE_USERNAME',
    'mysql_password' => 'YOUR_DATABASE_PASSWORD',
    'mysql_charset' => 'utf8mb4',
];
```

Or nested keys:

```php
<?php
return [
    'db' => [
        'host' => 'YOUR_DATABASE_HOST',
        'name' => 'YOUR_DATABASE_NAME',
        'user' => 'YOUR_DATABASE_USERNAME',
        'pass' => 'YOUR_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'mysql_charset' => 'utf8mb4',
];
```

Use the live database credentials only in the live file and staging credentials only in the staging file.

## Fallbacks

If no complete environment-specific file is found, `api/db.php` checks the shared `github-deploy-config.php` locations and then reads literal `DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASSWORD` values from the environment's `wp-config.php` without including or executing that file.

The shared deployment configuration may use either the nested `db` shape above or the legacy flat `mysql_host`, `mysql_database`, `mysql_username` and `mysql_password` keys. An incomplete candidate is skipped so a later complete source can be checked.

## Safety and verification

- Never put database credentials in this repository, browser code, or a public web directory.
- Never copy live credentials into staging.
- Do not overwrite the shared deployment configuration when changing database credentials.
- After a configuration change, test staging first and confirm its API connects to the staging database before testing live.
- If the API reports missing configuration, check the private file name, location, returned array keys, and file permissions. Do not paste the password into an issue when asking for help.
