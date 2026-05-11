# Console Commands

## Fresh Setup (Full Import Order)

Run these commands in order for a clean database setup:

```bash
# 1. Create the database schema (if not already done)
php bin/console doctrine:migrations:migrate

# 2. Create CMS admin user (no client association)
php bin/console app:create-admin --clear

# 3. Create clients with their users
php bin/console app:create-clients --clear

# 4. Import products (parts) from XLSX with images
php bin/console app:import-parts --clear

# 5. Import machine categories + machines from XLSX with images
php bin/console app:import-machines-xlsx --clear

# 6. Link products to machines via category mappings
php bin/console app:import-part-machine-relations --clear
```

After running all 5 commands you should have:

| Entity              | Count |
|---------------------|-------|
| Admin users         | 1     |
| Clients             | 3     |
| Client users        | 13    |
| Products (parts)    | 194   |
| Machine categories  | 13    |
| Machines            | 20    |
| Machine-product links | 876 |

---

## Command Reference

### 1. `app:create-admin`

Creates the CMS admin user with `ROLE_ADMIN` (no client association).

```bash
php bin/console app:create-admin              # Create admin user
php bin/console app:create-admin --clear      # Delete and re-create
php bin/console app:create-admin --dry-run    # Preview only
```

| Email                              | Password         | Roles              |
|------------------------------------|------------------|--------------------|
| inquiry.deckard@deckard.hr   | StarAdmin2025!   | ROLE_USER, ROLE_ADMIN |

---

### 2. `app:create-clients`

Creates 3 clients (Grafit, Deckard, Deckard) with their users.

```bash
php bin/console app:create-clients              # Create clients + users
php bin/console app:create-clients --clear      # Delete and re-create
php bin/console app:create-clients --dry-run    # Preview only
```

**Clients created:**

| Client                           | Code       | Users | Admin email          |
|----------------------------------|------------|-------|----------------------|
| Grafit d.o.o.                    | GRAFIT     | 5     | admin@grafit.net     |
| Deckard d.o.o.                   | DECKARD    | 4     | admin@deckard.hr     |
| Deckard & Co Gesellschaft m.b.H. | DECKARD | 4 | admin@deckard.com |

**Passwords by client:**

| Client     | User password    | Admin password       |
|------------|------------------|----------------------|
| GRAFIT     | Grafit2025!      | GrafitAdmin2025!     |
| DECKARD    | Deckard2025!     | DeckardAdmin2025!    |
| DECKARD | Deckard2025!  | DeckardAdmin2025! |

---

### 3. `app:import-parts`

Imports 194 products from `src/Resources/parts/parts.xlsx` (sheets 1-5, skipping "All") with images auto-discovered from `src/Resources/parts/parts_images/`.

```bash
php bin/console app:import-parts                          # Import parts
php bin/console app:import-parts --clear                  # Delete all products first, then import
php bin/console app:import-parts --dry-run                # Preview only
php bin/console app:import-parts --batch-size=100         # Custom batch size (default: 50)
php bin/console app:import-parts --memory-limit=2G        # Custom memory limit (default: 1G)
```

**Data source:** `src/Resources/parts/parts.xlsx`
**Images:** `src/Resources/parts/parts_images/` (matched by `{partno_lowercase}_*.jpg`)

**Note:** `--clear` will cascade-delete related `order_item`, `client_product_price`, `machine_product`, and `media_item` rows before deleting products.

---

### 4. `app:import-machines-xlsx`

Imports 13 machine categories and 20 machines from `src/Resources/machines/machines.xlsx` with images from `src/Resources/machines_images/`.

```bash
php bin/console app:import-machines-xlsx                   # Import categories + machines
php bin/console app:import-machines-xlsx --clear           # Delete all machines/categories first
php bin/console app:import-machines-xlsx --dry-run         # Preview only
php bin/console app:import-machines-xlsx --batch-size=100  # Custom batch size (default: 50)
php bin/console app:import-machines-xlsx --memory-limit=2G # Custom memory limit (default: 1G)
```

**Data source:** `src/Resources/machines/machines.xlsx` (Sheet "Categories" + Sheet "Machines")
**Images:** `src/Resources/machines_images/`

**Note:** `--clear` will cascade-delete `machine_product`, `client_machine_installed_base`, machine `media_item` rows, then machines and categories.

---

### 5. `app:import-part-machine-relations`

Links products to machines via category mappings. Each product is assigned to ALL machines within its mapped category.

```bash
php bin/console app:import-part-machine-relations          # Create relations
php bin/console app:import-part-machine-relations --clear  # Clear machine_product table first
php bin/console app:import-part-machine-relations --dry-run # Preview only
```

**Data source:** `src/Resources/machine_part_relations/part_machine_relations.xlsx` (Sheet "Relations")

**Prerequisite:** Run `app:import-parts` and `app:import-machines-xlsx` first. This command reads products and machines from the database and links them.

---

### 6. `app:import-documentation`

Imports documentation from markdown files into the database.

```bash
php bin/console app:import-documentation /path/to/docs/              # Import .md files from directory
php bin/console app:import-documentation /path/to/docs/ --category=user-guide  # Assign category
php bin/console app:import-documentation /path/to/docs/ --update     # Update existing documents
php bin/console app:import-documentation /path/to/docs/ --dry-run    # Preview only
```

**Note:** Requires a directory path argument containing `.md` files.

---

### 7. `app:load-area-dummy-data`

Creates dummy data for the Area Management System (areas, managers, criteria, availability schedules).

```bash
php bin/console app:load-area-dummy-data
```

**Prerequisite:** At least 1 active client and 2 active users must exist in the database. Run `app:create-clients` first.

**Creates:** 5 areas (NA, EU, APAC, EU-WEST, EU-EAST), up to 5 area managers, 5 area criteria, and manager availability schedules.

---

## Utility Commands

### `app:send-test-mail`

Sends a test email to verify SMTP/mailer configuration.

```bash
php bin/console app:send-test-mail recipient@example.com
php bin/console app:send-test-mail recipient@example.com --subject="Custom Subject"
php bin/console app:send-test-mail recipient@example.com --template=emails/custom.html.twig
```

---

### `app:abas-test-connection`

Tests connectivity to the ABAS ERP middleware.

```bash
php bin/console app:abas-test-connection                    # Basic connectivity test
php bin/console app:abas-test-connection --probe            # Discover POST endpoints
php bin/console app:abas-test-connection --send-test        # Send a test inquiry
php bin/console app:abas-test-connection --url=http://custom:3000  # Override URL
```

---

## Quick Reference

| Command                           | Purpose                              | Depends on        |
|-----------------------------------|--------------------------------------|--------------------|
| `app:create-admin`                | CMS admin user                       | -                  |
| `app:create-clients`              | Clients + client users               | -                  |
| `app:import-parts`                | Products from XLSX + images          | -                  |
| `app:import-machines-xlsx`        | Machine categories + machines        | -                  |
| `app:import-part-machine-relations` | Product-machine links              | parts, machines    |
| `app:import-documentation`        | Docs from .md files                  | -                  |
| `app:load-area-dummy-data`        | Area management test data            | clients            |
| `app:send-test-mail`              | Test email sending                   | MAILER_DSN env     |
| `app:abas-test-connection`        | Test ABAS middleware                 | ABAS_INTERFACE_URL env |
