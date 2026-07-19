# Requerimientos — Tornea

Transcripción de `Requerimientos.pdf` (documento del proyecto final) para poder buscarla como texto.

## Requerimientos funcionales

### Usuarios y acceso
- RF-01. El sistema permitirá iniciar sesión.
- RF-02. El sistema permitirá cerrar sesión.
- RF-03. El administrador podrá crear usuarios administrativos.
- RF-04. El administrador podrá modificar usuarios administrativos.
- RF-05. El administrador podrá eliminar usuarios administrativos.
- RF-06. El sistema asignará un rol a cada usuario.
- RF-07. El sistema mostrará diferentes opciones según el rol del usuario.

### Participantes
- RF-08. El sistema permitirá registrar participantes.
- RF-09. El sistema permitirá modificar los datos de un participante.
- RF-10. El sistema permitirá eliminar participantes.
- RF-11. El sistema permitirá consultar participantes registrados.
- RF-12. El sistema permitirá inscribir participantes en torneos.

### Equipos
- RF-13. El sistema permitirá registrar equipos.
- RF-14. El sistema permitirá modificar equipos.
- RF-15. El sistema permitirá eliminar equipos.
- RF-16. El sistema permitirá agregar participantes a un equipo.
- RF-17. El sistema permitirá consultar equipos registrados.

### Torneos
- RF-18. El sistema permitirá crear torneos.
- RF-19. El sistema permitirá modificar torneos.
- RF-20. El sistema permitirá eliminar torneos.
- RF-21. El sistema permitirá configurar torneos.
- RF-22. El sistema permitirá publicar torneos.
- RF-23. El sistema permitirá cerrar torneos.
- RF-24. El sistema permitirá definir la fecha de inicio de un torneo.
- RF-25. El sistema permitirá definir la fecha de finalización de un torneo.
- RF-26. El sistema permitirá seleccionar el tipo de torneo.

### Liga
- RF-27. El sistema permitirá crear torneos tipo liga.
- RF-28. El sistema generará automáticamente los enfrentamientos de una liga.
- RF-29. El sistema mostrará la tabla de posiciones de una liga.
- RF-30. El sistema actualizará la tabla de posiciones cuando se cargue un resultado.

### Eliminación directa
- RF-31. El sistema permitirá crear torneos de eliminación directa.
- RF-32. El sistema generará automáticamente las llaves del torneo.
- RF-33. El sistema actualizará las llaves cuando finalice un enfrentamiento.
- RF-34. El sistema determinará automáticamente quién avanza de ronda.

### Sistema suizo
- RF-35. El sistema permitirá crear torneos con sistema suizo.
- RF-36. El sistema generará los emparejamientos de la primera ronda.
- RF-37. El sistema generará los emparejamientos de las siguientes rondas.
- RF-38. El sistema calculará la puntuación acumulada de cada participante.

### Rondas
- RF-39. El sistema permitirá generar rondas.
- RF-40. El sistema permitirá publicar rondas.
- RF-41. El sistema permitirá cerrar rondas.
- RF-42. El sistema permitirá consultar rondas creadas.

### Resultados
- RF-43. El sistema permitirá registrar resultados.
- RF-44. El sistema permitirá modificar resultados.
- RF-45. El sistema permitirá validar resultados.
- RF-46. El sistema permitirá consultar resultados.
- RF-47. El sistema actualizará las clasificaciones cuando se registre un resultado.

### Consulta pública
- RF-48. El usuario público podrá ver torneos publicados.
- RF-49. El usuario público podrá consultar calendarios.
- RF-50. El usuario público podrá consultar resultados oficiales.
- RF-51. El usuario público podrá consultar tablas de posiciones.
- RF-52. El usuario público podrá consultar llaves de competición.
- RF-53. El usuario público podrá ver perfiles públicos habilitados.

### Participantes autenticados
- RF-54. El participante podrá consultar los torneos en los que participa.
- RF-55. El participante podrá consultar su calendario de enfrentamientos.
- RF-56. El participante podrá consultar sus resultados.
- RF-57. El participante podrá consultar su posición en el torneo.
- RF-58. El participante podrá modificar los datos permitidos de su perfil.

### Administración
- RF-59. El administrador podrá habilitar módulos de competencia.
- RF-60. El administrador podrá deshabilitar módulos de competencia.
- RF-61. El administrador podrá consultar reportes.
- RF-62. El administrador podrá gestionar configuraciones generales del sistema.

