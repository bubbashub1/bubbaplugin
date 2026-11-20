# Bubba Hub Site Manager (foundation)

Branch: `staging/site-manager-foundation-20261120`

## What this iteration adds
- A WordPress admin submenu under the existing Bubba Hub admin menu: **Site Manager**.
- Global palette controls (primary/accent/background/text), font controls, card corner/gap and button corner controls.
- Live preview of a sample component in the settings screen.
- Draft/publish toggle. Saved drafts are retained in the WordPress option but are not applied to the public site.
- Published values are injected as CSS variables and a deliberately limited set of global styles.

## Installation
The MU-plugin and its companion CSS/JS files are placed in `wp-content/mu-plugins/`. MU plugins load automatically. This must be deployed to staging and checked in the actual hosting environment before any live use.

## Scope and limitations
This is the first foundation, not a full visual editor. CSS variable values do not yet control all custom standalone components. Next work should inventory the existing design systems and safely map selected components to these variables, add revision/restore and staging-only safety, and then implement page/section controls. Avoid broad selectors or injecting global styles beyond the controlled variables.

## Safety
- Settings require WordPress `manage_options` permission.
- Saving is protected with a WordPress nonce.
- Settings are validated against fixed font and spacing choices and sanitized colours.
- Published state is off by default.
- This branch is based on `staging/bubba-manager-mu-plugin`; it must not be merged into main/live until staging verification.
