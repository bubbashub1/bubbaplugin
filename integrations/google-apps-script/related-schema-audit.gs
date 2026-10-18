/**
 * Bubba Hub Phase 2 — staging-only related-table schema audit.
 * Read-only. Run bhAuditStagingRelatedTables().
 * Requires BH_SYNC_TOKEN in Apps Script Properties.
 */
function bhAuditStagingRelatedTables() {
  const token=PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
  if(!token) throw new Error('BH_SYNC_TOKEN is missing from Script Properties');
  const url='https://staging.bubbahub.co.uk/api/sheets-sync-schema-audit.php';
  const response=UrlFetchApp.fetch(url,{
    method:'post',
    contentType:'application/json',
    headers:{Authorization:'Bearer '+token},
    payload:JSON.stringify({action:'schema_audit'}),
    muteHttpExceptions:true,
    followRedirects:false
  });
  if(response.getResponseCode()!==200) throw new Error('Staging schema audit HTTP '+response.getResponseCode()+' (no writes)');
  const result=JSON.parse(response.getContentText());
  if(result.ok!==true||result.mode!=='schema_audit_read_only'||result.ready_for_automatic_writes!==false||!result.tables)
    throw new Error('Unexpected schema audit response (no writes)');
  const targets=[
    'bh_activities','bh_organisers','bh_venues','bh_sessions',
    'bh_activity_venues','bh_activity_schedules','bh_activity_images',
    'bh_tags','bh_activity_tags','bh_activity_categories',
    'bh_categories','bh_activity_age_ranges'
  ];
  const tables=targets.map(name=>{
    const t=result.tables[name];
    return {
      name:name,
      exists:!!(t&&t.exists),
      columns:t&&Array.isArray(t.columns)?t.columns.map(c=>c.name):[],
      requiredWithoutDefault:t&&Array.isArray(t.required_without_default)?t.required_without_default:[]
    };
  });
  const report={
    mode:'RELATED_SCHEMA_AUDIT_READ_ONLY',
    stagingOnly:true,
    automaticWritesAllowed:false,
    allTargetTablesExist:tables.every(t=>t.exists),
    missingTables:tables.filter(t=>!t.exists).map(t=>t.name),
    missingExpectedActivityColumns:result.missing_expected_activity_columns||[],
    foreignKeys:Array.isArray(result.foreign_keys)?result.foreign_keys:[],
    foreignKeyMetadataPresent:Array.isArray(result.foreign_keys),
    tables:tables,
    nextStep:'Review table columns and relations, then design a separate five-listing DRY RUN. Do not import yet.',
    writesPerformed:false
  };
  Logger.log(JSON.stringify(report,null,2));
  return report;
}
