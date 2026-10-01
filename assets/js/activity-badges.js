/* Shared Activity Badges component.
 * Keep badge rules and rendering in one place so directory cards,
 * activity pages and future views can use the same badge system.
 */
(function(){
  "use strict";

  function isTruthy(value){
    return value===true ||
      value===1 ||
      value==="1" ||
      String(value||"").toLowerCase()==="true" ||
      String(value||"").toLowerCase()==="yes";
  }

  function isNewActivity(activity){
    const raw=activity?.created_at ||
      activity?.createdAt ||
      activity?.published_at ||
      activity?.date_created;
    if(!raw)return false;
    const date=new Date(raw);
    return !Number.isNaN(date.getTime()) &&
      (Date.now()-date.getTime()<=30*24*60*60*1000);
  }

  function escape(value){
    return String(value??"").replace(/[&<>"']/g,ch=>({
      "&":"&amp;",
      "<":"&lt;",
      ">":"&gt;",
      '"':"&quot;",
      "'":"&#39;"
    }[ch]));
  }

  function getBadges(activity){
    const badges=[];
    if(isTruthy(activity?.verified ?? activity?.is_verified ?? activity?.organiser_verified)){
      badges.push({key:"verified",icon:"✓",label:"Verified"});
    }
    if(isTruthy(activity?.featured ?? activity?.is_featured)){
      badges.push({key:"featured",icon:"★",label:"Featured"});
    }
    if(isTruthy(activity?.popular ?? activity?.is_popular)){
      badges.push({key:"popular",icon:"♥",label:"Popular"});
    }
    if(isNewActivity(activity) || isTruthy(activity?.is_new)){
      badges.push({key:"new",icon:"✦",label:"New"});
    }
    return badges;
  }

  function renderBadges(badges){
    const list=Array.isArray(badges)?badges:[];
    if(!list.length)return "";
    return '<div class="bh-badges bh-badges-activity" aria-label="Activity badges">'+
      list.map(b=>{
        const key=escape(b.key);
        const label=escape(b.label);
        const icon=escape(b.icon);
        return '<span class="bh-badge bh-badge-'+key+'" title="'+label+'">'+
          '<span class="bh-badge-icon" aria-hidden="true">'+icon+'</span>'+
          label+
        '</span>';
      }).join("")+
    '</div>';
  }

  window.BHActivityBadges={
    get:getBadges,
    render:renderBadges
  };

  /* Backwards-compatible global helpers for existing page code. */
  window.getActivityBadges=getBadges;
  window.renderActivityBadges=renderBadges;
})();
