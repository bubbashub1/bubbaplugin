(()=>{'use strict';
const form=document.getElementById('leaderFilters'),rows=document.getElementById('leaderDirectoryRows'),status=document.getElementById('leaderDirectoryStatus'),prev=document.getElementById('leaderDirectoryPrev'),next=document.getElementById('leaderDirectoryNext');
if(!form||!rows)return;
let page=1,total=0,size=25;
async function load(){
 status.textContent='Loading leaders…';rows.replaceChildren();prev.disabled=true;next.disabled=true;
 const params=new URLSearchParams(new FormData(form));params.set('role','leader');params.set('page',String(page));
 try{
  const response=await fetch('/api/admin-user-directory.php?'+params.toString(),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
  if(response.status===401){location.href='/admin-login.html?next='+encodeURIComponent(location.pathname);return;}
  if(!(response.headers.get('content-type')||'').includes('application/json'))throw Error('Unexpected server response');
  const data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'Unable to load leaders');
  total=Number(data.total)||0;size=Number(data.page_size)||25;
  for(const user of data.users||[]){
   const tr=document.createElement('tr');
   for(const value of [user.id,user.email,user.status,user.email_verified?'Verified':'Not verified']){
    const td=document.createElement('td');td.textContent=String(value??'');td.style.padding='10px 8px';td.style.borderBottom='1px solid #d8e6db';tr.append(td);
   }rows.append(tr);
  }
  status.textContent=total+' leader account(s) found · Page '+page+(data.users.length?'':' · No results');
  prev.disabled=page<=1;next.disabled=page*size>=total;
 }catch(error){status.textContent='Could not load leader accounts: '+error.message;}
}
form.addEventListener('submit',event=>{event.preventDefault();page=1;load()});
prev.addEventListener('click',()=>{if(page>1){page--;load()}});
next.addEventListener('click',()=>{if(page*size<total){page++;load()}});
load();
})();