<?php
declare(strict_types=1);
namespace ItmoSoft\Leads\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class LeadStage extends Model {
 protected $guarded=['id']; protected function casts():array{return ['position'=>'integer','is_active'=>'boolean','is_won'=>'boolean','is_lost'=>'boolean'];}
 public function getTable():string{return config('itmosoft-leads.table_prefix','itmosoft_').config('itmosoft-leads.tables.stages','lead_stages');}
 public function leads():HasMany{return $this->hasMany(config('itmosoft-leads.models.lead',Lead::class));}
}
