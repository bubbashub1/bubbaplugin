async function init(){
const r=await fetch('data/activities.json');const all=await r.json();
const saved=JSON.parse(localStorage.getItem('bubba.saved')||'[]');
document.querySelector('#statActivities').textContent=all.length;
document.querySelector('#statSaved').textContent=saved.length;
const render=()=>{const q=document.querySelector('#adminSearch').value.toLowerCase();const list=all.filter(a=>`${a.title} ${a.category} ${a.town}`.toLowerCase().includes(q));document.querySelector('#adminActivities').innerHTML=list.map(a=>`<tr><td><strong>${a.title}</strong><small>${a.location}</small></td><td>${a.category}</td><td>${a.town}</td><td>${a.day} ${a.time}</td><td>${a.price}</td><td><span class="admin-status">Published</span></td></tr>`).join('')};
document.querySelector('#adminSearch').addEventListener('input',render);
document.querySelector('#newActivity').addEventListener('click',()=>alert('The secure activity editor will connect to the production API/database in the next backend phase.'));
document.querySelector('#deployLatest').addEventListener('click',async()=>{
const status=document.querySelector('#deployStatus');
const key=prompt('Enter your Bubba Hub deployment key:');
if(!key)return;
status.textContent='Starting deployment…';
try{
const response=await fetch('deploy.php',{method:'POST',headers:{'X-Bubba-Deploy-Key':key}});
const data=await response.json();
if(!response.ok||!data.ok)throw new Error(data.error||'Deployment failed');
status.textContent='✓ Deployment started. GitHub Actions is now publishing the latest main branch.';
}catch(e){status.textContent='Deployment failed: '+e.message;}
});
render();
}init();