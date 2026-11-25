# Changelog

## [1.0.1] - 2026-11-25

### Fixed
- Validate lead status and score configuration, including default status membership and database score bounds.
- Validate required lead name, email, field lengths, currency and estimated value precision/range.
- Reject non-array or non-JSON-encodable custom fields and activity properties.
- Require persisted leads for assignment, status and activity operations.
- Validate conversion target identifiers and prevent concurrent duplicate conversion through a row lock.
- Keep lead updates and their activity history within database transactions.
- Add regression coverage for invalid inputs and configuration.

## [1.0.0] - 2026-11-17

### Added
- Lead, source, pipeline stage and activity models.
- Lead creation, filters, assignment, status and score management.
- Follow-up scope and conversion references.
- Configurable tables, models, statuses and score bounds.
- Migrations, PHPUnit tests and GitHub Actions workflow.
