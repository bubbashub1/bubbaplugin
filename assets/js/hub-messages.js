document.addEventListener("DOMContentLoaded",async()=>{
  const root=document.getElementById("hubSupportMessages");
  if(!root)return;
  const esc=value=>String(value??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
  try{
    const response=await fetch("/api/support.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});
    const data=await response.json();
    if(!response.ok||!data.ok)throw Error(data.error==="login_required"?"Sign in to view your messages.":"Messages are temporarily unavailable.");
    const items=Array.isArray(data.questions)?data.questions:[];
    if(!items.length){root.innerHTML='<p>No messages yet. Your private questions and replies will appear here.</p>';return;}
    root.innerHTML=items.map(item=>{
      const date=item.created_at?new Date(String(item.created_at).replace(" ","T")).toLocaleDateString("en-GB"):"";
      return '<article class="hub-message-card" style="padding:18px;margin:12px 0;border:1px solid #d8e6db;border-radius:18px;background:#f7faf6">'+
        '<strong>'+esc(item.subject||"Support question")+'</strong> <small>· '+esc(item.status||"Submitted")+'</small>'+
        '<p>'+esc(item.question||"")+'</p>'+
        (item.answer?'<div style="border-top:1px solid #d8e6db;padding-top:12px"><strong>Reply</strong><p>'+esc(item.answer)+'</p></div>':'<small>Awaiting a reply</small>')+
        '<small style="display:block;margin-top:8px">'+esc(date)+'</small></article>';
    }).join("");
  }catch(error){root.textContent=error.message||"Could not load messages.";}
});
