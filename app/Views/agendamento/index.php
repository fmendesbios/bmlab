<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales-all.global.min.js'></script>

<div class="flex flex-col md:flex-row h-[calc(100vh-100px)] gap-4">
    <!-- Sidebar -->
    <div class="w-full md:w-1/4 flex flex-col gap-4 overflow-y-auto pr-2">
        <div class="card bg-base-100 shadow-xl compact">
            <div class="card-body p-4">
                <h3 class="font-bold text-lg mb-2">Pacientes Cadastrados</h3>
                <input type="text" id="busca-paciente" class="input input-bordered input-sm w-full mb-2" placeholder="Buscar paciente...">
                
                <div id="external-events" class="max-h-60 overflow-y-auto border border-base-300 rounded p-2 bg-base-200">
                    <?php foreach ($pacientes as $p): ?>
                        <div class="fc-event external-event badge badge-primary w-full justify-start p-3 mb-1 cursor-grab"
                            data-id="<?= $p['id'] ?>"
                            data-nome="<?= htmlspecialchars($p['nome']) ?>"
                            data-whatsapp="<?= $p['whatsapp'] ?>">
                            <?= htmlspecialchars($p['nome']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-xl compact">
            <div class="card-body p-4">
                <h3 class="font-bold text-lg mb-2">Paciente Avulso</h3>
                <form id="form-avulso" class="flex flex-col gap-2">
                    <input type="text" name="nome" class="input input-bordered input-sm w-full" placeholder="Nome" required>
                    <input type="text" name="whatsapp" class="input input-bordered input-sm w-full" placeholder="WhatsApp" required>
                    <button type="submit" class="btn btn-sm btn-secondary w-full">Adicionar à Lista</button>
                </form>
                
                <div id="external-avulsos" class="mt-2 max-h-40 overflow-y-auto border border-base-300 rounded p-2 bg-base-200 min-h-[50px]"></div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-xl compact mt-auto">
            <div class="card-body p-4 flex flex-col gap-2">
                <button type="button" class="btn btn-success btn-sm w-full text-white" onclick="enviarLembretes()">
                    <i class="bi bi-bell"></i> Enviar Lembretes
                </button>
                <button type="button" class="btn btn-outline btn-sm w-full" onclick="abrirModulo('disparos_whatsapp/logs_envio.php','Logs de Envios','bi-chat-dots')">
                    <i class="bi bi-chat-dots"></i> Logs de Envios
                </button>
            </div>
        </div>
    </div>

    <!-- Calendar -->
    <div class="w-full md:w-3/4 card bg-base-100 shadow-xl">
        <div class="card-body p-2 md:p-4 h-full">
            <div id='calendar' class="h-full"></div>
        </div>
    </div>
</div>

<!-- Modal Modulo (Iframe) -->
<dialog id="modal_modulo" class="modal">
    <div class="modal-box w-11/12 max-w-5xl h-[80vh]">
        <div class="flex justify-between items-center mb-2">
            <h3 class="font-bold text-lg" id="modal_modulo_title">Módulo</h3>
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost">✕</button>
            </form>
        </div>
        <iframe id="modal_modulo_iframe" src="" class="w-full h-full border-0"></iframe>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
    let calendar;
    
    function abrirModulo(url, title, icon) {
        document.getElementById('modal_modulo_title').innerText = title;
        document.getElementById('modal_modulo_iframe').src = url;
        document.getElementById('modal_modulo').showModal();
    }

    // Filter Patients
    document.getElementById('busca-paciente').addEventListener('keyup', function() {
        let term = this.value.toLowerCase();
        let events = document.querySelectorAll('#external-events .external-event');
        events.forEach(el => {
            let nome = el.innerText.toLowerCase();
            el.style.display = nome.includes(term) ? 'flex' : 'none';
        });
    });

    // Add Avulso
    document.getElementById('form-avulso').addEventListener('submit', function(e) {
        e.preventDefault();
        let nome = this.nome.value;
        let whatsapp = this.whatsapp.value;
        
        let div = document.createElement('div');
        div.className = 'fc-event external-event badge badge-secondary w-full justify-start p-3 mb-1 cursor-grab';
        div.dataset.nome = nome;
        div.dataset.whatsapp = whatsapp;
        div.dataset.id = ''; // Empty for avulso
        div.innerText = nome + ' (Avulso)';
        
        document.getElementById('external-avulsos').appendChild(div);
        
        // Make draggable
        new FullCalendar.Draggable(div); // Wait, need global Draggable or initialize container
        this.reset();
    });

    document.addEventListener('DOMContentLoaded', function() {
        var Draggable = FullCalendar.Draggable;
        var containerEl = document.getElementById('external-events');
        var containerAvulsos = document.getElementById('external-avulsos');

        new Draggable(containerEl, {
            itemSelector: '.fc-event',
            eventData: function(eventEl) {
                return {
                    title: eventEl.innerText,
                    extendedProps: {
                        whatsapp: eventEl.dataset.whatsapp
                    }
                };
            }
        });

        new Draggable(containerAvulsos, {
            itemSelector: '.fc-event',
            eventData: function(eventEl) {
                return {
                    title: eventEl.innerText,
                    extendedProps: {
                        whatsapp: eventEl.dataset.whatsapp
                    }
                };
            }
        });

        const calendarEl = document.getElementById('calendar');
        calendar = new FullCalendar.Calendar(calendarEl, {
            events: <?= json_encode($eventos_js) ?>,
            initialView: 'timeGridWeek',
            editable: true,
            droppable: true,
            locale: 'pt-br',
            buttonText: {
                today: 'Hoje',
                month: 'Mês',
                week: 'Semana',
                day: 'Dia',
                list: 'Lista'
            },
            nowIndicator: true,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            datesSet: function(info) {
                var today = new Date();
                today.setHours(0,0,0,0);
                
                if (today >= info.start && today < info.end) {
                    var btn = document.querySelector('.fc-today-button');
                    if (btn) btn.classList.add('fc-button-active');
                } else {
                    var btn = document.querySelector('.fc-today-button');
                    if (btn) btn.classList.remove('fc-button-active');
                }
            },
            slotMinTime: "06:00:00",
            slotMaxTime: "18:00:00",
            slotDuration: '00:30:00',
            allDaySlot: false,
            
            eventDidMount: function(info) {
                const nome = info.event.title;
                const whatsapp = info.event.extendedProps.whatsapp || 'Não informado';
                info.el.setAttribute('title', `${nome} - WhatsApp: ${whatsapp}`);
            },

            eventReceive: function(info) {
                // Remove dragged event to prevent duplication before saving
                info.event.remove(); 

                const el = info.draggedEl;
                const paciente_id = el.dataset.id || null;
                const nome = el.dataset.nome;
                const whatsapp = el.dataset.whatsapp;
                const data_hora = info.dateStr || info.event.startStr;

                fetch('index.php?r=agendamento/store', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ paciente_id, nome, whatsapp, data_hora })
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.sucesso) {
                        alert('Erro ao salvar agendamento: ' + (data.erro || ''));
                        return;
                    }

                    const dataFim = new Date(new Date(data_hora).getTime() + 30 * 60000);
                    calendar.addEvent({
                        id: data.id,
                        title: nome,
                        start: data_hora,
                        end: dataFim.toISOString(),
                        allDay: false,
                        extendedProps: { whatsapp: whatsapp }
                    });

                    if (el && el.parentNode && el.parentNode.id === 'external-avulsos') el.remove();

                    if (confirm('Deseja enviar uma mensagem via WhatsApp para este paciente?')) {
                        enviarMensagemWhatsapp(nome, whatsapp, data_hora);
                    }
                });
            },

            eventClick: function(info) {
                if (confirm('Deseja remover este agendamento?')) {
                    fetch('index.php?r=agendamento/delete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: info.event.id })
                    }).then(res => res.json()).then(data => {
                        if (data.sucesso) {
                            info.event.remove();
                        } else {
                            alert('Erro ao remover agendamento');
                        }
                    });
                }
            },

            eventDrop: function(info) {
                if (!confirm('Deseja realmente alterar o horário deste agendamento?')) {
                    info.revert();
                    return;
                }

                const id = info.event.id;
                // Fix timezone offset for ISO string
                const offset = info.event.start.getTimezoneOffset() * 60000;
                const localISOTime = new Date(info.event.start.getTime() - offset).toISOString().slice(0, -1);
                
                let whatsapp = info.event.extendedProps.whatsapp;

                fetch('index.php?r=agendamento/update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, data_hora: localISOTime })
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.sucesso) {
                        alert('Erro ao atualizar agendamento');
                        info.revert();
                    } else {
                         if (whatsapp && confirm('Deseja notificar o paciente sobre a mudança de horário via WhatsApp?')) {
                            // Call legacy script adjusted path
                            fetch('disparos_whatsapp/agendamento/enviar_reagendamento.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ telefone: whatsapp, data_hora: localISOTime, nome: info.event.title })
                            }).then(r => r.json()).then(resp => {
                                if(resp.sucesso) alert('Mensagem enviada!');
                                else alert('Erro ao enviar mensagem.');
                            });
                        }
                    }
                });
            },

            eventResize: function(info) {
                if (!confirm('Deseja alterar a duração deste agendamento?')) {
                    info.revert();
                    return;
                }

                const id = info.event.id;
                const offsetStart = info.event.start.getTimezoneOffset() * 60000;
                const offsetEnd = info.event.end.getTimezoneOffset() * 60000;
                const localISOStart = new Date(info.event.start.getTime() - offsetStart).toISOString().slice(0, -1);
                const localISOEnd = new Date(info.event.end.getTime() - offsetEnd).toISOString().slice(0, -1);
                const nome = info.event.title;
                let whatsapp = info.event.extendedProps.whatsapp;

                fetch('index.php?r=agendamento/update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id,
                        data_hora: localISOStart,
                        data_fim: localISOEnd
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.sucesso) {
                        alert('Erro ao atualizar duração');
                        info.revert();
                    } else {
                        if (whatsapp && confirm('Deseja notificar o paciente sobre a nova duração via WhatsApp?')) {
                            // Using legacy script for notification
                            fetch('disparos_whatsapp/agendamento/enviar_reagendamento.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    telefone: whatsapp,
                                    nome: nome,
                                    data_hora: localISOStart
                                })
                            }).then(res => res.json()).then(resp => {
                                if (!resp.sucesso) {
                                    alert('Erro ao enviar mensagem via WhatsApp');
                                } else {
                                    alert('Mensagem enviada com sucesso!');
                                }
                            });
                        }
                    }
                });
            }
        });

        calendar.render();
    });

    function enviarMensagemWhatsapp(nome, whatsapp, data_hora) {
        const dataObj = new Date(data_hora);
        const dataF = dataObj.toLocaleDateString('pt-BR');
        const horaF = dataObj.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

        const mensagem = `Olá *${nome}*, 👋\n\nSua *coleta* foi *agendada* para:\n\n📅 *${dataF}*\n🕗 *${horaF}h*\n\n📍 *Local:* Rua Presidente Vargas, 625\n(Atrás do América Hall) – *Rondon do Pará/PA*\n\n🕒 *Horários de Atendimento:*\n- Segunda à Sexta: *07h00 às 17h00*\n- Sábados: *07h00 às 11h00*\n*Não fechamos para o almoço.*\n\n💙 *Braga Mendes Laboratório*`;

        fetch('disparos_whatsapp/agendamento/enviar_mensagem.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nome: nome, telefone: whatsapp, mensagem: mensagem })
        }).then(res => res.json()).then(resp => {
            if (!resp.sucesso) {
                alert('Erro ao enviar mensagem via WhatsApp');
            } else {
                alert('✅ Mensagem enviada com sucesso para ' + nome);
            }
        });
    }

    function enviarLembretes() {
        if (!confirm('Deseja realmente enviar os lembretes de hoje?')) return;

        fetch('disparos_whatsapp/agendamento/lembrete_agendamentos.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    alert(`✅ Lembretes enviados com sucesso para ${data.resultados.length} paciente(s).`);
                } else {
                    alert('❌ Erro ao enviar os lembretes: ' + (data.erro || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('❌ Erro ao enviar os lembretes.');
            });
    }
</script>
