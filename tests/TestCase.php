<?php
declare(strict_types=1);
namespace ItmoSoft\Leads\Tests;
use Illuminate\Support\Facades\Artisan;
use ItmoSoft\Leads\LeadsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
abstract class TestCase extends Orchestra {
 protected function getPackageProviders($app):array{return [LeadsServiceProvider::class];}
 protected function getEnvironmentSetUp($app):void{$app['config']->set('database.default','testing');$app['config']->set('database.connections.testing',['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]);$app['config']->set('itmosoft-leads.table_prefix','itmosoft_');}
 protected function setUp():void{parent::setUp();Artisan::call('migrate',['--database'=>'testing','--path'=>realpath(__DIR__.'/../database/migrations'),'--realpath'=>true,'--force'=>true]);}
}
