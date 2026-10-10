// Menú lateral para celulares y tablets (RNF-05, RNF-12).
// El botón de tres rayas abre el menú; se cierra con la X, tocando afuera o con Escape.
(function () {
  var html = document.documentElement;
  var boton = document.querySelector('.menu-toggle');
  var menu = document.getElementById('menu-principal');
  if (!boton || !menu) {
    return;
  }
  var cerrar = menu.querySelector('.menu-cerrar');
  var fondo = document.querySelector('.menu-fondo');

  function estaAbierto() {
    return html.classList.contains('menu-abierto');
  }

  function abrir() {
    html.classList.add('menu-abierto');
    boton.setAttribute('aria-expanded', 'true');
    // El foco pasa al menú para poder recorrerlo con el teclado
    var primero = menu.querySelector('a');
    if (primero) {
      primero.focus();
    }
  }

  function cerrarMenu(devolverFoco) {
    html.classList.remove('menu-abierto');
    boton.setAttribute('aria-expanded', 'false');
    if (devolverFoco) {
      boton.focus();
    }
  }

  boton.addEventListener('click', function () {
    if (estaAbierto()) {
      cerrarMenu(true);
    } else {
      abrir();
    }
  });
  cerrar.addEventListener('click', function () {
    cerrarMenu(true);
  });
  fondo.addEventListener('click', function () {
    cerrarMenu(true);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && estaAbierto()) {
      cerrarMenu(true);
    }
  });

  // Si la pantalla se agranda hasta mostrar el menú normal, el lateral se cierra solo
  var escritorio = window.matchMedia('(min-width: 960px)');
  var alCambiar = function (e) {
    if (e.matches) {
      cerrarMenu(false);
    }
  };
  if (escritorio.addEventListener) {
    escritorio.addEventListener('change', alCambiar);
  } else {
    escritorio.addListener(alCambiar); // Safari viejo
  }
})();
