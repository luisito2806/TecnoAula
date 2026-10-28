// Login, registro y recuperación: mostrar contraseña, validación en cliente y estado de carga
(function () {
    const EMAIL_VALIDO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    document.querySelectorAll('.ver-password').forEach((boton) => {
        boton.addEventListener('click', () => {
            const input = boton.parentElement.querySelector('input');
            const mostrar = input.type === 'password';
            input.type = mostrar ? 'text' : 'password';
            boton.classList.toggle('visible', mostrar);
            boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    });

    // El botón "Iniciar sesión" del header lleva al formulario
    document.querySelectorAll('[data-enfocar-login]').forEach((enlace) => {
        enlace.addEventListener('click', (ev) => {
            const email = document.getElementById('email');
            if (!email) return;
            ev.preventDefault();
            email.scrollIntoView({ behavior: 'smooth', block: 'center' });
            email.focus({ preventScroll: true });
        });
    });

    function validar(input) {
        const valor = input.value.trim();
        if (input.required && valor === '') return 'Este campo es obligatorio.';
        if (input.type === 'email' && !EMAIL_VALIDO.test(valor)) return 'Ingresa un correo con formato válido.';
        if (input.minLength > 0 && input.value.length < input.minLength) return `Debe tener al menos ${input.minLength} caracteres.`;
        if (input.dataset.igualA) {
            const otro = document.getElementById(input.dataset.igualA);
            if (otro && otro.value !== input.value) return 'Las contraseñas no coinciden.';
        }
        return '';
    }

    function mostrarError(input, mensaje) {
        const destino = document.querySelector(`[data-error-de="${input.name}"]`);
        if (destino) destino.textContent = mensaje;
        input.closest('.input-icono')?.classList.toggle('invalido', mensaje !== '');
    }

    document.querySelectorAll('.form-acceso').forEach((form) => {
        const inputs = [...form.querySelectorAll('input[name]:not([type=hidden]):not([type=checkbox])')];

        inputs.forEach((input) => {
            input.addEventListener('blur', () => { if (input.value !== '') mostrarError(input, validar(input)); });
            input.addEventListener('input', () => {
                if (input.closest('.input-icono')?.classList.contains('invalido')) mostrarError(input, validar(input));
            });
        });

        form.addEventListener('submit', (ev) => {
            let primero = null;
            inputs.forEach((input) => {
                const mensaje = validar(input);
                mostrarError(input, mensaje);
                if (mensaje && !primero) primero = input;
            });
            if (primero) {
                ev.preventDefault();
                primero.focus();
                return;
            }
            const boton = form.querySelector('button[type=submit]');
            boton.disabled = true;
            boton.classList.add('cargando');
        });
    });

    // Si el usuario vuelve con "atrás", el botón no debe quedarse cargando
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('button.cargando').forEach((b) => { b.disabled = false; b.classList.remove('cargando'); });
    });
})();
