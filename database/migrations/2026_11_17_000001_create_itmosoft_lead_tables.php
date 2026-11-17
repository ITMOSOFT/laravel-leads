<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 private function table(string $key,string $default):string{return config('itmosoft-leads.table_prefix','itmosoft_').config("itmosoft-leads.tables.{$key}",$default);}
 public function up():void {
  Schema::create($this->table('sources','lead_sources'),function(Blueprint $t){$t->id();$t->string('name');$t->string('slug')->unique();$t->text('description')->nullable();$t->boolean('is_active')->default(true)->index();$t->timestamps();});
  Schema::create($this->table('stages','lead_stages'),function(Blueprint $t){$t->id();$t->string('name');$t->string('slug')->unique();$t->unsignedInteger('position')->default(0)->index();$t->boolean('is_active')->default(true)->index();$t->boolean('is_won')->default(false);$t->boolean('is_lost')->default(false);$t->timestamps();});
  Schema::create($this->table('leads','leads'),function(Blueprint $t){$t->id();$t->string('first_name');$t->string('last_name')->nullable();$t->string('email')->nullable()->index();$t->string('phone',50)->nullable()->index();$t->string('company')->nullable()->index();$t->string('job_title')->nullable();$t->string('website')->nullable();$t->string('status',32)->default('new')->index();$t->unsignedTinyInteger('score')->default(0)->index();$t->foreignId('lead_source_id')->nullable()->constrained($this->table('sources','lead_sources'))->nullOnDelete();$t->foreignId('lead_stage_id')->nullable()->constrained($this->table('stages','lead_stages'))->nullOnDelete();$t->unsignedBigInteger('assigned_to')->nullable()->index();$t->decimal('estimated_value',14,2)->nullable();$t->char('currency',3)->default('USD');$t->text('notes')->nullable();$t->json('custom_fields')->nullable();$t->timestamp('last_contacted_at')->nullable();$t->timestamp('follow_up_at')->nullable()->index();$t->timestamp('converted_at')->nullable();$t->string('converted_to_type',32)->nullable();$t->string('converted_to_id',64)->nullable();$t->timestamps();});
  Schema::create($this->table('activities','lead_activities'),function(Blueprint $t){$t->id();$t->foreignId('lead_id')->constrained($this->table('leads','leads'))->cascadeOnDelete();$t->string('type',48)->index();$t->text('description');$t->unsignedBigInteger('actor_id')->nullable()->index();$t->json('properties')->nullable();$t->timestamps();$t->index(['lead_id','created_at']);});
 }
 public function down():void{Schema::dropIfExists($this->table('activities','lead_activities'));Schema::dropIfExists($this->table('leads','leads'));Schema::dropIfExists($this->table('stages','lead_stages'));Schema::dropIfExists($this->table('sources','lead_sources'));}
};
