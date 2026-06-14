# SplitTrack — Shared Expenses App

## What it is
A full-stack shared expense tracker built for four flatmates (Aisha, Rohan, Priya, Meera) and their extended household. Handles time-aware group membership, four expense split types, foreign-currency conversion, debt settlement, and a CSV import with full anomaly detection and an inline report.

## Tech Stack
| Layer | Choice | Why |
|-------|--------|-----|
| Framework | Laravel 12 | PHP MVC, built-in auth, Eloquent ORM |
| Database | SQLite | Zero-config, relational, file-based — easy to inspect |
| Frontend | Blade + Vanilla CSS | No JS framework overhead; server-rendered for simplicity |
| Build | Vite | Fast asset bundling |

## AI Used
Claude Sonnet (via Antigravity IDE assistant) was used as the primary development collaborator.  
See `AI_USAGE.md` for details, including 3+ cases where the AI was wrong and how it was corrected.

## Setup Instructions

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+

### Installation

```bash
# 1. Install PHP dependencies
composer install

# 2. Copy environment file
cp .env.example .env

# 3. Generate app key
php artisan key:generate

# 4. Create SQLite database
touch database/database.sqlite

# 5. Run migrations and seed demo data
php artisan migrate:fresh --seed

# 6. Install JS dependencies and build assets
npm install && npm run build

# 7. Start the development server
php artisan serve
```

App will be live at http://localhost:8000

### Demo Login Credentials
All accounts have password: `password`

| Name  | Email                    | Role         |
|-------|--------------------------|--------------|
| Aisha | aisha@flat4b.test        | Original flatmate |
| Rohan | rohan@flat4b.test        | Original flatmate |
| Priya | priya@flat4b.test        | Original flatmate |
| Meera | meera@flat4b.test        | Left March 31 |
| Dev   | dev@flat4b.test          | Goa trip only |
| Sam   | sam@flat4b.test          | Joined April 15 |

### Importing the CSV
1. Log in as any user
2. Go to the "Flat 4B" group
3. Click "Import CSV" in the sidebar
4. Upload `expenses_export.csv` from the project root
5. Click "View Report" to see all 21 anomalies detected

## Database Schema Overview
See `SCOPE.md` for the full schema and anomaly catalogue.

## Key Design Decisions
See `DECISIONS.md` for documented decisions with alternatives considered.

## Testing
```bash
php artisan test
```

## Repository Structure
```
app/
  Http/Controllers/    # 6 controllers (Auth, Group, Expense, Balance, Settlement, Import)
  Models/              # 8 Eloquent models
  Services/
    SplitCalculator.php   # Pure functions: compute per-person shares
    BalanceCalculator.php # Pure functions: net balances + greedy settle-up
    CsvImporter.php       # Two-phase CSV import with anomaly detection
database/
  migrations/          # 8 migration files
  seeders/             # Demo data seeder
resources/
  views/               # 18 Blade templates
  css/app.css          # Glassmorphism dark-mode design system
expenses_export.csv    # The CSV from the assignment (21 deliberate issues)
SCOPE.md               # Anomaly catalogue + DB schema
DECISIONS.md           # Engineering decision log
AI_USAGE.md            # AI collaboration notes
```
