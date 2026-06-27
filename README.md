# Contribute Enhancements

Companion module for [Omeka S Contribute](https://gitlab.com/Daniel-KM/Omeka-S-module-Contribute). Extends the patch-contribution workflow so reviewers and contributors can handle value removals, duplicate values, interim decisions, archiving, and contributor messaging without forking Contribute.

## Requirements

- Omeka S **4.0+**
- [Contribute](https://gitlab.com/Daniel-KM/Omeka-S-module-Contribute) module
- [Advanced Resource Template](https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedResourceTemplate) module (same as Contribute)

Optional companion: **InternetArchiveOutboundSync** (IA Outbound) auto-queues validated metadata revisions on items that already have Internet Archive identifiers. Internet Archive publishing is separate from Contribute email and status workflow.

## What it does

### Patch contributions and value removals

| Topic | Behaviour |
|-------|-----------|
| Proposal normalization | On save, editable values missing from the submitted proposal are stored as explicit removals |
| Duplicate values | Position-aware matching on validation so removing one of two identical literals works |
| Per-value decisions | Reviewers can accept or reject individual proposed removals before validation |
| Restore | Rejected removals can be restored from the admin review UI |
| Dedupe | User-submitted duplicate values stay distinct; duplicate entries for the same original collapse |

### Review workflow

- Workflow notice on admin contribution pages (validation, undertaking, email, archive)
- Undertaking syncs with validation status
- **Send message** dialog with templates (Accepted, Rejected, Needs changes, Added to Omeka)
- Manual archive of completed contributions (read-only audit records)

### Guest and admin UI

- Overrides Contribute admin list and browse templates (status labels, diff display, archive filter)
- Guest contribution show and browse pages (edit/delete rules, change history, archive state)
- Optional guest values partial with `(proposed removal)` labels

### Theme integration

Point `displayValues()` at the module guest template on your contribution show page:

```php
<?= $contribution->displayValues([
    'viewName' => 'contribute-enhancements/guest/contribution-values',
]) ?>
```

The module also ships a full `contribution-show.phtml` override via template map. Your theme can still override surrounding layout and styles.

If you omit the values partial change, admin fixes and proposal normalization still work; only guest After labels use default Contribute wording.

## Install (assumes use of [Ghent Docker](https://github.com/GhentCDH/Omeka-S-Docker))

1. Install Contribute and Advanced Resource Template first.
2. Install module files under `modules/ContributeEnhancements/` (bind-mount, git clone, or ZIP URL in `OMEKA_S_MODULES` — see below).
3. **Admin → Modules → Install → Activate** Contribute Enhancements.
4. Ensure the module loads **after** Contribute (module name sort order is usually sufficient).
5. Retest a patch contribution: delete one duplicate subject value, submit, review, validate.

Note: this module has not been tested with other Docker setups.

### Bind-mounting the module (Ghent Docker)

File: `compose.override.yaml`

```yaml
services:
  omeka:
    volumes:
      - ../ContributeEnhancements:/volume/modules/ContributeEnhancements
```

Use this for active development: edit the module repo, commit, and push; pull changes into each checkout as needed.

### Installing from a release ZIP (Ghent Docker)

Add a GitHub Release zip URL to `OMEKA_S_MODULES` in `.env`:

```env
OMEKA_S_MODULES="Common Contribute AdvancedResourceTemplate … https://github.com/c-host/ContributeEnhancements/releases/download/v1.3.3/ContributeEnhancements.zip"
```

On container start, Ghent Docker downloads the zip into `data/omeka/modules/` if that folder does not already exist. To upgrade, remove the module directory, bump the URL, restart the container, then **Admin → Modules → Upgrade**.

### Choosing bind-mount vs ZIP URL

| Approach | Best for |
|----------|----------|
| **Bind-mount** | Development; git pull in the mounted repo updates the running code |
| **ZIP URL in `.env`** | Simpler instance setup without compose overrides per module |

ZIP install extracts plain files (not a git repository). To publish module changes, release a new zip and update `.env` on each instance.

## Upgrading Contribute

Keep stock Contribute from upstream. Upgrade Contribute independently, then retest:

- Delete one of two duplicate literal values, save, submit.
- Admin review shows removal notice and `remove` process.
- Validate contribution; item should retain only the remaining value.

This module does not fork Contribute. It overrides selected view templates and listens to Contribute API events.

## Tests

Quick smoke tests (no Composer):

```bash
php test/smoke.php
```

PHPUnit (from module root):

```bash
composer install
composer test
```

## Companion modules

| Module | Role |
|--------|------|
| **Contribute** | Required — contribution forms and validation |
| **InternetArchiveOutboundSync** | Optional — publish validated metadata revisions to Internet Archive |
| **InternetArchiveInboundSync** | Optional — import items from Internet Archive into Omeka |

## Upgrade note

If you used an unpublished local build, **Admin → Modules → Upgrade** after pulling a new release. The archive table is created automatically on install and upgrade. Archiving data in `contribute_enhancements_archive` is preserved across upgrades.

## License

GPL-3.0-only — see [LICENSE](LICENSE).
