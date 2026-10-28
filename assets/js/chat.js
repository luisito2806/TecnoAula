// Chat del curso: envío sin recargar y consulta de mensajes nuevos cada 5 segundos
(function () {
    const chat = document.getElementById('chat');
    if (!chat) return;
    const lista = document.getElementById('chat-mensajes');
    const form = document.getElementById('chat-form');
    const campo = form.elements.contenido;
    const csrf = document.querySelector('input[name="csrf"]').value;
    let ultimo = Number(chat.dataset.ultimo);
    let ocupado = false;

    const alFinal = () => { lista.scrollTop = lista.scrollHeight; };
    alFinal();

    function agregar(mensajes) {
        mensajes.forEach((m) => {
            if (m.id <= ultimo) return;
            ultimo = m.id;
            document.getElementById('chat-vacio')?.remove();
            const div = document.createElement('div');
            div.className = 'chat-mensaje' + (m.propio ? ' propio' : '');
            const meta = document.createElement('span');
            meta.className = 'chat-meta';
            meta.textContent = `${m.propio ? 'Tú' : m.autor} · ${m.fecha}`;
            const burbuja = document.createElement('div');
            burbuja.className = 'chat-burbuja';
            burbuja.innerHTML = m.html; // el servidor ya lo escapó
            div.append(meta, burbuja);
            lista.appendChild(div);
        });
        if (mensajes.length) alFinal();
    }

    async function consultar() {
        if (ocupado || document.hidden) return;
        ocupado = true;
        try {
            const r = await fetch(`${chat.dataset.api}?curso=${chat.dataset.curso}&despues=${ultimo}`);
            if (r.ok) agregar((await r.json()).mensajes);
        } finally {
            ocupado = false;
        }
    }

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const contenido = campo.value.trim();
        if (!contenido) return;
        const boton = form.querySelector('button');
        boton.disabled = true;
        try {
            const r = await fetch(chat.dataset.api, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ curso_id: Number(chat.dataset.curso), contenido, despues: ultimo }),
            });
            const datos = await r.json();
            if (!r.ok) throw new Error(datos.error);
            campo.value = '';
            agregar(datos.mensajes);
        } catch (error) {
            alert(error.message || 'No se pudo enviar el mensaje.');
        } finally {
            boton.disabled = false;
            campo.focus();
        }
    });

    // Enter envía; Shift+Enter hace salto de línea
    campo.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter' && !ev.shiftKey) {
            ev.preventDefault();
            form.requestSubmit();
        }
    });

    setInterval(consultar, 5000);
})();
