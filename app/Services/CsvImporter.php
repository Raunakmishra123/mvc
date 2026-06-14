<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\ImportAnomaly;
use App\Models\ImportBatch;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Imports expenses_export.csv into the database.
 *
 * Two-phase design:
 *   Phase 1  parse()   — reads CSV, detects every anomaly, returns candidates.
 *                        No DB writes. Can be used for a dry-run preview.
 *   Phase 2  persist() — writes expenses / splits / settlements / anomaly log
 *                        inside a single transaction.
 *
 * Every anomaly found is stored in import_anomalies so the Import Report
 * can show exactly what happened to every row.
 *
 * Anomaly IDs referenced in comments (A1-A21) match SCOPE.md.
 */
class CsvImporter
{
    // Documented fixed rate for the Goa trip USD expenses.
    // Using a single rate per import (rather than a per-day historical rate)
    // makes every converted amount fully traceable: original × rate = INR.
    // See DECISIONS.md "USD conversion rate".
    const CURRENCY_RATES = ['INR' => 1.0, 'USD' => 83.0];

    const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
        'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
        'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    private int   $groupId;
    private int   $importedBy;
    private array $nameToId         = [];   // lower(name) → user_id
    private array $pendingAnomalies = [];

    public function __construct(int $groupId, int $importedBy)
    {
        $this->groupId    = $groupId;
        $this->importedBy = $importedBy;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ──────────────────────────────────────────────────────────────────────────

    /** Run the full import; return the batch ID. */
    public function import(string $csvPath, string $filename): int
    {
        // Build name→id lookup (case-insensitive)
        $this->nameToId = [];
        foreach (User::all() as $u) {
            $this->nameToId[strtolower(trim($u->name))] = $u->id;
        }

        $rows        = $this->readCsv($csvPath);
        $parsedDates = $this->parseAllDates(array_column($rows, 'date'));

        // Build candidates (pure, no DB except membership lookups)
        $candidates = [];
        foreach ($rows as $idx => $row) {
            $candidates[] = $this->buildCandidate($row, $idx + 2, $parsedDates[$idx]);
        }

        // Duplicate detection mutates candidates in place
        $this->detectDuplicates($candidates);

        // Create import batch
        $batch = ImportBatch::create([
            'group_id'    => $this->groupId,
            'filename'    => $filename,
            'imported_by' => $this->importedBy,
            'imported_at' => now(),
            'row_count'   => count($candidates),
            'status'      => 'processing',
        ]);

        // Persist expenses / settlements in one transaction
        DB::transaction(function () use ($candidates, $batch) {
            foreach ($candidates as $c) {
                if ($c['kind'] === 'settlement') {
                    $this->persistSettlement($c, $batch->id);
                } else {
                    $this->persistExpense($c, $batch->id);
                }
            }
        });

        // Save anomalies after the transaction (they reference created records)
        foreach ($this->pendingAnomalies as $a) {
            ImportAnomaly::create(array_merge($a, ['batch_id' => $batch->id]));
        }

        $batch->update([
            'anomaly_count' => count($this->pendingAnomalies),
            'status'        => 'done',
        ]);

        return $batch->id;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CSV READING
    // ──────────────────────────────────────────────────────────────────────────

    private function readCsv(string $path): array
    {
        $handle  = fopen($path, 'r');
        $headers = null;
        $rows    = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map('trim', $line);
                continue;
            }
            if (count(array_filter($line, fn ($v) => $v !== '')) === 0) {
                continue; // skip fully-blank rows
            }
            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = isset($line[$i]) ? $line[$i] : '';
            }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DATE PARSING  (A3, A14)
    // ──────────────────────────────────────────────────────────────────────────

    private function parseSingleDate(string $raw): array
    {
        $s     = trim($raw);
        $notes = [];
        $year  = 2026; // only year appearing in this dataset

        // YYYY-MM-DD  (A3: ISO format, different from sheet's usual DD/MM/YYYY)
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
            $notes[] = $this->note(
                'DATE_FORMAT_ISO', 'info',
                "Date '{$raw}' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY.",
                'Parsed as ISO; no ambiguity.'
            );
            return [
                'iso'       => sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]),
                'notes'     => $notes,
                'ambiguous' => false,
            ];
        }

        // DD/MM/YYYY or MM/DD/YYYY  (A14: structural ambiguity when both ≤ 12)
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $s, $m)) {
            [$a, $b, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
            $ambiguous   = ($a <= 12 && $b <= 12 && $a !== $b);

            if ($a > 12) {
                // Day > 12 is impossible as a month → unambiguous DD/MM
                [$day, $month] = [$a, $b];
            } elseif ($b > 12) {
                // Month > 12 is impossible → unambiguous MM/DD → swap to DD/MM
                [$day, $month] = [$b, $a];
                $notes[] = $this->note(
                    'DATE_FORMAT_MMDD', 'info',
                    "'{$raw}': first component {$a} > 12 so it cannot be a day in DD/MM/YYYY; "
                    . 'parsed as MM/DD/YYYY instead.',
                    'Parsed as MM/DD/YYYY.'
                );
            } else {
                // Both ≤ 12: default to DD/MM/YYYY (dominant format on this sheet)
                [$day, $month] = [$a, $b];
            }
            return [
                'iso'       => sprintf('%04d-%02d-%02d', $y, $month, $day),
                'notes'     => $notes,
                'ambiguous' => $ambiguous,
                'raw'       => $raw,
            ];
        }

        // "Mon DD"  e.g. "Mar 9"  (A3: missing year)
        if (preg_match('/^([A-Za-z]{3,})[\s\-]+(\d{1,2})$/', $s, $m)) {
            $mo = self::MONTHS[strtolower(substr($m[1], 0, 3))] ?? null;
            if ($mo) {
                $notes[] = $this->note(
                    'DATE_YEAR_MISSING', 'info',
                    "'{$raw}' has no year component; inferred {$year} (the only year appearing "
                    . 'elsewhere in this file).',
                    "Year {$year} assumed."
                );
                return [
                    'iso'       => sprintf('%04d-%02d-%02d', $year, $mo, (int)$m[2]),
                    'notes'     => $notes,
                    'ambiguous' => false,
                ];
            }
        }

        // Unparseable fallback
        $notes[] = $this->note(
            'DATE_UNPARSEABLE', 'high',
            "Cannot parse date '{$raw}'.",
            'Stored as-is; expense flagged for review.'
        );
        return ['iso' => $raw, 'notes' => $notes, 'ambiguous' => false];
    }

    /**
     * Parse all dates then do a second pass:
     * any structurally-ambiguous date whose DD/MM reading would place it
     * AFTER the chronologically next row is flagged AMBIGUOUS_DATE_FORMAT.
     * This catches "04/05/2026" (May 4 vs Apr 5) without false-positives
     * on the many other unambiguous DD/MM/YYYY dates.
     */
    private function parseAllDates(array $rawDates): array
    {
        $parsed = array_map([$this, 'parseSingleDate'], $rawDates);

        for ($i = 0; $i < count($parsed) - 1; $i++) {
            if (!($parsed[$i]['ambiguous'] ?? false)) {
                continue;
            }
            if ($parsed[$i]['iso'] > $parsed[$i + 1]['iso']) {
                $alt = $this->altDate($parsed[$i]['raw'] ?? '');
                $parsed[$i]['notes'][] = $this->note(
                    'AMBIGUOUS_DATE_FORMAT', 'high',
                    "'{$parsed[$i]['raw']}' is ambiguous (day and month both ≤ 12). "
                    . "Read as DD/MM/YYYY = {$parsed[$i]['iso']}, but that date falls "
                    . "AFTER the next row ({$parsed[$i+1]['iso']}), breaking chronological order. "
                    . "MM/DD/YYYY would give {$alt}, which fits chronologically.",
                    "Imported with date {$parsed[$i]['iso']} and flagged needs_review=1. "
                    . "Edit the expense to change it to {$alt} if that is correct."
                );
                $parsed[$i]['force_review'] = true;
            }
        }

        return $parsed;
    }

    /** Return the alternative (MM/DD swap) interpretation of a DD/MM/YYYY string. */
    private function altDate(string $raw): string
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
        }
        return $raw;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // NAME NORMALISATION  (A1)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Match a raw name against the known user roster.
     * Returns [canonical_name_or_null, note_or_null].
     */
    private function normaliseName(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [null, null];
        }

        $cleaned = trim(preg_replace('/\s+/', ' ', $raw));
        $lower   = strtolower($cleaned);

        // 1. Exact match (case-insensitive)
        foreach (array_keys($this->nameToId) as $knownLower) {
            if ($lower === $knownLower) {
                // Return the real display name (proper case)
                $realName = User::where(DB::raw('LOWER(name)'), $knownLower)->value('name') ?? $cleaned;
                return [$realName, null];
            }
        }

        // 2. Prefix match — "Priya S" starts with "priya " (A1)
        foreach (array_keys($this->nameToId) as $knownLower) {
            if (str_starts_with($lower, $knownLower . ' ')) {
                $realName = User::where(DB::raw('LOWER(name)'), $knownLower)->value('name') ?? ucfirst($knownLower);
                $note     = "Name '{$raw}' matched to '{$realName}' (extra suffix dropped).";
                return [$realName, $note];
            }
        }

        // No match
        return [$cleaned, null];
    }

    private function resolveUserId(?string $name): ?int
    {
        if ($name === null) {
            return null;
        }
        return $this->nameToId[strtolower(trim($name))] ?? null;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AMOUNT PARSING  (A2, A15, A17, A18)
    // ──────────────────────────────────────────────────────────────────────────

    private function parseAmount(string $raw): array
    {
        $notes   = [];
        $trimmed = trim($raw);

        // A15: thousands-separator comma
        $noCommas = str_replace(',', '', $trimmed);
        if ($noCommas !== $trimmed) {
            $notes[] = $this->note(
                'AMOUNT_THOUSANDS_SEPARATOR', 'low',
                "Amount '{$raw}' contains a comma (thousands separator); parsed as {$noCommas}.",
                'Comma removed before parsing.'
            );
        }

        $value   = (float) $noCommas;

        // A2: sub-paisa precision
        $rounded = round($value, 2, PHP_ROUND_HALF_UP);
        if (abs($value - $rounded) > 1e-9) {
            $notes[] = $this->note(
                'AMOUNT_PRECISION', 'low',
                "Amount {$value} has more than 2 decimal places (sub-paisa); rounded to {$rounded}.",
                'Rounded to 2 decimal places, half-up.'
            );
            $value = $rounded;
        }

        // A17: negative amount = refund
        if ($value < 0) {
            $notes[] = $this->note(
                'NEGATIVE_AMOUNT', 'info',
                "Amount is negative ({$value}). Treated as a refund/credit that reduces balances.",
                'Imported as-is; negative share reduces what members owe.'
            );
        }

        // A18: zero amount
        if ($value == 0.0) {
            $notes[] = $this->note(
                'ZERO_AMOUNT', 'info',
                'Amount is 0. Imported for the audit trail but has no effect on any balance.',
                'Imported with amount_inr = 0.'
            );
        }

        return [$value, $notes];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SPLIT FIELD PARSING
    // ──────────────────────────────────────────────────────────────────────────

    private function parseSplitWith(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(';', $raw))));
    }

    /**
     * Parse "Aisha 300;Rohan 300;..." or "Aisha 30%;Rohan 30%;..."
     * Returns [name => value] where value is the numeric portion.
     */
    private function parseSplitDetails(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        $result = [];
        foreach (explode(';', $raw) as $part) {
            $part = trim($part);
            if (preg_match('/^(.+?)\s+([\d.]+)\s*%?\s*$/', $part, $m)) {
                $result[trim($m[1])] = (float) $m[2];
            }
        }
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CANDIDATE BUILDING
    // ──────────────────────────────────────────────────────────────────────────

    private function buildCandidate(array $row, int $rowNum, array $dateInfo): array
    {
        $notes       = array_values($dateInfo['notes'] ?? []);
        $forceReview = $dateInfo['force_review'] ?? false;
        $dateIso     = $dateInfo['iso'];

        // ── paid_by ───────────────────────────────────────────────────────────
        [$paidByName, $nameNote] = $this->normaliseName($row['paid_by'] ?? '');
        if ($nameNote) {
            $notes[] = $this->note('NAME_NORMALIZED', 'low', $nameNote,
                'Matched to existing roster member.');
        }
        if ($paidByName === null) {
            // A5
            $notes[]     = $this->note(
                'MISSING_PAYER', 'high',
                "Row {$rowNum} has no paid_by value.",
                'Imported with paid_by unset; excluded from balances until a person is assigned.'
            );
            $forceReview = true;
        }

        // ── amount ────────────────────────────────────────────────────────────
        [$amount, $amtNotes] = $this->parseAmount($row['amount'] ?? '0');
        $notes = array_merge($notes, $amtNotes);

        // ── currency ──────────────────────────────────────────────────────────
        $currency = strtoupper(trim($row['currency'] ?? ''));
        if ($currency === '') {
            // A6
            $notes[]  = $this->note(
                'CURRENCY_DEFAULTED', 'low',
                "Row {$rowNum}: currency field is empty; defaulted to INR (group home currency).",
                'currency=INR assumed.'
            );
            $currency = 'INR';
        }

        $rate = self::CURRENCY_RATES[$currency] ?? null;
        if ($rate === null) {
            // Unknown currency (not in our rate table)
            $notes[] = $this->note(
                'UNKNOWN_CURRENCY', 'high',
                "Unrecognised currency '{$currency}'; no conversion rate available.",
                'Treated as INR (rate 1.0); please verify.'
            );
            $rate = 1.0;
        } elseif ($currency !== 'INR') {
            // A4: USD conversion
            $converted = round($amount * $rate, 2);
            $notes[]   = $this->note(
                'CURRENCY_CONVERTED', 'info',
                "{$amount} {$currency} converted to INR at the fixed Goa-trip rate of {$rate} → ₹{$converted}.",
                "amount_inr = {$amount} × {$rate} = {$converted}. Fixed rate documented in DECISIONS.md."
            );
        }

        $amountInr = round($amount * $rate, 2);

        // ── split fields ─────────────────────────────────────────────────────
        $splitWithRaw    = $this->parseSplitWith($row['split_with']    ?? '');
        $splitDetailsRaw = $this->parseSplitDetails($row['split_details'] ?? '');
        $splitType       = strtolower(trim($row['split_type'] ?? ''));

        // ── A7 / A8: settlement detection ────────────────────────────────────
        // A single person in split_with (who is not the payer) = direct payment.
        if (count($splitWithRaw) === 1) {
            [$otherName]  = $this->normaliseName($splitWithRaw[0]);
            $otherId      = $otherName ? $this->resolveUserId($otherName) : null;
            $paidById     = $paidByName ? $this->resolveUserId($paidByName) : null;
            if ($otherId && $paidById && $otherId !== $paidById) {
                $notes[] = $this->note(
                    'SETTLEMENT_AS_EXPENSE', 'high',
                    "split_with is a single person ({$otherName}) who is not the payer — "
                    . "this is a direct payment from {$paidByName} to {$otherName}, not a shared expense.",
                    "Imported as a settlement ({$paidByName} → {$otherName}, ₹{$amountInr})."
                );
                return [
                    'kind'        => 'settlement',
                    'row_number'  => $rowNum,
                    'raw_row'     => $row,
                    'date'        => $dateIso,
                    'paid_by_id'  => $paidById,
                    'paid_to_id'  => $otherId,
                    'amount_inr'  => $amountInr,
                    'notes_str'   => $row['notes'] ?? '',
                    'notes'       => $notes,
                    'needs_review' => $forceReview,
                ];
            }
        }

        // ── description ───────────────────────────────────────────────────────
        $description = trim($row['description'] ?? '');
        if ($description === '') {
            // A16
            $notes[] = $this->note(
                'MISSING_DESCRIPTION', 'low',
                "Row {$rowNum} has no description.",
                "Description set to 'Imported expense (row {$rowNum})'."
            );
            $description = "Imported expense (row {$rowNum})";
        }

        // ── split_type inference ──────────────────────────────────────────────
        if ($splitType === '') {
            if (!empty($splitDetailsRaw)) {
                // Has named amounts/percentages → infer from values
                $vals   = array_values($splitDetailsRaw);
                $isPerc = array_sum($vals) <= 101 && max($vals) <= 100;
                $splitType = $isPerc ? 'percentage' : 'unequal';
                $notes[] = $this->note(
                    'SPLIT_TYPE_INFERRED', 'low',
                    "split_type was blank; inferred '{$splitType}' from split_details values.",
                    "split_type set to '{$splitType}'."
                );
            } else {
                $splitType = 'equal';
                $notes[] = $this->note(
                    'SPLIT_TYPE_DEFAULTED', 'low',
                    "split_type was blank with no split_details; defaulted to 'equal'.",
                    "split_type set to 'equal'."
                );
            }
        }

        // ── resolve member IDs ────────────────────────────────────────────────
        // Collect split_with names; if empty, fall back to all active members.
        $memberIds   = [];
        $detailsById = [];

        if (!empty($splitWithRaw)) {
            foreach ($splitWithRaw as $rawName) {
                [$norm] = $this->normaliseName($rawName);
                $uid    = $norm ? $this->resolveUserId($norm) : null;
                if ($uid === null) {
                    // A9: unknown member
                    $notes[] = $this->note(
                        'UNKNOWN_MEMBER', 'high',
                        "'{$rawName}' in split_with does not match any known user.",
                        'Row excluded from balance calculation; needs_review set.'
                    );
                    $forceReview = true;
                    continue;
                }
                $memberIds[] = $uid;
            }
            // Map detail values from name → user_id
            foreach ($splitDetailsRaw as $rawName => $val) {
                [$norm] = $this->normaliseName($rawName);
                $uid    = $norm ? $this->resolveUserId($norm) : null;
                if ($uid !== null) {
                    $detailsById[$uid] = $val;
                }
            }
        } else {
            // A10: no split_with → use all active group members
            $memberIds = DB::table('group_memberships')
                ->where('group_id', $this->groupId)
                ->whereNull('left_on')
                ->pluck('user_id')
                ->toArray();
            if (empty($memberIds)) {
                $notes[]     = $this->note(
                    'NO_ACTIVE_MEMBERS', 'high',
                    "split_with is empty and the group has no active members.",
                    'Expense created with no splits; excluded from balances.'
                );
                $forceReview = true;
            } else {
                $notes[] = $this->note(
                    'SPLIT_WITH_DEFAULTED', 'info',
                    'split_with was empty; expense split equally among all active group members.',
                    'Used current active membership list.'
                );
            }
        }

        // ── A11: payer not in split ───────────────────────────────────────────
        $paidById = $paidByName ? $this->resolveUserId($paidByName) : null;
        if ($paidById && !in_array($paidById, $memberIds, true)) {
            $notes[] = $this->note(
                'PAYER_NOT_IN_SPLIT', 'low',
                "Payer ({$paidByName}) is not listed in split_with; added to split list.",
                'Payer added to memberIds so their fronted amount is tracked.'
            );
            $memberIds[] = $paidById;
        }

        // ── A12: unequal totals don't sum to expense amount ───────────────────
        if ($splitType === 'unequal' && !empty($detailsById)) {
            $splitSum = round(array_sum($detailsById), 2);
            if (abs($splitSum - $amountInr) > 0.02) {
                $notes[] = $this->note(
                    'UNEQUAL_SUM_MISMATCH', 'high',
                    "Unequal split values sum to ₹{$splitSum} but expense total is ₹{$amountInr} "
                    . "(difference: ₹" . round($amountInr - $splitSum, 2) . ").",
                    'SplitCalculator will distribute the rounding difference to the payer.'
                );
                $forceReview = true;
            }
        }

        // ── A13: percentage doesn't sum to 100 ───────────────────────────────
        if ($splitType === 'percentage' && !empty($detailsById)) {
            $pctSum = round(array_sum($detailsById), 2);
            if (abs($pctSum - 100) > 0.5) {
                $notes[] = $this->note(
                    'PERCENTAGE_SUM_MISMATCH', 'low',
                    "Percentage values sum to {$pctSum}% (expected 100%). "
                    . 'Values will be normalised so they sum to exactly 100%.',
                    'Normalised before computing shares.'
                );
            }
        }

        // ── compute shares ────────────────────────────────────────────────────
        $splits = [];
        if (!empty($memberIds)) {
            try {
                $splits = SplitCalculator::compute(
                    $amountInr, $splitType, $memberIds, $detailsById, $paidById
                );
            } catch (\Throwable $e) {
                $notes[] = $this->note(
                    'SPLIT_COMPUTE_ERROR', 'high',
                    "SplitCalculator threw: {$e->getMessage()}",
                    'Expense imported with no splits; needs_review=1.'
                );
                $forceReview = true;
            }
        }

        return [
            'kind'                   => 'expense',
            'row_number'             => $rowNum,
            'raw_row'                => $row,
            'description'            => $description,
            'date'                   => $dateIso,
            'paid_by_id'             => $paidById,
            'split_type'             => $splitType,
            'original_amount'        => $amount,
            'original_currency'      => $currency,
            'exchange_rate'          => $rate,
            'amount_inr'             => $amountInr,
            'notes_str'              => $row['notes'] ?? '',
            'splits'                 => $splits,
            'needs_review'           => $forceReview,
            'excluded_from_balances' => ($paidById === null),
            'notes'                  => $notes,
            'is_duplicate_of'        => null, // filled in by detectDuplicates
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DUPLICATE DETECTION  (A19, A20)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Mark candidates that are identical to an earlier row in the same import.
     * Also checks against already-persisted expenses in the DB.
     *
     * Two rows are "exact duplicates" when they share the same:
     *   date + description (lowercased) + amount_inr + paid_by_id
     *
     * "Possible duplicates" (same date + amount + payer, different description)
     * are flagged needs_review but still imported.
     */
    private function detectDuplicates(array &$candidates): void
    {
        $seen = []; // fingerprint → first candidate index

        foreach ($candidates as $idx => &$c) {
            if ($c['kind'] !== 'expense') {
                continue;
            }

            $fp = implode('|', [
                $c['date'],
                strtolower($c['description']),
                $c['amount_inr'],
                $c['paid_by_id'] ?? '',
            ]);

            $softFp = implode('|', [
                $c['date'],
                $c['amount_inr'],
                $c['paid_by_id'] ?? '',
            ]);

            // Check within this import batch first
            if (isset($seen[$fp])) {
                $c['notes'][] = $this->note(
                    'EXACT_DUPLICATE_IN_BATCH', 'high',
                    "Row {$c['row_number']} is an exact duplicate of row {$candidates[$seen[$fp]]['row_number']} "
                    . "in this import (same date, description, amount, payer).",
                    'needs_review=1. Both rows imported; link is_duplicate_of once first is saved.'
                );
                $c['needs_review'] = true;
            } else {
                $seen[$fp] = $idx;
            }

            // Check against DB
            $existing = DB::table('expenses')
                ->where('group_id', $this->groupId)
                ->where('expense_date', $c['date'])
                ->where('amount_inr', $c['amount_inr'])
                ->where('paid_by', $c['paid_by_id'])
                ->first();

            if ($existing) {
                $sameDesc = strtolower(trim($existing->description)) === strtolower($c['description']);
                $anomalyType = $sameDesc ? 'EXACT_DUPLICATE_IN_DB' : 'POSSIBLE_DUPLICATE_IN_DB';
                $severity    = $sameDesc ? 'high' : 'low';
                $c['notes'][] = $this->note(
                    $anomalyType, $severity,
                    ($sameDesc
                        ? "Exact match found in DB (expense #{$existing->id}): same date, description, amount, payer."
                        : "Possible duplicate: expense #{$existing->id} has same date/amount/payer but different description."),
                    'needs_review=1 and is_duplicate_of set to existing expense ID.'
                );
                $c['is_duplicate_of'] = $existing->id;
                $c['needs_review']    = true;
            }
        }
        unset($c);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PERSISTENCE
    // ──────────────────────────────────────────────────────────────────────────

    private function persistExpense(array $c, int $batchId): void
    {
        $reviewReason = $this->buildReviewReason($c['notes']);

        $expense = Expense::create([
            'group_id'               => $this->groupId,
            'description'            => $c['description'],
            'expense_date'           => $c['date'],
            'paid_by'                => $c['paid_by_id'],
            'split_type'             => $c['split_type'],
            'original_amount'        => $c['original_amount'],
            'original_currency'      => $c['original_currency'],
            'exchange_rate'          => $c['exchange_rate'],
            'amount_inr'             => $c['amount_inr'],
            'notes'                  => $c['notes_str'],
            'needs_review'           => $c['needs_review'],
            'review_reason'          => $reviewReason,
            'is_duplicate_of'        => $c['is_duplicate_of'],
            'excluded_from_balances' => $c['excluded_from_balances'],
            'source'                 => 'import',
            'import_batch_id'        => $batchId,
            'created_by'             => $this->importedBy,
        ]);

        foreach ($c['splits'] as $s) {
            ExpenseSplit::create([
                'expense_id'      => $expense->id,
                'user_id'         => $s['user_id'],
                'raw_value'       => $s['raw_value'],
                'share_amount_inr' => $s['share_amount_inr'],
            ]);
        }

        // Queue anomalies referencing the newly-created expense
        foreach ($c['notes'] as $n) {
            $this->pendingAnomalies[] = array_merge($n, [
                'row_number'        => $c['row_number'],
                'raw_row'           => json_encode($c['raw_row']),
                'expense_id'        => $expense->id,
                'settlement_id'     => null,
                'needs_human_review' => $c['needs_review'],
            ]);
        }
    }

    private function persistSettlement(array $c, int $batchId): void
    {
        $settlement = Settlement::create([
            'group_id'        => $this->groupId,
            'paid_by'         => $c['paid_by_id'],
            'paid_to'         => $c['paid_to_id'],
            'amount_inr'      => $c['amount_inr'],
            'settlement_date' => $c['date'],
            'notes'           => $c['notes_str'],
            'source'          => 'import',
            'import_batch_id' => $batchId,
            'created_by'      => $this->importedBy,
        ]);

        foreach ($c['notes'] as $n) {
            $this->pendingAnomalies[] = array_merge($n, [
                'row_number'        => $c['row_number'],
                'raw_row'           => json_encode($c['raw_row']),
                'expense_id'        => null,
                'settlement_id'     => $settlement->id,
                'needs_human_review' => $c['needs_review'],
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    /** Build a structured anomaly note array (not yet persisted). */
    private function note(
        string $type,
        string $severity,
        string $description,
        string $actionTaken
    ): array {
        return [
            'anomaly_type' => $type,
            'severity'     => $severity,
            'description'  => $description,
            'action_taken' => $actionTaken,
        ];
    }

    /** Concatenate high-severity notes into a review_reason string. */
    private function buildReviewReason(array $notes): ?string
    {
        $high = array_filter($notes, fn ($n) => $n['severity'] === 'high');
        if (empty($high)) {
            return null;
        }
        return implode(' | ', array_column(array_values($high), 'description'));
    }
}
