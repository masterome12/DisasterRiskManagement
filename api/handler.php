<?php
// DRRMS API — /drrms/api/handler.php?mod=X&act=Y
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../config/helpers.php';
sess_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$mod = ss($_GET['mod']??'',40);
$act = ss($_GET['act']??'list',40);
$id  = isset($_GET['id'])?(int)$_GET['id']:null;
$mth = $_SERVER['REQUEST_METHOD'];

const INC_TYPES=['Fire','Flood','Earthquake','Landslide','Typhoon','Infrastructure','Chemical','Other'];
const SEVS=['Critical','High','Moderate','Low'];
const STATS=['Pending','Active','Responding','Monitoring','Resolved','Archived'];
const AL_LVL=['Critical','High','Moderate','Advisory'];
const AL_TYP=['Flood','Fire','Typhoon','Earthquake','Landslide','Tsunami','General','Chemical'];

try {
switch ($mod) {

// ══════════════════════════════════════════════
// DASHBOARD
// ══════════════════════════════════════════════
case 'dashboard':
    require_auth();
    $inc=DB::run("SELECT SUM(status NOT IN('Resolved','Archived'))AS active,SUM(status='Pending')AS pending,
      SUM(DATE(incident_datetime)=CURDATE())AS today,SUM(affected_families)AS af,
      SUM(affected_persons)AS ap,COUNT(*)AS total FROM incidents")->fetch();
    $hz=DB::run("SELECT COUNT(*)AS total,SUM(risk_level='Critical')AS critical,SUM(risk_level='High')AS high,
      SUM(population_risk)AS pop FROM hazard_zones WHERE is_active=1")->fetch();
    $al=DB::run("SELECT COUNT(*)AS total,SUM(level='Critical')AS critical FROM alerts WHERE status='Active'")->fetch();
    $us=DB::run("SELECT COUNT(*)AS total,SUM(status='active')AS active_users FROM users")->fetch();
    $ev=DB::run("SELECT COUNT(*)AS total,SUM(status='Open')AS open,SUM(current_count)AS evacuees,SUM(capacity)AS cap FROM evacuation_centers")->fetch();
    $rec=DB::run("SELECT i.*,CONCAT(u.first_name,' ',u.last_name)AS reporter FROM incidents i
      LEFT JOIN users u ON u.id=i.reported_by ORDER BY i.incident_datetime DESC LIMIT 8")->fetchAll();
    $log=DB::run("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10")->fetchAll();
    $bt=DB::run("SELECT type,COUNT(*)AS cnt FROM incidents WHERE YEAR(incident_datetime)=YEAR(NOW()) GROUP BY type ORDER BY cnt DESC")->fetchAll();
    $bb=DB::run("SELECT barangay,COUNT(*)AS cnt FROM incidents WHERE YEAR(incident_datetime)=YEAR(NOW()) GROUP BY barangay ORDER BY cnt DESC LIMIT 5")->fetchAll();
    $bm=DB::run("SELECT MONTH(incident_datetime)AS m,COUNT(*)AS cnt FROM incidents WHERE YEAR(incident_datetime)=YEAR(NOW()) GROUP BY m ORDER BY m")->fetchAll();
    $aa=DB::run("SELECT * FROM alerts WHERE status='Active' ORDER BY FIELD(level,'Critical','High','Moderate','Advisory') LIMIT 5")->fetchAll();
    jok('ok',compact('inc','hz','al','us','ev','rec','log','bt','bb','bm','aa'));

// ══════════════════════════════════════════════
// INCIDENTS
// ══════════════════════════════════════════════
case 'incidents':
    require_auth();

    if ($act==='geojson') {
        $type=ss($_GET['type']??''); $p=[]; $w=['1=1'];
        if (rslug()==='public'){$w[]='i.is_verified=1';}
        if ($type&&venum($type,INC_TYPES)){$w[]='i.type=?';$p[]=$type;}
        $ws=implode(' AND ',$w);
        $rows=DB::run("SELECT i.id,i.incident_code,i.type,i.severity,i.status,i.title,i.barangay,
          i.address,i.latitude,i.longitude,i.affected_families,i.affected_persons,i.is_verified,
          i.incident_datetime,CONCAT(u.first_name,' ',u.last_name)AS reporter
          FROM incidents i LEFT JOIN users u ON u.id=i.reported_by WHERE $ws ORDER BY i.incident_datetime DESC LIMIT 500",$p)->fetchAll();
        $feats=[];
        foreach($rows as $r){
            $feats[]=['type'=>'Feature',
              'geometry'=>['type'=>'Point','coordinates'=>[(float)$r['longitude'],(float)$r['latitude']]],
              'properties'=>['id'=>$r['id'],'incident_code'=>$r['incident_code'],'type'=>$r['type'],
                'severity'=>$r['severity'],'status'=>$r['status'],'title'=>$r['title'],
                'barangay'=>$r['barangay'],'address'=>$r['address'],
                'affected_families'=>(int)$r['affected_families'],'affected_persons'=>(int)$r['affected_persons'],
                'reporter'=>$r['reporter'],'datetime'=>$r['incident_datetime'],'is_verified'=>(bool)$r['is_verified']]];
        }
        header('Content-Type: application/geo+json; charset=utf-8');
        echo json_encode(['type'=>'FeatureCollection','features'=>$feats],JSON_UNESCAPED_UNICODE); exit;
    }

    if ($act==='stats') {
        $bt=DB::run("SELECT type,COUNT(*)AS cnt FROM incidents WHERE YEAR(incident_datetime)=YEAR(NOW()) GROUP BY type ORDER BY cnt DESC")->fetchAll();
        $bm=DB::run("SELECT MONTH(incident_datetime)AS m,COUNT(*)AS cnt FROM incidents WHERE YEAR(incident_datetime)=YEAR(NOW()) GROUP BY m ORDER BY m")->fetchAll();
        $bb=DB::run("SELECT barangay,COUNT(*)AS cnt FROM incidents WHERE YEAR(incident_datetime)=YEAR(NOW()) GROUP BY barangay ORDER BY cnt DESC LIMIT 8")->fetchAll();
        $bs=DB::run("SELECT severity,COUNT(*)AS cnt FROM incidents WHERE YEAR(incident_datetime)=YEAR(NOW()) GROUP BY severity ORDER BY FIELD(severity,'Critical','High','Moderate','Low')")->fetchAll();
        $bst=DB::run("SELECT status,COUNT(*)AS cnt FROM incidents WHERE YEAR(incident_datetime)=YEAR(NOW()) GROUP BY status ORDER BY FIELD(status,'Pending','Active','Responding','Monitoring','Resolved','Archived')")->fetchAll();
        jok('ok',compact('bt','bm','bb','bs','bst'));
    }

    if ($act==='export'&&($_GET['format']??'')==='csv') {
        $rows=DB::run("SELECT incident_code,type,severity,status,title,barangay,address,affected_families,affected_persons,is_verified,incident_datetime FROM incidents ORDER BY incident_datetime DESC")->fetchAll();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="incidents_'.date('Ymd_His').'.csv"');
        $out=fopen('php://output','w');
        fputcsv($out, ['Code','Type','Severity','Status','Title','Barangay','Address','Affected Families','Affected Persons','Verified','Date/Time']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['incident_code'],$r['type'],$r['severity'],$r['status'],$r['title'],$r['barangay'],$r['address'],$r['affected_families'],$r['affected_persons'],$r['is_verified']?'Yes':'No',$r['incident_datetime']]);
        }
        fclose($out);
        exit;
    }

    if ($act==='single'&&$id) {
        $row=DB::run("SELECT i.*,CONCAT(ur.first_name,' ',ur.last_name)AS reporter_name,
          CONCAT(uv.first_name,' ',uv.last_name)AS validator_name
          FROM incidents i LEFT JOIN users ur ON ur.id=i.reported_by LEFT JOIN users uv ON uv.id=i.validated_by
          WHERE i.id=? LIMIT 1",[$id])->fetch();
        if(!$row) jdie(false,'Not found.',null,404);
        jok('ok',$row);
    }

    if ($mth==='GET') {
        $pg=max(1,(int)($_GET['page']??1)); $lim=min(100,(int)($_GET['limit']??25)); $off=($pg-1)*$lim;
        $w=['1=1']; $p=[];
        if(!empty($_GET['search'])){$q='%'.ss($_GET['search']).'%';$w[]='(i.title LIKE ? OR i.incident_code LIKE ? OR i.barangay LIKE ? OR i.address LIKE ?)';$p=array_merge($p,[$q,$q,$q,$q]);}
        if(!empty($_GET['type'])&&venum(ss($_GET['type']),INC_TYPES)){$w[]='i.type=?';$p[]=ss($_GET['type']);}
        if(!empty($_GET['status'])&&venum(ss($_GET['status']),STATS)){$w[]='i.status=?';$p[]=ss($_GET['status']);}
        if(rslug()==='public'){$w[]='i.is_verified=1';}
        $ws=implode(' AND ',$w);
        $total=(int)DB::run("SELECT COUNT(*) FROM incidents i WHERE $ws",$p)->fetchColumn();
        $rows=DB::run("SELECT i.*,CONCAT(u.first_name,' ',u.last_name)AS reporter_name
          FROM incidents i LEFT JOIN users u ON u.id=i.reported_by
          WHERE $ws ORDER BY i.incident_datetime DESC LIMIT $lim OFFSET $off",$p)->fetchAll();
        jok('ok',['items'=>$rows,'total'=>$total,'page'=>$pg,'limit'=>$lim,'pages'=>ceil($total/$lim)]);
    }

    if ($mth==='POST'&&$act==='create') {
        need_csrf();
        $d=['type'=>ss($_POST['type']??''),'severity'=>ss($_POST['severity']??''),
          'title'=>ss($_POST['title']??'',200),'description'=>ss($_POST['description']??'',2000),
          'barangay'=>ss($_POST['barangay']??''),'address'=>ss($_POST['address']??'',255),
          'latitude'=>slat($_POST['latitude']??''),'longitude'=>slng($_POST['longitude']??''),
          'aff_fam'=>si($_POST['affected_families']??0),'aff_per'=>si($_POST['affected_persons']??0),
          'dt'=>$_POST['incident_datetime']??date('Y-m-d H:i:s')];
        if(!venum($d['type'],INC_TYPES))   jdie(false,'Invalid type.',null,422);
        if(!venum($d['severity'],SEVS))     jdie(false,'Invalid severity.',null,422);
        if(!$d['title'])                    jdie(false,'Title required.',null,422);
        if(!$d['description'])              jdie(false,'Description required.',null,422);
        if(!$d['barangay'])                 jdie(false,'Barangay required.',null,422);
        if(!$d['address'])                  jdie(false,'Address required.',null,422);
        if($d['latitude']===false)          jdie(false,'Invalid latitude.',null,422);
        if($d['longitude']===false)         jdie(false,'Invalid longitude.',null,422);
        $code=next_code('INC','incidents','incident_code');
        $photo=null;
        if(!empty($_FILES['photo']['tmp_name'])){
            $pn=upload_img($_FILES['photo'],UPLOAD_PATH.'/incidents');
            if($pn) $photo='uploads/incidents/'.$pn;
        }
        DB::run("INSERT INTO incidents(incident_code,reported_by,type,severity,status,title,description,
          barangay,address,latitude,longitude,affected_families,affected_persons,photo,is_verified,incident_datetime)
          VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
          [$code,uid(),$d['type'],$d['severity'],'Pending',$d['title'],$d['description'],
           $d['barangay'],$d['address'],$d['latitude'],$d['longitude'],
           $d['aff_fam'],$d['aff_per'],$photo,is_staff()?1:0,$d['dt']]);
        $nid=(int)DB::lastId();
        log_act('create_incident','Incidents',"Created $code");
        jok("Incident $code submitted.",['code'=>$code,'id'=>$nid],201);
    }

    if ($mth==='POST'&&$act==='update'&&$id) {
        need_csrf(); if(!is_staff()) jdie(false,'Access denied.',null,403);
        $ns=ss($_POST['status']??''); $rm=ss($_POST['remarks']??'',1000);
        if($ns&&!venum($ns,STATS)) jdie(false,'Invalid status.',null,422);
        DB::run("UPDATE incidents SET status=COALESCE(NULLIF(?,''),status),
          is_verified=CASE WHEN ?=1 THEN 1 ELSE is_verified END,
          validated_by=CASE WHEN ?=1 THEN ? ELSE validated_by END,updated_at=NOW() WHERE id=?",
          [$ns,isset($_POST['verify'])?1:0,isset($_POST['verify'])?1:0,uid(),$id]);
        if($rm) log_act('update_incident','Incidents',"#$id → $ns: $rm");
        jok('Incident updated.');
    }

    if ($mth==='POST'&&$act==='delete'&&$id) {
        need_csrf(); if(!is_admin()) jdie(false,'Access denied.',null,403);
        $c=DB::run("SELECT incident_code FROM incidents WHERE id=?",[$id])->fetchColumn();
        if(!$c) jdie(false,'Not found.',null,404);
        DB::run("DELETE FROM incidents WHERE id=?",[$id]);
        log_act('delete_incident','Incidents',"Deleted $c");
        jok('Incident deleted.');
    }
    jdie(false,'Invalid incidents request.',null,400);

// ══════════════════════════════════════════════
// HAZARD ZONES
// ══════════════════════════════════════════════
case 'hazards':
    require_auth();

    if ($act==='geojson') {
        $rows=DB::run("SELECT * FROM hazard_zones WHERE is_active=1")->fetchAll();
        $feats=[];
        foreach($rows as $r){
            $pts=[];$la=deg2rad((float)$r['center_lat']);$ln=deg2rad((float)$r['center_lng']);
            $d=(float)($r['radius_meters']??300)/6378137;
            for($i=0;$i<=32;$i++){
                $a=deg2rad($i*(360/32));
                $plat=asin(sin($la)*cos($d)+cos($la)*sin($d)*cos($a));
                $plng=$ln+atan2(sin($a)*sin($d)*cos($la),cos($d)-sin($la)*sin($plat));
                $pts[]=[round(rad2deg($plng),6),round(rad2deg($plat),6)];
            }
            $feats[]=['type'=>'Feature','geometry'=>['type'=>'Polygon','coordinates'=>[$pts]],
              'properties'=>['id'=>$r['id'],'name'=>$r['name'],'type'=>$r['type'],
                'risk_level'=>$r['risk_level'],'description'=>$r['description'],
                'barangays'=>$r['barangays'],'area_sqkm'=>$r['area_sqkm'],
                'population_risk'=>(int)$r['population_risk'],'color'=>$r['color'],
                'fill_opacity'=>(float)$r['fill_opacity'],'center_lat'=>(float)$r['center_lat'],
                'center_lng'=>(float)$r['center_lng'],'radius_meters'=>(int)($r['radius_meters']??300)]];
        }
        header('Content-Type: application/geo+json; charset=utf-8');
        echo json_encode(['type'=>'FeatureCollection','features'=>$feats],JSON_UNESCAPED_UNICODE); exit;
    }

    if ($mth==='GET') {
        $rows=DB::run("SELECT hz.*,CONCAT(u.first_name,' ',u.last_name)AS creator
          FROM hazard_zones hz LEFT JOIN users u ON u.id=hz.created_by
          WHERE hz.is_active=1 ORDER BY FIELD(hz.risk_level,'Critical','High','Moderate','Low')")->fetchAll();
        jok('ok',$rows);
    }

    if ($mth==='POST'&&in_array($act,['create','update'])) {
        need_csrf(); if(!is_drrmo()) jdie(false,'Access denied.',null,403);
        $d=['name'=>ss($_POST['name']??'',150),'type'=>ss($_POST['type']??''),
          'risk_level'=>ss($_POST['risk_level']??''),'description'=>ss($_POST['description']??'',2000),
          'barangays'=>ss($_POST['barangays']??'',500),'area_sqkm'=>sf($_POST['area_sqkm']??0),
          'pop_risk'=>si($_POST['population_risk']??0),
          'clat'=>slat($_POST['center_lat']??''),'clng'=>slng($_POST['center_lng']??''),
          'radius'=>si($_POST['radius_meters']??300,0,99999),
          'color'=>preg_match('/^#[0-9a-fA-F]{6}$/',$_POST['color']??'')?$_POST['color']:'#ef4444'];
        if(!$d['name']) jdie(false,'Zone name required.',null,422);
        if(!venum($d['type'],['Flood','Fire','Earthquake','Landslide','Storm Surge','Tsunami','Volcanic','Other'])) jdie(false,'Invalid type.',null,422);
        if(!venum($d['risk_level'],SEVS)) jdie(false,'Invalid risk level.',null,422);
        if($d['clat']===false) jdie(false,'Invalid latitude.',null,422);
        if($d['clng']===false) jdie(false,'Invalid longitude.',null,422);
        if($act==='create'){
            DB::run("INSERT INTO hazard_zones(created_by,name,type,risk_level,description,barangays,area_sqkm,population_risk,center_lat,center_lng,radius_meters,color)VALUES(?,?,?,?,?,?,?,?,?,?,?,?)",
              [uid(),$d['name'],$d['type'],$d['risk_level'],$d['description'],$d['barangays'],$d['area_sqkm']?:null,$d['pop_risk'],$d['clat'],$d['clng'],$d['radius'],$d['color']]);
            log_act('create_hazard','Hazards',$d['name']);
            jok('Hazard zone created.',['id'=>(int)DB::lastId()],201);
        } else {
            if(!$id) jdie(false,'ID required.',null,422);
            DB::run("UPDATE hazard_zones SET name=?,type=?,risk_level=?,description=?,barangays=?,area_sqkm=?,population_risk=?,center_lat=?,center_lng=?,radius_meters=?,color=?,updated_at=NOW() WHERE id=?",
              [$d['name'],$d['type'],$d['risk_level'],$d['description'],$d['barangays'],$d['area_sqkm']?:null,$d['pop_risk'],$d['clat'],$d['clng'],$d['radius'],$d['color'],$id]);
            log_act('update_hazard','Hazards',"Updated #$id");
            jok('Hazard zone updated.');
        }
    }
    if ($mth==='POST'&&$act==='delete'&&$id){
        need_csrf(); if(!is_admin()) jdie(false,'Access denied.',null,403);
        $n=DB::run("SELECT name FROM hazard_zones WHERE id=?",[$id])->fetchColumn();
        DB::run("UPDATE hazard_zones SET is_active=0 WHERE id=?",[$id]);
        log_act('delete_hazard','Hazards',$n?:"#$id");
        jok('Hazard zone removed.');
    }
    jdie(false,'Invalid hazards request.',null,400);

// ══════════════════════════════════════════════
// EVACUATION CENTERS
// ══════════════════════════════════════════════
case 'evac':
    require_auth();
    $rows=DB::run("SELECT * FROM evacuation_centers ORDER BY name")->fetchAll();
    $feats=[];
    foreach($rows as $r){
        $pct=$r['capacity']>0?round($r['current_count']/$r['capacity']*100,1):0;
        $feats[]=['type'=>'Feature',
          'geometry'=>['type'=>'Point','coordinates'=>[(float)$r['longitude'],(float)$r['latitude']]],
          'properties'=>array_merge($r,['occupancy_pct'=>$pct])];
    }
    header('Content-Type: application/geo+json; charset=utf-8');
    echo json_encode(['type'=>'FeatureCollection','features'=>$feats],JSON_UNESCAPED_UNICODE); exit;

// ══════════════════════════════════════════════
// ALERTS
// ══════════════════════════════════════════════
case 'alerts':
    require_auth();
    DB::run("UPDATE alerts SET status='Expired' WHERE status='Active' AND valid_until IS NOT NULL AND valid_until<NOW()");

    if ($act==='active'){
        $rows=DB::run("SELECT * FROM alerts WHERE status='Active' ORDER BY FIELD(level,'Critical','High','Moderate','Advisory')")->fetchAll();
        jok('ok',$rows);
    }
    if ($mth==='GET'){
        $rows=DB::run("SELECT a.*,CONCAT(u.first_name,' ',u.last_name)AS issuer FROM alerts a
          LEFT JOIN users u ON u.id=a.issued_by ORDER BY FIELD(a.level,'Critical','High','Moderate','Advisory'),a.created_at DESC")->fetchAll();
        jok('ok',$rows);
    }
    if ($mth==='POST'&&$act==='create'){
        need_csrf(); if(!is_drrmo()) jdie(false,'Access denied.',null,403);
        $d=['level'=>ss($_POST['level']??''),'type'=>ss($_POST['type']??''),
          'title'=>ss($_POST['title']??'',200),'message'=>ss($_POST['message']??'',3000),
          'areas'=>ss($_POST['affected_areas']??'',500),'agency'=>ss($_POST['source_agency']??'',100),
          'from'=>$_POST['valid_from']??date('Y-m-d H:i:s'),'until'=>$_POST['valid_until']??null];
        if(!venum($d['level'],AL_LVL)) jdie(false,'Invalid level.',null,422);
        if(!venum($d['type'],AL_TYP))  jdie(false,'Invalid type.',null,422);
        if(!$d['title'])   jdie(false,'Title required.',null,422);
        if(!$d['message']) jdie(false,'Message required.',null,422);
        $code=next_code('ALT','alerts','alert_code');
        DB::run("INSERT INTO alerts(issued_by,alert_code,level,type,title,message,affected_areas,source_agency,valid_from,valid_until,status)VALUES(?,?,?,?,?,?,?,?,?,?,'Active')",
          [uid(),$code,$d['level'],$d['type'],$d['title'],$d['message'],$d['areas'],$d['agency'],$d['from'],$d['until']?:null]);
        log_act('issue_alert','Alerts',"$code: ".$d['title']);
        jok("Alert $code issued.",['code'=>$code,'id'=>(int)DB::lastId()],201);
    }
    if ($mth==='POST'&&$act==='cancel'&&$id){
        need_csrf(); if(!is_drrmo()) jdie(false,'Access denied.',null,403);
        $c=DB::run("SELECT alert_code FROM alerts WHERE id=?",[$id])->fetchColumn();
        DB::run("UPDATE alerts SET status='Cancelled' WHERE id=?",[$id]);
        log_act('cancel_alert','Alerts',$c?:"#$id");
        jok('Alert cancelled.');
    }
    jdie(false,'Invalid alerts request.',null,400);

// ══════════════════════════════════════════════
// USERS
// ══════════════════════════════════════════════
case 'users':
    require_auth(); if(rid()<4) jdie(false,'Access denied.',null,403);
    if ($mth==='GET'){
        $w=["u.status!='inactive'"]; $p=[];
        if(!empty($_GET['search'])){$q='%'.ss($_GET['search']).'%';$w[]='(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';$p=array_merge($p,[$q,$q,$q]);}
        if(!empty($_GET['role_id'])){$w[]='u.role_id=?';$p[]=(int)$_GET['role_id'];}
        $ws=implode(' AND ',$w);
        $rows=DB::run("SELECT u.id,u.first_name,u.last_name,u.email,u.username,u.phone,u.barangay,
          u.status,u.last_login_at,u.created_at,r.name AS role_name,r.slug AS role_slug
          FROM users u JOIN roles r ON r.id=u.role_id WHERE $ws ORDER BY u.created_at DESC",$p)->fetchAll();
        jok('ok',$rows);
    }
    if ($mth==='POST'&&$act==='create'){
        need_csrf();
        $em=semail($_POST['email']??''); $un=ss($_POST['username']??'',60); $pw=$_POST['password']??'';
        if(!$em) jdie(false,'Valid email required.',null,422);
        if(strlen($un)<4) jdie(false,'Username min 4 chars.',null,422);
        if(strlen($pw)<8) jdie(false,'Password min 8 chars.',null,422);
        if(DB::run("SELECT id FROM users WHERE email=?",[$em])->fetch()) jdie(false,'Email already exists.',null,409);
        if(DB::run("SELECT id FROM users WHERE username=?",[$un])->fetch()) jdie(false,'Username taken.',null,409);
        $rl=si($_POST['role_id']??1,1,5);
        if($rl>=rid()) jdie(false,'Cannot assign role equal or higher than yours.',null,403);
        DB::run("INSERT INTO users(role_id,first_name,last_name,email,username,password_hash,phone,barangay,status)VALUES(?,?,?,?,?,?,?,?,'active')",
          [$rl,ss($_POST['first_name']??'',80),ss($_POST['last_name']??'',80),$em,$un,
           password_hash($pw,PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]),ss($_POST['phone']??'',20),ss($_POST['barangay']??'',100)]);
        log_act('create_user','Users',"$em ($un)");
        jok('User created.',['id'=>(int)DB::lastId()],201);
    }
    if ($mth==='POST'&&$act==='update'&&$id){
        need_csrf(); $up=[]; $p=[];
        foreach(['first_name','last_name','phone','barangay'] as $f){if(isset($_POST[$f])){$up[]="$f=?";$p[]=ss($_POST[$f],100);}}
        if(isset($_POST['status'])&&venum(ss($_POST['status']),['active','inactive','suspended','pending'])){$up[]='status=?';$p[]=ss($_POST['status']);}
        if(isset($_POST['role_id'])){$rl=si($_POST['role_id'],1,5);if($rl<rid()){$up[]='role_id=?';$p[]=$rl;}}
        if(isset($_POST['password'])&&strlen($_POST['password'])>=8){$up[]='password_hash=?';$p[]=password_hash($_POST['password'],PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]);}
        if(empty($up)) jdie(false,'Nothing to update.',null,422);
        $p[]=$id; DB::run("UPDATE users SET ".implode(',',$up).",updated_at=NOW() WHERE id=?",$p);
        log_act('update_user','Users',"#$id");
        jok('User updated.');
    }
    if ($mth==='POST'&&$act==='delete'&&$id){
        need_csrf(); if(rid()<5) jdie(false,'Access denied.',null,403);
        if($id===uid()) jdie(false,'Cannot deactivate own account.',null,403);
        DB::run("UPDATE users SET status='inactive' WHERE id=?",[$id]);
        log_act('delete_user','Users',"#$id");
        jok('User deactivated.');
    }
    jdie(false,'Invalid users request.',null,400);

// ══════════════════════════════════════════════
// SETTINGS
// ══════════════════════════════════════════════
case 'settings':
    require_auth(); if(rid()<4) jdie(false,'Access denied.',null,403);
    if ($mth==='GET'){
        $rows=DB::run("SELECT grp,key_name,value,label FROM system_settings ORDER BY grp,id")->fetchAll();
        $out=[];
        foreach($rows as $r) $out[$r['grp']][$r['key_name']]=['value'=>$r['value'],'label'=>$r['label']];
        jok('ok',$out);
    }
    if ($mth==='POST'&&in_array($act,['save','update'])){
        need_csrf(); $s=$_POST['settings']??[];
        if(!is_array($s)) jdie(false,'Invalid data.',null,422);
        foreach($s as $k=>$v) DB::run("UPDATE system_settings SET value=? WHERE key_name=?",[ss($v,500),ss($k,100)]);
        log_act('update_settings','Settings','Settings saved');
        jok('Settings saved.');
    }
    jdie(false,'Invalid settings request.',null,400);

// ══════════════════════════════════════════════
// ACTIVITY LOGS
// ══════════════════════════════════════════════
case 'logs':
    require_auth(); if(rid()<4) jdie(false,'Access denied.',null,403);
    $pg=max(1,(int)($_GET['page']??1)); $lim=50; $off=($pg-1)*$lim;
    $rows=DB::run("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT $lim OFFSET $off")->fetchAll();
    $total=(int)DB::run("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
    jok('ok',['items'=>$rows,'total'=>$total,'page'=>$pg]);

default:
    jdie(false,"Unknown module: $mod",null,404);
}
} catch(PDOException $e){
    error_log('DRRMS DB: '.$e->getMessage());
    jdie(false,'Database error. Please try again.',null,500);
} catch(Throwable $e){
    error_log('DRRMS: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
    jdie(false,'Server error.',null,500);
}
