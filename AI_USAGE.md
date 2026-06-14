# AI_USAGE.md — AI Collaboration Notes

## Tools Used
- **Primary:** Claude Sonnet (via Antigravity IDE assistant) — used throughout for code generation, architecture design, and debugging
- **Scope:** Used as a pair programmer, not as a black box. Every generated line was read, understood, and verified before inclusion.

---

## Key Prompts

### 1. Architecture Design
> "This is a Laravel 12 project with SQLite. Design the complete relational schema for a shared expense tracker with: time-aware group memberships, four split types (equal/unequal/percentage/share), foreign currency conversion, import batch tracking, and per-row anomaly logging."

The AI produced a 7-table schema. I reviewed each table and added the `is_duplicate_of` FK on expenses (for pointing flagged duplicates at their canonical row) which the AI had omitted.

### 2. CsvImporter — Anomaly Detection
> "Write a PHP CsvImporter service with two phases (parse/persist). For each row in expenses_export.csv, detect: ISO date format, 'Mar 9' style date, ambiguous DD/MM vs MM/DD dates via chronological order check, thousands separators in amounts, sub-paisa precision, missing currency, missing payer, settlement-disguised-as-expense (single person in split_with), percentage sum ≠ 100%, non-roster member in split, membership mismatch vs join/leave dates, exact duplicate vs possible conflict detection using word overlap."

The AI wrote the first version. See correction #1 below.

### 3. BalanceCalculator
> "Write a BalanceCalculator service with three pure functions: groupBalances(group_id), settleUp(balances) using the greedy minimum-transactions algorithm, and userBreakdown(group_id, user_id) returning every expense and settlement line affecting that user with effect_on_balance per line."

The AI produced this correctly on the first try. I verified the settlement effect formula manually (see DECISIONS.md D11).

### 4. SplitCalculator
> "Write a SplitCalculator::compute() function handling equal/unequal/percentage/share split types. For percentage, normalise if sum ≠ 100%. Add any rounding remainder to the payer's share (fallback: min user_id). Always round to 2 decimal places using PHP_ROUND_HALF_UP."

Correct first try. I added a unit test trace by hand for the 3-way equal split (₹1000 → ₹333.34, ₹333.33, ₹333.33) to verify the remainder logic.

---

## Cases Where the AI Was Wrong

### Correction 1: Settlement Effect on Balances — Inverted Sign

**What the AI wrote:**
```php
// Settlement: A pays B
$add($s->paid_to, $s->amount_inr);    // creditor gets money → their balance improves
$add($s->paid_by, -$s->amount_inr);   // debtor pays → their balance decreases
```

