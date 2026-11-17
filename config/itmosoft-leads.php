<?php
declare(strict_types=1);
return [
 'table_prefix'=>'itmosoft_',
 'tables'=>['leads'=>'leads','sources'=>'lead_sources','stages'=>'lead_stages','activities'=>'lead_activities'],
 'models'=>[
  'lead'=>ItmoSoft\Leads\Models\Lead::class,
  'source'=>ItmoSoft\Leads\Models\LeadSource::class,
  'stage'=>ItmoSoft\Leads\Models\LeadStage::class,
  'activity'=>ItmoSoft\Leads\Models\LeadActivity::class,
 ],
 'user_model'=>env('AUTH_MODEL', App\Models\User::class),
 'defaults'=>['status'=>'new','score'=>0,'currency'=>'USD'],
 'statuses'=>['new','contacted','qualified','unqualified','converted','lost'],
 'score'=>['minimum'=>0,'maximum'=>100],
];
