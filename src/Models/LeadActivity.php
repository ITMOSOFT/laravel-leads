<?php
declare(strict_types=1);
namespace ItmoSoft\Leads\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class LeadActivity extends Model {
 protected $guarded=['id']; protected function casts():array{return ['properties'=>'array'];}
 public function getTable():string{return config('itmosoft-leads.table_prefix','itmosoft_').config('itmosoft-leads.tables.activities','lead_activities');}
 public function lead():BelongsTo{return $this->belongsTo(config('itmosoft-leads.models.lead',Lead::class));}
}