**Why this is wrong:** The convention is `balance > 0 = others owe you`. When A pays B:
- A's debt decreases → A's balance *increases* (they owe less)
- B's credit decreases → B's balance *decreases* (they're owed less)

The AI had the signs reversed.

**How I caught it:** I traced the settle-up example by hand. After recording a settlement, re-running `settleUp()` should produce a smaller transaction set. With the AI's code, re-running after a settlement produced a larger one — the settlement was making things worse.

**What I changed:**
```php
$add($s->paid_by, $s->amount_inr);    // debtor's balance improves (they owe less)
$add($s->paid_to, -$s->amount_inr);   // creditor's balance decreases (they've been paid)
```

---

### Correction 2: Duplicate Detection — Wrong Index Reference

**What the AI wrote:**
```php
foreach ($candidates as $ci => $c) {
    for ($j = 0; $j < $ci; $j++) {
        $o = $candidates[$j];  // WRONG — $j is an expense index, not a candidates index
        ...
    }
}
```

**Why this is wrong:** The outer loop iterates over all candidates (expenses AND settlements). The inner loop uses `$j` as a candidate index, but `$j < $ci` compares against the outer candidates index, not the expense-only index. This caused settlements to be compared against expenses, and the inner loop sometimes accessed the wrong candidate.

**How I caught it:** The detect-duplicates function was comparing a settlement row (row 23) to an expense (row 22) because both had `date === '2026-03-31'` and the word-overlap of "Meera" appearing in both descriptions was > 0.4. This produced a spurious `POSSIBLE_DUPLICATE_CONFLICT` anomaly for a settlement and an expense on the same date.

**What I changed:** Rewrote the function to first collect only expense candidates with their original indices in a separate `$expenses` array, then compare only within that filtered list, mapping back to the original `$candidates` array via an index map.

---

### Correction 3: Percentage Normalisation Applied to Removed Members

**What the AI wrote:**
```php
// Check percentage sum using ALL split_with_raw members
$totalPct = array_sum(array_values($splitDetailsRaw));
if (abs($totalPct - 100) > 0.01) { ... }
```

**Why this is wrong:** The check was run before membership filtering. If "Dev's friend Kabir" (non-member, anomaly A10) was in `split_details_raw` with a 20% share, the sum check used all five members' percentages. But by the time `SplitCalculator::compute()` was called, Kabir had been removed, and the remaining four members' percentages were being normalised against the wrong total. The resulting shares were correct numbers but the anomaly message said "percentages sum to 130%" when only 110% was attributable to actual members.

**How I caught it:** Traced through row 16 (water sports, "Dev's friend Kabir") manually and noticed the anomaly message referenced percentages that didn't match the CSV values for the remaining members.

**What I changed:** Moved the percentage sum check to AFTER membership filtering, and computed `$totalPct` only from `$effectiveMemberIds`:
```php
$totalPct = (float) array_sum(
    array_map(fn($uid) => $detailsByUserId[$uid] ?? 0, $effectiveMemberIds)
);
```

---

### Correction 4: PHP Foreach Reference Pollution in `detectDuplicates()`

**What the AI wrote:**
```php
private function detectDuplicates(array &$candidates): void
{
    ...
    foreach ($candidates as $idx => &$c) {
        ...
        if (!$foundExact) {
            foreach ($candidates as $prevIdx => $prev) {
                ...
            }
        }
        ...
        $softSeen[$softFp][] = $idx;
    }
    unset($c);
}
```

**Why this is wrong:**
In PHP, when iterating over an array by reference (`&$c`) in an outer loop, performing a nested loop over the *same* array (`foreach ($candidates as $prevIdx => $prev)`) can cause the active reference pointer to get polluted or mutated. This resulted in elements comparing themselves to themselves, flagging several rows (like Row 11, 12, 13) incorrectly as `EXACT_DUPLICATE_IN_BATCH` with themselves.

**How I caught it:**
When running the import on the updated CSV, rows from 11 onwards were flagged as duplicate of themselves (e.g., "Row 11 is an exact duplicate of row 11"). Tracing the `detectDuplicates()` logic step-by-step revealed that the outer loop's reference `&$c` was being polluted by the inner iteration.

**What I changed:**
Modified the outer loop to iterate by copy (`$c`) and explicitly write back the mutated candidate to the array using the index at the end of the loop:
```php
foreach ($candidates as $idx => $c) {
    ...
    $softSeen[$softFp][] = $idx;
    $candidates[$idx] = $c;
}
```

---

## AI Prompt Patterns That Worked Well

1. **Giving the AI the full context upfront** (schema + all anomaly types + policies) before asking for code — this reduced back-and-forth significantly.

2. **Asking for pure functions first**, then adding DB interaction — the `SplitCalculator` and `BalanceCalculator` services were written as pure functions, making them easy to trace and mentally verify.

3. **Tracing examples by hand** immediately after generation — catching the balance sign bug (correction #1) required only a 3-line worked example.

## What I Would Not Delegate to AI

- The DECISIONS.md rationale — these are product engineering choices that require understanding the user's requirements, not just code generation.
- The CSV anomaly catalogue in SCOPE.md — the AI can write the format, but the actual anomaly-by-anomaly analysis required reading the CSV carefully.
- Final verification of balance calculations — all balance outputs were verified against manual calculations before the live walkthrough.
