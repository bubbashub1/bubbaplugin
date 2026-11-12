<?php
declare(strict_types=1);
/**
 * Read-only Listing Checker environment preflight.
 * Run before applying the migration or configuring cron:
 * php /path/to/api/listing-checker-preflight.php
 * This script does not create tables or modify listing data.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$checks=[];
$add=static function(string $name,string $status,string $detail) use (&$checks): void {
    $checks[]=['check'=>$name,'status'=>$status,'detail'=>$detail];
};
foreach(['pdo','pdo_mysql'] as $extension) {
    $add('PHP '.$extension,extension_loaded($extension)?'pass':'fail',extension_loaded($extension)?'Extension available':'Required PHP extension is missing');
}
foreach(['curl','SimpleXML'] as $extension) {
    $add('Discovery '.$extension,extension_loaded($extension)?'pass':'warning',extension_loaded($extension)?'Extension available':'New-listing discovery requires this extension');
}

try {
    require_once __DIR__.'/db.php';
    if(!function_exists('bh_mysql')) throw new RuntimeException('Database connector bh_mysql() is unavailable');
    $db=bh_mysql();
    if(!$db instanceof PDO) throw new RuntimeException('Database connector did not return PDO');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $database=(string)$db->query('SELECT DATABASE()')->fetchColumn();
    $version=(string)$db->query('SELECT VERSION()')->fetchColumn();
    $add('Database connection',$database!==''?'pass':'fail',$database!==''?'Connected to '.$database:'No database selected');
    $add('Database version','pass',$version);

    $tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $tableSet=array_fill_keys($tables,true);
    $add('Source table bh_activities',isset($tableSet['bh_activities'])?'pass':'fail',isset($tableSet['bh_activities'])?'Source listing table found':'Required source listing table is missing');
    if(isset($tableSet['bh_activities'])) {
        $columns=$db->query('SHOW COLUMNS FROM bh_activities')->fetchAll(PDO::FETCH_COLUMN);
        $columnSet=array_fill_keys($columns,true);
        $add('Source primary identifier',isset($columnSet['id'])?'pass':'fail',isset($columnSet['id'])?'bh_activities.id is available':'bh_activities.id is required');
        $optional=['title','description','category','age_range','website','booking_url','contact_email','contact_phone','facebook_url','instagram_url','image_path','updated_at'];
        $missing=array_values(array_filter($optional,static fn($column)=>!isset($columnSet[$column])));
        $add('Supported listing fields',$missing?'warning':'pass',$missing?'Supported fields not present: '.implode(', ',$missing):'All expected listing fields are available');
    }

    $checkerRequirements=[
        'bh_listing_checker_settings'=>['setting_key','setting_value'],
        'bh_listing_discovery_terms'=>['id','term','term_group','regions_json','enabled','last_searched_at'],
        'bh_listing_check_runs'=>['id','check_type','status','started_at','finished_at','processed_count','error_count','summary_json'],
        'bh_listing_check_queue'=>['id','check_type','target_id','target_term_id','status','attempts','next_check_at','last_checked_at','last_error'],
        'bh_listing_check_findings'=>['id','check_type','activity_id','term_id','candidate_json','finding_type','confidence','source_url','checked_at','review_status','reviewed_at']
    ];
    $migrationIssues=[];
    foreach($checkerRequirements as $table=>$requiredColumns){
        if(!isset($tableSet[$table])){$migrationIssues[]=$table.' (table missing)';continue;}
        $actual=$db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
        $actualSet=array_fill_keys($actual,true);
        $missingColumns=array_values(array_filter($requiredColumns,static fn($column)=>!isset($actualSet[$column])));
        if($missingColumns)$migrationIssues[]=$table.' (missing columns: '.implode(', ',$missingColumns).')';
    }
    $add('Checker SQL migration',$migrationIssues?'warning':'pass',$migrationIssues?'Not applied or incomplete: '.implode('; ',$migrationIssues):'All five checker tables and required columns are present');

    try {
        $lock=$db->query("SELECT GET_LOCK('bh_listing_checker_preflight',0)")->fetchColumn();
        if((int)$lock===1) {
            $db->query("SELECT RELEASE_LOCK('bh_listing_checker_preflight')");
            $add('MySQL advisory locks','pass','Lock acquisition and release succeeded');
        } else {
            $add('MySQL advisory locks','fail','Could not acquire a temporary advisory lock');
        }
    } catch(Throwable $e) {
        $add('MySQL advisory locks','fail','Advisory lock test failed');
    }
} catch(Throwable $e) {
    error_log('Listing Checker preflight: '.$e->getMessage());
    $add('Database connection','fail','Could not connect using the existing Bubba Hub database configuration');
}

$failed=count(array_filter($checks,static fn($item)=>$item['status']==='fail'));
$warnings=count(array_filter($checks,static fn($item)=>$item['status']==='warning'));
echo json_encode(['ok'=>$failed===0,'failed'=>$failed,'warnings'=>$warnings,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
exit($failed===0?0:2);
