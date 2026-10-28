// Comportamiento común del panel: menú móvil, menú de usuario, diálogos, confirmaciones y avisos
(function () {
    const sidebar = document.getElementById('sidebar');
    const fondo = document.getElementById('sidebar-fondo');
    const alternarMenu = (abrir) => { sidebar.classList.toggle('abierto', abrir); fondo.classList.toggle('visible', abrir); };
    document.getElementById('btn-menu').addEventListener('click', () => alternarMenu(true));
    fondo.addEventListener('click', () => alternarMenu(false));

    document.addEventListener('click', (ev) => {
        document.querySelectorAll('details.menu-usuario[open], details.desplegable[open]').forEach((d) => {
            if (!d.contains(ev.target)) d.open = false;
        });
    });

    // Avisos que llegan del servidor (flash) se ocultan solos
    document.querySelectorAll('#toasts .toast').forEach((toast) => {
        setTimeout(() => toast.classList.add('saliendo'), 4200);
        setTimeout(() => toast.remove(), 4600);
    });

    // <button data-abrir="id-dialogo" data-titulo="…" data-valores='{"campo": "valor"}'>
    // abre el <dialog> y rellena su formulario (vacío si no trae valores)
    document.addEventListener('click', (ev) => {
        const disparador = ev.target.closest('[data-abrir]');
        if (!disparador) return;
        const dialogo = document.getElementById(disparador.dataset.abrir);
        if (!dialogo) return;
        ev.preventDefault();
        const form = dialogo.querySelector('form');
        if (form) {
            form.reset();
            const valores = JSON.parse(disparador.dataset.valores || '{}');
            Object.entries(valores).forEach(([nombre, valor]) => {
                const campo = form.elements[nombre];
                if (!campo) return;
                if (campo.type === 'checkbox') campo.checked = Boolean(Number(valor)) || valor === true;
                else campo.value = valor ?? '';
            });
        }
        if (disparador.dataset.titulo) {
            const titulo = dialogo.querySelector('.dialogo-encabezado h2');
            if (titulo) titulo.textContent = disparador.dataset.titulo;
        }
        dialogo.showModal();
        dialogo.querySelector('input:not([type=hidden]), textarea, select')?.focus();
    });

    document.addEventListener('click', (ev) => {
        if (ev.target.closest('[data-cerrar]')) ev.target.closest('dialog')?.close();
        // Clic en el fondo oscuro del diálogo
        if (ev.target.tagName === 'DIALOG') ev.target.close();
    });

    // <form data-confirmar="¿Seguro…?"> pide confirmación con un modal antes de enviarse
    const dlgConfirmar = document.getElementById('dlg-confirmar');
    document.addEventListener('submit', (ev) => {
        const form = ev.target;
        if (form.dataset.confirmar && !form.dataset.confirmado) {
            ev.preventDefault();
            document.getElementById('confirmar-titulo').textContent = form.dataset.confirmarTitulo || 'Confirmar';
            document.getElementById('confirmar-texto').textContent = form.dataset.confirmar;
            dlgConfirmar.returnValue = '';
            dlgConfirmar.showModal();
            dlgConfirmar.addEventListener('close', () => {
                if (dlgConfirmar.returnValue === 'si') {
                    form.dataset.confirmado = '1';
                    form.requestSubmit(ev.submitter);
                }
            }, { once: true });
            return;
        }
        // Estado de carga en el botón que envió el formulario
        const boton = ev.submitter;
        if (boton && form.method !== 'dialog' && !form.hasAttribute('data-sin-carga')) {
            boton.classList.add('cargando');
            setTimeout(() => { boton.disabled = true; }, 0);
        }
    });

    window.addEventListener('pageshow', () => {
        document.querySelectorAll('button.cargando').forEach((b) => { b.classList.remove('cargando'); b.disabled = false; });
    });
})();
