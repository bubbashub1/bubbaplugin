# Bubba Hub Independent Disaster Recovery

**Status: design/inventory phase — not operational and not yet protecting production.**

This recovery system is for the standalone Bubba Hub application. It must not depend on WordPress, the Bubba Hub frontend/admin, or the eWebAll server remaining available.

## Scope

Protect and recover, as one matched recovery point:

- Standalone application files deployed from `bubbaplugin`.
- Production MySQL data used by the standalone application.
- User-uploaded files and other runtime data not tracked in Git.
- Required server-side configuration, without exposing secrets in GitHub or backup manifests.
- WordPress only as a separately inventoried supporting installation, not as the primary CMS.

GitHub is the source of versioned code. It is not the backup destination for production databases, credentials, user uploads or personal data.

## Required architecture

1. **Independent scheduler:** a scheduled GitHub Actions workflow or another external scheduler initiates backups. It must not require a working website/admin.
2. **Read-only collection:** use a restricted SSH account/command and database account with only the permissions needed to export data and read protected application files.
3. **Consistent database export:** use a transaction-consistent MySQL dump where supported. Record database identity, export time, exit status and checksum.
4. **Files and uploads:** archive the deployed application and runtime uploads/data. Exclude caches, temporary files and secrets unless separately encrypted and specifically required for recovery.
5. **Client-side encryption:** encrypt before data leaves the trusted backup process. Keep encryption/recovery keys outside GitHub and outside eWebAll. Never log them.
6. **Off-site storage:** use a separate storage provider/account, not another directory, database or backup product on the same eWebAll hosting account.
7. **Retention and integrity:** retain daily, weekly and monthly recovery points; verify object checksums and periodically test restoration.
8. **Independent recovery:** documented procedure and tooling must work when the live site and WordPress are unavailable. Rebuild on staging or alternate hosting first.
9. **Human approval:** never replace production automatically. A live restore requires a verified staging restore, an additional pre-restore snapshot and explicit approval.

## Initial repository/hosting findings

- `bubbaplugin` is documented as the standalone application and master repository.
- `api/db.php` reads credentials from a server-only `github-deploy-config.php`, outside the repository. Credentials must remain there or in a dedicated secret manager; never commit that file.
- `api/health.php` provides a safe application/database health check, but it is not a recovery mechanism.
- `.github/workflows/deploy.yml` already uses GitHub Actions SSH secrets to deploy the standalone application to eWebAll `public_html`.
- The current deployment workflow is a deployment path, not a disaster-recovery backup. It does not establish that production data or uploads are safely backed up.
- The exact production/staging file roots, database names/permissions, upload locations, available server utilities, cron availability, external storage and recovery credentials still require a read-only environment inventory.

## Decisions required before implementation

- Select an off-site storage provider/account with sufficient capacity and lifecycle/retention support.
- Establish how its access credentials and the encryption recovery key will be held independently and securely.
- Verify whether the eWebAll account permits the required read-only SSH export and whether database dump utilities are available.
- Inventory which paths contain user uploads/runtime data and which configuration values are essential for a rebuild.
- Confirm a separate recovery host/staging target and the DNS/domain procedure for a hosting outage.

## Phased delivery

### Phase 1 — inventory (no production writes)
Document exact paths, databases, data owners, utilities, storage capacity, cron/scheduler access and secret locations. Do not print credentials or sensitive database contents.

### Phase 2 — backup creation and off-site storage
Implement an external scheduled backup, encrypted before upload, with matched file/database manifests, retention, alerts and failure handling. Do not delete old backups until a new backup has passed integrity verification.

### Phase 3 — restore tooling and staging drill
Restore a selected recovery point to an isolated staging/alternate host. Verify files, database connection, key API health checks, uploads and representative read-only application journeys.

### Phase 4 — recovery controls and runbook
Provide a minimal status/reporting view only after the independent process works. Document recovery steps that remain usable without the live application.

### Phase 5 — production readiness
Run and record a disaster-recovery exercise. Production activation requires verified off-site copies, tested encryption-key recovery, a successful staging restore and explicit approval.

## Safety rules

- No production writes, schema changes, live file replacement or deployment as part of the initial inventory.
- No public unauthenticated backup/restore endpoint.
- No secrets, passwords, private keys, encryption keys, personal data or database dumps in GitHub.
- Never treat a successful archive/upload as proof that restore works.
- Never claim protection is active until an off-site backup and a restore test have both succeeded.
