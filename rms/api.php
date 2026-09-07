<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';
$action=$_GET['action']??'';
$store=load_store();

if($action==='properties'){
    $rows=array_values(array_filter($store['properties'],fn($p)=>$p['status']==='active'));
    json_response(['properties'=>$rows]);
}
if($action==='availability'){
    $in=trim($_GET['check_in']??'');$out=trim($_GET['check_out']??'');$guests=max(1,(int)($_GET['guests']??1));
    if(!valid_date($in)||!valid_date($out)||$in>=$out) json_response(['error'=>'Please select valid check-in and check-out dates.'],422);
    $rows=[];
    foreach($store['properties'] as $p){if($p['status']!=='active'||$p['capacity']<$guests)continue;if(!overlap_exists($store,(int)$p['id'],$in,$out)){$p['available']=true;$rows[]=$p;}}
    json_response(['check_in'=>$in,'check_out'=>$out,'guests'=>$guests,'properties'=>$rows]);
}
if($action==='reserve'&&$_SERVER['REQUEST_METHOD']==='POST'){
    $payload=json_decode(file_get_contents('php://input'),true)?:$_POST;
    $pid=(int)($payload['property_id']??0);$name=trim((string)($payload['guest_name']??''));$phone=trim((string)($payload['guest_phone']??''));$email=trim((string)($payload['guest_email']??''));$guests=max(1,(int)($payload['guests']??1));$in=trim((string)($payload['check_in']??''));$out=trim((string)($payload['check_out']??''));$notes=trim((string)($payload['notes']??''));
    if(!$pid||$name===''||$phone===''||!valid_date($in)||!valid_date($out)||$in>=$out)json_response(['error'=>'Please complete all required booking fields.'],422);
    try{
        $result=with_store_lock(function()use($pid,$name,$phone,$email,$guests,$in,$out,$notes){
            $s=load_store();$p=find_property($s,$pid);
            if(!$p||$p['status']!=='active')return ['error'=>'Selected apartment is not available.','status'=>404];
            if($guests>(int)$p['capacity'])return ['error'=>'Guest count exceeds the apartment capacity.','status'=>422];
            if(overlap_exists($s,$pid,$in,$out))return ['error'=>'Those dates were just taken. Please search again.','status'=>409];
            $nights=nights_between($in,$out);
            $total=(float)$p['rate']*($p['rate_period']==='month'?max(1,(int)ceil($nights/30)):$nights);
            $ref=reservation_reference();$id=(int)$s['next_reservation_id']++;$s['reservations'][]=['id'=>$id,'reference'=>$ref,'property_id'=>$pid,'guest_name'=>$name,'guest_phone'=>$phone,'guest_email'=>$email,'guests'=>$guests,'check_in'=>$in,'check_out'=>$out,'nights'=>$nights,'rate'=>(float)$p['rate'],'total'=>$total,'amount_paid'=>0,'status'=>'pending','notes'=>$notes,'created_at'=>date('c')];
            save_store($s);return ['success'=>true,'reference'=>$ref,'total'=>$total,'currency'=>RMS_CURRENCY,'property'=>$p['name'],'check_in'=>$in,'check_out'=>$out];
        });
        if(isset($result['status']))json_response(['error'=>$result['error']],$result['status']);json_response($result);
    }catch(Throwable $e){json_response(['error'=>'Booking could not be completed.'],500);}
}
json_response(['error'=>'Unknown action.'],404);
