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
function createHistory(limit=50){
 const max=Number.isInteger(limit)?Math.max(1,Math.min(100,limit)):50;
 const undoStack=[],redoStack=[];
 const clone=value=>JSON.parse(JSON.stringify(value));
 const same=(a,b)=>JSON.stringify(a)===JSON.stringify(b);
 const hasDifferent=(stack,current)=>stack.some(snapshot=>!same(snapshot,current));
 return Object.freeze({
  checkpoint(document){
   const snapshot=clone(document),last=undoStack[undoStack.length-1];
   if(!last||!same(last,snapshot))undoStack.push(snapshot);
   if(undoStack.length>max)undoStack.shift();
   redoStack.length=0;
  },
  undo(current){
   const currentSnapshot=clone(current);
   while(undoStack.length){
    const previous=undoStack.pop();
    if(same(previous,currentSnapshot))continue;
    redoStack.push(currentSnapshot);
    if(redoStack.length>max)redoStack.shift();
    return clone(previous);
   }
   return null;
  },
  redo(current){
   const currentSnapshot=clone(current);
   while(redoStack.length){
    const next=redoStack.pop();
    if(same(next,currentSnapshot))continue;
    undoStack.push(currentSnapshot);
    if(undoStack.length>max)undoStack.shift();
    return clone(next);
   }
   return null;
  },
  reset(){undoStack.length=0;redoStack.length=0;},
  canUndo(current){return !!current&&hasDifferent(undoStack,current);},
  canRedo(current){return !!current&&hasDifferent(redoStack,current);}
 });
}
const api=Object.freeze({duplicateSection,createHistory});
if(typeof window!=='undefined')window.BHVisualBuilderDocumentActions=api;
if(typeof module!=='undefined'&&module.exports)module.exports=api;
})();