# DECISIONS.md — Engineering Decision Log

Each entry documents a significant decision, the options considered, and the rationale.

---

## D1 — Framework: Laravel (PHP) vs Flask (Python)

**Context:** The project scaffold was already a Laravel 12 app with SQLite configured.

**Options considered:**
1. Keep Laravel — use the existing scaffold, benefit from Eloquent, Blade templating, built-in auth, and Artisan CLI.
2. Rebuild in Flask (Python) — the original prompt mentioned Python snippets.

**Decision:** Keep Laravel.

**Reasoning:** The scaffold already existed with a working `.env`, SQLite database file, and Composer lock. Rebuilding from scratch in another language would have wasted the scaffold and risked introducing more moving parts. Laravel's built-in features (auth middleware, request validation, migrations, Eloquent relationships) let us build the required features faster and with fewer lines of custom code. The assignment specifies "relational DBs only" — Eloquent on SQLite satisfies this without an ORM abstraction layer the marker might not be familiar with.

---

## D2 — Database: SQLite vs MySQL/PostgreSQL

**Options:**
1. SQLite (already configured in `.env` as `DB_CONNECTION=sqlite`)
2. MySQL or PostgreSQL

**Decision:** SQLite.

**Reasoning:** Zero additional setup for the evaluator — no database server to install or configure. SQLite is a full relational database with foreign keys, transactions, and JSON support. The dataset (< 1000 rows) is well within SQLite's performance envelope. The database file is a single file (`database/database.sqlite`) that can be inspected with any SQLite browser, making the schema immediately transparent during the live walkthrough.

---

## D3 — USD Conversion Rate: Fixed Rate vs Historical API

**Context:** Five Goa-trip expenses (rows 13–17) are denominated in USD. Priya's requirement: "The sheet pretends a dollar is a rupee. That can't be right."

**Options:**
1. Fixed rate per import (e.g. 83.00 INR/USD for this dataset).
2. Pull the rate from an external API (e.g. exchangeratesapi.io) per expense date.
3. Let the user enter a rate at import time.

**Decision:** Fixed rate constant in `CsvImporter::CURRENCY_RATES = ['USD' => 83.0]`.

**Reasoning:**
- **Traceability:** With a fixed rate, every converted amount is 100% traceable: `original_amount × 83.0 = amount_inr`. No hidden historical lookups.
- **Reproducibility:** Re-running the import next month produces the same result — external APIs change.
- **Simplicity during live walkthrough:** Any evaluator can verify the arithmetic on paper.
- **Assignment scope:** The brief says the app should handle the Goa trip. The rate was chosen to reflect the real approximate INR/USD rate for March 2026 (≈ 83).
- **Alternative considered:** A UI field at import time for the user to enter the rate. This is better UX for a production app but adds UI complexity not required here. The constant is at the top of `CsvImporter.php` and is trivial to make configurable in a follow-up.

Both `original_amount` and `original_currency` are stored in the `expenses` table, so the conversion is always visible and reversible.

---

## D4 — Rounding: Which person gets the remainder?

**Context:** When splitting ₹1000 three ways, each person gets ₹333.33... We must round to 2 decimal places (paise). After rounding, the three shares sum to ₹999.99, leaving a ₹0.01 gap.

