<?php
declare(strict_types=1);
namespace ItmoSoft\Leads\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Lead extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['custom_fields'=>'array','last_contacted_at'=>'datetime','follow_up_at'=>'datetime','converted_at'=>'datetime','score'=>'integer','estimated_value'=>'decimal:2'];}
 public function getTable():string{return config('itmosoft-leads.table_prefix','itmosoft_').config('itmosoft-leads.tables.leads','leads');}
 public function source():BelongsTo{return $this->belongsTo(config('itmosoft-leads.models.source',LeadSource::class),'lead_source_id');}
 public function stage():BelongsTo{return $this->belongsTo(config('itmosoft-leads.models.stage',LeadStage::class),'lead_stage_id');}
 public function activities():HasMany{return $this->hasMany(config('itmosoft-leads.models.activity',LeadActivity::class))->latest();}
 public function assignee():BelongsTo{return $this->belongsTo(config('itmosoft-leads.user_model'),'assigned_to');}
 public function scopeStatus(Builder $q,string $status):Builder{return $q->where('status',$status);}
 public function scopeAssignedTo(Builder $q,int|string $id):Builder{return $q->where('assigned_to',$id);}
 public function scopeUnassigned(Builder $q):Builder{return $q->whereNull('assigned_to');}
 public function scopeDueForFollowUp(Builder $q):Builder{return $q->whereNotNull('follow_up_at')->where('follow_up_at','<=',now())->whereNotIn('status',['converted','lost','unqualified']);}
 public function setStatus(string $status,?int $actorId=null,?string $note=null):void{
  if(!in_array($status,config('itmosoft-leads.statuses',[]),true))throw new InvalidArgumentException("Unsupported lead status [{$status}].");
  $old=$this->status;$this->status=$status;if($status==='converted'&&!$this->converted_at)$this->converted_at=now();$this->save();
  if($old!==$status)$this->activities()->create(['type'=>'status_changed','description'=>$note??"Lead status changed from {$old} to {$status}.",'actor_id'=>$actorId,'properties'=>['from'=>$old,'to'=>$status]]);
 }
 public function assignTo(int|string|null $userId,?int $actorId=null):void{
  $old=$this->assigned_to;$this->assigned_to=$userId;$this->save();
  if((string)$old!==(string)$userId)$this->activities()->create(['type'=>'assigned','description'=>$userId===null?'Lead unassigned.':"Lead assigned to user {$userId}.",'actor_id'=>$actorId,'properties'=>['from'=>$old,'to'=>$userId]]);
 }
 public function setScore(int $score):void{
  $min=(int)config('itmosoft-leads.score.minimum',0);$max=(int)config('itmosoft-leads.score.maximum',100);
  if($score<$min||$score>$max)throw new InvalidArgumentException("Lead score must be between {$min} and {$max}.");
  $this->score=$score;$this->save();
 }
}
