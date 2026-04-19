import os

file_path = r'd:\xampp\htdocs\bmlab\exames.php'

def update_file():
    if not os.path.exists(file_path):
        print(f"File not found: {file_path}")
        return

    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # Marker from editor_laudo.txt
    js_init_marker = "// Começa vazio. Se você quiser carregar do banco, me diga quais variáveis você já tem (layoutRows/colunasCfg/celulasCfg)."
    
    # Bridge code wrapped in DOMContentLoaded to ensure data script tags (at bottom) are ready
    bridge_code = r"""
  // BRIDGE: Carregar dados do banco ao iniciar
  document.addEventListener('DOMContentLoaded', () => {
      try {
        const rowsDbEl = document.getElementById('layoutRowsData');
        const colsCfgDbEl = document.getElementById('colunasCfgDbData');
        const cellsCfgDbEl = document.getElementById('celulasCfgDbData');

        const rowsDb = rowsDbEl ? JSON.parse(rowsDbEl.textContent || '[]') : [];
        const colsCfgDb = colsCfgDbEl ? JSON.parse(colsCfgDbEl.textContent || '[]') : [];
        const cellsCfgDb = cellsCfgDbEl ? JSON.parse(cellsCfgDbEl.textContent || '[]') : [];
        
        if (Array.isArray(rowsDb) && rowsDb.length > 0) {
          const secoesMap = new Map();

          // 1. Agrupar linhas
          rowsDb.forEach(r => {
            const sId = parseInt(r.secao) || 0;
            if (sId <= 0) return;
            if (!secoesMap.has(sId)) {
              secoesMap.set(sId, { id: sId, colunas: [], linhas: [] });
            }
          });

          const secoesOrdenadas = Array.from(secoesMap.values()).sort((a,b) => a.id - b.id);

          secoesOrdenadas.forEach(sec => {
            const sId = sec.id;
            const rowsDaSecao = rowsDb.filter(r => parseInt(r.secao) === sId)
                                      .sort((a,b) => (parseInt(a.ordem)||0) - (parseInt(b.ordem)||0));

            // Descobrir colunas via config DB
            let colsDb = colsCfgDb.filter(c => parseInt(c.secao) === sId)
                                  .sort((a,b) => (parseInt(a.coluna)||0) - (parseInt(b.coluna)||0));
            
            let maxCol = 0;
            if (colsDb.length > 0) {
              maxCol = parseInt(colsDb[colsDb.length-1].coluna);
            } else {
               // fallback: scan rows
               rowsDaSecao.forEach(r => {
                 for(let k in r) {
                   if (k.startsWith('col')) {
                     const idx = parseInt(k.substring(3));
                     if (idx > maxCol && r[k]) maxCol = idx;
                   }
                 }
               });
               if(maxCol === 0) maxCol = 1;
            }

            // Construir colunas
            for(let i=1; i<=maxCol; i++) {
               const cfg = colsDb.find(c => parseInt(c.coluna) === i);
               sec.colunas.push({
                 id: 'c' + i,
                 titulo: cfg ? (cfg.titulo || (i===1?'Descrição':'Col '+i)) : (i===1?'Descrição':'Col '+i),
                 tipo: cfg ? cfg.tipo : '',
                 largura: cfg ? cfg.largura : null,
                 singleLine: cfg ? !!cfg.single_line : false,
                 customFont: cfg ? !!cfg.custom_font : false,
                 fontFamily: cfg ? cfg.font_family : '',
                 fontSize: cfg ? cfg.font_size : null,
                 fontColor: cfg ? cfg.font_color : '',
                 bold: cfg ? !!cfg.bold : false,
                 italic: cfg ? !!cfg.italic : false,
                 underline: cfg ? !!cfg.underline : false,
                 alignH: cfg ? cfg.align_h : '',
                 alignV: cfg ? cfg.align_v : ''
               });
            }

            // Construir linhas
            rowsDaSecao.forEach((r, rIndex) => {
               const cells = [];
               for(let i=1; i<=maxCol; i++) {
                 const val = r['col'+i] || '';
                 // Tenta achar config da celula (linha = rIndex)
                 const cellCfg = cellsCfgDb.find(c => parseInt(c.secao) === sId && parseInt(c.linha) === rIndex && parseInt(c.coluna) === i);
                 
                 cells.push({
                   valor: val,
                   uppercase: cellCfg ? !!cellCfg.uppercase : false,
                   singleLine: cellCfg ? !!cellCfg.single_line : false,
                   customFont: cellCfg ? !!cellCfg.custom_font : false,
                   fontFamily: cellCfg ? cellCfg.font_family : '',
                   fontSize: cellCfg ? cellCfg.font_size : null,
                   fontColor: cellCfg ? cellCfg.font_color : '',
                   bold: cellCfg ? !!cellCfg.bold : false,
                   italic: cellCfg ? !!cellCfg.italic : false,
                   underline: cellCfg ? !!cellCfg.underline : false,
                   alignH: cellCfg ? cellCfg.align_h : '',
                   alignV: cellCfg ? cellCfg.align_v : '',
                   rtTipo: cellCfg ? cellCfg.rt_tipo : '',
                   rtId: cellCfg ? cellCfg.rt_id : '',
                   numTipo: cellCfg ? cellCfg.numTipo : '',
                   numPos: cellCfg ? cellCfg.numPos : null,
                   numDec: cellCfg ? cellCfg.numDec : null,
                   numFormula: cellCfg ? cellCfg.numFormula : '',
                   varAlias: cellCfg ? cellCfg.varAlias : ''
                 });
               }
               sec.linhas.push({ cells });
            });
            
            secoesLaudo.push(sec);
          });
          
          if (secoesLaudo.length > 0) secaoAtivaId = secoesLaudo[0].id;
          renderizarEditorLaudo();
        } else {
          secoesLaudo = [];
          secaoAtivaId = null;
          renderizarEditorLaudo();
        }
      } catch(e) {
        console.error('Erro ao carregar layout do banco:', e);
        secoesLaudo = [];
        renderizarEditorLaudo();
      }
  });
"""

    old_init_block = """// Começa vazio. Se você quiser carregar do banco, me diga quais variáveis você já tem (layoutRows/colunasCfg/celulasCfg).
  secoesLaudo = [];
  secaoAtivaId = null;
  renderizarEditorLaudo();"""

    if old_init_block in content:
        content = content.replace(old_init_block, bridge_code)
        print("Replaced JS initialization block.")
    elif js_init_marker in content:
        # Fallback replacement
        # Find where the marker is and replace until renderizarEditorLaudo();
        # Simpler: just replace the marker + next 3 lines if possible, or just the marker
        # But we need to remove the "secoesLaudo = []" lines too.
        
        # Let's try to match slightly looser
        idx = content.find(js_init_marker)
        if idx != -1:
             # Find the end of this block
             end_marker = "renderizarEditorLaudo();"
             end_idx = content.find(end_marker, idx)
             if end_idx != -1:
                 # Replace from idx to end_idx + len(end_marker)
                 content = content[:idx] + bridge_code + content[end_idx + len(end_marker):]
                 print("Replaced JS initialization block (via markers).")
             else:
                 print("Could not find end of initialization block.")
    else:
        print("JS initialization marker not found.")

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

if __name__ == '__main__':
    update_file()
