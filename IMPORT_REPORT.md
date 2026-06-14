# Import Report: expenses_export.csv

This report is produced by **SplitTrack** upon ingesting the raw CSV spreadsheet export. It lists every data anomaly detected, its severity, description, and the automated action taken by the importer to maintain database integrity and correct the balances.

## Ingestion Summary

- **Source File:** `expenses_export.csv`
- **Total CSV Rows Processed:** 42 (excluding header)
- **Successfully Imported Expenses:** 40
- **Successfully Imported Settlements:** 2
- **Total Anomalies Detected:** 53

---

## Detailed Anomaly Log

| Row | Anomaly Type | Severity | Description | Action Taken |
|:---|:---|:---|:---|:---|
| **2** | `DATE_FORMAT_ISO` | Info | Date '2026-02-01' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **3** | `DATE_FORMAT_ISO` | Info | Date '2026-02-03' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **4** | `DATE_FORMAT_ISO` | Info | Date '2026-02-05' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **5** | `DATE_FORMAT_ISO` | Info | Date '2026-02-08' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **5** | `PAYER_MEMBERSHIP_MISMATCH` | Low | Payer (Dev) was not an active member on 2026-02-08. | Imported; payer credited but not added to split. |
| **5** | `MEMBERSHIP_MISMATCH` | High | 'Dev' is listed in split_with but was not an active member on 2026-02-08. | Removed from split; share redistributed among active split members. |
| **6** | `DATE_FORMAT_ISO` | Info | Date '2026-02-08' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **6** | `PAYER_MEMBERSHIP_MISMATCH` | Low | Payer (Dev) was not an active member on 2026-02-08. | Imported; payer credited but not added to split. |
| **6** | `MEMBERSHIP_MISMATCH` | High | 'Dev' is listed in split_with but was not an active member on 2026-02-08. | Removed from split; share redistributed among active split members. |
| **6** | `POSSIBLE_DUPLICATE_CONFLICT` | High | Row 6 has same date/amount/payer as row 5 with similar description ('dinner - marina bites' vs 'Dinner at Marina Bites'). | Imported and counted in balances; flagged `needs_review=1` for human comparison. |
| **7** | `DATE_FORMAT_ISO` | Info | Date '2026-02-10' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **7** | `AMOUNT_THOUSANDS_SEPARATOR` | Low | Amount '1,200' contains a comma (thousands separator); parsed as 1200. | Comma removed before parsing. |
| **8** | `DATE_FORMAT_ISO` | Info | Date '2026-02-12' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **9** | `DATE_FORMAT_ISO` | Info | Date '2026-02-14' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **10** | `DATE_FORMAT_ISO` | Info | Date '2026-02-15' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **10** | `AMOUNT_PRECISION` | Low | Amount 899.995 has more than 2 decimal places (sub-paisa); rounded to 900. | Rounded to 2 decimal places, half-up. |
| **11** | `DATE_FORMAT_ISO` | Info | Date '2026-02-18' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **11** | `NAME_NORMALIZED` | Low | Name 'Priya S' matched to 'Priya' (extra suffix dropped). | Matched to existing roster member. |
| **12** | `DATE_FORMAT_ISO` | Info | Date '2026-02-20' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **13** | `DATE_FORMAT_ISO` | Info | Date '2026-02-22' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **13** | `MISSING_PAYER` | High | Row 13 has no paid_by value. | Imported with paid_by unset; excluded from balances until a person is assigned. |
| **14** | `DATE_FORMAT_ISO` | Info | Date '2026-02-25' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **14** | `SETTLEMENT_AS_EXPENSE` | High | split_with is a single person (Aisha) who is not the payer — this is a direct payment from Rohan to Aisha, not a shared expense. | Imported as a settlement (Rohan → Aisha, ₹5000). |
| **15** | `DATE_FORMAT_ISO` | Info | Date '2026-02-28' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **15** | `PERCENTAGE_SUM_MISMATCH` | Low | Percentage values sum to 110% (expected 100%). Values will be normalised so they sum to exactly 100%. | Normalised before computing shares. |
| **19** | `MEMBERSHIP_MISMATCH` | High | 'Dev' is listed in split_with but was not an active member on 2026-03-08. | Removed from split; share redistributed among active split members. |
| **20** | `CURRENCY_CONVERTED` | Info | 540 USD converted to INR at the fixed Goa-trip rate of 83 → ₹44820. | amount_inr = 540 × 83 = 44820. Fixed rate documented in DECISIONS.md. |
| **21** | `CURRENCY_CONVERTED` | Info | 84 USD converted to INR at the fixed Goa-trip rate of 83 → ₹6972. | amount_inr = 84 × 83 = 6972. Fixed rate documented in DECISIONS.md. |
| **23** | `CURRENCY_CONVERTED` | Info | 150 USD converted to INR at the fixed Goa-trip rate of 83 → ₹12450. | amount_inr = 150 × 83 = 12450. Fixed rate documented in DECISIONS.md. |
| **23** | `UNKNOWN_MEMBER` | High | 'Dev's friend Kabir' in split_with does not match any known user. | Row excluded from balance calculation; needs_review set. |
| **25** | `POSSIBLE_DUPLICATE_CONFLICT` | High | Row 25 is a possible duplicate of row 24 on the same date with a similar description ('Thalassa dinner' vs 'Dinner at Thalassa') but different payer/amount. | Imported and counted in balances; flagged `needs_review=1` for human comparison. |
| **26** | `NEGATIVE_AMOUNT` | Info | Amount is negative (-30). Treated as a refund/credit that reduces balances. | Imported as-is; negative share reduces what members owe. |
| **26** | `CURRENCY_CONVERTED` | Info | -30 USD converted to INR at the fixed Goa-trip rate of 83 → ₹-2490. | amount_inr = -30 × 83 = -2490. Fixed rate documented in DECISIONS.md. |
| **27** | `DATE_YEAR_MISSING` | Info | 'Mar 14' has no year component; inferred 2026 (the only year appearing elsewhere in this file). | Year 2026 assumed. |
| **27** | `MEMBERSHIP_MISMATCH` | High | 'Dev' is listed in split_with but was not an active member on 2026-03-14. | Removed from split; share redistributed among active split members. |
| **28** | `CURRENCY_DEFAULTED` | Low | Row 28: currency field is empty; defaulted to INR (group home currency). | currency=INR assumed. |
| **31** | `ZERO_AMOUNT` | Info | Amount is 0. Imported for the audit trail but has no effect on any balance. | Imported with amount_inr = 0. |
| **32** | `PERCENTAGE_SUM_MISMATCH` | Low | Percentage values sum to 110% (expected 100%). Values will be normalised so they sum to exactly 100%. | Normalised before computing shares. |
| **34** | `AMBIGUOUS_DATE_FORMAT` | High | '04/05/2026' is ambiguous (day and month both ≤ 12). Read as DD/MM/YYYY = 2026-05-04, but that date falls AFTER the next row (2026-04-01), breaking chronological order. MM/DD/YYYY would give 2026-04-05, which fits chronologically. | Imported with date 2026-05-04 and flagged `needs_review=1`. Edit the expense to change it to 2026-04-05 if that is correct. |
| **35** | `DATE_FORMAT_ISO` | Info | Date '2026-04-01' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **36** | `DATE_FORMAT_ISO` | Info | Date '2026-04-02' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **36** | `MEMBERSHIP_MISMATCH` | High | 'Meera' is listed in split_with but was not an active member on 2026-04-02. | Removed from split; share redistributed among active split members. |
| **37** | `DATE_FORMAT_ISO` | Info | Date '2026-04-05' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **38** | `DATE_FORMAT_ISO` | Info | Date '2026-04-08' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **38** | `SETTLEMENT_AS_EXPENSE` | High | split_with is a single person (Aisha) who is not the payer — this is a direct payment from Sam to Aisha, not a shared expense. | Imported as a settlement (Sam → Aisha, ₹15000). |
| **39** | `DATE_FORMAT_ISO` | Info | Date '2026-04-10' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **39** | `MEMBERSHIP_MISMATCH` | High | 'Sam' is listed in split_with but was not an active member on 2026-04-10. | Removed from split; share redistributed among active split members. |
| **39** | `PAYER_MEMBERSHIP_MISMATCH` | Low | Payer (Sam) was not an active member on 2026-04-10. | Imported; payer credited but not added to split. |
| **40** | `DATE_FORMAT_ISO` | Info | Date '2026-04-12' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **40** | `MEMBERSHIP_MISMATCH` | High | 'Sam' is listed in split_with but was not an active member on 2026-04-12. | Removed from split; share redistributed among active split members. |
| **41** | `DATE_FORMAT_ISO` | Info | Date '2026-04-15' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **42** | `DATE_FORMAT_ISO` | Info | Date '2026-04-18' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
| **43** | `DATE_FORMAT_ISO` | Info | Date '2026-04-20' uses ISO (YYYY-MM-DD) format; all others on this sheet use DD/MM/YYYY. | Parsed as ISO; no ambiguity. |
