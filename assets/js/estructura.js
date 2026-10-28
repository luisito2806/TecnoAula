// Reordenar bloques y temas arrastrando desde el asa (docente/curso.php, pestaña Estructura)
(function () {
    const contenedor = document.getElementById('estructura');
    if (!contenedor) return;
    const csrf = document.querySelector('input[name="csrf"]').value;
    let arrastrado = null;

    // Solo se puede arrastrar si se tomó el asa (así los botones y textos siguen funcionando)
    contenedor.addEventListener('pointerdown', (ev) => {
        const asa = ev.target.closest('.asa');
        if (!asa) return;
        const item = asa.closest('.tema-fila') || asa.closest('.bloque');
        item.draggable = true;
    });

    contenedor.addEventListener('dragstart', (ev) => {
        arrastrado = ev.target.closest('.tema-fila, .bloque');
        if (!arrastrado || !arrastrado.draggable) return;
        ev.stopPropagation();
        ev.dataTransfer.effectAllowed = 'move';
        ev.dataTransfer.setData('text/plain', arrastrado.dataset.id);
        requestAnimationFrame(() => arrastrado.classList.add('arrastrando'));
    });

    contenedor.addEventListener('dragover', (ev) => {
        if (!arrastrado) return;
        const esTema = arrastrado.classList.contains('tema-fila');
        // Los temas se reordenan dentro de su bloque; los bloques, dentro de la estructura
        const lista = esTema ? arrastrado.parentElement : contenedor;
        if (!lista.contains(ev.target)) return;
        ev.preventDefault();
        const hermanos = [...lista.children].filter((el) => el !== arrastrado && el.matches(esTema ? '.tema-fila' : '.bloque'));
        const siguiente = hermanos.find((el) => {
            const caja = el.getBoundingClientRect();
            return ev.clientY < caja.top + caja.height / 2;
        });
        lista.insertBefore(arrastrado, siguiente || null);
    });

    contenedor.addEventListener('drop', (ev) => { if (arrastrado) ev.preventDefault(); });

    contenedor.addEventListener('dragend', async () => {
        if (!arrastrado) return;
        const item = arrastrado;
        arrastrado = null;
        item.classList.remove('arrastrando');
        item.draggable = false;
        const esTema = item.classList.contains('tema-fila');
        const lista = esTema ? item.parentElement : contenedor;
        const ids = [...lista.children].filter((el) => el.matches(esTema ? '.tema-fila' : '.bloque')).map((el) => Number(el.dataset.id));
        try {
            const respuesta = await fetch(contenedor.dataset.api || '../api/estructura.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ curso_id: Number(contenedor.dataset.curso), tipo: esTema ? 'temas' : 'bloques', bloque_id: esTema ? Number(lista.dataset.bloque) : null, ids }),
            });
            if (!respuesta.ok) throw new Error();
            // Renumerar "Bloque n" sin recargar
            contenedor.querySelectorAll('.bloque .bloque-numero').forEach((el, i) => { el.textContent = `Bloque ${i + 1}`; });
        } catch {
            alert('No se pudo guardar el nuevo orden. Se recargará la página.');
            location.reload();
        }
    });
})();
