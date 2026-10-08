document.addEventListener("DOMContentLoaded",()=>{const form=document.querySelector("#importActivityCsv");if(!form)return;form.addEventListener("click",async()=>{const input=document.querySelector("#activityCsvUrl"),status=document.querySelector("#activityImportStatus"),button=form;const url=input?.value.trim();if(!url){status.textContent="Paste the published Google Sheets CSV link first.";status.classList.add("is-error");return;}try{const u=new URL(url);if(u.protocol!=="https:"||u.hostname!=="docs.google.com"||!/^\/spreadsheets\/d\/e\/[A-Za-z0-9_-]+\/pub$/.test(u.pathname)||(u.searchParams.get("output")||"").toLowerCase()!=="csv")throw new Error("Please use the published Google Sheets CSV link from File → Share → Publish to web → CSV.");}catch(e){status.textContent=e.message;status.classList.add("is-error");return;}button.disabled=true;status.classList.remove("is-error");status.textContent="Fetching published Google Sheet…";try{const fd=new FormData();fd.append("csv_url",url);fd.append("mode",document.querySelector("#activityCsvMode")?.value||"update");const r=await fetch("/api/admin-import-export.php",{method:"POST",credentials:"same-origin",body:fd});const raw=await r.text();let d;try{d=JSON.parse(raw);}catch{throw new Error('CSV server returned '+(raw.trim()?'invalid data':'an empty response')+' (HTTP '+r.status+'). Check PHP logs.');}if(!r.ok||!d.ok)throw new Error(d.error||"Google Sheets import failed.");status.textContent="✓ Import complete. "+d.created+" created, "+d.updated+" updated, "+d.skipped+" skipped, "+d.failed+" failed."+(d.errors?.length?" "+d.errors.join(" "):"");}catch(e){status.textContent=e.message||"Google Sheets import failed.";status.classList.add("is-error");}finally{button.disabled=false;}});});
document.addEventListener("DOMContentLoaded",()=>{
  const button=document.getElementById("uploadActivityCsv");
  const input=document.getElementById("activityCsvFile");
  const status=document.getElementById("activityImportStatus");
  if(!button||!input||!status)return;
  button.addEventListener("click",async()=>{
    const file=input.files?.[0];
    status.classList.remove("is-error");
    if(!file){status.textContent="Choose a CSV file first.";status.classList.add("is-error");return;}
    if(file.size>10*1024*1024){status.textContent="CSV file must be 10 MB or smaller.";status.classList.add("is-error");return;}
    button.disabled=true;
    status.textContent="Uploading and importing CSV…";
    try{
      const data=new FormData();data.append("csv",file);data.append("mode",document.getElementById("activityCsvMode")?.value||"update");
      const response=await fetch("/api/admin-import-export.php",{method:"POST",credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"},body:data});
      const raw=await response.text();let result;
      try{result=JSON.parse(raw);}catch{throw new Error("CSV server returned an invalid or empty response (HTTP "+response.status+").");}
      if(!response.ok||!result.ok)throw new Error(result.error||"CSV import failed (HTTP "+response.status+").");
      status.textContent="Import complete: "+(result.created||0)+" created, "+(result.updated||0)+" updated, "+(result.skipped||0)+" skipped, "+(result.failed||0)+" failed."+(result.errors?.length?" "+result.errors.join(" "):"");
      if(result.failed)status.classList.add("is-error");
    }catch(error){status.textContent=error.message||"CSV import failed.";status.classList.add("is-error");}
    finally{button.disabled=false;}
  });
});
