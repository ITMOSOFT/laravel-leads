<?php
declare(strict_types=1);
namespace ItmoSoft\Leads\Services;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use ItmoSoft\Leads\Models\Lead;
use ItmoSoft\Leads\Models\LeadActivity;
use InvalidArgumentException;
class LeadManager {
 public function create(array $data,?int $actorId=null):Lead {
  $data['status']??=config('itmosoft-leads.defaults.status','new');$data['score']??=config('itmosoft-leads.defaults.score',0);
  if(!in_array($data['status'],config('itmosoft-leads.statuses',[]),true))throw new InvalidArgumentException('The supplied lead status is not configured.');
  $score=(int)$data['score'];$min=(int)config('itmosoft-leads.score.minimum',0);$max=(int)config('itmosoft-leads.score.maximum',100);
  if($score<$min||$score>$max)throw new InvalidArgumentException("Lead score must be between {$min} and {$max}.");
  return DB::transaction(function()use($data,$actorId):Lead{$lead=Lead::query()->create($data);$lead->activities()->create(['type'=>'created','description'=>'Lead created.','actor_id'=>$actorId,'properties'=>['status'=>$lead->status]]);return $lead;});
 }
 public function search(array $filters=[]):Builder {
  return Lead::query()
   ->when($filters['status']??null,fn(Builder $q,$v)=>$q->where('status',$v))
   ->when($filters['source_id']??null,fn(Builder $q,$v)=>$q->where('lead_source_id',$v))
   ->when($filters['stage_id']??null,fn(Builder $q,$v)=>$q->where('lead_stage_id',$v))
   ->when(array_key_exists('assigned_to',$filters),function(Builder $q)use($filters){$filters['assigned_to']===null?$q->whereNull('assigned_to'):$q->where('assigned_to',$filters['assigned_to']);})
   ->when($filters['search']??null,function(Builder $q,string $term){$q->where(function(Builder $n)use($term){$n->where('first_name','like',"%{$term}%")->orWhere('last_name','like',"%{$term}%")->orWhere('email','like',"%{$term}%")->orWhere('company','like',"%{$term}%");});});
 }
 public function recordActivity(Lead $lead,string $type,string $description,?int $actorId=null,array $properties=[]):LeadActivity {
  return $lead->activities()->create(['type'=>$type,'description'=>$description,'actor_id'=>$actorId,'properties'=>$properties?:null]);
 }
 public function convert(Lead $lead,string $targetType,int|string $targetId,?int $actorId=null):Lead {
  return DB::transaction(function()use($lead,$targetType,$targetId,$actorId):Lead{
   if($lead->status==='converted')throw new InvalidArgumentException('This lead has already been converted.');
   if(!in_array($targetType,['customer','account','contact','other'],true))throw new InvalidArgumentException('Unsupported conversion target type.');
   $lead->converted_to_type=$targetType;$lead->converted_to_id=(string)$targetId;$lead->setStatus('converted',$actorId,'Lead converted.');
   $lead->activities()->create(['type'=>'converted','description'=>"Lead converted to {$targetType} {$targetId}.",'actor_id'=>$actorId,'properties'=>['target_type'=>$targetType,'target_id'=>(string)$targetId]]);
   return $lead->refresh();
  });
 }
}
