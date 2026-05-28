# Database Migration System for gmcore_core

## Migration File Structure

```
mariadb/flyway/gmcore_core/
├── flyway.conf          # Flyway configuration
└── sql/                 # Migration files
    ├── V1__baseline.sql # Initial schema (gmcore_core.sql)
    ├── V2__add_new_column.sql
    └── V3__create_index.sql
```

## Naming Convention

Migration files **must** follow this pattern:
```
V<VERSION>__<DESCRIPTION>.sql
```

- **V** - Prefix (capital V)
- **VERSION** - Version number (1, 2, 3, or 1.0, 1.1, 2.0, etc.)
- **__** - Double underscore separator
- **DESCRIPTION** - Brief description using underscores for spaces
- **.sql** - File extension

### Examples:
- ✅ `V2__add_user_email_column.sql`
- ✅ `V3__create_audit_log_table.sql`
- ✅ `V4__add_indexes_to_bans.sql`
- ✅ `V2.1__hotfix_user_permissions.sql`
- ❌ `v2__migration.sql` (lowercase v)
- ❌ `V2_migration.sql` (single underscore)
- ❌ `migration.sql` (no version)

## Creating a New Migration

1. **Create the migration file** in `mariadb/flyway/gmcore_core/sql/`:

2. **Test the migration** by running:
   ```bash
   docker-compose up flyway-gmcore-core
   ```

3. **Verify** the migration was applied:
   ```bash
   docker-compose exec mariadb mariadb -u$MARIADB_USER -p$MARIADB_PASSWORD gmcore_core -e "SELECT * FROM flyway_schema_history;"
   ```

## Migration Best Practices

### DO:
- **Keep migrations small and focused** - One logical change per migration
- **Use descriptive names** - `V2__add_email_to_users.sql` not `V2__changes.sql`
- **Test locally first** - Always test migrations before deploying
- **Make migrations idempotent where possible** - Use `IF NOT EXISTS`, `IF EXISTS`
- **Add indexes separately** - Create indexes in separate migrations for large tables
- **Document complex changes** - Add SQL comments explaining the why
- **Use transactions** - Wrap DDL in transactions when possible

### DON'T:
- **Never modify existing migrations** - Once applied, they're immutable
- **Don't use database-specific features** without documenting
- **Avoid mixing DDL and DML** - Keep schema changes separate from data changes
- **Don't depend on external files** - All SQL should be self-contained

## Data Migration
```sql
-- V5__migrate_old_ban_data.sql
-- Migrate data from old table to new table
INSERT INTO bans (steamid, name, reason, banned_on, unban_time, banned_by, server)
SELECT
    steamid,
    COALESCE(name, 'Unknown'),
    reason,
    banned_on,
    unban_time,
    banned_by,
    server
FROM _bans_old
WHERE void = 'N';
```

## Running Migrations

### Development
```bash
# Start stack (migrations run automatically)
docker-compose up -d

# Or run migrations manually
docker-compose up flyway-gmcore-core

# View migration history
docker-compose exec mariadb mariadb -u$MARIADB_USER -p$MARIADB_PASSWORD gmcore_core \
  -e "SELECT installed_rank, version, description, success, installed_on FROM flyway_schema_history;"
```

### Skipping Migrations
If you need to mark a migration as applied without running it:
```bash
# This is dangerous - only for emergency recovery
docker-compose run --rm flyway-gmcore-core baseline -baselineVersion=<version>
```

### Rolling Back
Flyway Community Edition doesn't support automatic rollbacks. To rollback:
1. Create a new migration that reverses the changes
2. Name it with the next version number: `V6__rollback_feature_x.sql`

## Flyway Commands

```bash
# Run pending migrations
docker-compose up flyway-gmcore-core

# Show migration history
docker-compose run --rm flyway-gmcore-core info

# Validate migrations
docker-compose run --rm flyway-gmcore-core validate

# Repair schema history (removes failed migrations)
docker-compose run --rm flyway-gmcore-core repair

# Get Flyway info
docker-compose run --rm flyway-gmcore-core -v
```

## Environment Variables

Flyway uses these environment variables from your `.env` file:
- `MARIADB_USER`: Database user
- `MARIADB_PASSWORD`: Database password

The MariaDB host is configured in `flyway.conf` as `mariadb` (Docker service name).

## CI/CD Validation

A GitHub Actions workflow automatically validates all migrations on pull requests and pushes.

**What it validates:**
- Migration file naming convention (`V<VERSION>__<DESCRIPTION>.sql`)
- No duplicate version numbers
- SQL syntax is valid
- Migrations apply successfully to a fresh database
- Migrations are idempotent (can run multiple times safely)
- Schema history is properly tracked

**View results:**
- Check status on pull requests
- View detailed logs in GitHub Actions tab
- See summary in PR checks

**Local validation before pushing:**
```bash
# Test your migration locally first
docker-compose down -v
docker-compose up -d mariadb
docker-compose up flyway-gmcore-core

# Verify
docker-compose exec mariadb bash -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" gmcore_core -e "SELECT * FROM flyway_schema_history;"'
```