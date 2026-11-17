<?php
declare(strict_types=1);
namespace ItmoSoft\Leads\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class LeadSource extends Model {
 protected $guarded=['id']; protected function casts():array{return ['is_active'=>'boolean'];}
 public function getTable():string{return config('itmosoft-leads.table_prefix','itmosoft_').config('itmosoft-leads.tables.sources','lead_sources');}
 public function leads():HasMany{return $this->hasMany(config('itmosoft-leads.models.lead',Lead::class));}
}
