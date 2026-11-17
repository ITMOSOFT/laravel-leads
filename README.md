# ITMOSOFT Laravel Leads

Lead capture, qualification, assignment, pipeline stages, activity history, follow-up tracking, and conversion references for Laravel 12 and 13.

## Requirements
- PHP 8.3+
- Laravel 12 or 13

## Installation
```bash
composer require itmosoft/laravel-leads
php artisan vendor:publish --tag=itmosoft-leads-config
php artisan migrate
```

## Create a lead
```php
use ItmoSoft\Leads\Services\LeadManager;

$lead = app(LeadManager::class)->create([
    'first_name' => 'Sam',
    'last_name' => 'Taylor',
    'email' => 'sam@example.com',
    'company' => 'Example Ltd',
    'status' => 'new',
    'score' => 25,
    'estimated_value' => 5000,
    'currency' => 'USD',
    'follow_up_at' => now()->addDays(2),
], auth()->id());
```

Statuses are configurable; defaults: `new`, `contacted`, `qualified`, `unqualified`, `converted`, `lost`.

## Sources and pipeline stages
```php
use ItmoSoft\Leads\Models\LeadSource;
use ItmoSoft\Leads\Models\LeadStage;

$source = LeadSource::query()->create(['name'=>'Website','slug'=>'website']);
$stage = LeadStage::query()->create(['name'=>'Discovery','slug'=>'discovery','position'=>1]);
$lead->update(['lead_source_id'=>$source->id,'lead_stage_id'=>$stage->id]);
```
Stage order and won/lost markers are data only in this release; no enforced sales state machine is included.

## Search
```php
$leads = app(LeadManager::class)->search([
    'status'=>'qualified',
    'source_id'=>2,
    'stage_id'=>3,
    'assigned_to'=>auth()->id(),
    'search'=>'Taylor',
])->paginate(20);
```
Use `'assigned_to' => null` to find unassigned leads. `Lead::query()->dueForFollowUp()->get()` finds open leads whose follow-up time has arrived.

## Assignment, status and scoring
```php
$lead->assignTo(auth()->id(), auth()->id());
$lead->setStatus('qualified', auth()->id(), 'Budget confirmed.');
$lead->setScore(82);
```
Assignment and status changes create activity records. Scores default to 0–100.

## Activity timeline
```php
app(LeadManager::class)->recordActivity(
    $lead, 'call', 'Discussed requirements.', auth()->id(),
    ['duration_minutes'=>15]
);
$activities = $lead->activities;
```
Activity type strings are extensible; validate your application's vocabulary at the boundary.

## Conversion
The package does not create a customer. Create one through your customer/domain package, then store a reference:
```php
$customer = /* create through your application integration */;
app(LeadManager::class)->convert($lead, 'customer', $customer->getKey(), auth()->id());
```
Allowed target labels: `customer`, `account`, `contact`, `other`. The target ID is a scalar string with no foreign key, avoiding coupling to a specific customer schema.

## Boundaries and security
- This package owns lead records, stages and lead activities. It does not own customer profiles, campaigns, email delivery, notifications, promotions, forms, HTTP routes, controllers, admin UI or authorization policies.
- `assigned_to` and `actor_id` are scalar user identifiers; no user foreign key is imposed.
- Add authorization checks before reading, assigning, exporting, converting or deleting leads.
- Validate email, phone, custom fields and status transitions in your application.
- Configure consent, retention and privacy workflows for your jurisdiction.
- Search uses SQL `LIKE`, not a full-text search engine.
- Tenant isolation is not automatic. Integrate with your tenancy layer and scope all lead queries appropriately.

## Tests
```bash
composer validate --strict
composer install
composer test
```

## License
MIT.
