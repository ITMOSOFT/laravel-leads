<?php
declare(strict_types=1);

namespace ItmoSoft\Leads\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use ItmoSoft\Leads\Models\Lead;
use ItmoSoft\Leads\Models\LeadActivity;
use InvalidArgumentException;
use JsonException;

class LeadManager
{
    public function __construct()
    {
        $this->validateConfiguration();
    }

    public function create(array $data, ?int $actorId = null): Lead
    {
        $data['status'] ??= config('itmosoft-leads.defaults.status', 'new');
        $data['score'] ??= config('itmosoft-leads.defaults.score', 0);
        $data['currency'] ??= config('itmosoft-leads.defaults.currency', 'USD');

        $this->validateLeadData($data);

        return DB::transaction(function () use ($data, $actorId): Lead {
            $lead = Lead::query()->create($data);
            $lead->activities()->create([
                'type' => 'created',
                'description' => 'Lead created.',
                'actor_id' => $actorId,
                'properties' => ['status' => $lead->status],
            ]);

            return $lead;
        });
    }

    public function search(array $filters = []): Builder
    {
        if (isset($filters['status']) && !in_array($filters['status'], config('itmosoft-leads.statuses', []), true)) {
            throw new InvalidArgumentException('The supplied lead status is not configured.');
        }

        return Lead::query()
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['source_id'] ?? null, fn (Builder $q, $v) => $q->where('lead_source_id', $v))
            ->when($filters['stage_id'] ?? null, fn (Builder $q, $v) => $q->where('lead_stage_id', $v))
            ->when(array_key_exists('assigned_to', $filters), function (Builder $q) use ($filters): void {
                $filters['assigned_to'] === null
                    ? $q->whereNull('assigned_to')
                    : $q->where('assigned_to', $filters['assigned_to']);
            })
            ->when($filters['search'] ?? null, function (Builder $q, string $term): void {
                $q->where(function (Builder $nested) use ($term): void {
                    $nested->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('company', 'like', "%{$term}%");
                });
            });
    }

    public function recordActivity(
        Lead $lead,
        string $type,
        string $description,
        ?int $actorId = null,
        array $properties = [],
    ): LeadActivity {
        $this->assertPersistedLead($lead);

        if ($type === '' || mb_strlen($type) > 48) {
            throw new InvalidArgumentException('Activity type must contain between 1 and 48 characters.');
        }

        if (trim($description) === '') {
            throw new InvalidArgumentException('Activity description cannot be empty.');
        }

        $this->assertJsonEncodable($properties, 'Activity properties');

        return $lead->activities()->create([
            'type' => $type,
            'description' => $description,
            'actor_id' => $actorId,
            'properties' => $properties === [] ? null : $properties,
        ]);
    }

    public function convert(Lead $lead, string $targetType, int|string $targetId, ?int $actorId = null): Lead
    {
        $this->assertPersistedLead($lead);

        if ($lead->status === 'converted') {
            throw new InvalidArgumentException('This lead has already been converted.');
        }

        if (!in_array($targetType, ['customer', 'account', 'contact', 'other'], true)) {
            throw new InvalidArgumentException('Unsupported conversion target type.');
        }

        $targetId = (string) $targetId;
        if ($targetId === '' || mb_strlen($targetId) > 64) {
            throw new InvalidArgumentException('Conversion target ID must contain between 1 and 64 characters.');
        }

        if (!in_array('converted', config('itmosoft-leads.statuses', []), true)) {
            throw new InvalidArgumentException('The configured lead statuses must include converted to support conversion.');
        }

        return DB::transaction(function () use ($lead, $targetType, $targetId, $actorId): Lead {
            $lockedLead = Lead::query()->lockForUpdate()->findOrFail($lead->getKey());

            if ($lockedLead->status === 'converted') {
                throw new InvalidArgumentException('This lead has already been converted.');
            }

            $lockedLead->converted_to_type = $targetType;
            $lockedLead->converted_to_id = $targetId;
            $lockedLead->setStatus('converted', $actorId, 'Lead converted.');
            $lockedLead->activities()->create([
                'type' => 'converted',
                'description' => "Lead converted to {$targetType} {$targetId}.",
                'actor_id' => $actorId,
                'properties' => ['target_type' => $targetType, 'target_id' => $targetId],
            ]);

            return $lockedLead->refresh();
        });
    }

    private function validateConfiguration(): void
    {
        $statuses = config('itmosoft-leads.statuses');
        if (!is_array($statuses) || $statuses === [] || count($statuses) !== count(array_unique($statuses, SORT_REGULAR))) {
            throw new InvalidArgumentException('Lead statuses must be a non-empty array of unique values.');
        }

        foreach ($statuses as $status) {
            if (!is_string($status) || $status === '' || mb_strlen($status) > 32) {
                throw new InvalidArgumentException('Each configured lead status must contain between 1 and 32 characters.');
            }
        }

        $defaultStatus = config('itmosoft-leads.defaults.status', 'new');
        if (!in_array($defaultStatus, $statuses, true)) {
            throw new InvalidArgumentException('The default lead status must be included in configured statuses.');
        }

        $minimum = config('itmosoft-leads.score.minimum', 0);
        $maximum = config('itmosoft-leads.score.maximum', 100);
        if (!is_int($minimum) || !is_int($maximum) || $minimum < 0 || $maximum > 255 || $minimum > $maximum) {
            throw new InvalidArgumentException('Lead score bounds must be integers between 0 and 255, with minimum not exceeding maximum.');
        }

        $currency = config('itmosoft-leads.defaults.currency', 'USD');
        if (!is_string($currency) || !preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('The default lead currency must be a three-letter uppercase currency code.');
        }
    }

    private function validateLeadData(array $data): void
    {
        $status = $data['status'] ?? null;
        if (!is_string($status) || !in_array($status, config('itmosoft-leads.statuses', []), true)) {
            throw new InvalidArgumentException('The supplied lead status is not configured.');
        }

        $score = $data['score'] ?? null;
        if (!is_int($score) || $score < config('itmosoft-leads.score.minimum', 0) || $score > config('itmosoft-leads.score.maximum', 100)) {
            throw new InvalidArgumentException('Lead score must be an integer within the configured score bounds.');
        }

        $firstName = $data['first_name'] ?? null;
        if (!is_string($firstName) || trim($firstName) === '' || mb_strlen($firstName) > 255) {
            throw new InvalidArgumentException('Lead first name is required and must not exceed 255 characters.');
        }

        $limits = [
            'last_name' => 255,
            'email' => 255,
            'phone' => 50,
            'company' => 255,
            'job_title' => 255,
            'website' => 255,
        ];

        foreach ($limits as $field => $limit) {
            if (isset($data[$field]) && (!is_string($data[$field]) || mb_strlen($data[$field]) > $limit)) {
                throw new InvalidArgumentException("Lead {$field} must be a string of at most {$limit} characters.");
            }
        }

        if (isset($data['email']) && $data['email'] !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Lead email must be a valid email address.');
        }

        $currency = $data['currency'] ?? null;
        if (!is_string($currency) || !preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Lead currency must be a three-letter uppercase currency code.');
        }

        if (array_key_exists('estimated_value', $data) && $data['estimated_value'] !== null) {
            $value = $data['estimated_value'];
            if (!is_int($value) && !is_float($value) && !is_string($value)) {
                throw new InvalidArgumentException('Estimated value must be numeric.');
            }

            if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0 || (float) $value > 999999999999.99) {
                throw new InvalidArgumentException('Estimated value must be between 0 and 999999999999.99.');
            }

            $decimal = (string) $value;
            if (str_contains($decimal, '.') && strlen(rtrim(substr(strrchr($decimal, '.') ?: '', 1), '0')) > 2) {
                throw new InvalidArgumentException('Estimated value may contain at most two decimal places.');
            }
        }

        if (array_key_exists('custom_fields', $data) && $data['custom_fields'] !== null) {
            if (!is_array($data['custom_fields'])) {
                throw new InvalidArgumentException('Custom fields must be an array or null.');
            }
            $this->assertJsonEncodable($data['custom_fields'], 'Custom fields');
        }
    }

    private function assertPersistedLead(Lead $lead): void
    {
        if (!$lead->exists || $lead->getKey() === null) {
            throw new InvalidArgumentException('The lead must be persisted before this operation.');
        }
    }

    private function assertJsonEncodable(array $value, string $label): void
    {
        try {
            json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("{$label} must be JSON encodable.", previous: $exception);
        }
    }
}