**Options:**
1. Add the remainder to the **payer's** share.
2. Add it to the **first member** by user_id.
3. Distribute the remainder across members (complex, invisible).
4. Leave a rounding error (wrong — balances don't reconcile).

**Decision:** Add to payer's share; fall back to `min(user_ids)` if payer is not a member.

**Reasoning:** The payer already fronted the full amount. Giving them the rounding remainder is directionally correct (they get back slightly more than their equal share, or owe slightly less). It's also the simplest deterministic rule — the same rule applies consistently across all split types. The alternative of distributing remainders across multiple people would make individual shares harder to explain line-by-line.

Implementation: `SplitCalculator::compute()` computes `diff = amount_inr - sum(rounded_shares)` and adds it to the target user's share.

---

## D5 — Duplicate Detection: Keep or Delete?

**Context:** Rows 10 and 11 are exact duplicates. Row 12 has the same date and similar description but different payer and amount. Meera's requirement: "I want to approve anything the app deletes or changes."

**Options for exact duplicate (A12):**
1. Delete the duplicate row entirely.
2. Import it but set `excluded_from_balances = true` + `needs_review = true`.
3. Reject the import if any duplicate is detected.

**Decision:** Import with `excluded_from_balances = true` + `needs_review = true`.

**Reasoning:** Nothing is deleted. The duplicate is fully visible in the Import Report and in the expenses list (with an "Excluded" badge). A human can toggle exclusion on/off via Edit Expense. This satisfies Meera's requirement exactly. Deleting the row would be irreversible and hide the fact that the input had a duplicate.

**For possible conflict (A13):** Both rows are imported and counted in balances. Both are flagged `needs_review`. A human compares them and excludes the incorrect one. We deliberately do NOT pick a winner algorithmically — the two entries may reflect genuinely different expenses (e.g. pre-dinner drinks vs the dinner itself), and only the humans who were there can judge.

---

## D6 — Membership Timeline: Who is "in" for a given expense?

**Context:** Meera left at end of March. Sam joined mid-April. Some CSV rows list people as split members when they weren't active members.

**Options:**
1. Silently include everyone listed in `split_with`, ignoring membership dates.
2. Check membership dates and remove inactive members, flagging the anomaly.
3. Reject the entire import if any membership mismatch is found.

**Decision:** Remove inactive members from the split, redistribute their share, flag as `MEMBERSHIP_MISMATCH` with `needs_review = true`.

**Reasoning:**
- Sam's requirement: "I moved in mid-April. Why would March electricity affect my balance?" — so we must enforce date-based membership.
- Meera's requirement: Changes need approval — flagging for review lets a human confirm the redistribution.
- The original CSV is not hand-edited, so we can't simply fix it at source. The importer must handle this programmatically.
- Redistribution is automatic (remaining active members split the excluded person's share proportionally) because leaving the expense short by a person's share would cause balances to not sum to zero.

The membership timeline is stored in `group_memberships.joined_on` / `left_on`. The check is: `joined_on ≤ expense_date AND (left_on IS NULL OR expense_date ≤ left_on)`.

---

## D7 — Percentage Normalisation (A9)

**Context:** Beach restaurant split sums to 110% (four people, four percentages, one is 30% when it should be 25% or the others should be adjusted).

**Options:**
1. Reject the import row (leave it out entirely).
2. Flag for review, import with equal split as fallback.
3. Normalise proportionally (each person's % / totalPct × 100) and flag for review.

**Decision:** Normalise proportionally.

**Reasoning:** The relative intent is clear — Aisha, Rohan, and Dev each pay the same (30/110 each) and Priya pays less (20/110). Normalising preserves this intent while making the shares sum to 100%. The alternative of equal split would likely be wrong (it ignores the explicit intention). Rejection leaves a gap in the accounts. The flag ensures a human confirms the normalised amounts are acceptable.

---

## D8 — Settlement Detection (A7, A8)

**Context:** Two rows have `split_with` containing exactly one person who is not the payer.

**Detection rule:** `count(split_with) === 1 AND resolveUserId(split_with[0]) !== null AND split_with[0] !== paid_by`

**Options:**
1. Import as a regular expense (wrong — creates false shared-cost entries).
2. Skip the row (wrong — the payment happened and affects balances).
3. Import as a settlement.

**Decision:** Import as a `settlements` row.

**Reasoning:** A payment from person A to person B is not a shared cost — it's a debt repayment. Putting it in `expenses` would double-count: it would look like both an expense AND a balance adjustment. Putting it in `settlements` is semantically correct and produces the right balance effect (debtor's balance improves, creditor's balance decreases).

---

## D9 — Unequal Split Mismatch (A19)

**Context:** Electricity bill row lists Aisha 600 + Rohan 600 + Priya 300 + Meera 200 = 1700, but total is 1800.

**Options:**
1. Reject the row.
2. Scale all shares proportionally so they sum to 1800.
3. Add the remainder to the payer's share (same as rounding rule D4).

**Decision:** Add the ₹100 remainder to the payer's (Priya's) share, flag for review.

**Reasoning:** We cannot guess whether the intent was "Meera pays 300" or "Priya pays 400" or something else. The safest assumption is that whoever entered the data made an arithmetic error in one entry, and the total amount (₹1800) is more reliable than the individual entries. The remainder goes to the payer using the same rule as D4. The flag ensures a human reviews and adjusts if needed.

---

## D10 — Ambiguous Date Format (A14)

**Context:** `04/05/2026` can be read as April 5 (DD/MM/YYYY) or May 4 (MM/DD/YYYY).

**Detection:** Both components ≤ 12, so the date is structurally ambiguous. Second pass: if the DD/MM reading places this row after the chronologically next row, it's flagged.

**Policy:** Default to DD/MM/YYYY (the dominant format on this sheet). Flag with `needs_review = true` and surface the alternative date in the anomaly description so the user can correct it.

**Why DD/MM as default:** 24 out of 25 slash-delimited date entries in the CSV use DD/MM/YYYY. The one MM/DD exception (if any) is detected by the "first component > 12 is impossible as a month" rule. The ambiguous date triggers the chronological-order check.

---

## D11 — Balance Calculation: Convention

**Convention:**
- `balance[user] > 0` → the group owes this person money (they overpaid / fronted)
- `balance[user] < 0` → this person owes the group money
- `sum(all balances) ≈ 0` (conservation of money)

**Expense effect:**
- `balance[payer] += amount_inr` (they fronted this amount)
- `balance[each_split_member] -= their_share`

**Settlement effect (A pays B amount X):**
- `balance[A] += X` (A's debt decreases — they paid off X)
- `balance[B] -= X` (B's credit decreases — they received X)

This convention is consistent with how popular expense-splitting apps (Splitwise, Tricount) work.

---

## D12 — Greedy Settle-Up Algorithm

**Context:** Aisha's requirement: "I just want one number per person. Who pays whom, how much, done."

**Algorithm:** Greedy — repeatedly match the largest debtor with the largest creditor. This produces at most `n-1` transactions, which is optimal for most practical graphs.

**Alternative considered:** Linear programming for absolute minimum transactions in a general graph. This is rarely needed for groups of < 10 people and is significantly harder to explain or verify manually. The greedy algorithm is O(n log n) and produces a clean, readable transaction list.

**Limitation acknowledged:** For certain balance configurations, greedy may not produce the absolute minimum transaction count. This is documented and acceptable — the result is still simpler than the raw balance map.
