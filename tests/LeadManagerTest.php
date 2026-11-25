<?php
declare(strict_types=1);

namespace ItmoSoft\Leads\Tests;

use Illuminate\Support\Facades\Schema;
use ItmoSoft\Leads\Models\Lead;
use ItmoSoft\Leads\Models\LeadSource;
use ItmoSoft\Leads\Models\LeadStage;
use ItmoSoft\Leads\Services\LeadManager;
use InvalidArgumentException;

class LeadManagerTest extends TestCase
{
    public function test_creates_lead_with_default_status_and_activity(): void
    {
        $lead = app(LeadManager::class)->create(['first_name' => 'Adeel', 'email' => 'adeel@example.test'], 7);
        $this->assertSame('new', $lead->status);
        $this->assertSame(0, $lead->score);
        $this->assertSame('created', $lead->activities()->first()->type);
    }

    public function test_rejects_unknown_status(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(LeadManager::class)->create(['first_name' => 'Bad', 'status' => 'unknown']);
    }

    public function test_rejects_out_of_range_or_non_integer_score(): void
    {
        $manager = app(LeadManager::class);
        try {
            $manager->create(['first_name' => 'Bad', 'score' => 101]);
            $this->fail('Expected score range validation.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $manager->create(['first_name' => 'Bad', 'score' => 1.5]);
    }

    public function test_rejects_invalid_email_currency_and_first_name(): void
    {
        $manager = app(LeadManager::class);
        foreach ([
            ['first_name' => 'Adeel', 'email' => 'not-an-email'],
            ['first_name' => 'Adeel', 'currency' => 'US'],
            ['first_name' => '   '],
        ] as $data) {
            try {
                $manager->create($data);
                $this->fail('Expected lead input validation.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_rejects_invalid_estimated_value_and_json_fields(): void
    {
        $manager = app(LeadManager::class);
        $resource = fopen('php://memory', 'r');

        try {
            foreach ([
                ['first_name' => 'Bad', 'estimated_value' => INF],
                ['first_name' => 'Bad', 'estimated_value' => 1.234],
                ['first_name' => 'Bad', 'custom_fields' => ['invalid' => $resource]],
            ] as $data) {
                try {
                    $manager->create($data);
                    $this->fail('Expected value validation.');
                } catch (InvalidArgumentException) {
                    $this->assertTrue(true);
                }
            }
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function test_rejects_invalid_configuration(): void
    {
        config(['itmosoft-leads.score' => ['minimum' => 100, 'maximum' => 10]]);
        $this->expectException(InvalidArgumentException::class);
        new LeadManager();
    }

    public function test_searches_by_name_and_status(): void
    {
        $manager = app(LeadManager::class);
        $manager->create(['first_name' => 'Adeel']);
        $manager->create(['first_name' => 'Sara']);
        $results = $manager->search(['status' => 'new', 'search' => 'Adeel'])->get();
        $this->assertCount(1, $results);
        $this->assertSame('Adeel', $results->first()->first_name);
    }

    public function test_assignment_records_activity(): void
    {
        $lead = app(LeadManager::class)->create(['first_name' => 'Adeel']);
        $lead->assignTo(42, 9);
        $this->assertEquals(42, $lead->fresh()->assigned_to);
        $this->assertSame('assigned', $lead->activities()->first()->type);
    }

    public function test_status_changes_record_history_and_conversion_timestamp(): void
    {
        $lead = app(LeadManager::class)->create(['first_name' => 'Adeel']);
        $lead->setStatus('qualified', 9);
        $lead->setStatus('converted', 9);
        $this->assertNotNull($lead->fresh()->converted_at);
        $this->assertSame(2, $lead->activities()->where('type', 'status_changed')->count());
    }

    public function test_score_can_be_updated(): void
    {
        $lead = app(LeadManager::class)->create(['first_name' => 'Adeel']);
        $lead->setScore(85);
        $this->assertSame(85, $lead->fresh()->score);
    }

    public function test_conversion_stores_external_reference(): void
    {
        $lead = app(LeadManager::class)->create(['first_name' => 'Adeel']);
        $result = app(LeadManager::class)->convert($lead, 'customer', 1234, 7);
        $this->assertSame('converted', $result->status);
        $this->assertSame('customer', $result->converted_to_type);
        $this->assertSame('1234', $result->converted_to_id);
    }

    public function test_prevents_double_conversion_and_invalid_target_id(): void
    {
        $lead = app(LeadManager::class)->create(['first_name' => 'Adeel']);
        try {
            app(LeadManager::class)->convert($lead, 'customer', str_repeat('x', 65));
            $this->fail('Expected target ID validation.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        app(LeadManager::class)->convert($lead, 'customer', 1);
        $this->expectException(InvalidArgumentException::class);
        app(LeadManager::class)->convert($lead->fresh(), 'customer', 2);
    }

    public function test_rejects_operations_on_unsaved_leads(): void
    {
        $lead = new Lead(['first_name' => 'Unsaved']);
        $this->expectException(InvalidArgumentException::class);
        $lead->assignTo(1);
    }

    public function test_due_follow_up_excludes_closed_leads(): void
    {
        $manager = app(LeadManager::class);
        $due = $manager->create(['first_name' => 'Due', 'follow_up_at' => now()->subMinute()]);
        $manager->create(['first_name' => 'Future', 'follow_up_at' => now()->addDay()]);
        $closed = $manager->create(['first_name' => 'Closed', 'follow_up_at' => now()->subDay()]);
        $closed->setStatus('lost');
        $this->assertSame([$due->id], Lead::query()->dueForFollowUp()->pluck('id')->all());
    }

    public function test_source_and_stage_relations(): void
    {
        $source = LeadSource::query()->create(['name' => 'Website', 'slug' => 'website']);
        $stage = LeadStage::query()->create(['name' => 'Discovery', 'slug' => 'discovery', 'position' => 1]);
        $lead = app(LeadManager::class)->create([
            'first_name' => 'Adeel',
            'lead_source_id' => $source->id,
            'lead_stage_id' => $stage->id,
        ]);
        $this->assertSame('Website', $lead->source->name);
        $this->assertSame('Discovery', $lead->stage->name);
    }

    public function test_expected_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('itmosoft_leads'));
        $this->assertTrue(Schema::hasTable('itmosoft_lead_sources'));
        $this->assertTrue(Schema::hasTable('itmosoft_lead_stages'));
        $this->assertTrue(Schema::hasTable('itmosoft_lead_activities'));
    }
}