### Auditoría
- RF-63. El sistema registrará los cambios realizados en la información.
- RF-64. El sistema guardará la fecha y hora de cada cambio.
- RF-65. El sistema guardará el usuario que realizó cada cambio.
- RF-66. El administrador podrá consultar el historial de cambios.

## Requerimientos no funcionales

- RNF-01. El sistema deberá desarrollarse como una aplicación web.
- RNF-02. El sistema deberá utilizar PHP para el backend.
- RNF-03. El sistema deberá utilizar HTML para la interfaz.
- RNF-04. El sistema deberá utilizar CSS para el diseño visual.
- RNF-05. El sistema deberá utilizar JavaScript para funciones interactivas.
- RNF-06. El sistema deberá utilizar MySQL como base de datos.
- RNF-07. El sistema deberá seguir la arquitectura MVC.
- RNF-08. La interfaz deberá ser fácil de entender para usuarios sin conocimientos técnicos.
- RNF-09. Los formularios deberán utilizar textos claros.
- RNF-10. Los mensajes de error deberán explicar qué ocurrió.
- RNF-11. Los mensajes de error deberán indicar cómo corregir el problema.
- RNF-12. El sistema deberá funcionar correctamente en celulares.
- RNF-13. El sistema deberá funcionar correctamente en tablets.
- RNF-14. El sistema deberá funcionar correctamente en computadoras.
- RNF-15. El sistema deberá validar los datos ingresados por el usuario.
- RNF-16. El sistema deberá validar los datos tanto en frontend como en backend.
- RNF-17. Las contraseñas deberán almacenarse de forma segura.
- RNF-18. El sistema deberá limitar el acceso según el rol de cada usuario.
- RNF-19. El sistema deberá registrar actividades importantes realizadas por los usuarios.
- RNF-20. El sistema deberá mantener la información guardada aunque se cierre la aplicación.
- RNF-21. El sistema deberá evitar el ingreso de datos incorrectos.
- RNF-22. El sistema deberá mantener organizados los módulos del proyecto.
- RNF-23. El sistema deberá reutilizar componentes cuando sea posible.
- RNF-24. El sistema deberá manejar los errores desde un único mecanismo centralizado.
- RNF-25. El sistema deberá utilizar rutas claras y fáciles de entender.
- RNF-26. El código deberá estar documentado.
- RNF-27. El proyecto deberá tener una estructura de carpetas ordenada.
- RNF-28. El sistema deberá ejecutarse mediante Docker.
- RNF-29. El sistema deberá incluir scripts de instalación.
- RNF-30. El sistema deberá incluir scripts de respaldo.
- RNF-31. El sistema deberá incluir scripts de mantenimiento.
- RNF-32. El sistema deberá permitir realizar respaldos periódicos.
- RNF-33. El sistema deberá minimizar la pérdida de información ante fallos.
- RNF-34. El sistema deberá conservar un historial de modificaciones realizadas.
- RNF-35. El sistema deberá aplicar medidas de seguridad basadas en OWASP.
- RNF-36. El sistema deberá utilizar HTTPS cuando sea publicado.
- RNF-37. El sistema deberá permitir la trazabilidad de las acciones realizadas.
- RNF-38. El sistema deberá contar con documentación técnica y funcional.

## Modelo MER (resumen)

Entidades principales: `usuario`, `rol`, `usuario_rol`, `participante`, `equipo`, `equipo_participante`, `torneo`, `modulo`, `configuracion`, `ronda`, `encuentro`, `resultado`, `inscripcion`, `posicion`, `auditoria`.

Notas del modelo:
- `inscripcion` tiene tanto `participante_id` como `equipo_id` opcionales, porque el sistema maneja torneos individuales y por equipo según el tipo.
- `encuentro` tiene el mismo patrón: referencias a participante o equipo según corresponda.
- `resultado` usa relación 1 a 1 con `encuentro`.
- `posicion` cubre tanto clasificaciones individuales como grupales.
- `torneo.tipo` es un enum: `liga`, `eliminacion_directa`, `suizo`.
- `torneo.estado` es un enum: `borrador`, `publicado`, `en_curso`, `cerrado`.
- `encuentro.estado` es un enum: `pendiente`, `en_curso`, `finalizado`.
- `ronda.estado` es un enum: `generada`, `publicada`, `cerrada`.

El diagrama completo (con tipos de columna y FKs) está en `Requerimientos.pdf`, sección "Modelo MER".
