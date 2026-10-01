# qbank_duplicate

`qbank_duplicate` is a Moodle 4.5+ question-bank plugin that finds exact, near-textual, and semantic duplicate questions
while avoiding an N² AI comparison of the whole bank.

The plugin is intentionally conservative: PHP performs normalization, hashing, answer signatures, keywords, MinHash/LSH
bucketing, candidate scoring, cache invalidation, permissions, batching and progress tracking. AI is used only after a
pair has survived those local filters. No question is deleted automatically.

## Requirements

- Moodle 4.5 or later.
- `local_ai_bridge >= 2026093001`: https://github.com/EduardoKrausME/moodle-local_ai_bridge/
- The bridge purpose `qbankduplicate-compare` must be enabled and routed for the users who run semantic scans.

`version.php` declares the dependency explicitly. This plugin does not contain API keys, provider endpoints, model
settings or direct OpenAI/Gemini/Claude/Ollama calls.

All AI calls are made only through:

```php
\local_ai_bridge\api::generate('qbankduplicate-compare', $messages);
```

## How it scales

A scan works in four layers:

1. The latest non-hidden version of each question in the selected category is normalized and cached
   by `questionbankentryid` and content hash.
2. Cheap indexes are generated from exact normalized-text hashes, answer signatures, significant keywords and MinHash
   bands.
3. Only questions sharing a useful bucket are scored locally. Oversized generic buckets are skipped, exact duplicate
   clusters use a connected N−1 chain instead of generating every pair, each question has a candidate cap, and every
   scan has a hard cap on new AI pairs.
4. Candidate pairs are processed by Moodle adhoc tasks in bounded batches. Previously analysed pairs are reused while
   both source hashes remain unchanged.

This means the plugin never constructs or sends all possible `N × N` combinations to AI.

## Semantic classifications

The bridge must return a strict JSON object using one of these values:

- `same_question`
- `strongly_overlapping`
- `related_but_distinct`
- `unrelated`

The response also contains `confidence`, `reason`, and an `evidence` array. The parser rejects invalid classifications,
invalid confidence ranges, malformed JSON, and unexpected evidence types.

## Human decisions and invalidation

The report shows Question A, Question B, local similarity, semantic classification, confidence, explanation and
evidence. Teachers can open either question, mark a pair as **not duplicate**, or **ignore** it.

A `notduplicate` decision suppresses the pair while the two question source hashes remain unchanged. Question lifecycle
events invalidate non-ignored comparisons and cached snapshots immediately. If either question receives a new/changed
version, that pair may therefore be reconsidered on the next scan.

An `ignored` pair is deliberately stronger: it remains suppressed even if the question later changes, so a pair
explicitly dismissed by the teacher does not keep reappearing. Nothing is deleted from the question bank.

## Security and privacy

The report is category-scoped and checks both Moodle question-bank access and the plugin capabilities in the category
context. The background tasks run as the user who queued the scan, so the bridge and Moodle capability checks are
applied again during asynchronous processing.

The plugin sends only question content needed for semantic comparison: local opaque IDs, question type, plain question
text, answer options and local heuristic evidence. It does not send or store student attempts, student answers, grades,
enrolment data or other student information.

The plugin itself does not persist AI prompts or provider credentials. AI Bridge controls provider routing, tenant
isolation, credits and its own usage metadata.

## Capabilities

- `qbank/duplicate:view`
- `qbank/duplicate:scan`
- `qbank/duplicate:manage`

Editing teachers and managers receive these capabilities by default. Students do not.

## Administration settings

- Maximum non-exact bucket size.
- Maximum candidate pairs per question.
- Maximum new AI pairs per scan.
- Number of candidate pairs processed by each adhoc task.

These are safety valves, not accuracy controls. Raising them can find more borderline pairs, but it also increases
database work and AI consumption.

## Tests

PHPUnit coverage includes:

- normalization and stable hashing;
- MinHash determinism;
- candidate generation;
- invalidation after a question changes;
- persistence of ignored pairs;
- capability defaults;
- strict AI response parsing.

The GitHub Actions workflow tests PostgreSQL and MariaDB, Moodle 4.5 and a current Moodle branch,
installs `local_ai_bridge` as a required extra plugin, runs `moodle-plugin-ci`, and
runs `EduardoKrausME/moodle-plugin-validate`.

## Installation

Install the directory as:

```
question/bank/duplicate
```

Then complete the Moodle upgrade, configure the `qbankduplicate-compare` purpose in AI Bridge, and open **Question
bank → Duplicate questions** while viewing the category you want to analyse.

## License

GNU GPL v3 or later.
