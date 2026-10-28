// Panel de gestión de usuarios y roles (admin/usuarios.php)
(function () {
    const { api, csrf, yo, roles, permisos, iconos } = window.APP;

    const $ = (selector, contexto = document) => contexto.querySelector(selector);
    const $$ = (selector, contexto = document) => [...contexto.querySelectorAll(selector)];
    const esc = (texto) => String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const ico = (nombre, tam = 18) =>
        `<svg class="icono" width="${tam}" height="${tam}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${iconos[nombre] || ''}</svg>`;

    const filtros = {
        buscar: new URLSearchParams(location.search).get('buscar') || '',
        rol: new URLSearchParams(location.search).get('rol') || '', estado: '', desde: '', hasta: '',
        orden: 'creado_en', dir: 'desc', pagina: 1, por_pagina: 10,
    };
    let usuariosPagina = new Map();

    // ---------- API ----------

    async function pedir(accion, { params = {}, body = null } = {}) {
        const url = new URL(api, location.origin);
        url.searchParams.set('accion', accion);
        Object.entries(params).forEach(([k, v]) => { if (v !== '' && v != null) url.searchParams.set(k, v); });
        const opciones = body
            ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(body) }
            : { headers: { Accept: 'application/json' } };
        const respuesta = await fetch(url, opciones);
        const datos = await respuesta.json().catch(() => ({ error: 'Error inesperado del servidor.' }));
        if (respuesta.status === 401) {
            location.reload();
        }
        if (!respuesta.ok) {
            const error = new Error(datos.error || 'Ocurrió un error.');
            error.campos = datos.campos || {};
            throw error;
        }
        return datos;
    }

    // ---------- Componentes ----------

    function toast(mensaje, tipo = 'exito') {
        const el = document.createElement('div');
        el.className = `toast toast-${tipo}`;
        el.innerHTML = `${ico(tipo === 'exito' ? 'check-circle' : 'alert-circle', 20)}<span>${esc(mensaje)}</span>`;
        $('#toasts').appendChild(el);
        setTimeout(() => el.classList.add('saliendo'), 3800);
        setTimeout(() => el.remove(), 4200);
    }

    function avatar(u, tam = '') {
        const iniciales = u.nombre.trim().split(/\s+/).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
        const contenido = u.avatar_url ? `<img src="${esc(u.avatar_url)}" alt="">` : esc(iniciales);
        return `<span class="avatar ${tam} avatar-c${u.id % 6}">${contenido}</span>`;
    }

    const badgeRol = (rol) => `<span class="badge rol-${rol}">${ico(roles[rol].icono, 14)}${esc(roles[rol].nombre)}</span>`;
    const badgeEstado = (estado) =>
        `<span class="badge estado-${estado}"><i class="punto"></i>${estado === 'activo' ? 'Activo' : 'Inactivo'}</span>`;

    function mostrarErroresCampos(contenedor, campos) {
        $$('[data-error-de]', contenedor).forEach((el) => { el.textContent = ''; });
        $$('.invalido', contenedor).forEach((el) => el.classList.remove('invalido'));
        Object.entries(campos).forEach(([campo, mensaje]) => {
            const destino = $(`[data-error-de="${campo}"]`, contenedor);
            if (destino) destino.textContent = mensaje;
            $(`[name="${campo}"]`, contenedor)?.classList.add('invalido');
        });
    }

    function conCarga(boton, promesa) {
        boton.disabled = true;
        boton.classList.add('cargando');
        return promesa.finally(() => { boton.disabled = false; boton.classList.remove('cargando'); });
    }

    // ---------- Modal ----------

    const modal = $('#modal');
    let alCerrarModal = null;

    function abrirModal(titulo, cuerpo, pie) {
        $('#modal-titulo').textContent = titulo;
        $('#modal-cuerpo').innerHTML = cuerpo;
        $('#modal-pie').innerHTML = pie;
        modal.hidden = false;
        requestAnimationFrame(() => modal.classList.add('visible'));
        setTimeout(() => $('input:not([type=hidden]), select, .modal-pie .btn-primario, .modal-pie .btn-peligro', modal)?.focus(), 50);
    }

    function cerrarModal() {
        modal.classList.remove('visible');
        modal.hidden = true;
        const callback = alCerrarModal;
        alCerrarModal = null;
        callback?.();
    }

    function confirmar({ titulo, mensaje, boton = 'Confirmar', peligro = false }) {
        return new Promise((resolver) => {
            abrirModal(titulo, `<p class="texto-modal">${mensaje}</p>`,
                `<button type="button" class="btn btn-contorno" data-cerrar-modal>Cancelar</button>
                 <button type="button" class="btn ${peligro ? 'btn-peligro' : 'btn-primario'}" id="btn-confirmar">${esc(boton)}</button>`);
            alCerrarModal = () => resolver(false);
            $('#btn-confirmar').addEventListener('click', () => {
                alCerrarModal = null;
                cerrarModal();
                resolver(true);
            });
        });
    }

    // Solo en modo desarrollo: la API devuelve el enlace porque no hay servicio de correo
    function mostrarEnlace(mensaje, enlace) {
        abrirModal('Enlace generado',
            `<p class="texto-modal">${esc(mensaje)}</p>
             <p class="texto-secundario">Modo desarrollo: este enlace normalmente llegaría por correo.</p>
             <input class="input" readonly value="${esc(enlace)}" onclick="this.select()">`,
            `<button type="button" class="btn btn-primario" data-cerrar-modal>Entendido</button>`);
    }

    modal.addEventListener('click', (ev) => {
        if (ev.target === modal || ev.target.closest('[data-cerrar-modal]')) cerrarModal();
    });

    // ---------- Métricas ----------

    async function cargarStats() {
        try {
            const stats = await pedir('stats');
            Object.entries(stats).forEach(([clave, { valor, tendencia }]) => {
                const tarjeta = $(`[data-metrica="${clave}"]`);
                $('.metrica-valor', tarjeta).textContent = valor.toLocaleString('es-MX');
                const sube = tendencia >= 0;
                $('.metrica-tendencia', tarjeta).innerHTML =
                    `<span class="tendencia ${sube ? 'sube' : 'baja'}">${ico(sube ? 'trending-up' : 'trending-down', 14)} ${Math.abs(tendencia)}%</span> vs. mes anterior`;
            });
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    // ---------- Tabla ----------

    const cuerpoTabla = $('#tabla-usuarios');
    let peticionActual = 0;

    function filasSkeleton() {
        const fila = `<tr class="fila-skeleton">
            <td><div class="celda-usuario"><span class="skeleton skeleton-circulo"></span><div><span class="skeleton skeleton-texto"></span><span class="skeleton skeleton-texto corto"></span></div></div></td>
            <td><span class="skeleton skeleton-texto"></span></td><td><span class="skeleton skeleton-badge"></span></td>
            <td><span class="skeleton skeleton-badge"></span></td><td><span class="skeleton skeleton-texto corto"></span></td><td></td></tr>`;
        cuerpoTabla.innerHTML = fila.repeat(Math.min(filtros.por_pagina, 6));
    }

    async function cargarUsuarios() {
        const id = ++peticionActual;
        filasSkeleton();
        try {
            const datos = await pedir('listar', { params: filtros });
            if (id !== peticionActual) return;
            filtros.pagina = datos.pagina;
            usuariosPagina = new Map(datos.usuarios.map((u) => [u.id, u]));
            renderTabla(datos.usuarios);
            renderPaginacion(datos.total);
        } catch (error) {
            if (id !== peticionActual) return;
            cuerpoTabla.innerHTML = `<tr><td colspan="6"><div class="estado-vacio">${ico('alert-circle', 32)}<p>${esc(error.message)}</p></div></td></tr>`;
        }
    }

    function renderTabla(usuarios) {
        if (!usuarios.length) {
            cuerpoTabla.innerHTML = `<tr><td colspan="6"><div class="estado-vacio">
                <span class="estado-vacio-icono">${ico('inbox', 30)}</span>
                <h3>No se encontraron usuarios</h3><p class="texto-secundario">Prueba con otra búsqueda o cambia los filtros.</p></div></td></tr>`;
            return;
        }
        cuerpoTabla.innerHTML = usuarios.map((u) => `
            <tr>
                <td data-titulo="Nombre"><div class="celda-usuario">${avatar(u)}<div><strong>${esc(u.nombre)}</strong><small>${esc(u.cargo || roles[u.rol].nombre)}</small></div></div></td>
                <td data-titulo="Email" class="celda-email">${esc(u.email)}</td>
                <td data-titulo="Rol">${badgeRol(u.rol)}</td>
                <td data-titulo="Estado">${badgeEstado(u.estado)}</td>
                <td data-titulo="Fecha alta" class="celda-fecha">${esc(u.fecha_alta)}</td>
                <td class="col-acciones"><div class="acciones">
                    <button type="button" class="btn-icono" data-accion="editar" data-id="${u.id}" title="Editar" aria-label="Editar a ${esc(u.nombre)}">${ico('pencil', 17)}</button>
                    <button type="button" class="btn-icono" data-accion="roles" data-id="${u.id}" title="Roles y permisos" aria-label="Roles y permisos de ${esc(u.nombre)}">${ico('user-cog', 17)}</button>
                    <button type="button" class="btn-icono" data-accion="mas" data-id="${u.id}" title="Más acciones" aria-label="Más acciones">${ico('more-vertical', 17)}</button>
                </div></td>
            </tr>`).join('');
    }

    function renderPaginacion(total) {
        const paginas = Math.max(1, Math.ceil(total / filtros.por_pagina));
        const desde = total ? (filtros.pagina - 1) * filtros.por_pagina + 1 : 0;
        const hasta = Math.min(total, filtros.pagina * filtros.por_pagina);
        $('#resumen-paginacion').textContent = `Mostrando ${desde}–${hasta} de ${total} usuarios`;

        const numeros = [];
        for (let p = 1; p <= paginas; p++) {
            if (p === 1 || p === paginas || Math.abs(p - filtros.pagina) <= 1) numeros.push(p);
            else if (numeros[numeros.length - 1] !== '…') numeros.push('…');
        }
        $('#paginacion').innerHTML =
            `<button type="button" class="btn-pagina" data-pagina="${filtros.pagina - 1}" ${filtros.pagina === 1 ? 'disabled' : ''} aria-label="Anterior">${ico('chevron-left', 16)}</button>`
            + numeros.map((p) => p === '…'
                ? '<span class="pagina-puntos">…</span>'
                : `<button type="button" class="btn-pagina${p === filtros.pagina ? ' activa' : ''}" data-pagina="${p}" ${p === filtros.pagina ? 'aria-current="page"' : ''}>${p}</button>`).join('')
            + `<button type="button" class="btn-pagina" data-pagina="${filtros.pagina + 1}" ${filtros.pagina === paginas ? 'disabled' : ''} aria-label="Siguiente">${ico('chevron-right', 16)}</button>`;
    }

    function recargarTodo() {
        cargarUsuarios();
        cargarStats();
    }

    // ---------- Filtros y orden ----------

    let temporizador;
    function buscarConRetraso(valor) {
        clearTimeout(temporizador);
        temporizador = setTimeout(() => {
            filtros.buscar = valor.trim();
            filtros.pagina = 1;
            cargarUsuarios();
        }, 300);
    }

    $('#filtro-rol').value = roles[filtros.rol] ? filtros.rol : '';
    if (!roles[filtros.rol]) filtros.rol = '';

    const inputBuscar = $('#filtro-buscar');
    const buscadorGlobal = $('#buscador-global');
    inputBuscar.value = filtros.buscar;
    inputBuscar.addEventListener('input', () => { buscadorGlobal.value = inputBuscar.value; buscarConRetraso(inputBuscar.value); });
    // En esta página el buscador del topbar filtra la tabla directamente
    buscadorGlobal.addEventListener('input', () => { inputBuscar.value = buscadorGlobal.value; buscarConRetraso(buscadorGlobal.value); });
    buscadorGlobal.form.addEventListener('submit', (ev) => ev.preventDefault());

    [['#filtro-rol', 'rol'], ['#filtro-estado', 'estado'], ['#filtro-desde', 'desde'], ['#filtro-hasta', 'hasta']].forEach(([selector, clave]) => {
        $(selector).addEventListener('change', (ev) => {
            filtros[clave] = ev.target.value;
            filtros.pagina = 1;
            cargarUsuarios();
        });
    });

    $('#btn-mas-filtros').addEventListener('click', (ev) => {
        const panel = $('#mas-filtros');
        panel.hidden = !panel.hidden;
        ev.currentTarget.setAttribute('aria-expanded', String(!panel.hidden));
    });

    $('#btn-limpiar-filtros').addEventListener('click', () => {
        ['#filtro-buscar', '#filtro-rol', '#filtro-estado', '#filtro-desde', '#filtro-hasta'].forEach((s) => { $(s).value = ''; });
        buscadorGlobal.value = '';
        Object.assign(filtros, { buscar: '', rol: '', estado: '', desde: '', hasta: '', pagina: 1 });
        cargarUsuarios();
    });

    $('#por-pagina').addEventListener('change', (ev) => {
        filtros.por_pagina = Number(ev.target.value);
        filtros.pagina = 1;
        cargarUsuarios();
    });

    $('#paginacion').addEventListener('click', (ev) => {
        const boton = ev.target.closest('[data-pagina]');
        if (!boton || boton.disabled) return;
        filtros.pagina = Number(boton.dataset.pagina);
        cargarUsuarios();
    });

    $$('.ordenar').forEach((boton) => {
        boton.addEventListener('click', () => {
            const orden = boton.dataset.orden;
            if (filtros.orden === orden) {
                filtros.dir = filtros.dir === 'asc' ? 'desc' : 'asc';
            } else {
                filtros.orden = orden;
                filtros.dir = orden === 'creado_en' ? 'desc' : 'asc';
            }
            $$('.ordenar').forEach((b) => {
                const activo = b.dataset.orden === filtros.orden;
                b.classList.toggle('activo', activo);
                b.closest('th').setAttribute('aria-sort', activo ? (filtros.dir === 'asc' ? 'ascending' : 'descending') : 'none');
            });
            filtros.pagina = 1;
            cargarUsuarios();
        });
    });

    // ---------- Acciones de fila ----------

    const popover = document.createElement('div');
    popover.className = 'popover';
    popover.hidden = true;
    document.body.appendChild(popover);

    function cerrarPopover() { popover.hidden = true; }

    function abrirPopover(boton, u) {
        const activo = u.estado === 'activo';
        popover.innerHTML = `
            <button type="button" class="menu-item" data-opcion="estado">${ico('power', 17)} ${activo ? 'Desactivar' : 'Activar'}</button>
            <button type="button" class="menu-item" data-opcion="restablecer">${ico('key', 17)} Restablecer contraseña</button>
            <button type="button" class="menu-item peligro" data-opcion="eliminar">${ico('trash', 17)} Eliminar</button>`;
        popover.hidden = false;
        const r = boton.getBoundingClientRect();
        const alto = popover.offsetHeight;
        const arriba = r.bottom + alto + 8 > window.innerHeight ? r.top - alto - 4 : r.bottom + 4;
        popover.style.top = `${arriba + window.scrollY}px`;
        popover.style.left = `${Math.max(8, r.right - popover.offsetWidth) + window.scrollX}px`;
        popover.onclick = (ev) => {
            const opcion = ev.target.closest('[data-opcion]')?.dataset.opcion;
            if (!opcion) return;
            cerrarPopover();
            ({ estado: cambiarEstado, restablecer: restablecerPassword, eliminar: eliminarUsuario })[opcion](u);
        };
    }

    cuerpoTabla.addEventListener('click', (ev) => {
        const boton = ev.target.closest('[data-accion]');
        if (!boton) return;
        const u = usuariosPagina.get(Number(boton.dataset.id));
        if (boton.dataset.accion === 'mas') {
            ev.stopPropagation();
            popover.hidden || popover.dataset.id !== String(u.id) ? abrirPopover(boton, u) : cerrarPopover();
            popover.dataset.id = u.id;
        } else {
            abrirDrawer(u.id, boton.dataset.accion === 'roles' ? 'roles' : 'info');
        }
    });

    document.addEventListener('click', (ev) => { if (!popover.contains(ev.target)) cerrarPopover(); });
    window.addEventListener('resize', cerrarPopover);
    $('.tabla-scroll').addEventListener('scroll', cerrarPopover);

    async function cambiarEstado(u) {
        const activar = u.estado !== 'activo';
        if (!activar && !(await confirmar({
            titulo: 'Desactivar usuario',
            mensaje: `<strong>${esc(u.nombre)}</strong> no podrá iniciar sesión y se cerrarán sus sesiones activas.`,
            boton: 'Desactivar', peligro: true,
        }))) return;
        try {
            const r = await pedir('estado', { body: { id: u.id, estado: activar ? 'activo' : 'inactivo' } });
            toast(r.mensaje);
            recargarTodo();
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    async function restablecerPassword(u) {
        if (!(await confirmar({
            titulo: 'Restablecer contraseña',
            mensaje: `Se enviará a <strong>${esc(u.email)}</strong> un enlace para crear una nueva contraseña (válido 24 horas).`,
            boton: 'Enviar enlace',
        }))) return;
        try {
            const r = await pedir('restablecer', { body: { id: u.id } });
            r.enlace ? mostrarEnlace(r.mensaje, r.enlace) : toast(r.mensaje);
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    async function eliminarUsuario(u) {
        if (!(await confirmar({
            titulo: 'Eliminar usuario',
            mensaje: `¿Seguro que quieres eliminar a <strong>${esc(u.nombre)}</strong>? Dejará de aparecer en la plataforma y no podrá iniciar sesión.`,
            boton: 'Eliminar', peligro: true,
        }))) return;
        try {
            const r = await pedir('eliminar', { body: { id: u.id } });
            toast(r.mensaje);
            recargarTodo();
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    // ---------- Drawer "Editar usuario" ----------

    const drawer = $('#drawer');
    const capaDrawer = $('#capa-drawer');
    const cuerpoDrawer = $('#drawer-cuerpo');
    let edicion = null;

    function campo(nombre, etiqueta, valor, tipo = 'text', extra = '') {
        return `<div class="campo"><label for="d-${nombre}">${etiqueta}</label>
            <input class="input" id="d-${nombre}" name="${nombre}" type="${tipo}" value="${esc(valor)}" ${extra}>
            <small class="campo-error" data-error-de="${nombre}"></small></div>`;
    }

    async function abrirDrawer(id, pestana) {
        edicion = null;
        $('#drawer-usuario').innerHTML = `<span class="skeleton skeleton-circulo grande"></span><div><span class="skeleton skeleton-texto"></span><span class="skeleton skeleton-texto corto"></span></div>`;
        cuerpoDrawer.innerHTML = '<span class="skeleton skeleton-bloque"></span><span class="skeleton skeleton-bloque"></span>';
        capaDrawer.hidden = false;
        drawer.classList.add('abierto');
        drawer.setAttribute('aria-hidden', 'false');
        try {
            const u = await pedir('detalle', { params: { id } });
            edicion = {
                usuario: u,
                pestana,
                info: { nombre: u.nombre, email: u.email, cargo: u.cargo || '', avatar_url: u.avatar_url || '', estado: u.estado },
                acceso: { rol: u.rol, roles: new Set(u.roles), permisos: new Set(u.permisos) },
                infoCambiada: false,
                accesoCambiado: false,
            };
            renderDrawer();
        } catch (error) {
            toast(error.message, 'error');
            cerrarDrawer();
        }
    }

    function cerrarDrawer() {
        drawer.classList.remove('abierto');
        drawer.setAttribute('aria-hidden', 'true');
        capaDrawer.hidden = true;
        edicion = null;
    }

    function renderDrawer() {
        const u = edicion.usuario;
        $('#drawer-usuario').innerHTML = `${avatar(u, 'grande')}
            <div><strong id="drawer-nombre">${esc(u.nombre)}</strong><small>${esc(u.email)}</small>${badgeEstado(u.estado)}</div>`;
        $$('[data-pestana]', drawer).forEach((b) => {
            const activa = b.dataset.pestana === edicion.pestana;
            b.classList.toggle('activa', activa);
            b.setAttribute('aria-selected', String(activa));
        });
        $('#btn-guardar-drawer').hidden = edicion.pestana === 'actividad';
        ({ info: renderInfo, roles: renderRoles, actividad: renderActividad })[edicion.pestana]();
    }

    function renderInfo() {
        const i = edicion.info;
        const esYo = edicion.usuario.id === yo.id;
        cuerpoDrawer.innerHTML = `<form id="form-info" novalidate>
            ${campo('nombre', 'Nombre completo', i.nombre, 'text', 'maxlength="100" required')}
            ${campo('email', 'Correo electrónico', i.email, 'email', 'maxlength="100" required')}
            ${campo('cargo', 'Cargo / título', i.cargo, 'text', 'maxlength="100" placeholder="Ej. Director Académico"')}
            ${campo('avatar_url', 'URL del avatar', i.avatar_url, 'url', 'placeholder="https://…"')}
            <div class="campo">
                <span class="etiqueta">Estado de la cuenta</span>
                <label class="toggle-fila${esYo ? ' deshabilitado' : ''}">
                    <span><strong>Cuenta activa</strong><small>${esYo ? 'No puedes desactivar tu propia cuenta.' : 'Los usuarios inactivos no pueden iniciar sesión.'}</small></span>
                    <span class="toggle"><input type="checkbox" name="estado" ${i.estado === 'activo' ? 'checked' : ''} ${esYo ? 'disabled' : ''}><span></span></span>
                </label>
                <small class="campo-error" data-error-de="estado"></small>
            </div>
            <div class="caja-accion">
                <div><strong>Contraseña</strong><small>Envía un enlace para que el usuario cree una nueva contraseña.</small></div>
                <button type="button" class="btn btn-contorno" id="btn-reset-drawer">${ico('key', 16)} Restablecer contraseña</button>
            </div>
        </form>`;
        const form = $('#form-info');
        form.addEventListener('input', (ev) => {
            const { name, type, checked, value } = ev.target;
            i[name] = type === 'checkbox' ? (checked ? 'activo' : 'inactivo') : value;
            edicion.infoCambiada = true;
        });
        $('#btn-reset-drawer').addEventListener('click', () => restablecerPassword(edicion.usuario));
    }

    function renderRoles() {
        const a = edicion.acceso;
        // Un gestor que no es Super Admin no puede otorgar ese rol
        const bloqueado = (rol) => rol === 'superadmin' && !yo.superadmin;
        cuerpoDrawer.innerHTML = `
            <section class="seccion-drawer">
                <h3>Asignar rol principal</h3>
                <select class="select" id="d-rol" aria-describedby="ayuda-rol">
                    ${Object.entries(roles).map(([clave, r]) => `<option value="${clave}" ${clave === a.rol ? 'selected' : ''} ${bloqueado(clave) && clave !== a.rol ? 'disabled' : ''}>${esc(r.nombre)}</option>`).join('')}
                </select>
                <small class="ayuda" id="ayuda-rol">Cada usuario debe tener un rol principal en la plataforma.</small>
            </section>
            <section class="seccion-drawer">
                <h3>Roles disponibles</h3>
                <p class="ayuda">Puedes asignar roles adicionales si es necesario.</p>
                <div class="lista-opciones">
                    ${Object.entries(roles).map(([clave, r]) => {
                        const principal = clave === a.rol;
                        return `<label class="opcion-rol${principal ? ' principal' : ''}">
                            <input type="checkbox" value="${clave}" ${principal || a.roles.has(clave) ? 'checked' : ''} ${principal || (bloqueado(clave) && !a.roles.has(clave)) ? 'disabled' : ''}>
                            <span class="rol-circulo rol-${clave}">${ico(r.icono, 16)}</span>
                            <span class="opcion-texto"><strong>${esc(r.nombre)}${principal ? ' <em>Principal</em>' : ''}</strong><small>${esc(r.descripcion)}</small></span>
                        </label>`;
                    }).join('')}
                </div>
            </section>
            <section class="seccion-drawer">
                <h3>Permisos adicionales</h3>
                <p class="ayuda">Configura permisos específicos para este usuario.</p>
                ${Object.entries(permisos).map(([clave, texto]) => `
                    <label class="toggle-fila">
                        <span><strong>${esc(texto)}</strong></span>
                        <span class="toggle"><input type="checkbox" data-permiso="${clave}" ${a.permisos.has(clave) ? 'checked' : ''}><span></span></span>
                    </label>`).join('')}
            </section>`;

        $('#d-rol').addEventListener('change', (ev) => {
            a.rol = ev.target.value;
            a.roles.delete(a.rol);
            edicion.accesoCambiado = true;
            renderRoles();
        });
        $$('.opcion-rol input', cuerpoDrawer).forEach((input) => {
            input.addEventListener('change', () => {
                input.checked ? a.roles.add(input.value) : a.roles.delete(input.value);
                edicion.accesoCambiado = true;
            });
        });
        $$('[data-permiso]', cuerpoDrawer).forEach((input) => {
            input.addEventListener('change', () => {
                input.checked ? a.permisos.add(input.dataset.permiso) : a.permisos.delete(input.dataset.permiso);
                edicion.accesoCambiado = true;
            });
        });
    }

    function renderActividad() {
        const u = edicion.usuario;
        cuerpoDrawer.innerHTML = `
            <div class="datos-actividad">
                <div class="dato">${ico('activity', 18)}<div><small>Último acceso</small><strong>${esc(u.ultimo_acceso)}</strong></div></div>
                <div class="dato">${ico('calendar', 18)}<div><small>Fecha de alta</small><strong>${esc(u.fecha_alta)}</strong></div></div>
            </div>
            <h3 class="subtitulo-drawer">Historial reciente</h3>
            ${u.actividad.length ? `<ol class="linea-tiempo">${u.actividad.map((a) => `
                <li><strong>${esc(a.detalle || a.accion)}</strong>
                <small>${esc(a.fecha)}${a.actor ? ` · por ${esc(a.actor)}` : ''}</small></li>`).join('')}</ol>`
                : '<p class="texto-secundario">Todavía no hay actividad registrada.</p>'}`;
    }

    $$('[data-pestana]', drawer).forEach((boton) => {
        boton.addEventListener('click', () => {
            if (!edicion) return;
            edicion.pestana = boton.dataset.pestana;
            renderDrawer();
        });
    });

    $$('[data-cerrar-drawer]').forEach((b) => b.addEventListener('click', cerrarDrawer));
    capaDrawer.addEventListener('click', cerrarDrawer);

    $('#btn-guardar-drawer').addEventListener('click', (ev) => {
        if (!edicion) return;
        if (!edicion.infoCambiada && !edicion.accesoCambiado) {
            cerrarDrawer();
            return;
        }
        conCarga(ev.currentTarget, guardarDrawer());
    });

    async function guardarDrawer() {
        const { usuario, info, acceso } = edicion;
        try {
            if (edicion.infoCambiada) {
                await pedir('actualizar', { body: { id: usuario.id, ...info } });
                edicion.infoCambiada = false;
            }
            if (edicion.accesoCambiado) {
                await pedir('roles', { body: { id: usuario.id, rol: acceso.rol, roles: [...acceso.roles], permisos: [...acceso.permisos] } });
            }
            toast('Cambios guardados');
            cerrarDrawer();
            recargarTodo();
        } catch (error) {
            toast(error.message, 'error');
            if (Object.keys(error.campos || {}).length) {
                edicion.pestana = 'info';
                renderDrawer();
                mostrarErroresCampos(cuerpoDrawer, error.campos);
            }
        }
    }

    // ---------- Modal "Nuevo usuario" ----------

    $('#btn-nuevo').addEventListener('click', () => {
        const opcionesRol = Object.entries(roles)
            .filter(([clave]) => clave !== 'superadmin' || yo.superadmin)
            .map(([clave, r]) => `<option value="${clave}" ${clave === 'estudiante' ? 'selected' : ''}>${esc(r.nombre)}</option>`).join('');
        abrirModal('Nuevo usuario', `<form id="form-nuevo" novalidate>
            <div class="rejilla-2">
                ${campo('nombre', 'Nombre completo', '', 'text', 'maxlength="100" required')}
                ${campo('email', 'Correo electrónico', '', 'email', 'maxlength="100" required placeholder="tu@correo.com"')}
            </div>
            ${campo('cargo', 'Cargo / título (opcional)', '', 'text', 'maxlength="100" placeholder="Ej. Ingeniería en Sistemas"')}
            <div class="rejilla-2">
                <div class="campo"><label for="n-rol">Rol principal</label><select class="select" id="n-rol" name="rol">${opcionesRol}</select>
                    <small class="campo-error" data-error-de="rol"></small></div>
                <div class="campo"><label for="n-estado">Estado</label><select class="select" id="n-estado" name="estado">
                    <option value="activo">Activo</option><option value="inactivo">Inactivo</option></select></div>
            </div>
            <fieldset class="campo opciones-acceso">
                <legend>Acceso inicial</legend>
                <label class="radio"><input type="radio" name="modo" value="password" checked> Asignar contraseña temporal</label>
                <label class="radio"><input type="radio" name="modo" value="invitacion"> Enviar invitación por correo</label>
            </fieldset>
            <div id="bloque-password">${campo('password', 'Contraseña temporal', '', 'text', 'minlength="8" autocomplete="off" placeholder="Mínimo 8 caracteres"')}</div>
        </form>`,
        `<button type="button" class="btn btn-contorno" data-cerrar-modal>Cancelar</button>
         <button type="submit" form="form-nuevo" class="btn btn-primario" id="btn-crear"><span class="spinner"></span>${ico('plus', 18)} Crear usuario</button>`);

        const form = $('#form-nuevo');
        form.addEventListener('change', (ev) => {
            if (ev.target.name === 'modo') $('#bloque-password').hidden = ev.target.value === 'invitacion';
        });
        form.addEventListener('submit', (ev) => {
            ev.preventDefault();
            const datos = Object.fromEntries(new FormData(form));
            datos.invitacion = datos.modo === 'invitacion';
            const errores = {};
            if (!datos.nombre.trim()) errores.nombre = 'Escribe un nombre.';
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(datos.email.trim())) errores.email = 'Escribe un correo válido.';
            if (!datos.invitacion && datos.password.length < 8) errores.password = 'Debe tener al menos 8 caracteres.';
            mostrarErroresCampos(form, errores);
            if (Object.keys(errores).length) return;

            conCarga($('#btn-crear'), pedir('crear', { body: datos }).then((r) => {
                cerrarModal();
                r.enlace ? mostrarEnlace(r.mensaje, r.enlace) : toast(r.mensaje);
                recargarTodo();
            }).catch((error) => {
                toast(error.message, 'error');
                mostrarErroresCampos(form, error.campos || {});
            }));
        });
    });

    document.addEventListener('keydown', (ev) => {
        if (ev.key !== 'Escape') return;
        if (!modal.hidden) cerrarModal();
        else if (!popover.hidden) cerrarPopover();
        else if (drawer.classList.contains('abierto')) cerrarDrawer();
    });

    recargarTodo();
})();
