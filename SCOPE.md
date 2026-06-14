# SCOPE.md — Anomaly Catalogue & Database Schema

## Database Schema

### Table: `users`
| Column | Type | Notes |
|--------|------|-------|
| id | INTEGER PK | |
| name | TEXT | Display name (e.g. "Aisha") |
| email | TEXT UNIQUE | Login email |
| password | TEXT | Bcrypt hash |
| remember_token | TEXT NULL | Laravel auth |
| created_at / updated_at | TIMESTAMP | |

### Table: `groups`
| Column | Type | Notes |
|--------|------|-------|
| id | INTEGER PK | |
| name | TEXT | "Flat 4B" |
| home_currency | TEXT(3) | Default "INR" |
| description | TEXT NULL | |
| created_by | INTEGER FK users | |

### Table: `group_memberships`
| Column | Type | Notes |
|--------|------|-------|
| id | INTEGER PK | |
| group_id | INTEGER FK groups | |
| user_id | INTEGER FK users | |
| joined_on | DATE | "2026-02-01" |
| left_on | DATE NULL | NULL = still active |

**Design note:** Membership is a timeline — a member can leave and rejoin (two separate rows). The importer checks `joined_on ≤ expense_date ≤ left_on` (or left_on IS NULL) to decide whether a person counts as active for a given expense.

### Table: `expenses`
| Column | Type | Notes |
|--------|------|-------|
| id | INTEGER PK | |
| group_id | FK groups | |
| description | TEXT | |
| expense_date | DATE | |
| paid_by | FK users NULL | NULL = unassigned (anomaly A5) |
| split_type | TEXT | equal \| unequal \| percentage \| share |
| original_amount | REAL | Amount as it appears in source |
| original_currency | TEXT(3) | e.g. "USD" |
| exchange_rate | REAL | Default 1.0; 83.0 for USD |
| amount_inr | REAL | = original_amount × exchange_rate |
| notes | TEXT NULL | |
| needs_review | BOOLEAN | Set by importer or manually |
| review_reason | TEXT NULL | Pipe-delimited anomaly types |
| is_duplicate_of | FK expenses NULL | Points to canonical row |
| excluded_from_balances | BOOLEAN | Duplicates excluded; toggle manually |
| source | TEXT | "manual" \| "import" |
| import_batch_id | FK import_batches NULL | |
| created_by | FK users NULL | |

### Table: `expense_splits`
| Column | Type | Notes |
|--------|------|-------|
| id | INTEGER PK | |
| expense_id | FK expenses | CASCADE delete |
| user_id | FK users | |
| raw_value | REAL NULL | Original input (%,  weight, or INR) |
| share_amount_inr | REAL | Final computed share |

### Table: `settlements`
| Column | Type | Notes |
|--------|------|-------|
| id | INTEGER PK | |
| group_id | FK groups | |
| paid_by | FK users | The debtor who is paying |
| paid_to | FK users | The creditor receiving payment |
| amount_inr | REAL | |
| settlement_date | DATE | |
| notes | TEXT NULL | |
| source | TEXT | "manual" \| "import" |
| import_batch_id | INT NULL | |
| created_by | FK users NULL | |

### Table: `import_batches`
| Column | Type | Notes |
|--------|------|-------|
| id | INTEGER PK | |
| group_id | FK groups | |
| filename | TEXT | Original uploaded filename |
| imported_by | FK users | |
| imported_at | TIMESTAMP | |
| row_count | INTEGER | Data rows processed |
| anomaly_count | INTEGER | Total anomaly records created |
| status | TEXT | processing \| done \| failed |

### Table: `import_anomalies`
| Column | Type | Notes |
|--------|------|-------|
| id | INTEGER PK | |
| batch_id | FK import_batches | |
| row_number | INTEGER | 2-based (1 = header) |
| raw_row | JSON | Full original CSV row |
| anomaly_type | TEXT | e.g. "EXACT_DUPLICATE" |
| severity | TEXT | info \| low \| high |
| description | TEXT | What was detected |
| action_taken | TEXT | What the importer did |
| expense_id | FK expenses NULL | Linked expense (if any) |
| settlement_id | INT NULL | Linked settlement (if any) |
| needs_human_review | BOOLEAN | TRUE when severity=high |

---

## Anomaly Catalogue (expenses_export.csv)

> The CSV has **21 distinct anomaly instances** across 15 anomaly types — well above the required 12.

