import hotkeys from 'hotkeys-js';

export default function useKeybinds({ onSave, onDelete }){
  hotkeys('ctrl+s, command+s', (e)=>{ e.preventDefault(); onSave(); });
  hotkeys('del, backspace',   (e)=>{ onDelete?.(); });
  return () => hotkeys.unbind('ctrl+s, command+s, del, backspace');
}
