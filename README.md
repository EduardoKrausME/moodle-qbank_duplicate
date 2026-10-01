# qbank_duplicate

`qbank_duplicate` is a Moodle question-bank plugin that finds exact, near-textual, and semantic duplicate questions
while avoiding an N² AI comparison of the whole bank.

The plugin is intentionally conservative: PHP performs normalization, hashing, answer signatures, keywords, MinHash/LSH
bucketing, candidate scoring, cache invalidation, permissions, batching and progress tracking. AI is used only after a
pair has survived those local filters. No question is deleted automatically.

## How it scales

A scan works in four layers:

1. The latest non-hidden revision of each question in the selected category is normalized and cached
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
revision, that pair may therefore be reconsidered on the next scan.

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
