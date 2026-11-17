<?php
declare(strict_types=1);
namespace ItmoSoft\Leads;
use Illuminate\Support\ServiceProvider;
use ItmoSoft\Leads\Services\LeadManager;
class LeadsServiceProvider extends ServiceProvider {
 public function register():void{$this->mergeConfigFrom(__DIR__.'/../config/itmosoft-leads.php','itmosoft-leads');$this->app->singleton(LeadManager::class);}
 public function boot():void{$this->loadMigrationsFrom(__DIR__.'/../database/migrations');$this->publishes([__DIR__.'/../config/itmosoft-leads.php'=>config_path('itmosoft-leads.php')],'itmosoft-leads-config');}
}
