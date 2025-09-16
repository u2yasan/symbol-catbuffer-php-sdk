# Repository Guidelines

## Project Structure & Module Organization
The PHP sources live in `src/` under the `SymbolSdk\` namespace; IO, model, and transaction classes follow the catbuffer layout. Tests under `tests/` mirror the same hierarchy and rely on JSON or `.hex` fixtures stored in `tests/vectors`. Prompt definitions sit in `prompts/` (shared guardrails under `_partials/`) and are executed by the TypeScript runner in `tools/`. Keep cache directories such as `var/phpstan`, along with `vendor/` and `node_modules/`, untracked while reviewing generated diffs in `src/`.

## Build, Test & Development Commands
Start by running `composer install` and `npm install`. `composer analyse` wraps PHPStan level 8 and should succeed with an empty report; its cache is written to `var/phpstan`. Execute the PHPUnit suite via `composer test`, and verify coding style with `composer check:cs`; use `composer fix:cs` to apply PSR-12 fixes. `composer ci` runs validation, lint, static analysis, and tests in one sweep. Prompt-driven updates are produced with `npm run gen:mosaic-id`, `npm run gen:io`, or the transaction-specific `npm run gen:tx:*` scripts, each invoking `tools/promptgen.ts`.

## Coding Style & Naming Conventions
Adhere to PSR-12, four-space indentation, and the `<?php` + `declare(strict_types=1);` preamble. Declare typed properties, prefer `readonly` when values are assigned exactly once, and restrict exceptions to `InvalidArgumentException` for validation and `RuntimeException` for I/O failure. Document array shapes with `@param list<T>` or `@return array{...}` annotations, and represent unsigned 64-bit numbers as decimal strings. Run `composer check:cs` or the guarded Rector rules rather than manual formatting.

## Testing Guidelines
Tests target PHPUnit 10 and are grouped by feature, e.g. `tests/Transaction/TransferFromJsonVectorsTest.php`. Add new fixtures to `tests/vectors` and assert round-trip encode/decode to guard binary compatibility. If a scenario must be skipped, call `markTestSkipped()` and immediately `return;` to satisfy static analysis.

## Commit & Pull Request Guidelines
History alternates between `chore:`-prefixed commits and concise imperative statements like `Update TransferTransaction.php`; follow the same style, grouping related generator output. Include the prompt command and resulting files in each commit message or PR body, and link any tracking issue. Before requesting review, rerun `composer analyse` and `composer test`, and note the results.

## Prompt Generation Workflow
Stage manual edits before regeneration so the resulting diff highlights generated changes. Run the relevant `npm run gen:*` command, confirm the output honours the guardrails (strict types, EOF checks, documented array shapes), then rerun the affected tests and analysis. When guardrail tweaks are needed, update the `_partials` templates rather than hand-editing generated PHP.
