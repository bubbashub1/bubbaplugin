# BubbaPlugin

Standalone Bubba Hub family activity directory and planner.

This repository is intentionally independent of WordPress, Directorist, ACF, Ninja Forms and other CMS/plugin dependencies.

## Status

Standalone front-end foundation with directory, activity detail, My Planner, My Hub, account preferences and admin shell.

## Deployment

GitHub Actions deployment workflow is included in `.github/workflows/deploy.yml` for GitHub Pages.

The next production stage is a real API/database for authenticated admin, organiser, family, booking and planner data.
## File Manager

The File Manager is available at `/library/` and manages the repository image folders:
- `images/listings/`
- `images/home/`
- `images/logos/`
- `images/general/`

Before using it, add a private key to the existing server-side `github-deploy-config.php`:

```php
'file_manager_key' => 'YOUR_LONG_RANDOM_PRIVATE_KEY',
```

The File Manager sends uploads through the existing server-side GitHub token, so the GitHub token is never exposed to the browser. Images are committed into the appropriate `images/` folder and the existing deployment workflow publishes the updated image folders to the server. Uploads are limited to JPG, JPEG, PNG, WebP and GIF, up to 8 MB each.
