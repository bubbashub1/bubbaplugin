/* Apply the published Site Manager design tokens without changing page structure or interactions. */
(function(){
  if(window.__bhSiteManagerDesignLoaded)return;
  window.__bhSiteManagerDesignLoaded=true;
  fetch('/api/site-design.php',{credentials:'same-origin',cache:'default',headers:{Accept:'application/json'}})
    .then(response=>response.ok?response.json():null)
    .then(payload=>{
      const s=payload?.published;
      if(!s||typeof s!=='object')return;
      const root=document.documentElement;
      const set=(name,value)=>root.style.setProperty(name,String(value),'important');
      const colour=(value)=>typeof value==='string'&&/^#[0-9a-f]{6}$/i.test(value);
      if(colour(s.primary)){
        set('--bh-primary',s.primary);set('--bh-green',s.primary);set('--bh-brand',s.primary);
      }
      if(colour(s.accent)){
        set('--bh-secondary',s.accent);set('--bh-teal',s.accent);set('--bh-accent',s.accent);
      }
      if(colour(s.surface)){set('--bh-bg',s.surface);set('--bh-page',s.surface);set('--bh-surface',s.surface);}
      if(colour(s.text)){set('--bh-text',s.text);set('--bh-ink',s.text);}
      const fonts=['Quicksand','Arial','Calibri','Georgia','Verdana'];
      if(fonts.includes(s.heading_font))set('--bh-heading-font',"'"+s.heading_font+"'");
      if(fonts.includes(s.body_font))set('--bh-body-font',"'"+s.body_font+"'");
      const radii=[0,4,8,12,16,20,24,28];
      if(radii.includes(Number(s.card_radius)))set('--bh-radius',Number(s.card_radius)+'px');
      if([4,8,12,16,20,999].includes(Number(s.button_radius)))set('--bh-button-radius',Number(s.button_radius)+'px');
      if([8,12,16,20,24,32,40,48].includes(Number(s.section_gap)))set('--bh-section-gap',Number(s.section_gap)+'px');
      document.body?.classList.add('bh-site-manager-design-active');
    })
    .catch(()=>{/* Design settings are optional; existing styling remains the fallback. */});
})();