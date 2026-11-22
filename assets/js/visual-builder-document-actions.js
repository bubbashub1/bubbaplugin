(()=>{'use strict';
function duplicateSection(source,createId){
 if(!source||source.type!=='section'||!Array.isArray(source.children)||typeof createId!=='function')throw new TypeError('A valid section and ID factory are required.');
 const copy=JSON.parse(JSON.stringify(source));
 copy.id=createId('container-copy');
 copy.children=copy.children.map(child=>{
  if(!child||typeof child!=='object'||typeof child.type!=='string')throw new TypeError('Section contains an invalid child block.');
  return {...child,id:createId('block-copy')};
 });
 return copy;
}
const api=Object.freeze({duplicateSection});
if(typeof window!=='undefined')window.BHVisualBuilderDocumentActions=api;
if(typeof module!=='undefined'&&module.exports)module.exports=api;
})();