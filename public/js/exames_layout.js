document.addEventListener('DOMContentLoaded', function () {
  // ==========================
  // ESTADO DO EDITOR
  // ==========================
  const editorLaudoEl = document.getElementById("areaLayoutLaudo-v2");
  let secoesLaudo = [];
  let secaoAtivaId = null;
  window.examOptionsCache = {}; // Cache de opções para dropdowns

  let clipboardFull = null;
  let clipboardSecao = null;

  let editColSecaoId = null;
  let editColIndex = null;

  let menuCtx = null;

  // ==========================
  // HELPERS
  // ==========================
  function obterSecao(id) { return secoesLaudo.find(s => s.id === id); }

  function labelPorTipo(tipo) {
    if (!tipo) return '';
    const map = {
      'descricao': 'Descrição',
      'material_biologico': 'Material biológico',
      'exame_nome': 'Nome do exame',
      'exame_mnemonico': 'Mnemônico local',
      'exame_metodo': 'Método',
      'exame_prazo_local': 'Prazo execução local',
      'nome_laboratorio': 'Nome do laboratório',
      'nome_posto': 'Nome do posto do pedido',
      'numero_amostra': 'Número da amostra',
      'resultado_num': 'Resultado numérico',
      'resultado_texto': 'Resultado texto',
      'resultado_texto_formatado': 'Resultado texto formatado',
      'resultado_foto': 'Resultado Foto',
      'resultado_img_dinamica': 'Resultado Imagem Dinâmica',
      'resultado_img_estatica': 'Resultado Imagem Estática',
      'observacao_resultado': 'Observação do Resultado',
      'valor_referencia': 'Valor de referência',
      'unidade': 'Unidade',
      'data_coleta': 'Data da coleta',
      'data_liberacao': 'Data da liberação',
      'resultado_anterior': 'Resultado anterior',
      'data_resultado_anterior': 'Data resultado anterior'
    };
    return map[tipo] || '';
  }

  // Delegated event for auto-growing textareas
  document.addEventListener('input', function(e) {
      if (e.target && e.target.classList.contains('auto-grow')) {
          e.target.style.height = 'auto';
          e.target.style.height = (e.target.scrollHeight) + 'px';
      }
  });

  function valorPorTipo(tipo) {
    if (tipo === 'descricao') return null;
    if (tipo === 'material_biologico') {
      const nomeEl = document.getElementById('exame_material_biologico_val');
      const idEl = document.getElementById('exame_material_biologico_id_val');
      const nome = (nomeEl ? nomeEl.value : '').trim();
      if (nome) return nome;
      const id = (idEl ? idEl.value : '').toString();
      return id || '';
    }
    if (tipo === 'exame_nome') return (document.getElementById('exame_nome_val')?.value || '');
    if (tipo === 'exame_mnemonico') return (document.getElementById('exame_mnemonico_val')?.value || '');
    if (tipo === 'exame_metodo') return (document.getElementById('exame_metodo_val')?.value || '');
    if (tipo === 'exame_prazo_local') return (document.getElementById('exame_prazo_val')?.value || '');
    if (tipo === 'data_coleta') return '00/00/0000';
    if (tipo === 'data_liberacao') return '00/00/0000';
    if (tipo === 'resultado_num') return '0';
    if (tipo === 'resultado_texto_formatado') return '';
    if (tipo === 'resultado_foto') return '[FOTO]';
    if (tipo === 'resultado_img_dinamica') return '[IMAGEM DINÂMICA]';
    if (tipo === 'resultado_img_estatica') return '[IMAGEM ESTÁTICA]';
    return '';
  }

  function selecionarSecao(id) {
    secaoAtivaId = id;
    renderizarEditorLaudo();
  }

  function fecharMenuCtx() {
    if (menuCtx) { menuCtx.remove(); menuCtx = null; }
  }

  function aplicarLargurasSecao(secao) {
    const cols = secao.colunas || [];
    if (!cols.length) return;

    const setVals = cols.map(c => ({ w: (c && c.largura != null) ? parseFloat(c.largura) : null }));
    const sumSet = setVals.reduce((a, x) => a + (x.w != null ? x.w : 0), 0);
    const unsetIdx = [];
    setVals.forEach((x, i) => { if (x.w == null || isNaN(x.w)) unsetIdx.push(i); });

    if (unsetIdx.length > 0) {
      let remain = 100 - sumSet;
      if (remain < 0) remain = 0;
      const share = remain / unsetIdx.length;
      unsetIdx.forEach(i => { cols[i].largura = share; });
    } else {
      if (sumSet <= 0) {
        const base = 100 / cols.length;
        cols.forEach(c => c.largura = base);
      } else if (Math.abs(sumSet - 100) > 0.5) {
        const factor = 100 / sumSet;
        cols.forEach(c => { c.largura = (parseFloat(c.largura) || 0) * factor; });
      }
    }
  }

  function salvarValoresDigitadosDaTela() {
    if (!editorLaudoEl) return;
    editorLaudoEl.querySelectorAll('td[contenteditable="true"]').forEach(td => {
      const secaoId = parseInt(td.dataset.secaoId, 10);
      const r = parseInt(td.dataset.rowIndex, 10);
      const c = parseInt(td.dataset.colIndex, 10);
      const valor = td.textContent; // .innerText as vezes traz \n a mais

      const secao = obterSecao(secaoId);
      if (secao && secao.linhas[r] && secao.linhas[r].cells[c]) {
        secao.linhas[r].cells[c].valor = valor;
      }
    });
  }

  function carregarOpcoesVariaveis() {
      const exameIdVal = window.GLOBAL_EXAME_ID || document.querySelector('#formExame input[name="id"]')?.value || document.querySelector('input[name="exame_id"]')?.value || 0;
      
      if (!exameIdVal) return Promise.resolve();

      return fetch('index.php?r=exames/get_all_variaveis_opcoes&exame_id=' + exameIdVal)
      .then(r => r.json())
      .then(res => {
          if (res.sucesso) {
              window.examOptionsCache = res.dados;
              // Se quisermos atualizar o editor caso já tenha renderizado:
              // renderizarEditorLaudo();
          }
      })
      .catch(console.error);
  }

  // ==========================
  // RENDERIZAÇÃO PRINCIPAL
  // ==========================
  function renderizarEditorLaudo() {
    if (!editorLaudoEl) return;
    editorLaudoEl.innerHTML = '';

    if (!secoesLaudo || secoesLaudo.length === 0) {
      editorLaudoEl.innerHTML = '<div class="layout-vazio">Nenhuma seção. Adicione uma.</div>';
      return;
    }

    secoesLaudo.forEach(secao => {
      const divSecao = document.createElement('div');
      divSecao.className = 'secao-laudo border bg-white'; 
      divSecao.style.marginBottom = '-1px'; 
      if (secao.id === secaoAtivaId) {
        divSecao.classList.add('secao-ativa');
        // Se precisar de destaque visual, pode ser via borda ou background
        divSecao.style.position = 'relative'; 
        divSecao.style.zIndex = '1'; // Ensure active section border is on top
      }

      divSecao.onclick = (e) => {
        // se clicar na area da secao, seleciona
        selecionarSecao(secao.id);
      };

      // Cabeçalho da tabela
      const table = document.createElement('table');
      table.className = 'table table-bordered table-sm tabela-secao mb-0';
      table.style.width = '100%';
      table.style.tableLayout = 'fixed';

      // COLGROUP
      const colgroup = document.createElement('colgroup');
      const colId = document.createElement('col');
      colId.style.width = '38px';
      colgroup.appendChild(colId);

      const colsCount = secao.colunas.length;
      secao.colunas.forEach(col => {
        const cEl = document.createElement('col');
        // se nao tiver largura definida, divide igual
        const w = parseFloat(col.largura) || (100 / colsCount);
        cEl.style.width = w + '%';
        colgroup.appendChild(cEl);
      });
      table.appendChild(colgroup);

      // THEAD
      const thead = document.createElement('thead');
      const trHead = document.createElement('tr');

      // Coluna ID (drag handle secao?)
      const thId = document.createElement('th');
      thId.className = 'text-center table-light';
      thId.textContent = secao.id;
      thId.style.verticalAlign = 'middle';
      trHead.appendChild(thId);

      secao.colunas.forEach((col, idx) => {
        const th = document.createElement('th');
        th.className = 'table-light';
        const lbl = col.tipo ? labelPorTipo(col.tipo) : (col.titulo || 'Col '+(idx+1));
        th.textContent = lbl;
        th.style.position = 'relative';

        // estilos
        if (col.alignH) th.style.textAlign = col.alignH;
        if (col.alignV) th.style.verticalAlign = col.alignV;
        if (col.bold) th.style.fontWeight = 'bold';
        if (col.italic) th.style.fontStyle = 'italic';
        if (col.underline) th.style.textDecoration = 'underline';
        if (col.customFont) {
          if (col.fontFamily) th.style.fontFamily = col.fontFamily;
          if (col.fontSize) th.style.fontSize = col.fontSize + 'px';
          if (col.fontColor) th.style.color = col.fontColor;
        }

        // Resizer (only if not the last column)
        if (idx < secao.colunas.length - 1) {
            const resizer = document.createElement('div');
            resizer.className = 'col-resizer';
            resizer.addEventListener('mousedown', (e) => iniciarResize(e, secao.id, idx));
            // evitar propagar click
            resizer.addEventListener('click', e => e.stopPropagation());
            th.appendChild(resizer);
        }

        // Context Menu Coluna
        th.addEventListener('contextmenu', (e) => {
          e.preventDefault();
          selecionarSecao(secao.id);
          abrirMenuContexto(e, 'coluna', secao.id, idx);
        });
        
        // Double click -> Editar Coluna
        th.addEventListener('dblclick', (e) => {
           e.stopPropagation();
           selecionarSecao(secao.id);
           abrirModalConfigColuna(secao.id, idx);
        });

        trHead.appendChild(th);
      });
      thead.appendChild(trHead);
      table.appendChild(thead);

      // TBODY
      const tbody = document.createElement('tbody');
      secao.linhas.forEach((linha, rIdx) => {
        const tr = document.createElement('tr');
        
        // Célula ID da linha (pode ter menu de linha)
        const tdId = document.createElement('td');
        tdId.className = 'text-center table-light text-muted';
        tdId.style.fontSize = '0.75rem';
        tdId.style.verticalAlign = 'middle';
        tdId.textContent = '';
        tdId.addEventListener('contextmenu', (e) => {
          e.preventDefault();
          selecionarSecao(secao.id);
          abrirMenuContexto(e, 'linha', secao.id, rIdx);
        });
        // Double click -> Config Linha
        tdId.addEventListener('dblclick', (e) => {
          e.stopPropagation();
          selecionarSecao(secao.id);
          abrirModalConfigLinha(secao.id, rIdx);
        });
        tr.appendChild(tdId);

        // Configs visuais da linha
        if (linha.altura) tr.style.height = linha.altura + 'px';
        if (linha.quebraAntes) tr.classList.add('border-top-thick-dashed'); // Indicador visual
        if (linha.quebraDepois) tr.classList.add('border-bottom-thick-dashed'); // Indicador visual

        linha.cells.forEach((cell, cIdx) => {
          const td = document.createElement('td');
          td.dataset.secaoId = secao.id;
          td.dataset.rowIndex = rIdx;
          td.dataset.colIndex = cIdx;

          const col = secao.colunas[cIdx];
          
          // Valor
          // Se coluna for dinamica, mostra placeholder
          const dynSet = new Set(['material_biologico','exame_nome','exame_mnemonico','exame_metodo','exame_prazo_local']);
          const isDynamic = !!(col && col.tipo && dynSet.has(col.tipo));
          
          let v = isDynamic ? (valorPorTipo(col.tipo) ?? '') : (cell.valor || '');
          
          // placeholder numerico?
          const isNumCol = !!(col && col.tipo === 'resultado_num');
          if (isNumCol) {
             const modoN = cell.numTipo || 'numero';
             if (modoN === 'numero') {
               const pos = (cell.numPos != null) ? Math.max(0,Math.min(12, parseInt(cell.numPos,10))) : 0;
               const dec = (cell.numDec != null) ? Math.max(0,Math.min(12, parseInt(cell.numDec,10))) : 0;
               const left = '0'.repeat(Math.max(1,pos));
               const right = dec > 0 ? '0'.repeat(dec) : '';
               v = right ? (left+','+right) : left;
             } else if (modoN === 'calculo') {
               v = '[Cálculo]';
             } else {
               v = '';
             }
          }

          td.textContent = String(v||'').replace(/\\r/g,'\\n');
          
          // Editable?
          let editable = isDynamic ? 'false' : 'true';
          // Se for RT e estiver em modo "sem" ou "cadastrado", nao edita direto
          const isRtCol = !!(col && (col.tipo === 'resultado_texto' || col.tipo === 'resultado_texto_formatado'));
          const modoRt = cell.rtTipo || 'livre';
          if (isRtCol && (modoRt === 'sem' || modoRt === 'cadastrado')) editable = 'false';
          if (isNumCol) editable = 'false'; // num edita via modal

          td.contentEditable = editable;

          // Estilos
          // Prioridade: Celula > Coluna
          const singleLine = !!((cell.singleLine) || (col && col.singleLine));
          if (singleLine) {
            td.style.whiteSpace = 'nowrap';
            td.style.overflow = 'hidden';
          } else {
            td.style.whiteSpace = 'pre-wrap';
          }

          const alignH = (cell.alignH) || (col && col.alignH);
          if (alignH) td.style.textAlign = alignH;

          const alignV = (cell.alignV) || (col && col.alignV);
          if (alignV) td.style.verticalAlign = alignV;

          const ff = (cell.fontFamily) || (col && col.fontFamily) || '';
          const fs = (cell.fontSize != null) ? cell.fontSize : ((col && col.fontSize != null) ? col.fontSize : null);
          const fc = (cell.fontColor) || (col && col.fontColor) || '';
          
          if (ff) td.style.fontFamily = ff;
          if (fs != null) td.style.fontSize = fs + 'px';
          if (fc) td.style.color = fc;
          
          const bold = (cell.bold != null) ? cell.bold : !!(col && col.bold);
          if (bold) td.style.fontWeight = 'bold';

          const italic = (cell.italic != null) ? cell.italic : !!(col && col.italic);
          if (italic) td.style.fontStyle = 'italic';

          const underline = (cell.underline != null) ? cell.underline : !!(col && col.underline);
          if (underline) td.style.textDecoration = 'underline';

          if (cell.uppercase) td.textContent = (td.textContent||'').toUpperCase();

          // Double click -> Config Celula
          td.addEventListener('dblclick', (e) => {
            e.stopPropagation();
            selecionarSecao(secao.id);
            const col = secao.colunas[cIdx];
            if (col && (col.tipo === 'resultado_texto' || col.tipo === 'resultado_texto_formatado')) {
                abrirModalConfigResultadoTexto(secao.id, rIdx, cIdx);
            } else {
                abrirModalConfigCelula(secao.id, rIdx, cIdx);
            }
          });
          
          // Context Menu Celula
           td.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            e.stopPropagation(); // para nao abrir o da linha
            selecionarSecao(secao.id);
            abrirMenuContexto(e, 'celula', secao.id, rIdx, cIdx);
          });

          tr.appendChild(td);
        });
        tbody.appendChild(tr);
      });
      table.appendChild(tbody);

      divSecao.appendChild(table);
      editorLaudoEl.appendChild(divSecao);
    });
  }

  // ==========================
  // RESIZE COLUNA
  // ==========================
  function iniciarResize(e, secaoId, colIdx) {
    e.preventDefault();
    const startX = e.pageX;
    const secao = obterSecao(secaoId);
    if (!secao) return;
    
    const th = e.target.closest('th');
    const table = th.closest('table');
    const tableW = table.offsetWidth;
    const startW = th.offsetWidth;
    
    // Identifica o elemento <col> correspondente (offset +1 por causa da coluna de ID)
    const colgroup = table.querySelector('colgroup');
    const colEl = colgroup ? colgroup.children[colIdx + 1] : null;
    const nextColEl = colgroup ? colgroup.children[colIdx + 2] : null; // Next column

    // Next TH width (approximate)
    const nextTh = th.nextElementSibling;
    const startWNext = nextTh ? nextTh.offsetWidth : 0;

    if (!colEl || !nextColEl) return; // Must have next column to resize

    document.body.style.cursor = 'col-resize';

    const onMove = (evt) => {
      const dx = evt.pageX - startX;
      
      let newW = startW + dx;
      let newWNext = startWNext - dx;
      
      // Limites de segurança (px)
      if (newW < 30) { newW = 30; newWNext = startW + startWNext - 30; }
      if (newWNext < 30) { newWNext = 30; newW = startW + startWNext - 30; }

      let newPerc = (newW / tableW) * 100;
      let newPercNext = (newWNext / tableW) * 100;
      
      // Atualiza visualmente na hora
      colEl.style.width = newPerc + '%';
      nextColEl.style.width = newPercNext + '%';
    };
    
    const onUp = (evt) => {
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
      document.body.style.cursor = '';
      
      const dx = evt.pageX - startX;
      let newW = startW + dx;
      let newWNext = startWNext - dx;
      
      if (newW < 30) { newW = 30; newWNext = startW + startWNext - 30; }
      if (newWNext < 30) { newWNext = 30; newW = startW + startWNext - 30; }

      const newPerc = (newW / tableW) * 100;
      const newPercNext = (newWNext / tableW) * 100;

      if (secao.colunas[colIdx]) secao.colunas[colIdx].largura = newPerc;
      if (secao.colunas[colIdx+1]) secao.colunas[colIdx+1].largura = newPercNext;

      aplicarLargurasSecao(secao);
      renderizarEditorLaudo();
    };

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  }

  // ==========================
  // CONTEXT MENUS
  // ==========================
  function abrirMenuContexto(e, tipo, secId, arg1, arg2) {
    fecharMenuCtx();
    const menu = document.createElement('ul');
     menu.className = 'dropdown-menu show shadow-sm custom-ctx-menu';
     menu.style.position = 'absolute';
    menu.style.left = e.pageX + 'px';
    menu.style.top = e.pageY + 'px';
    menu.style.zIndex = 9999;
    menu.style.listStyle = 'none';
    menu.style.padding = '0.5rem 0';
    menu.style.margin = '0';
    menu.style.backgroundColor = '#fff';
    menu.style.border = '1px solid rgba(0,0,0,.15)';
    menu.style.borderRadius = '0.375rem';
    menu.style.boxShadow = '0 0.5rem 1rem rgba(0, 0, 0, 0.15)';
    
    if (tipo === 'coluna') {
      const cIdx = arg1;
      menu.innerHTML = `
        <li><h6 class="dropdown-header">Coluna ${cIdx+1}</h6></li>
        <li><a class="dropdown-item" href="#" data-action="cfg_col">Configurar Coluna</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#" data-action="move_left"><i class="bi bi-arrow-left"></i> Mover para Esquerda</a></li>
        <li><a class="dropdown-item" href="#" data-action="move_right"><i class="bi bi-arrow-right"></i> Mover para Direita</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="#" data-action="del_col"><i class="bi bi-trash"></i> Excluir Coluna</a></li>
      `;
      menu.onclick = (evt) => {
        // Handle click on icon inside anchor
        const target = evt.target.closest('a');
        if (!target) return;
        const act = target.dataset.action;
        
        if (act === 'cfg_col') abrirModalConfigColuna(secId, cIdx);
        if (act === 'move_left') moverColuna(secId, cIdx, -1);
        if (act === 'move_right') moverColuna(secId, cIdx, 1);
        if (act === 'del_col') excluirColuna(secId, cIdx);
        fecharMenuCtx();
      };
    } else if (tipo === 'linha') {
      const rIdx = arg1;
      menu.innerHTML = `
        <li><h6 class="dropdown-header">Linha ${rIdx+1}</h6></li>
        <li><a class="dropdown-item" href="#" data-action="add_row_before"><i class="bi bi-arrow-bar-up"></i> Inserir Linha Antes</a></li>
        <li><a class="dropdown-item" href="#" data-action="add_row_after"><i class="bi bi-arrow-bar-down"></i> Inserir Linha Depois</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="#" data-action="del_row"><i class="bi bi-trash"></i> Excluir Linha</a></li>
      `;
      menu.onclick = (evt) => {
        const target = evt.target.closest('a');
        if (!target) return;
        const act = target.dataset.action;
        
        if (act === 'add_row_before') addLinha(secId, rIdx);
        if (act === 'add_row_after') addLinha(secId, rIdx+1);
        if (act === 'del_row') excluirLinha(secId, rIdx);
        fecharMenuCtx();
      };
    } else if (tipo === 'celula') {
       // arg1 = rIdx, arg2 = cIdx
       menu.innerHTML = `
        <li><a class="dropdown-item" href="#" data-action="cfg_cell"><i class="bi bi-gear"></i> Configurar Célula</a></li>
        <li><a class="dropdown-item" href="#" data-action="clear_cell"><i class="bi bi-eraser"></i> Limpar Valor</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#" data-action="add_row_before"><i class="bi bi-arrow-bar-up"></i> Inserir Linha Antes</a></li>
        <li><a class="dropdown-item" href="#" data-action="add_row_after"><i class="bi bi-arrow-bar-down"></i> Inserir Linha Depois</a></li>
        <li><a class="dropdown-item text-danger" href="#" data-action="del_row"><i class="bi bi-trash"></i> Excluir Linha</a></li>
      `;
       menu.onclick = (evt) => {
        const target = evt.target.closest('a');
        if (!target) return;
        const act = target.dataset.action;
        
        if (act === 'cfg_cell') abrirModalConfigCelula(secId, arg1, arg2);
        if (act === 'clear_cell') {
          const s = obterSecao(secId);
          if (s && s.linhas[arg1] && s.linhas[arg1].cells[arg2]) {
             s.linhas[arg1].cells[arg2].valor = '';
             renderizarEditorLaudo();
          }
        }
        if (act === 'add_row_before') addLinha(secId, arg1);
        if (act === 'add_row_after') addLinha(secId, arg1+1);
        if (act === 'del_row') excluirLinha(secId, arg1);

        fecharMenuCtx();
      };
    }

    document.body.appendChild(menu);
    menuCtx = menu;
    // fechar ao clicar fora
    setTimeout(() => {
       document.addEventListener('click', fecharMenuCtx, {once:true});
    }, 0);
  }

  function moverColuna(secId, cIdx, direction) {
    const secao = obterSecao(secId);
    if (!secao) return;

    const newIdx = cIdx + direction;

    // Verifica limites
    if (newIdx < 0 || newIdx >= secao.colunas.length) return;

    // Troca metadata da coluna
    const tempCol = secao.colunas[cIdx];
    secao.colunas[cIdx] = secao.colunas[newIdx];
    secao.colunas[newIdx] = tempCol;

    // Troca células em todas as linhas
    secao.linhas.forEach(linha => {
        // Verifica se células existem (segurança)
        if (linha.cells[cIdx] !== undefined && linha.cells[newIdx] !== undefined) {
            const tempCell = linha.cells[cIdx];
            linha.cells[cIdx] = linha.cells[newIdx];
            linha.cells[newIdx] = tempCell;
        }
    });

    renderizarEditorLaudo();
  }

  function excluirColuna(secId, cIdx) {
    if (!confirm('Excluir esta coluna?')) return;
    const s = obterSecao(secId);
    if (!s) return;
    s.colunas.splice(cIdx, 1);
    s.linhas.forEach(l => l.cells.splice(cIdx, 1));
    aplicarLargurasSecao(s);
    renderizarEditorLaudo();
  }

  function excluirLinha(secId, rIdx) {
    if (!confirm('Excluir esta linha?')) return;
    const s = obterSecao(secId);
    if (!s) return;
    s.linhas.splice(rIdx, 1);
    renderizarEditorLaudo();
  }

  function addLinha(secId, atIndex) {
    const s = obterSecao(secId);
    if (!s) return;
    const nova = { cells: s.colunas.map(() => ({ valor:'' })) };
    s.linhas.splice(atIndex, 0, nova);
    renderizarEditorLaudo();
  }


  // ==========================
  // MODAIS CONFIG
  // ==========================
  function abrirModalConfigColuna(secId, cIdx) {
    editColSecaoId = secId;
    editColIndex = cIdx;
    const secao = obterSecao(secId);
    if (!secao) return;
    const col = secao.colunas[cIdx];
    if (!col) return;

    // Preenche form
    document.getElementById('cfg_tipo').value = col.tipo || '';
    document.getElementById('cfg_largura').value = col.largura || '';
    document.getElementById('cfg_titulo').value = col.titulo || '';
    
    document.getElementById('cfg_single_line').checked = !!col.singleLine;
    document.getElementById('cfg_custom_font').checked = !!col.customFont;
    document.getElementById('cfg_font_family').value = col.fontFamily || '';
    document.getElementById('cfg_font_size').value = col.fontSize || '';
    const cfgFc = col.fontColor;
    document.getElementById('cfg_font_color').value = (/^#[0-9A-F]{6}$/i.test(cfgFc)) ? cfgFc : '#000000';
    document.getElementById('cfg_bold').checked = !!col.bold;
    document.getElementById('cfg_italic').checked = !!col.italic;
    document.getElementById('cfg_underline').checked = !!col.underline;
    
    // Alinhamento (Radio buttons)
    const alignH = col.alignH || 'left'; 
    const rH = document.querySelector(`input[name="align_h"][value="${alignH}"]`);
    if(rH) rH.checked = true;
    
    const alignV = col.alignV || 'middle';
    const rV = document.querySelector(`input[name="align_v"][value="${alignV}"]`);
    if(rV) rV.checked = true;

    // Configurações numéricas e alias
    // (Poderíamos adicionar aqui se necessário, baseado no HTML que vi: cfg_num_pos, cfg_num_dec, etc.)

    document.getElementById('modal_config_coluna').showModal();
  }

  function abrirModalConfigCelula(secId, rIdx, cIdx) {
    const secao = obterSecao(secId);
    if (!secao) return;
    const cell = secao.linhas[rIdx].cells[cIdx];
    
    document.getElementById('cel_secao_id').value = secId;
    document.getElementById('cel_row_idx').value = rIdx;
    document.getElementById('cel_col_idx').value = cIdx;

    document.getElementById('cel_valor').value = cell.valor || '';
    
    // Numérico
    document.getElementById('cel_num_tipo').value = cell.numTipo || '';
    document.getElementById('cel_num_pos').value = cell.numPos != null ? cell.numPos : '';
    document.getElementById('cel_num_dec').value = cell.numDec != null ? cell.numDec : '';
    document.getElementById('cel_num_formula').value = cell.numFormula || '';
    
    // Alias
    document.getElementById('cel_var_alias').value = cell.varAlias || '';

    // Estilos
    document.getElementById('cel_font_family').value = cell.fontFamily || '';
    document.getElementById('cel_font_size').value = cell.fontSize || '';
    const celFc = cell.fontColor;
    document.getElementById('cel_font_color').value = (/^#[0-9A-F]{6}$/i.test(celFc)) ? celFc : '#000000';
    
    document.getElementById('cel_bold').checked = !!cell.bold;
    document.getElementById('cel_italic').checked = !!cell.italic;
    document.getElementById('cel_underline').checked = !!cell.underline;
    
    // Alinhamento
    const alignH = cell.alignH || '';
    const rH = document.querySelector(`input[name="cel_align_h"][value="${alignH}"]`);
    if (rH) rH.checked = true;
    else {
        const defH = document.querySelector(`input[name="cel_align_h"][value=""]`);
        if(defH) defH.checked = true;
    }

    const alignV = cell.alignV || '';
    const rV = document.querySelector(`input[name="cel_align_v"][value="${alignV}"]`);
    if (rV) rV.checked = true;
    else {
        const defV = document.querySelector(`input[name="cel_align_v"][value=""]`);
        if(defV) defV.checked = true;
    }

    document.getElementById('cel_uppercase').checked = !!cell.uppercase;
    document.getElementById('cel_single_line').checked = !!cell.singleLine;
    document.getElementById('cel_custom_font').checked = !!cell.customFont;
    
    // Toggle visibilidade
    const divNum = document.getElementById('cel_opts_numero');
    const divCalc = document.getElementById('cel_opts_calculo');
    
    const updateVis = () => {
        const t = document.getElementById('cel_num_tipo').value;
        if (divNum) divNum.classList.toggle('hidden', t !== 'numero');
        if (divCalc) divCalc.classList.toggle('hidden', t !== 'calculo');
    };
    
    document.getElementById('cel_num_tipo').onchange = updateVis;
    updateVis();

    const formulaInput = document.getElementById('cel_num_formula');
    const formulaLegend = document.getElementById('cel_formula_legend');
    if (formulaInput && formulaLegend) {
      const showLegend = () => formulaLegend.classList.remove('hidden');
      const hideLegend = () => formulaLegend.classList.add('hidden');
      formulaInput.addEventListener('focus', showLegend);
      formulaInput.addEventListener('click', showLegend);
      formulaInput.addEventListener('blur', hideLegend);
      document.getElementById('cel_num_tipo').addEventListener('change', () => {
        const t = document.getElementById('cel_num_tipo').value;
        if (t !== 'calculo') hideLegend();
      });
    }

    document.getElementById('modal_config_celula').showModal();
  }

  // ==========================
  // CONFIG RESULTADO TEXTO
  // ==========================
  let rtTextosCache = [];
  let rtLibraryCache = null; // Cache da biblioteca global
  let rtEditandoId = null;

  function carregarBibliotecaTextos() {
      if (rtLibraryCache) return Promise.resolve(rtLibraryCache);
      return fetch('index.php?r=resultados_texto/get_all_json')
          .then(r => r.json())
          .then(data => {
              rtLibraryCache = data || [];
              return rtLibraryCache;
          })
          .catch(err => {
              console.error('Erro ao carregar biblioteca de textos:', err);
              return [];
          });
  }

  function abrirModalConfigResultadoTexto(secId, rIdx, cIdx) {
      const secao = obterSecao(secId);
      if (!secao || !secao.linhas[rIdx] || !secao.linhas[rIdx].cells[cIdx]) return;
      const cell = secao.linhas[rIdx].cells[cIdx];
      
      document.getElementById('rt_secao_id').value = secId;
      document.getElementById('rt_row_idx').value = rIdx;
      document.getElementById('rt_col_idx').value = cIdx;
      
      // Load current config
      const tipo = cell.rtTipo || 'livre'; // cadastrado, livre, sem
      document.getElementById('rt_tipo_modal').value = tipo;

      // Load selected text ID
      rtEditandoId = cell.rtId || null;

      // Load styles
      document.getElementById('rt_font_family').value = cell.fontFamily || '';
      document.getElementById('rt_font_size').value = cell.fontSize || '';
      document.getElementById('rt_font_color').value = cell.fontColor || '#000000';
      document.getElementById('rt_bold').checked = !!cell.bold;
      document.getElementById('rt_italic').checked = !!cell.italic;
      document.getElementById('rt_underline').checked = !!cell.underline;
      document.getElementById('rt_custom_font').checked = !!cell.customFont;
      
      // Align H
      const alignH = cell.alignH || '';
      const rH = document.querySelector(`input[name="rt_align_h"][value="${alignH}"]`);
      if (rH) rH.checked = true;
      else document.querySelector('input[name="rt_align_h"][value=""]').checked = true;
      
      document.getElementById('rt_uppercase').checked = !!cell.uppercase;
      document.getElementById('rt_single_line').checked = !!cell.singleLine;

      // Load texts
      // Primeiro carrega a biblioteca global, depois os textos do exame
      carregarBibliotecaTextos().then(() => {
          carregarTextosPadrao();
      });

      document.getElementById('modal_config_resultado_texto').showModal();
  }

  function carregarTextosPadrao() {
      const exameId = window.GLOBAL_EXAME_ID || document.querySelector('#formExame input[name="id"]')?.value || document.querySelector('input[name="exame_id"]')?.value || 0;
      
      if (exameId == 0) {
          // Se não tiver ID, nem tenta buscar (evita erro ou retorno vazio enganoso)
          rtTextosCache = [];
          renderizarTabelaTextos();
          return;
      }

      const secId = document.getElementById('rt_secao_id').value;
    const rIdx = document.getElementById('rt_row_idx').value;
    const cIdx = document.getElementById('rt_col_idx').value;

    fetch(`index.php?r=exames/get_textos_padrao&exame_id=${exameId}&secao=${secId}&linha=${rIdx}&coluna=${cIdx}`)
      .then(r => r.json())
      .then(res => {
          if (res.sucesso) {
              rtTextosCache = res.dados;
              renderizarTabelaTextos();
          } else {
              console.error('Erro ao carregar textos:', res.mensagem);
              // Não alertar usuário se for apenas vazio ou erro não crítico
          }
      })
      .catch(err => console.error('Erro fetch textos:', err));
  }

  function renderizarTabelaTextos() {
      const tbody = document.querySelector('#tabela_rt_textos tbody');
      tbody.innerHTML = '';
      
      const library = rtLibraryCache || [];

      rtTextosCache.forEach(t => {
          const tr = document.createElement('tr');
          
          tr.onclick = (e) => {
              if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT') selecionarTextoRt(t.id);
          };
          
          if (t.id == rtEditandoId) tr.classList.add('active');

          let selectHtml = '';
          // Revertido para input text conforme solicitado, mas readonly
          selectHtml = `<input type="text" class="input input-ghost input-xs w-full h-full rounded-none" 
                       value="${t.texto || ''}" 
                       onfocus="selecionarTextoRt('${t.id}')"
                       readonly
                       title="${t.texto || ''}"
                       placeholder="Texto">`;

          tr.innerHTML = `
            <td class="text-center" onclick="selecionarTextoRt('${t.id}')">
                <input type="radio" name="rt_sel" value="${t.id}" ${t.id == rtEditandoId ? 'checked' : ''}>
            </td>
            <td class="p-0">
                <input type="text" class="input input-ghost input-xs w-full h-full rounded-none" 
                       value="${t.codigo || ''}" 
                       onfocus="selecionarTextoRt('${t.id}')"
                       onblur="salvarTextoInline('${t.id}', 'codigo', formatarCodigoInline(this))"
                       placeholder="0000">
            </td>
            <td class="p-0">
                ${selectHtml}
            </td>
            <td class="text-center" onclick="selecionarTextoRt('${t.id}')">
                ${t.is_padrao == 1 ? '<i class="bi bi-check text-success font-bold" style="font-size: 1.2rem;"></i>' : ''}
            </td>
          `;
          tbody.appendChild(tr);
      });
  }

  window.onChangeRtSelect = function(id, selectEl) {
      const val = selectEl.value;
      if (!val || val === 'custom') return;

      const libItem = rtLibraryCache.find(x => x.id == val);
      if (libItem) {
          // Update text
          salvarTextoInline(id, 'texto', libItem.texto).then(() => {
              // Update code if available and different
              const row = selectEl.closest('tr');
              const codeInput = row.querySelector('td:nth-child(2) input');
              
              // Only update code if the library item has a code
              if (libItem.codigo) {
                   if (codeInput) codeInput.value = libItem.codigo;
                   salvarTextoInline(id, 'codigo', libItem.codigo);
              }
          });
      }
  }
  
  window.selecionarTextoRt = function(id) {
      rtEditandoId = id;
      const btns = ['btn_rt_excluir', 'btn_rt_padrao', 'btn_rt_sem_padrao'];
      btns.forEach(b => {
          const el = document.getElementById(b);
          if(el) el.disabled = false;
      });
      
      const rows = document.querySelectorAll('#tabela_rt_textos tbody tr');
      rows.forEach(r => {
          const radio = r.querySelector('input[type="radio"]');
          if (radio && radio.value == id) {
              r.classList.add('active');
              radio.checked = true;
          } else {
              r.classList.remove('active');
          }
      });
  }

  // Variável global para rastrear salvamentos pendentes
  window.pendingRtSaves = 0;
  window.rtIdMap = window.rtIdMap || {};

  window.salvarTextoInline = function(id, field, value) {
      const t = rtTextosCache.find(x => x.id == id);
      if (!t) return Promise.resolve({sucesso: false, mensagem: 'Texto não encontrado no cache'});
      
      const isTemp = typeof id === 'string' && id.startsWith('new_');

      // Se não for temporário e o valor não mudou, retorna sucesso imediato (skip)
      // Se for temporário, continua para forçar o salvamento (sincronização)
      if (!isTemp && t[field] === value && field !== 'is_padrao') {
          return Promise.resolve({sucesso: true, skipped: true});
      }

      t[field] = value;

      if (!t.texto) return Promise.resolve({sucesso: false, mensagem: 'Texto vazio'}); 

      const exameId = window.GLOBAL_EXAME_ID || document.querySelector('#formExame input[name="id"]')?.value || document.querySelector('input[name="exame_id"]')?.value || 0;
      
      const secId = document.getElementById('rt_secao_id').value;
      const rIdx = document.getElementById('rt_row_idx').value;
      const cIdx = document.getElementById('rt_col_idx').value;

      const formData = new FormData();
      formData.append('exame_id', exameId);
      formData.append('secao', secId);
      formData.append('linha', rIdx);
      formData.append('coluna', cIdx);
      // const isTemp... já calculado acima
      if (!isTemp) {
          formData.append('id', id);
      }
      formData.append('codigo', t.codigo || '');
      formData.append('texto', t.texto);
      formData.append('is_padrao', t.is_padrao || 0);
      
      window.pendingRtSaves++;
      const btnConfirmar = document.getElementById('btnSalvarConfigRT');
      if(btnConfirmar) {
          btnConfirmar.disabled = true;
          btnConfirmar.innerText = 'Salvando texto...';
      }

      return fetch('index.php?r=exames/save_texto_padrao', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(res => {
          if (res.sucesso) {
          if (res.id && isTemp) {
              // Store mapping for layout save
              window.rtIdMap = window.rtIdMap || {};
              window.rtIdMap[id] = res.id;
              console.log('Mapeado ID temporário:', id, '->', res.id);

              // ATUALIZAÇÃO PROATIVA: Atualizar todas as células que usam este ID temporário
              if (window.secoesLaudo) {
                  window.secoesLaudo.forEach(sec => {
                      sec.linhas.forEach(lin => {
                          lin.cells.forEach(c => {
                              if (c.rtId == id) {
                                  c.rtId = res.id;
                                  console.log('Célula atualizada proativamente:', id, '->', res.id);
                              }
                          });
                      });
                  });
              }

              if (rtEditandoId == id) rtEditandoId = res.id;
              t.id = res.id;
              renderizarTabelaTextos(); 
              carregarTextosPadrao();
          } else if (field === 'is_padrao') {
                  carregarTextosPadrao();
          }
          } else {
              console.error('Erro ao salvar texto:', res.mensagem);
              Swal.fire({
                  title: 'Erro',
                  text: 'Erro ao salvar texto: ' + (res.mensagem || 'Erro desconhecido'),
                  icon: 'error',
                  target: document.getElementById('modal_config_resultado_texto')
              });
          }
          return res;
      })
      .catch(err => {
          console.error(err);
          Swal.fire({
              title: 'Erro',
              text: 'Erro de conexão ao salvar texto: ' + err.message,
              icon: 'error',
              target: document.getElementById('modal_config_resultado_texto')
          });
          throw err;
      })
      .finally(() => {
          window.pendingRtSaves--;
          const btnConfirmar = document.getElementById('btnSalvarConfigRT');
          if (btnConfirmar) {
              btnConfirmar.disabled = false;
              btnConfirmar.innerText = 'Confirmar';
          }
      });
  }

  window.formatarCodigoInline = function(el) {
      let val = el.value.trim();
      if (val && /^\d+$/.test(val)) {
          el.value = val.padStart(4, '0');
      }
      return el.value;
  }

  document.getElementById('btn_rt_add')?.addEventListener('click', () => {
      // Carregar lista de resultados texto para seleção
      carregarBibliotecaTextos()
      .then(lista => {
          if(!lista || lista.length === 0) {
              Swal.fire({
                  title: 'Atenção',
                  text: 'Nenhum Texto de Resultado cadastrado. Cadastre em Cadastros > Resultados Texto.',
                  icon: 'warning',
                  target: document.getElementById('modal_config_resultado_texto')
              });
              return;
          }

          // Build initial options HTML
          let optionsHtml = '<option value="" disabled selected>Selecione...</option>';
          lista.forEach(item => {
              optionsHtml += `<option value="${item.id}">${escHTML(item.texto)}</option>`;
          });

          Swal.fire({
              title: 'Selecione um Texto',
              html: `
                <div style="margin-bottom: 10px;">
                    <input id="swal-rt-search" class="swal2-input" placeholder="Buscar..." style="margin: 0 0 10px 0; width: 100%;">
                </div>
                <select id="swal-rt-select" class="swal2-select" style="display:flex; width:100%; margin: 0;">
                  ${optionsHtml}
                </select>
              `,
              showCancelButton: true,
              confirmButtonText: 'Adicionar',
              cancelButtonText: 'Cancelar',
              target: document.getElementById('modal_config_resultado_texto'),
              didOpen: () => {
                  const searchInput = document.getElementById('swal-rt-search');
                  const select = document.getElementById('swal-rt-select');
                  
                  searchInput.addEventListener('input', () => {
                      const term = searchInput.value.toLowerCase();
                      
                      // Clear and rebuild
                      select.innerHTML = '<option value="" disabled selected>Selecione...</option>';
                      
                      const filtered = lista.filter(item => (item.texto || '').toLowerCase().includes(term));
                      filtered.forEach(item => {
                          const opt = document.createElement('option');
                          opt.value = item.id;
                          opt.textContent = item.texto;
                          select.appendChild(opt);
                      });
                  });
                  
                  searchInput.focus();
              },
              preConfirm: () => {
                  const select = document.getElementById('swal-rt-select');
                  const val = select.value;
                  if (!val) {
                      Swal.showValidationMessage('Selecione um item');
                  }
                  return val;
              }
          }).then((result) => {
              if (result.isConfirmed && result.value) {
                  const selectedId = result.value;
                  const selectedItem = lista.find(i => i.id == selectedId);
                  const selectedText = selectedItem ? selectedItem.texto : '';
                  const selectedCodigo = selectedItem ? (selectedItem.codigo || '') : '';
                  
                  const exameIdVal = window.GLOBAL_EXAME_ID || document.querySelector('#formExame input[name="id"]')?.value || document.querySelector('input[name="exame_id"]')?.value || 0;
                  
                  if (exameIdVal == 0) {
                      Swal.fire({
                          title: 'Atenção',
                          text: 'Salve o exame antes de adicionar textos de resultado.',
                          icon: 'warning',
                          target: document.getElementById('modal_config_resultado_texto')
                      });
                      return;
                  }

                  const tempId = 'new_' + Date.now();
                  rtTextosCache.push({
                      id: tempId,
                      codigo: selectedCodigo,
                      texto: selectedText,
                      is_padrao: 0,
                      exame_id: exameIdVal
                  });
                  
                  renderizarTabelaTextos();
                  selecionarTextoRt(tempId);
                  
                  // Salvar automaticamente
                  salvarTextoInline(tempId, 'texto', selectedText);
              }
          });
      })
      .catch(err => {
          console.error(err);
          Swal.fire({
              title: 'Erro',
              text: 'Erro ao buscar textos.',
              icon: 'error',
              target: document.getElementById('modal_config_resultado_texto')
          });
      });

      carregarOpcoesVariaveis();
  });

  document.getElementById('btn_rt_excluir')?.addEventListener('click', () => {
      if (!rtEditandoId) return;
      if (!confirm('Excluir este texto?')) return;
      
      if (typeof rtEditandoId === 'string' && rtEditandoId.startsWith('new_')) {
           rtTextosCache = rtTextosCache.filter(x => x.id != rtEditandoId);
           rtEditandoId = null;
           renderizarTabelaTextos();
           return;
      }
      
      const formData = new FormData();
      formData.append('id', rtEditandoId);
      
      fetch('index.php?r=exames/delete_texto_padrao', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(res => {
          if (res.sucesso) {
              rtEditandoId = null;
              carregarTextosPadrao();
          } else {
              alert('Erro: ' + res.mensagem);
          }
      });
  });
  
  document.getElementById('btn_rt_padrao')?.addEventListener('click', () => alterarPadraoRt(1));
  document.getElementById('btn_rt_sem_padrao')?.addEventListener('click', () => alterarPadraoRt(0));

  function alterarPadraoRt(val) {
      if (!rtEditandoId) return;
      const t = rtTextosCache.find(x => x.id == rtEditandoId);
      if (!t) return;
      
      t.is_padrao = val;
      if (t.texto) {
           salvarTextoInline(t.id, 'is_padrao', val);
      } else {
          renderizarTabelaTextos();
      }
  }

  document.getElementById('btnSalvarConfigRT')?.addEventListener('click', () => {
      const secId = parseInt(document.getElementById('rt_secao_id').value, 10);
      const rIdx = parseInt(document.getElementById('rt_row_idx').value, 10);
      const cIdx = parseInt(document.getElementById('rt_col_idx').value, 10);
      
      const secao = obterSecao(secId);
    if (secao && secao.linhas[rIdx] && secao.linhas[rIdx].cells[cIdx]) {
        const cell = secao.linhas[rIdx].cells[cIdx];
        cell.rtTipo = document.getElementById('rt_tipo_modal').value;
        cell.rtId = rtEditandoId;
        
        // Se houver um texto padrão selecionado, atualiza o valor da célula para exibição no editor
        if (cell.rtTipo === 'cadastrado' && rtTextosCache && rtTextosCache.length > 0) {
            const padrao = rtTextosCache.find(x => x.is_padrao == 1);
            if (padrao) {
                cell.valor = padrao.texto;
            } else {
                // Se não houver padrão, talvez limpar ou deixar o primeiro?
                // O usuário pediu "texto marcado como Padrão". Se não tiver padrão, não exibe nada específico?
                // Vou manter o valor vazio se não tiver padrão, ou o valor anterior.
                // Mas se ele acabou de configurar, faz sentido refletir o estado atual.
                cell.valor = ''; 
            }
        } else if (cell.rtTipo === 'livre') {
            // Se for livre, mantemos o valor que estava ou limpamos?
            // Geralmente livre começa vazio ou com o que o usuário digitou.
            // Não vamos forçar limpeza aqui para não perder dados se ele só abriu a modal para ver.
        }

        // Save styles
        cell.fontFamily = document.getElementById('rt_font_family').value || '';
        const fs = parseInt(document.getElementById('rt_font_size').value, 10);
        cell.fontSize = isNaN(fs) ? null : fs;
        cell.fontColor = document.getElementById('rt_font_color').value || '';
        
        cell.bold = !!document.getElementById('rt_bold').checked;
        cell.italic = !!document.getElementById('rt_italic').checked;
        cell.underline = !!document.getElementById('rt_underline').checked;
        cell.customFont = !!document.getElementById('rt_custom_font').checked;
        
        const rH = document.querySelector('input[name="rt_align_h"]:checked');
        cell.alignH = rH ? rH.value : '';

        const rV = document.querySelector('input[name="rt_align_v"]:checked');
        cell.alignV = rV ? rV.value : '';

        cell.uppercase = !!document.getElementById('rt_uppercase').checked;
        cell.singleLine = !!document.getElementById('rt_single_line').checked;
    }
    document.getElementById('modal_config_resultado_texto').close();
      renderizarEditorLaudo();
  });

  function abrirModalConfigLinha(secId, rIdx) {
      const secao = obterSecao(secId);
      if (!secao || !secao.linhas[rIdx]) return;
      const linha = secao.linhas[rIdx];
      
      document.getElementById('lin_secao_id').value = secId;
      document.getElementById('lin_row_idx').value = rIdx;
      
      document.getElementById('lin_altura').value = linha.altura || '';
      document.getElementById('lin_quebra_antes').checked = !!linha.quebraAntes;
      document.getElementById('lin_quebra_depois').checked = !!linha.quebraDepois;
      document.getElementById('lin_ocultar_vazia').checked = !!linha.ocultarVazia;
      
      document.getElementById('modal_config_linha').showModal();
  }


  // ==========================
  // INICIALIZAÇÃO E EVENTOS
  // ==========================
  // Carregar dados iniciais (vindo do PHP via layout_fetch ou input hidden)
  // No seu MVC atual, parece que ele carrega via AJAX ou imprime no HTML.
  // Vamos assumir que existe uma função ou chamada inicial.
  
  // Se o botao salvar layout server existir:
  const btnSalvar = document.getElementById('btnSalvarLayoutServer');
  if (btnSalvar) {
    btnSalvar.addEventListener('click', async (e) => {
      e.preventDefault();
      
      if (window.pendingRtSaves && window.pendingRtSaves > 0) {
          Swal.fire({
              title: 'Aguarde',
              text: 'Ainda há textos sendo salvos. Aguarde alguns segundos e tente novamente.',
              icon: 'info'
          });
          return;
      }

      salvarValoresDigitadosDaTela();
      
      // Serializar
      // Precisamos transformar o array de objetos em algo plano para o banco?
      // O backend espera rows, colunas_cfg, celulas_cfg? 
      // Ou um JSON único?
      // O seu form.php tem: layout_json, colunas_cfg_json, celulas_cfg_json.
      // Vou preencher layout_json com tudo e deixar o backend se virar, 
      // OU converter para o formato legado.
      
      // Vamos assumir que o backend novo (ExamesController) espera JSON estruturado ou plano.
      // Se você refatorou o controller, deve saber.
      // Mas para compatibilidade com a estrutura de tabelas (exame_layout, exame_layout_colunas, etc),
      // precisamos converter de volta para "rows".
      
      // CONVERSÃO REVERSA (secoesLaudo -> rows/cols/cells)
      // ... isso pode ser complexo. Se o controller aceitar JSON puro num campo blob seria melhor.
      // Mas como vi 'layout_fetch' retornando rows, ele usa tabelas relacionais.
      
      // Vamos tentar montar os JSONs que os hiddens esperam.
      
      const rows = [];
      const colCfgs = [];
      const cellCfgs = [];

      secoesLaudo.forEach((sec, sIdx) => {
        // sec.id é visual. Vamos usar sIdx+1 como ordem ou ID da seção? 
        // Melhor usar sec.id se for numérico persistente, ou gerar sequencial.
        const secId = sec.id; 
        
        // Colunas
        sec.colunas.forEach((col, cIdx) => {
           // colCfgs
           // { secao: secId, coluna: cIdx+1, tipo: ..., largura: ... }
           colCfgs.push({
             secao: secId,
             coluna: cIdx+1,
             tipo: col.tipo,
             titulo: col.titulo,
             largura: col.largura,
             single_line: col.singleLine?1:0,
             custom_font: col.customFont?1:0,
             font_family: col.fontFamily,
             font_size: col.fontSize,
             font_color: col.fontColor,
             bold: col.bold?1:0,
             italic: col.italic?1:0,
             underline: col.underline?1:0,
             align_h: col.alignH,
             align_v: col.alignV
           });
        });

        // Linhas
        sec.linhas.forEach((lin, rIdx) => {
           // cria row entry
           const rowData = { 
               secao: secId, 
               ordem: rIdx+1,
               altura: lin.altura,
               quebra_antes: lin.quebraAntes?1:0,
               quebra_depois: lin.quebraDepois?1:0,
               ocultar_vazia: lin.ocultarVazia?1:0
           };
           lin.cells.forEach((cell, cIdx) => {
              rowData['col'+(cIdx+1)] = cell.valor;
              
              // cell config se tiver algo diferente do padrão
              // mas salva tudo pra garantir
              cellCfgs.push({
                secao: secId,
                linha: rIdx+1,
                coluna: cIdx+1,
                valor_fixo: '', // ja ta no rowData? ou rowData é o valor padrao?
                // O controller salva layout rows com o valor.
                // Celula cfg é extra.
                
                num_tipo: cell.numTipo,
                num_pos: cell.numPos,
                num_dec: cell.numDec,
                num_formula: cell.numFormula,
                
                rt_tipo: cell.rtTipo,
                rt_id: (window.rtIdMap && window.rtIdMap[cell.rtId]) ? window.rtIdMap[cell.rtId] : cell.rtId,
                
                var_alias: cell.varAlias,
                
                uppercase: cell.uppercase?1:0,
                single_line: cell.singleLine?1:0,
                custom_font: cell.customFont?1:0,
                font_family: cell.fontFamily,
                font_size: cell.fontSize,
                font_color: cell.fontColor,
                bold: cell.bold?1:0,
                italic: cell.italic?1:0,
                underline: cell.underline?1:0,
                align_h: cell.alignH,
                align_v: cell.alignV
              });
           });
           rows.push(rowData);
        });
      });

      // Check for unsaved RT IDs and Try to Recover
      const invalidRts = cellCfgs.filter(c => c.rt_tipo === 'cadastrado' && typeof c.rt_id === 'string' && c.rt_id.startsWith('new_'));
      if (invalidRts.length > 0) {
          console.log('Detectados IDs temporários:', invalidRts);
          
          // Tenta recuperar do mapa
          let stillInvalid = [];
          
          for (let i = 0; i < invalidRts.length; i++) {
              const inv = invalidRts[i];
              if (window.rtIdMap && window.rtIdMap[inv.rt_id]) {
                  inv.rt_id = window.rtIdMap[inv.rt_id];
                  // Também atualiza o secoesLaudo para persistir na memória
                  // (embora cellCfgs seja reconstruído a cada save, é bom manter sync)
              } else {
                  stillInvalid.push(inv);
              }
          }

          if (stillInvalid.length > 0) {
              // Tentar forçar o salvamento dos itens perdidos
              // Procura no rtTextosCache
              const promises = [];
              const recoveringIds = [];

              stillInvalid.forEach(inv => {
                  const t = rtTextosCache.find(x => x.id == inv.rt_id);
                  if (t) {
                      if (recoveringIds.includes(t.id)) return; // Já está na fila
                      recoveringIds.push(t.id);
                      
                      console.log('Tentando recuperar texto não salvo:', t);
                      promises.push(
                          salvarTextoInline(t.id, 'texto', t.texto)
                          .then(res => {
                              if (res.sucesso && res.id) {
                                  inv.rt_id = res.id; // Atualiza no array local
                                  return true;
                              }
                              return false;
                          })
                          .catch(() => false)
                      );
                  }
              });

              if (promises.length > 0) {
                  const originalBtnText = btnSalvar.innerHTML;
                  btnSalvar.innerHTML = 'Sincronizando textos...';
                  
                  await Promise.all(promises);
                  
                  btnSalvar.innerHTML = originalBtnText;

                  // Re-verifica
                  const finalCheck = cellCfgs.find(c => c.rt_tipo === 'cadastrado' && typeof c.rt_id === 'string' && c.rt_id.startsWith('new_'));
                  if (finalCheck) {
                       // Tenta recuperar do mapa uma última vez (caso o salvarTextoInline tenha atualizado o mapa mas não a referência local inv.rt_id - embora tenhamos feito inv.rt_id = res.id)
                       if (window.rtIdMap && window.rtIdMap[finalCheck.rt_id]) {
                           finalCheck.rt_id = window.rtIdMap[finalCheck.rt_id];
                       } else {
                           Swal.fire({
                               title: 'Erro de Integridade',
                               text: 'Não foi possível sincronizar alguns textos (ID: ' + finalCheck.rt_id + '). Verifique se o texto foi salvo corretamente na lista.',
                               icon: 'error'
                           });
                           return;
                       }
                  }
              } else {
                  // Estão no layout mas não no cache??
                   Swal.fire({
                       title: 'Erro de Integridade',
                       text: 'Existem referências a textos que não foram encontrados (ID: ' + stillInvalid[0].rt_id + '). Remova e adicione novamente.',
                       icon: 'error'
                   });
                   return;
              }
          }
      }

      document.getElementById('layout_json').value = JSON.stringify(rows); // rows planas para exame_layout
      document.getElementById('colunas_cfg_json').value = JSON.stringify(colCfgs);
      document.getElementById('celulas_cfg_json').value = JSON.stringify(cellCfgs);
      
      // Submit via Fetch (AJAX) instead of Form Submit to avoid page reload/redirect issues
      const form = document.getElementById('formLayoutLaudo');
      const formData = new FormData(form);

      // Use btnSalvar from closure instead of e.target to be safe
      const originalText = btnSalvar.innerHTML;
      btnSalvar.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Salvando...';
      btnSalvar.disabled = true;

      fetch(form.action, {
        method: 'POST',
        body: formData
      })
      .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Server response:', text);
            throw new Error('Resposta inválida do servidor: ' + text.substring(0, 100));
        }
      })
      .then(data => {
        if (data.sucesso) {
            alert('Layout salvo com sucesso!');
        } else {
            alert('Erro ao salvar: ' + (data.mensagem || 'Erro desconhecido'));
        }
      })
      .catch(err => {
        console.error(err);
        alert('Erro ao salvar layout: ' + err.message);
      })
      .finally(() => {
        btnSalvar.innerHTML = originalText;
        btnSalvar.disabled = false;
      });
    });
  }
  
  // Botões da toolbar
  document.getElementById('btnAddSecaoLaudo')?.addEventListener('click', __addSecaoLaudo);
  
  const mapActions = {
    'btnExcluirSecaoLaudo': () => {
       if(secaoAtivaId===null) return;
       if(!confirm('Excluir seção?')) return;
       secoesLaudo = secoesLaudo.filter(s => s.id !== secaoAtivaId);
       secoesLaudo.forEach((s, i) => s.id = i + 1);
       secaoAtivaId = secoesLaudo.length ? secoesLaudo[0].id : null;
       renderizarEditorLaudo();
    },
    'btnAddLinhaLaudo': () => {
       if (secaoAtivaId === null) { alert('Selecione uma seção para adicionar linhas.'); return; }
       salvarValoresDigitadosDaTela();
       const secao = obterSecao(secaoAtivaId);
       if (!secao) return;
       secao.linhas.push({ cells: secao.colunas.map(() => ({ valor:'' })) });
       renderizarEditorLaudo();
    },
    'btnAddColunaLaudo': () => {
       if (secaoAtivaId === null) { alert('Selecione uma seção para adicionar colunas.'); return; }
       salvarValoresDigitadosDaTela();
       const s2 = obterSecao(secaoAtivaId);
       if (!s2) return;
       const idx = s2.colunas.length;
       s2.colunas.push({ id:'c'+(idx+1), titulo: 'Descrição', tipo:'', largura:null });
       s2.linhas.forEach(l => l.cells.push({ valor:'' }));
       aplicarLargurasSecao(s2);
       renderizarEditorLaudo();
    },
    'btnMoverSecaoUp': () => {
        if (secaoAtivaId === null) return;
        const iUp = secoesLaudo.findIndex(s => s.id === secaoAtivaId);
        if (iUp > 0) {
          [secoesLaudo[iUp-1], secoesLaudo[iUp]] = [secoesLaudo[iUp], secoesLaudo[iUp-1]];
          secoesLaudo.forEach((s,i)=> s.id = i+1);
          secaoAtivaId = secoesLaudo[iUp-1].id;
          renderizarEditorLaudo();
        }
    },
    'btnMoverSecaoDown': () => {
        if (secaoAtivaId === null) return;
        const iDown = secoesLaudo.findIndex(s => s.id === secaoAtivaId);
        if (iDown >= 0 && iDown < secoesLaudo.length - 1) {
          [secoesLaudo[iDown+1], secoesLaudo[iDown]] = [secoesLaudo[iDown], secoesLaudo[iDown+1]];
          secoesLaudo.forEach((s,i)=> s.id = i+1);
          secaoAtivaId = secoesLaudo[iDown+1].id;
          renderizarEditorLaudo();
        }
    },
    'btnCopiarConfig': () => {
        salvarValoresDigitadosDaTela();
        clipboardFull = JSON.parse(JSON.stringify(secoesLaudo));
        alert('Configuração copiada.');
    },
    'btnCopiarSecao': () => {
        if (secaoAtivaId === null) return;
        const sCopy = obterSecao(secaoAtivaId);
        clipboardSecao = sCopy ? JSON.parse(JSON.stringify(sCopy)) : null;
        alert('Seção copiada.');
    },
    'btnColarConfig': () => {
        salvarValoresDigitadosDaTela();
        if (clipboardSecao) {
          const idxIns = secoesLaudo.findIndex(s => s.id === secaoAtivaId);
          const nova = JSON.parse(JSON.stringify(clipboardSecao));
          secoesLaudo.splice(idxIns + 1, 0, nova);
          secoesLaudo.forEach((s,i)=> s.id = i+1);
          renderizarEditorLaudo();
        } else if (clipboardFull) {
          secoesLaudo = JSON.parse(JSON.stringify(clipboardFull));
          secoesLaudo.forEach((s,i)=> s.id = i+1);
          secaoAtivaId = secoesLaudo.length ? secoesLaudo[0].id : null;
          renderizarEditorLaudo();
        } else alert('Nada para colar.');
    },
    'btnImportarConfig': () => { importarConfigDeArquivo(); },
    'btnCarregarConfig': () => { exportarConfigLocal(); },
    'btnVisualizarLaudo': () => {
        carregarOpcoesVariaveis().then(() => {
            document.getElementById('previewLaudoContent').innerHTML = gerarHTMLPreview({ borderless:true, hideHeaders:true, hideSectionNumbers:true });
            
            // Inicializa componentes interativos do preview se existirem
            if (typeof initPreviewRT === 'function') initPreviewRT();
            if (typeof initPreviewNUM === 'function') initPreviewNUM();
            
            const modalEl = document.getElementById('modalPreviewLaudo');
            if (modalEl) {
                // Verifica se é um dialog nativo (DaisyUI/HTML5)
                if (typeof modalEl.showModal === 'function') {
                    if (!modalEl.open) modalEl.showModal();
                } else {
                    // Fallback para Bootstrap (se o HTML mudasse)
                    let modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (!modalInstance) {
                        modalInstance = new bootstrap.Modal(modalEl);
                    }
                    modalInstance.show();
                }
            }
        });
    },
    'btnVisualizarImpressao': () => {
        const w = window.open('', '_blank'); if (!w) return;
        const html = gerarHTMLPreview(true);
        const style = `
        <style>
          @page { size: A4; margin: 12mm; }
          body { padding: 16px; }
          .a4-page { width: 210mm; min-height: 297mm; margin: 0 auto; background:#fff; padding: 12mm; font-size: 11pt; }
          .table th, .table td { border: none !important; }
          .tabela-laudo { table-layout: fixed; border-collapse: collapse; }
          .tabela-laudo td { padding:0; line-height:1; vertical-align: top; }
          .pre-ln { white-space: pre-wrap; margin:0; }
          @media print { body { padding:0; } .a4-page { width:auto; min-height:auto; padding:0; } }
        </style>`;
        w.document.write(`<html><head><title>Impressão</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">${style}</head><body>${html}</body></html>`);
        w.document.close();
    }
  };

  Object.keys(mapActions).forEach(id => {
    const el = document.getElementById(id);
    if(el) el.addEventListener('click', mapActions[id]);
  });

  // ==========================
  // ADD SEÇÃO
  // ==========================
  function __addSecaoLaudo() {
    salvarValoresDigitadosDaTela();
    let maxId = 0;
    secoesLaudo.forEach(s => { if (s.id > maxId) maxId = s.id; });
    const novaId = (maxId || 0) + 1;

    const novaSecao = {
      id: novaId,
      colunas: [{ id:'c1', titulo:'Descrição', tipo:'', largura:null }],
      linhas: [{ cells: [{ valor:'' }] }]
    };

    secoesLaudo.push(novaSecao);
    secaoAtivaId = novaId;
    renderizarEditorLaudo();
  }

  // ==========================
  // SALVAR CONFIG COLUNA
  // ==========================
  const btnSalvarConfigColuna = document.getElementById('btnSalvarConfigColuna');
  if (btnSalvarConfigColuna) {
    btnSalvarConfigColuna.addEventListener('click', () => {
      if (editColSecaoId == null || editColIndex == null) return;
      const secao = obterSecao(editColSecaoId);
      if (!secao || !secao.colunas?.[editColIndex]) return;

      const c = secao.colunas[editColIndex];
      const tipo = document.getElementById('cfg_tipo').value || '';
      c.tipo = tipo;
      
      const titulo = document.getElementById('cfg_titulo').value;
      c.titulo = titulo || (tipo ? labelPorTipo(tipo) : '');

      const largura = parseFloat(document.getElementById('cfg_largura').value);
      if (!isNaN(largura)) c.largura = Math.max(5, Math.min(95, largura));

      c.singleLine = !!document.getElementById('cfg_single_line').checked;
      c.customFont = !!document.getElementById('cfg_custom_font').checked;
      c.fontFamily = document.getElementById('cfg_font_family').value || '';
      const fs = parseInt(document.getElementById('cfg_font_size').value, 10);
      c.fontSize = isNaN(fs) ? null : fs;
      c.fontColor = document.getElementById('cfg_font_color').value || '';
      c.bold = !!document.getElementById('cfg_bold').checked;
      c.italic = !!document.getElementById('cfg_italic').checked;
      c.underline = !!document.getElementById('cfg_underline').checked;
      
      const rH = document.querySelector('input[name="align_h"]:checked');
      c.alignH = rH ? rH.value : '';
      const rV = document.querySelector('input[name="align_v"]:checked');
      c.alignV = rV ? rV.value : '';

      // se virar coluna dinâmica, apaga valores fixos dessa coluna
      const dynSet = new Set(['material_biologico','exame_nome','exame_mnemonico','exame_metodo','exame_prazo_local']);
      if (tipo && dynSet.has(tipo)) {
        secao.linhas.forEach(l => { if (l.cells?.[editColIndex]) l.cells[editColIndex].valor = ''; });
      }

      renderizarEditorLaudo();
      document.getElementById('modal_config_coluna').close();
    });
  }

  // ==========================
  // SALVAR CONFIG CÉLULA
  // ==========================
  const btnSalvarConfigCelula = document.getElementById('btnSalvarConfigCelula');
  if (btnSalvarConfigCelula) {
    btnSalvarConfigCelula.addEventListener('click', () => {
      const secId = parseInt(document.getElementById('cel_secao_id').value, 10);
      const rIdx = parseInt(document.getElementById('cel_row_idx').value, 10);
      const cIdx = parseInt(document.getElementById('cel_col_idx').value, 10);

      const secao = obterSecao(secId);
      if (!secao || !secao.linhas?.[rIdx]) return;
      if (!secao.linhas[rIdx].cells[cIdx]) secao.linhas[rIdx].cells[cIdx] = { valor:'' };

      const cell = secao.linhas[rIdx].cells[cIdx];
      
      // Valor texto
      cell.valor = document.getElementById('cel_valor').value || '';
      
      // Numérico
      cell.numTipo = document.getElementById('cel_num_tipo').value || '';
      if (cell.numTipo === 'numero') {
         const p = parseInt(document.getElementById('cel_num_pos').value, 10);
         const d = parseInt(document.getElementById('cel_num_dec').value, 10);
         cell.numPos = isNaN(p) ? null : p;
         cell.numDec = isNaN(d) ? null : d;
         cell.numFormula = '';
      } else if (cell.numTipo === 'calculo') {
         cell.numFormula = document.getElementById('cel_num_formula').value || '';
         cell.numPos = null; 
         cell.numDec = null;
      } else {
         cell.numPos = null; 
         cell.numDec = null; 
         cell.numFormula = '';
      }
      
      // Alias
      cell.varAlias = document.getElementById('cel_var_alias').value || '';
      
      // Estilos
      cell.fontFamily = document.getElementById('cel_font_family').value || '';
      const fs = parseInt(document.getElementById('cel_font_size').value, 10);
      cell.fontSize = isNaN(fs) ? null : fs;
      cell.fontColor = document.getElementById('cel_font_color').value || '';
      
      cell.bold = !!document.getElementById('cel_bold').checked;
      cell.italic = !!document.getElementById('cel_italic').checked;
      cell.underline = !!document.getElementById('cel_underline').checked;
      
      const rH = document.querySelector('input[name="cel_align_h"]:checked');
      cell.alignH = rH ? rH.value : '';
      const rV = document.querySelector('input[name="cel_align_v"]:checked');
      cell.alignV = rV ? rV.value : '';
      
      cell.uppercase = !!document.getElementById('cel_uppercase').checked;
      cell.singleLine = !!document.getElementById('cel_single_line').checked;
      cell.customFont = !!document.getElementById('cel_custom_font').checked;
      
      renderizarEditorLaudo();
      document.getElementById('modal_config_celula').close();
    });
  }

  // ==========================
  // SALVAR CONFIG LINHA
  // ==========================
  const btnSalvarConfigLinha = document.getElementById('btnSalvarConfigLinha');
  if (btnSalvarConfigLinha) {
      btnSalvarConfigLinha.addEventListener('click', () => {
          const secId = parseInt(document.getElementById('lin_secao_id').value, 10);
          const rIdx = parseInt(document.getElementById('lin_row_idx').value, 10);
          
          const secao = obterSecao(secId);
          if (!secao || !secao.linhas?.[rIdx]) return;
          const linha = secao.linhas[rIdx];
          
          const alt = parseInt(document.getElementById('lin_altura').value, 10);
          linha.altura = isNaN(alt) ? null : alt;
          
          linha.quebraAntes = !!document.getElementById('lin_quebra_antes').checked;
          linha.quebraDepois = !!document.getElementById('lin_quebra_depois').checked;
          linha.ocultarVazia = !!document.getElementById('lin_ocultar_vazia').checked;
          
          renderizarEditorLaudo();
          document.getElementById('modal_config_linha').close();
      });
  }

  // ==========================
  // IMPORT/EXPORT LOCAL
  // ==========================
  function exportarConfigLocal() {
    salvarValoresDigitadosDaTela();
    const payload = { secoes: secoesLaudo };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type:'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'laudo_config.json';
    document.body.appendChild(a);
    a.click();
    URL.revokeObjectURL(a.href);
    document.body.removeChild(a);
  }

  function importarConfigDeArquivo() {
    const input = document.getElementById('fileConfigLaudo');
    if (!input) return;
    input.onchange = async function (e) {
      const f = e.target.files && e.target.files[0];
      if (!f) return;

      const txt = await f.text();
      let j = null;
      try { j = JSON.parse(String(txt).trim()); } catch {}
      if (!j) { alert('Arquivo inválido'); input.value = ''; return; }

      let novo = null;
      if (Array.isArray(j)) novo = j;
      else if (j.secoes) novo = j.secoes;
      else { alert('Arquivo inválido'); input.value = ''; return; }

      secoesLaudo = JSON.parse(JSON.stringify(novo));
      secoesLaudo.forEach((s,i)=> s.id = i+1);
      secaoAtivaId = secoesLaudo.length ? secoesLaudo[0].id : null;
      renderizarEditorLaudo();
      input.value = '';
    };
    input.click();
  }

  // ==========================
  // PREVIEW + NUM/RT (igual seu padrão)
  // ==========================
  function escHTML(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }
  function escNoBreakHyphen(s) {
    const e = escHTML(s);
    return e.replace(/(\S)-(\S)/g, '$1&#8209;$2');
  }

  function gerarHTMLPreview(opts) {
    const isPrint = opts === true || (opts && opts.mode === 'print');
    const cfg = {
      borderless: !!(isPrint || (opts && opts.borderless)),
      hideHeaders: !!(isPrint || (opts && opts.hideHeaders)),
      hideSectionNumbers: !!(isPrint || (opts && opts.hideSectionNumbers))
    };

    let inner = '';
    secoesLaudo.forEach(secao => {
      inner += `<div style="margin-bottom:8px">`;
      const tableClass = cfg.borderless ? 'table table-sm tabela-laudo' : 'table table-sm table-bordered tabela-laudo';
      inner += `<table class="${tableClass}" style="width:100%">`;

      inner += `<colgroup>`;
      if (!cfg.hideSectionNumbers) inner += `<col style="width:38px">`;
      const colsCount = secao.colunas.length;
      secao.colunas.forEach(col => {
        const w = parseFloat(col.largura) || (100 / colsCount);
        inner += `<col style="width:${w}%">`;
      });
      inner += `</colgroup>`;

      if (!cfg.hideHeaders) {
        inner += `<thead><tr>`;
        if (!cfg.hideSectionNumbers) inner += `<th style="width:38px"></th>`;
        secao.colunas.forEach(col => {
          const w = parseFloat(col.largura) || (100 / secao.colunas.length);
          const lbl = col.tipo ? labelPorTipo(col.tipo) : (col.titulo || '');
          let st = `width:${w}%`;
          if (col.alignH) st += `;text-align:${col.alignH}`;
          if (col.alignV) st += `;vertical-align:${col.alignV}`;
          if (col.bold) st += `;font-weight:bold`;
          if (col.italic) st += `;font-style:italic`;
          if (col.underline) st += `;text-decoration:underline`;
          if (col.customFont) {
            if (col.fontFamily) st += `;font-family:${col.fontFamily}`;
            if (col.fontSize) st += `;font-size:${col.fontSize}px`;
            if (col.fontColor) st += `;color:${col.fontColor}`;
          }
          inner += `<th style="${st}">${escHTML(lbl)}</th>`;
        });
        inner += `</tr></thead>`;
      }

      inner += `<tbody>`;
      secao.linhas.forEach((linha, rIdx) => {
        inner += `<tr>`;
        if (!cfg.hideSectionNumbers) inner += `<td></td>`;

        // Check if any column is 'observacao_resultado' and calculate colspan
        let skipNext = 0;

        linha.cells.forEach((cell, idx) => {
          if (skipNext > 0) {
              skipNext--;
              return;
          }

          const col = secao.colunas[idx];
          const dynSetPrev = new Set(['material_biologico','exame_nome','exame_mnemonico','exame_metodo','exame_prazo_local']);
          const vt = (col && col.tipo && dynSetPrev.has(col.tipo)) ? valorPorTipo(col.tipo) : null;
          let v = vt !== null ? vt : (cell.valor || '');
          v = String(v || '').replace(/\r/g,'\n');
          if (cell.uppercase) v = v.toUpperCase();

          let st = '';
          const singleLine = !!((cell.singleLine) || (col && col.singleLine));
          const alignH = (cell.alignH) || (col && col.alignH) || '';
          const alignV = (cell.alignV) || (col && col.alignV) || '';
          const bold = (cell.bold != null) ? cell.bold : !!(col && col.bold);
          const italic = (cell.italic != null) ? cell.italic : !!(col && col.italic);
          const underline = (cell.underline != null) ? cell.underline : !!(col && col.underline);
          const fontFamily = (cell.fontFamily) || (col && col.fontFamily) || '';
          const fontSize = (cell.fontSize != null) ? cell.fontSize : ((col && col.fontSize != null) ? col.fontSize : null);
          const fontColor = (cell.fontColor) || (col && col.fontColor) || '';

          if (singleLine) st += 'white-space:nowrap;overflow:hidden;';
          if (alignH) st += `text-align:${alignH};`;
          if (alignV) st += `vertical-align:${alignV};`;
          if (bold) st += 'font-weight:bold;';
          if (italic) st += 'font-style:italic;';
          if (underline) st += 'text-decoration:underline;';
          if (fontFamily) st += `font-family:${fontFamily};`;
          if (fontSize != null) st += `font-size:${fontSize}px;`;
          if (fontColor) st += `color:${fontColor};`;

          // widgets preview (RT e NUM) quando não imprimir
          let content = '';
          const isTextual = !!(col && (col.tipo === 'resultado_texto' || col.tipo === 'resultado_texto_formatado' || col.tipo === 'observacao_resultado'));
          const modo = cell.rtTipo ? cell.rtTipo : 'livre';
          const isNum = !!(col && col.tipo === 'resultado_num');

          let colspanAttr = '';
          // Special handling for 'observacao_resultado' to span remaining columns
          if (col && col.tipo === 'observacao_resultado') {
              // Calculate remaining columns
              const remaining = secao.colunas.length - 1 - idx;
              if (remaining > 0) {
                  colspanAttr = ` colspan="${remaining + 1}"`;
                  skipNext = remaining;
              } else {
                  // Even if it's the last column, try to force it to expand if table has space?
                  // No specific action for now, but ensure textarea is full width
              }
          }

          if (!isPrint && isTextual) {
            // Key must match how it is saved in DB
            // Try 0-based (standard)
            let key = `${secao.id}_${rIdx}_${idx}`;
            let opts = (window.examOptionsCache && window.examOptionsCache[key]) ? window.examOptionsCache[key] : [];

            // Fallback: Try 1-based indices (legacy support or mismatch)
            if (!opts || opts.length === 0) {
                const key1 = `${secao.id}_${rIdx+1}_${idx+1}`;
                if (window.examOptionsCache && window.examOptionsCache[key1]) {
                    opts = window.examOptionsCache[key1];
                }
            }

            if (opts.length > 0 && modo !== 'livre') {
                  let optsHtml = '<option value=""></option>';
                  opts.forEach(o => {
                      const sel = (cell.valor === o.texto) ? 'selected' : '';
                      optsHtml += `<option value="${escHTML(o.texto)}" ${sel}>${escHTML(o.texto)}</option>`;
                  });
                  // Adiciona classe populated-by-layout para evitar que initPreviewRT sobrescreva
                  content = `<select class="form-select form-select-sm preview-rt-select populated-by-layout" style="min-width:100px;">${optsHtml}</select>`;
            } else if (modo === 'cadastrado') {
              const rid = cell.rtId ? String(cell.rtId) : '';
              // Debug: Mostra se o cache estava vazio ou a chave falhou
              const debugKey = `${secao.id}_${rIdx}_${idx}`;
              const hasCache = (window.examOptionsCache && Object.keys(window.examOptionsCache).length > 0) ? 'Sim' : 'Não';
              
              content = `<select class="form-select form-select-sm preview-rt-select" data-rt-id="${escHTML(rid)}" data-debug-key="${debugKey}" data-has-cache="${hasCache}" title="Sem opções (Key: ${debugKey}, Cache: ${hasCache})"></select>`;
            } else if (modo === 'livre') {
              if (col && col.tipo === 'observacao_resultado') {
                  // Force full width and auto-grow
                  content = `<textarea class="form-control form-control-sm preview-rt-input auto-grow" rows="3" style="width:100%; min-width:100%;">${escHTML(v)}</textarea>`;
              } else {
                  content = `<input type="text" class="form-control form-control-sm preview-rt-input" value="${escHTML(v)}">`;
              }
            } else content = '';
          } else if (!isPrint && isNum) {
            const nt = cell.numTipo ? cell.numTipo : 'numero';
            if (nt === 'numero') {
              const pos = (cell.numPos != null) ? parseInt(cell.numPos,10) : 1;
              const dec = (cell.numDec != null) ? parseInt(cell.numDec,10) : 2;
              const p = isNaN(pos) ? 1 : Math.max(0, Math.min(12, pos));
              const d = isNaN(dec) ? 2 : Math.max(0, Math.min(12, dec));
              const key = `${secao.id}|${rIdx}|${idx+1}`;
              const alias = cell.varAlias ? String(cell.varAlias) : '';
              content = `<input type="text" class="form-control form-control-sm preview-num-input" data-pos="${p}" data-dec="${d}" data-key="${key}" data-alias="${escHTML(alias)}" value="">`;
            } else if (nt === 'calculo') {
              const f = cell.numFormula ? String(cell.numFormula) : '';
              const d = (cell.numDec != null) ? parseInt(cell.numDec,10) : 2;
              const key = `${secao.id}|${rIdx}|${idx+1}`;
              content = `<span class="preview-num-calc" data-formula="${escHTML(f)}" data-dec="${isNaN(d)?2:d}" data-key="${key}"></span>`;
            } else content = '';
          } else {
            content = singleLine
              ? escNoBreakHyphen(v)
              : v.split(/\n/).map(part => `<div class="pre-ln">${escNoBreakHyphen(part)}</div>`).join('');
          }

          inner += `<td style="${st}"${colspanAttr}>${content}</td>`;
        });

        inner += `</tr>`;
      });

      inner += `</tbody></table></div>`;
    });

    return `<div class="a4-page">${inner}</div>`;
  }

  const btnImprimirPreview = document.getElementById('btnImprimirPreview');
  if (btnImprimirPreview) {
    btnImprimirPreview.addEventListener('click', () => {
      const w = window.open('', '_blank');
      if (!w) return;
      const html = document.getElementById('previewLaudoContent').innerHTML;
      const style = `
        <style>
          @page { size: A4; margin: 12mm; }
          body { padding: 16px; }
          .a4-page { width: 210mm; min-height: 297mm; margin: 0 auto; background:#fff; padding: 12mm; font-size: 11pt; }
          .table th, .table td { border: none !important; }
          .tabela-laudo { table-layout: fixed; border-collapse: collapse; }
          .tabela-laudo td { padding:0; line-height:1; vertical-align: top; }
          .pre-ln { white-space: pre-wrap; margin:0; }
          @media print { body { padding:0; } .a4-page { width:auto; min-height:auto; padding:0; } }
        </style>`;
      w.document.write(`<html><head><title>Impressão</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">${style}</head><body>${html}</body></html>`);
      w.document.close();
    });
  }
  
  // ==========================
  // CARREGAR DADOS INICIAIS
  // ==========================
  window.initLayoutEditor = function(resp) {
     console.log('initLayoutEditor chamado', resp);
     if (resp && resp.sucesso) {
         const sectionsMap = new Map();
         const rows = resp.rows || [];
         const colCfgs = resp.colunas_cfg || [];
         const cellCfgs = resp.celulas_cfg || [];
         
         colCfgs.forEach(cc => {
             const sid = String(cc.secao);
             if (!sectionsMap.has(sid)) sectionsMap.set(sid, { id: sid, colunas: [], linhas: [] });
             const s = sectionsMap.get(sid);
             const cIdx = parseInt(cc.coluna) - 1;
             while(s.colunas.length <= cIdx) s.colunas.push({}); 
             
             s.colunas[cIdx] = {
                 id: 'c'+(cIdx+1),
                 tipo: cc.tipo,
                 titulo: cc.titulo || (cc.tipo?labelPorTipo(cc.tipo):'Col '+(cIdx+1)),
                 largura: cc.largura,
                 singleLine: !!cc.single_line,
                 customFont: !!cc.custom_font,
                 fontFamily: cc.font_family,
                 fontSize: cc.font_size,
                 fontColor: cc.font_color,
                 bold: !!cc.bold,
                 italic: !!cc.italic,
                 underline: !!cc.underline,
                 alignH: cc.align_h,
                 alignV: cc.align_v
             };
         });
         
         rows.sort((a,b) => a.ordem - b.ordem).forEach(r => {
             const sid = String(r.secao);
             if (!sectionsMap.has(sid)) {
                sectionsMap.set(sid, { id: sid, colunas: [], linhas: [] });
             }
             const s = sectionsMap.get(sid);
             
             const cells = [];
             let maxCols = s.colunas.length;
             if (maxCols === 0) {
                 Object.keys(r).forEach(k => {
                     if (k.startsWith('col')) {
                         const n = parseInt(k.substring(3));
                         if (n > maxCols) maxCols = n;
                     }
                 });
                 for(let k=0; k<maxCols; k++) s.colunas.push({id:'c'+(k+1), titulo:'Col '+(k+1)});
             }

             for(let i=0; i<s.colunas.length; i++) {
                 const val = r['col'+(i+1)];
                 cells.push({ valor: val !== undefined ? val : '' });
             }
             
             cells.forEach((cell, cIdx) => {
                 const cfg = cellCfgs.find(c => c.secao == sid && c.linha == (r.ordem) && c.coluna == (cIdx+1));
                 if (cfg) {
                     cell.numTipo = cfg.num_tipo;
                     cell.numPos = cfg.num_pos;
                     cell.numDec = cfg.num_dec;
                     cell.numFormula = cfg.num_formula;
                     cell.rtTipo = cfg.rt_tipo;
                     cell.rtId = cfg.rt_id;
                     cell.varAlias = cfg.var_alias;
                     
                     cell.uppercase = !!cfg.uppercase;
                     cell.singleLine = !!cfg.single_line;
                     cell.customFont = !!cfg.custom_font;
                     cell.fontFamily = cfg.font_family;
                     cell.fontSize = cfg.font_size;
                     cell.fontColor = cfg.font_color;
                     cell.bold = !!cfg.bold;
                     cell.italic = !!cfg.italic;
                     cell.underline = !!cfg.underline;
                     cell.alignH = cfg.align_h;
                     cell.alignV = cfg.align_v;
                 }
             });
             
             s.linhas.push({ 
                cells,
                altura: r.altura || null,
                quebraAntes: !!r.quebra_antes,
                quebraDepois: !!r.quebra_depois,
                ocultarVazia: !!r.ocultar_vazia
             });
         });
         
         secoesLaudo = Array.from(sectionsMap.values());
         secoesLaudo.forEach(s => s.id = parseInt(s.id, 10));
         secoesLaudo.sort((a,b) => a.id - b.id);
         
         if (secoesLaudo.length > 0) secaoAtivaId = secoesLaudo[0].id;
         renderizarEditorLaudo();
         
     } else {
         console.error('Erro ao carregar layout:', resp ? resp.mensagem : 'Resposta vazia');
     }
  };

});