| ID | Code | Row(s) | Severity | Description | Detection Method | Policy Applied |
|----|------|--------|----------|-------------|-----------------|----------------|
| A1 | `NAME_NORMALIZED` | 5 | low | `paid_by` is "Priya S" — extra suffix after the roster name | Prefix-match against all known user display names; if field starts with a roster name followed by a space, the extra text is treated as a suffix | Matched to "Priya"; suffix dropped; expense attributed correctly |
| A2 | `AMOUNT_PRECISION` | 8 | low | Amount is `899.995` — three decimal places (sub-paisa precision) | After removing thousands separators, check `abs(parsed - round(parsed, 2)) > 1e-9` | Rounded to `900.00` using PHP_ROUND_HALF_UP (the standard accounting convention) |
| A3a | `DATE_FORMAT_ISO` | 2 | info | Date `2026-02-08` uses ISO (YYYY-MM-DD) while all other rows use DD/MM/YYYY | Regex: `/^\d{4}-\d{1,2}-\d{1,2}$/` matched before the DD/MM regex | Parsed as ISO; flagged as informational inconsistency |
| A3b | `DATE_YEAR_MISSING` | 13 | info | Date `Mar 9` has no year component | After all numeric-format regexes fail, try `/^[A-Za-z]{3,}\s+\d{1,2}$/` | Year 2026 inferred (the only year used anywhere else in this file) |
| A4 | `CURRENCY_CONVERTED` | 13–17 (5 rows) | info | Five Goa-trip expenses have `currency=USD` but the original spreadsheet treated dollars as rupees | `CURRENCY_RATES` table; if currency ≠ INR, conversion note is logged | Converted at fixed rate 83.00 INR/USD; `original_amount` and `amount_inr` stored separately |
| A5 | `MISSING_PAYER` | 22 | high | `paid_by` is blank for the March gas bill | `normaliseName('')` returns null; importer checks for null paidByName | Imported with `paid_by = NULL`; `excluded_from_balances = true`; `needs_review = true` |
| A6 | `CURRENCY_DEFAULTED` | 6 | low | `currency` column is blank for the water bill | `strtoupper(trim(field)) === ''` | Defaulted to "INR" (group home currency); logged as anomaly |
| A7 | `SETTLEMENT_AS_EXPENSE` | 23 | high | Meera's "refund to Aisha" has `split_with = "Aisha"` (single person) | If `count(split_with) === 1` AND the single person is not the payer AND both are known users → settlement | Imported as `settlements` row (Meera → Aisha, ₹500), not as an expense |
| A8 | `SETTLEMENT_AS_EXPENSE` | 28 | high | Rohan's "rent share to Sam" has `split_with = "Sam"` (single person) | Same detection as A7 | Imported as settlement (Rohan → Sam, ₹2000) |
| A9 | `PERCENTAGE_SUM_MISMATCH` | 15 | high | Beach restaurant split: Aisha 30% + Rohan 30% + Priya 20% + Dev 30% = **110%** | After building `detailsByUserId`, `array_sum(percentages) !== 100` | Normalised proportionally: each person's % divided by 110 and multiplied by 100, preserving relative ratios |
| A10 | `NON_MEMBER_IN_SPLIT` | 16 | high | "Dev's friend Kabir" appears in `split_with` for the water-sports expense | `normaliseName()` prefix-match finds no roster match → `resolveUserId()` returns null | Removed from split; their 1/5 share redistributed among the 4 active members |
| A11 | `MEMBERSHIP_MISMATCH` | 27 | high | Row 27 (April electricity, 22/04/2026) lists Meera in `split_with`, but Meera's `left_on = 2026-03-31` | For each member in split_with, query `group_memberships` with `joined_on ≤ date ≤ left_on` | Meera removed from that split; her share redistributed among Aisha, Rohan, Priya, Sam |
| A12 | `EXACT_DUPLICATE` | 11 | high | Row 11 is an exact duplicate of row 10 (same date, payer, amount, description, split group) | Word-overlap Jaccard ≥ 0.4 AND same date AND same payer AND same amount AND same split_with set | Row 10 kept as canonical; row 11 imported with `excluded_from_balances = true` so Meera's "approve any deletion" requirement is met |
| A13 | `POSSIBLE_DUPLICATE_CONFLICT` | 12 | high | Row 12 has the same date and similar description as rows 10–11 but different payer (Priya not Rohan) and different amount (₹2800 vs ₹3200) | Word-overlap Jaccard ≥ 0.4 AND same date, but payer or amount differs | Both row 10 and row 12 imported and counted in balances; both flagged `needs_review = true` so a human can compare and exclude the wrong one |
| A14 | `AMBIGUOUS_DATE_FORMAT` | 25 | high | `04/05/2026` — both components ≤ 12, so could be April 5 (DD/MM) or May 4 (MM/DD) | `ambiguous = (a <= 12 && b <= 12 && a !== b)`; second pass checks if DD/MM reading > next row's date | Default DD/MM = May 4; but next row (row 26) is April 15, so May 4 > April 15 → chronological order break detected; flagged for review with alternative date shown |
| A15 | `AMOUNT_THOUSANDS_SEPARATOR` | 3 | low | Amount `1,200` contains a comma (thousands separator) | `str_replace(',', '', field)` before `floatval()` | Comma removed; parsed as 1200 |
| A16 | `SPLIT_TYPE_MISSING` | 7 | high | `split_type` column is blank for the house cleaning row | `strtolower(trim(field)) === ''` | Defaulted to `equal`; flagged `needs_review = true` |
| A17 | `NEGATIVE_AMOUNT` | 29 | info | Grocery refund row has amount `-200` | `$value < 0` check after parsing | Imported as-is; negative amount reduces everyone's share (each person gets ₹50 back) |
| A18 | `ZERO_AMOUNT` | 30 | info | Placeholder test entry with amount `0` | `$value == 0.0` check | Imported for audit trail; has zero effect on any balance |
| A19 | `UNEQUAL_SUM_MISMATCH` | 18 | high | Electricity bill: Aisha 600 + Rohan 600 + Priya 300 + Meera 200 = **1700**, not 1800 | After building `detailsByUserId`, `abs(sum - amount_inr) > 0.02` | ₹100 remainder added to payer's (Priya's) share via `SplitCalculator::compute()`'s rounding reconciliation |
| A20 | `SPLIT_DETAILS_IGNORED` | 4 | low | Internet bill has `split_type=equal` but `split_details` also contains explicit per-person amounts | `split_type === 'equal' && !empty(split_details_raw)` | `split_type` is authoritative; `split_details` ignored; equal split applied |

**Total: 21 anomaly instances across 20 distinct rows.**
