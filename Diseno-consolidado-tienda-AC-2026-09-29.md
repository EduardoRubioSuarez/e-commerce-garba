# Diseño consolidado de la tienda de aire acondicionado

Fecha: 29 de septiembre de 2026. Estado: diseño para revisión, sin implementación autorizada.

## 1. Propósito, autoridad y lectura

El propietario desarrollará una tienda con Laravel, dominio propio, HostGator básico y Stripe Checkout. El objetivo es contener costos y complejidad, administrar el catálogo sin editar código y vender al público y a instaladores con tarifas separadas.

Este documento consolida las decisiones comunicadas por el usuario. Tiene prioridad sobre las propuestas incompatibles de `Plan-sistema-ventas-AC.md` y `Plan-sistema-ventas-AC-Laravel-HostGator-Stripe.md`, que se conservan sin modificaciones. Antes de crear este archivo se buscaron documentos Markdown en el Escritorio y sus subcarpetas; no se encontró un consolidado previo.

**Convenciones:**

- **Acordado:** requisito expresamente confirmado por el usuario.
- **Propuesto:** detalle nuevo para resolver el diseño, sujeto a su revisión. No constituye un acuerdo previo.
- **Validación previa al lanzamiento:** condición que debe comprobarse con la cuenta o información real. No impide trabajar con datos ficticios.

Esta es una especificación funcional y arquitectónica; el orden de desarrollo es general. Tras revisar y aprobar el documento se elaborará el plan detallado mediante Superpowers: writing-plans. No se han creado código, dependencias ni infraestructura.

## 2. Alcance y exclusiones

### Acordado

- Catálogo administrable: crear, editar, desactivar y publicar productos, con nombre, descripción, categoría, fotografías, características técnicas y costo de fábrica sin IVA.
- Solo los productos publicados aparecen en la tienda.
- Público como invitado, con nombre, correo y teléfono para recogida.
- Instaladores creados exclusivamente por el administrador, invitados a activar su cuenta y autenticados para consultar y comprar con su tarifa común.
- Dos aumentos generales sobre costo sin IVA, excepciones manuales por producto y tarifa, precios finales con IVA incluido y desglose en resumen.
- Carrito, cupones locales, pago mediante Stripe Checkout y pedidos con información financiera.
- Recogida en el local y dos avisos de compra: confirmación del pago y notificación manual de que está listo para recoger.

### Acordado: fuera de alcance

No habrá inventario, existencias, reservas de stock, bloqueos por agotados, registro público de instaladores, cuentas obligatorias para público, entrega a domicilio ni estados de preparación, envío o entrega. El administrador solicitará mercancía a fábrica fuera del sistema.

**Aclaración acordada tras la revisión:** la empresa contactará directamente al cliente y resolverá fuera del sistema cualquier falta de suministro, retraso, negociación, descuento posterior, devolución parcial o devolución total. La aplicación no decide ni ejecuta estas soluciones comerciales. La identificación de quien recoge, recogida por terceros y control de entrega también son procesos externos. El sistema conserva los hechos de pago y los reembolsos que Stripe comunique; no presume conocer acuerdos ni devoluciones por otros medios.

### Propuesto: límites adicionales de la primera versión

Una tienda, una moneda y un local de recogida; pago completo en una sola operación. Sin agenda de instalaciones, ERP, compras automáticas a proveedores, crédito, suscripciones, aplicación móvil, importación masiva ni facturación fiscal automatizada. Inicialmente cada presentación vendible será un producto independiente, sin motor de variantes. No se heredan los límites de SKU, estimaciones de horas ni presupuestos antiguos.

Para pruebas se propone MXN y datos ficticios. País, moneda comercial, tasa fiscal y textos reales se validarán antes de abrir ventas. El proceso fiscal que corresponda se resolverá externamente si no se amplía el alcance.

## 3. Enfoque arquitectónico

**Stack acordado:** Laravel, dominio propio y Stripe Checkout, en el paquete más barato de HostGator México que cumpla los requisitos reales del sistema. La compatibilidad del paquete específico todavía no está validada. Se prioriza el costo total, incluyendo renovación y servicios necesarios; no se exige un plan superior por su etiqueta comercial de tienda.

**Alternativas propuestas dentro de ese stack:**

| Alternativa | Ventaja | Costo de complejidad | Recomendación |
|---|---|---|---|
| Monolito Laravel con vistas Blade y panel sencillo | Una aplicación y un despliegue | Construir formularios y reglas propias | Preferida para este alcance |
| Laravel con paquete de panel administrativo | Puede acelerar CRUD | Dependencias, versiones y personalización adicionales | Evaluar al planificar, sin comprometer el diseño |
| Laravel API con frontend independiente | Más independencia del frontend | Dos aplicaciones y más autenticación/despliegue | No se justifica inicialmente |

**Propuesta seleccionada para revisión:** monolito Laravel, vistas Blade, MySQL/MariaDB con transacciones y bloqueos de filas, assets compilados fuera de producción. Sin Redis obligatorio, workers permanentes, microservicios ni servidor Node en el hosting.

```text
Público invitado / instalador autenticado
                  │ HTTPS
                  ▼
        Laravel: tienda y panel
          │      │          │
          │      │          └── Correo transaccional
          │      └── Base de datos: pedidos, cupones, eventos y avisos
          └── Stripe Checkout alojado
                       │
                       └── Webhook firmado → Laravel

Cron permitido por el hosting → conciliación y reintentos acotados
```

**Módulos propuestos:** catálogo; identidad/autorización; cálculo de precios; cupones; pedidos/pagos; notificaciones. El cálculo produce un resumen que pedidos conserva y pagos entrega a Stripe. Cupones administra exclusivamente el derecho al descuento. Notificaciones consume hechos persistidos, sin decidir si el pago existe. No se necesitan servicios separados para estos límites.

## 4. Pantallas públicas y acceso de instaladores

La siguiente distribución de pantallas es **propuesta** para cumplir el alcance acordado.

| Pantalla | Contenido y comportamiento |
|---|---|
| Inicio / catálogo | Categorías, búsqueda sencilla, productos publicados, foto y precio con IVA de la tarifa autorizada |
| Producto | Fotografías, descripción, características, contenido del equipo y precio final; selector de cantidad y agregar al carrito |
| Carrito | Productos, cantidades, importes, cupón opcional y aviso de recogida exclusiva |
| Datos y revisión | Nombre, correo y teléfono; subtotal, descuento, base, IVA y total; condición del cupón; confirmación antes de ir a Stripe |
| Stripe Checkout | Pago alojado por Stripe por el total validado en Laravel |
| Resultado / consulta protegida | Pago pendiente de confirmación, confirmado o intento no completado; instrucciones de recogida y resumen permitido |
| Acceso instalador | Inicio y cierre de sesión, activación por invitación y recuperación de contraseña; sin registro público |
| Información | Local de recogida, contacto, condiciones de compra, privacidad y procedimiento de devoluciones |

**Acordado:** no se pedirán dirección de envío ni creación de cuenta al público. No se mostrará disponibilidad de stock. Todos los instaladores activos reciben la misma tarifa para un mismo producto.

**Propuesto:** interfaz adaptable a móvil, formularios con etiquetas y errores claros, navegación por teclado y mensajes de espera durante la confirmación. Los plazos reales de recogida deben explicarse comercialmente sin afirmar disponibilidad inmediata. El segundo correo indica cuándo acudir.

## 5. Secciones del panel administrativo

Distribución **propuesta**, con las funciones comerciales acordadas:

| Sección | Funciones |
|---|---|
| Resumen | Pedidos recientes, incidencias financieras, avisos fallidos y tareas de conciliación |
| Productos y categorías | Crear/editar, publicar/desactivar, fotografías, características, costo sin IVA y vista previa de ambas tarifas |
| Precios | Aumentos generales, tasa de prueba configurable, excepciones por producto/tarifa y eliminación explícita de una excepción para volver al cálculo automático |
| Instaladores | Alta, invitación, reenvío, activación visible y suspensión propuesta; sin solicitudes públicas |
| Cupones | Tipo, valor, vigencia, compra mínima, productos, límite, habilitación para instaladores e inclusión de precios manuales |
| Pedidos | Por defecto, compras pagadas, incluyendo las que tengan reembolsos o incidencias; contacto, líneas e importes históricos, estado financiero, intentos de pago y referencias Stripe. Filtros para consultar pendientes y cerrados sin pago cuando se necesite diagnóstico |
| Notificaciones | Botón “Notificar listo para recoger”, fechas, resultado e intentos; reenvío deliberado y auditado |
| Configuración / auditoría | Datos de recogida, parámetros, cambios sensibles y diagnóstico mínimo |

El costo de fábrica solo es accesible al administrador. El panel no tendrá pantallas de stock ni columnas de estado de preparación/entrega. “Notificado para recoger” será un dato del historial de comunicaciones, nunca una máquina de estados logística.

## 6. Productos, tarifas e impuestos

### Reglas acordadas

Los productos residen en base de datos, no hardcodeados. El costo de fábrica es privado y está expresado sin IVA. El aumento es sobre ese costo, no margen sobre venta. Los valores iniciales ilustrativos son 30% público y 10% instalador, ambos editables.

Cambiar costo o porcentajes actualiza los precios automáticos y conserva las excepciones manuales y los importes de pedidos confirmados. Las excepciones son independientes por tarifa: una excepción pública no fuerza una excepción para instaladores.

### Detalles propuestos de cálculo

Las excepciones manuales se capturan **sin IVA**, etiquetadas así en el panel y acompañadas de una vista previa con IVA. Se propone guardar dinero en unidades mínimas enteras y porcentajes en precisión decimal fija, evitando aritmética binaria de punto flotante.

Para cada tarifa:

1. Precio unitario base = excepción manual, si existe; de lo contrario, costo × (1 + aumento).
2. Redondear el precio unitario base a centavos con regla decimal de mitad hacia arriba.
3. Base de línea = precio unitario base × cantidad.
4. Aplicar y distribuir el descuento elegible según la sección de cupones.
5. IVA de línea = redondeo a centavos de la base descontada × tasa de ejemplo configurada.
6. Total = suma de bases descontadas + suma del IVA de las líneas.

Se propone una tasa única inicial. El resumen mostrará subtotal sin IVA, descuento sin IVA, base gravable, IVA y total; catálogo y carrito mostrarán importes finales con IVA. Las cantidades mayores a uno usan el cálculo por línea y el mismo criterio en todo el sistema.

**Ejemplo ficticio:** costo 1,000.00; aumento público 30% → 1,300.00 sin IVA; aumento instalador 10% → 1,100.00. Con tasa de prueba 16%, los finales son 1,508.00 y 1,276.00. PROMO10 sobre una unidad pública elegible descuenta 130.00 de base; base 1,170.00 + IVA 187.20 = 1,357.20. El 16% es exclusivamente un ejemplo, no una validación fiscal.

**Propuesto:** calcular los automáticos al consultar, sin un proceso masivo obligatorio de reescritura. Invalidar cachés al cambiar reglas. Validar costos, precios y porcentajes para evitar negativos o importes inválidos. Si una modificación cambia el resumen antes de crear Checkout, exigir que el comprador confirme el nuevo total.

Al iniciar un intento se congela su cotización, incluidas tasa, tarifa, descuento y cantidades. Se honra durante esa sesión; cambios posteriores rigen nuevas cotizaciones. Esta política adicional evita cobrar un total distinto del aceptado. Un producto desactivado impide nuevos intentos, pero no invalida por sí solo un Checkout ya abierto bajo esta propuesta.

**Acordado tras el critic: una cotización por pedido, sin versiones adicionales.** Los productos, cantidades, contacto, tarifa, cupón e importes aceptados se conservan sin sobrescribirlos. Reanudar un Checkout abierto conserva el mismo pedido e intento. Si el comprador cambia la cotización, primero se confirma que el intento anterior ya no puede cobrarse y después se crea otro pedido con el nuevo resumen aceptado. El anterior queda cerrado sin pago si no tiene cobros ni intentos cobrables. Los pedidos pendientes o cerrados sin pago se conservan internamente para recuperación y eventos tardíos, pero no aparecen en el historial predeterminado de compras pagadas. No se eliminan registros para limpiar el panel ni se añade un modelo separado de cotizaciones provisionales.

## 7. Clientes e instaladores

**Acordado:** compra pública como invitado; nombre, correo y teléfono obligatorios para recogida. El nombre es informativo y no bloquea cupones. Solo el administrador crea instaladores y envía invitación para activar su acceso. No existe registro público de instaladores.

**Propuesto:** cuentas únicamente para administradores e instaladores. Invitaciones de un solo uso, con vencimiento, token almacenado de forma segura y elección de contraseña por el destinatario. Reenviar invalida la invitación anterior; nunca se envían contraseñas por correo.

El servidor decide tarifa a partir de la sesión y del permiso vigente, nunca de un rol o importe enviado por el navegador. Cambiar de sesión recalcula el carrito y solicita confirmar el nuevo total. Un instalador compra con identidad autenticada, pero confirma los datos de contacto del pedido.

Suspender un instalador impide nuevas consultas y checkouts privados e invalida sus sesiones locales. Se propone intentar expirar Checkout privados abiertos. Si el pago ganó la carrera, se registra el dinero recibido y una incidencia para revisión; no se borra ni se oculta el cobro.

Para invitados, se propone consulta de pedido mediante sesión local o enlace de alta entropía con vencimiento y token protegido. El número de pedido o el identificador de Stripe por sí solos no conceden acceso. No se crea una cuenta pública por este mecanismo.

## 8. Cupones: reglas y concurrencia

### Acordado

- Administración en Laravel; porcentuales o de importe fijo, con vigencia, compra mínima, productos participantes y límite total.
- Un cupón por pedido, sin acumulación.
- Solo público por defecto; instaladores habilitados expresamente.
- Precios manuales excluidos por defecto, con opción de incluirlos.
- Un uso de cada cupón por número de contacto normalizado; sin SMS ni cuenta obligatoria.
- Condición visible: **“Cupón válido una vez por número de contacto”.**
- Nombre informativo. La regla limita un teléfono declarado, no identifica infaliblemente a una persona.
- Reservar al iniciar pago, confirmar al validar cobro y liberar después de confirmar expiración sin pago.
- Evitar usos simultáneos del mismo cupón/teléfono.
- Si ya fue usado, permitir continuar sin descuento, mostrando el total actualizado para confirmación.

### Propuesto: semántica precisa

Vigencia con instantes de inicio incluido y fin excluido, guardados en UTC y mostrados en zona del negocio. Normalizar el código sin distinguir mayúsculas; normalizar teléfono con código de país y formato internacional canónico, conservando la entrada original para contacto. Números equivalentes comparten clave; números imposibles de interpretar se corrigen antes de pagar.

La compra mínima se evalúa sobre la **base sin IVA de los artículos elegibles antes del descuento**. El importe fijo también se define sin IVA en la moneda de la tienda; se indicará en condiciones públicas. **Acordado tras la revisión:** porcentaje máximo de 90%; el descuento fijo aplicado tampoco supera el 90% de la base elegible. **Detalle técnico propuesto:** redondear ese tope hacia abajo a centavos. Si el valor fijo se recorta, mostrar el descuento efectivo antes de confirmar. Un carrito sin artículos elegibles no recibe descuento.

Distribuir el descuento proporcionalmente entre líneas elegibles, asignando centavos residuales por mayor resto y desempate estable. Ninguna línea ni total puede quedar negativo. No incluir artículos con excepción en la tarifa efectiva salvo habilitación expresa. Los productos publicados deben tener precio efectivo positivo. Validar también el mínimo de cobro aplicable de Stripe, tanto con cupón como sin él: el límite del 90% no lo garantiza. Si el total queda por debajo, explicar que debe agregar artículos o quitar el cupón; no modificar silenciosamente el importe ni crear pedido, reserva o Checkout inválidos. Stripe publica 10 MXN como mínimo para MXN, sujeto a la moneda de liquidación y condiciones de la cuenta. [Importes mínimos de Stripe](https://docs.stripe.com/currencies#minimum-and-maximum-charge-amounts).

El límite total cuenta **usos confirmados + reservas activas**, bajo una misma transacción con bloqueo del cupón. Una clave única de cupón y teléfono normalizado impide reservas simultáneas para ese par. La reserva se vincula a un pedido e intento; un reintento del mismo checkout recupera la reserva existente.

Flujo de reserva:

1. Validar cupón, identidad, elegibilidad, vigencia y capacidad en el servidor.
2. Bloquear el cupón y reclamar el par cupón/teléfono en una transacción; crear pedido, cotización, intento y reserva juntos.
3. Crear Checkout fuera de la transacción, con clave de idempotencia persistida.
4. Al confirmar pago, pasar reserva a consumida una sola vez, junto con la transición financiera.
5. Si Stripe confirma que la sesión expiró sin pago, liberar reserva y capacidad total, preservando historial.

Cerrar pestaña, volver desde Stripe, recibir un rechazo de tarjeta o vencer el reloj local **no libera** la reserva. Un intento puede seguir pagándose. Si Stripe no responde, conservarla y conciliar; no conceder otro descuento por suponer que no hubo pago.

Si la sesión nunca llegó a existir, liberar solo tras comprobar el fallo definitivo de creación; un timeout es resultado desconocido, no prueba de inexistencia. Se propone conservar el intento en revisión si no puede resolverse con certeza.

Si el cupón está consumido, mostrar total sin descuento y pedir confirmación. Si está reservado en otro intento, informar que está temporalmente en uso; no crear otro checkout del mismo pedido mientras el anterior pueda cobrarse. Reanudar el intento existente cuando corresponda, o confirmar su expiración antes de reemplazarlo. Las ediciones de reglas del cupón no cambian una cotización reservada y vigente; no se permite bajar el límite por debajo del consumo más reservas vigentes.

**Acordado tras la revisión:** el cupón permanece usado después de cualquier devolución, parcial o total. No se restituye el derecho de uso ni se recupera capacidad del límite total por ese reembolso.

Ejemplo de la consecuencia comercial: si el cliente usa PROMO10, paga y la empresa devuelve todo porque fábrica no puede surtir, su teléfono seguirá marcado como usuario de PROMO10. Una nueva compra no obtendrá ese descuento. Se conserva el historial y cualquier compensación o acuerdo se gestiona por la empresa fuera del sistema. No se añade un flujo de negociación ni de devolución al panel.

### Controles operativos propuestos

La duración de Checkout de 30 minutos está confirmada. Los demás valores iniciales siguen siendo propuestas técnicas sujetas a validación del hosting y pruebas:

| Control | Regla inicial y comprobación |
|---|---|
| Duración del pago | Acordado: Checkout expira 30 minutos después de su creación remota; persistir su fecha real. Stripe permite de 30 minutos a 24 horas. No liberar por el reloj local. |
| Conciliación | Cada 15 minutos, recuperar intentos creando o desconocidos, consultar sesiones abiertas y vencimientos por confirmar, y reintentar eventos pendientes. Así también se detecta un pago cuyo webhook no llegó. El webhook sigue siendo la vía normal de confirmación. Validar intervalo y recursos en la cuenta. |
| Alerta en panel | Mostrar intento de creación sin resolver durante 30 minutos, reserva que siga activa 30 minutos después del vencimiento remoto o cron sin ejecución exitosa durante 45 minutos. La alerta informa; no declara fallo ni libera descuentos. |
| Recuperación administrativa | Acción autorizada y auditada para volver a consultar Stripe usando la misma lógica de conciliación. Pago confirmado consume; expiración remota confirmada sin pago libera; sesión abierta mantiene la reserva. Si Stripe no responde, conservar y mostrar incidencia. No ofrecer liberación forzada basada solo en tiempo transcurrido. |
| Solicitudes repetidas | Máximo inicial de 10 solicitudes de validación de cupón por minuto y 10 solicitudes de inicio de pago por minuto, por sesión e IP, con respuesta de espera y sin crear reservas al excederlo. No limitar webhooks con estas reglas. |
| Creación de sesiones | Máximo inicial de 5 intentos nuevos por hora por sesión e IP. Reanudar el intento existente no crea ni consume otro intento; como máximo uno cobrable por pedido y una reserva por cupón/teléfono. El teléfono no autentica al comprador ni permite revelar un pedido ajeno para reanudarlo. |
| Trabajo acotado | Cron procesa lotes con exclusión mutua, tiempos máximos de red y presupuesto de ejecución menor al permitido por el hosting; guarda progreso entre ejecuciones. Los valores de lote se fijan tras medir la cuenta. |

No se garantiza impedir abuso mediante teléfonos distintos sin verificar identidad. Estos límites reducen automatización básica; deben probarse con compradores que compartan IP y con reintentos normales. Probar que exceder el límite no aumenta reservas y que el vencimiento remoto libera capacidad una sola vez. [Vencimiento de Checkout](https://docs.stripe.com/api/checkout/sessions/create#checkout_session_create-expires_at), [cron de HostGator](https://soporte.hostgator.mx/hc/es-419/articles/28438327533843-C%C3%B3mo-funciona-un-cron-job).

Datos ficticios de prueba: **PROMO10**, 10%, solo público, 50 usos. Usar otros casos ficticios para vigencia, mínimo, exclusión manual y límite concurrente. Estas reservas son de descuentos, **nunca de mercancía**.

## 9. Pedidos y pagos con Stripe

**Acordado:** Laravel calcula y valida precios y descuentos. Stripe Checkout recibe el importe final; no se mantiene un segundo sistema de cupones en Stripe. El pago se confirma por webhooks firmados con procesamiento idempotente; visitar la página de éxito no confirma nada. No almacenar números completos de tarjeta ni CVV.

### Flujo propuesto

1. El servidor revisa productos publicados, cantidades positivas, permisos actuales, contacto y precios; el comprador acepta el resumen.
2. Crear en transacción el pedido pendiente, sus líneas históricas, intento y eventual reserva de cupón. Sin consulta ni bloqueo de stock.
3. Crear Checkout con el total congelado y una clave de idempotencia estable por intento. Persistir el identificador remoto. No mantener abierta la transacción de base de datos durante la petición de red.
4. Propuesta sencilla: enviar una línea de cobro con referencia del pedido y el total final; Laravel conserva y muestra el detalle de productos, descuento e IVA. No activar cupones ni cálculo fiscal adicional en Stripe. Así el total no sufre un segundo cálculo ni diferencias de reparto.
5. La página de regreso muestra el estado persistido; si aún falta confirmación, informa que se está verificando.
6. El webhook verifica firma sobre el cuerpo original y vinculación con cuenta/entorno, sesión, intento, moneda, importe y estado efectivo del pago. Confirmar que la sesión terminó no basta si el pago sigue pendiente.
7. Persistir el cobro, consumir el cupón y crear la notificación pendiente de compra de manera atómica e idempotente. Una bandeja durable permite reintentar el correo por separado.

**Acordado tras la revisión:** una vez confirmado y persistido el pago, intentar enviar el correo inmediatamente, fuera de la transacción financiera. Registrar primero la notificación durable y reclamar su envío con exclusión mutua para evitar carreras con cron. El procesamiento de la petición tendrá un tiempo de envío acotado; si falla, se interrumpe o no puede ejecutarse, dejar el aviso pendiente para recuperación por cron. No condicionar la confirmación del pago al correo ni mantener bloqueos de BD durante SMTP. Un duplicado del webhook no vuelve a crear ni enviar un aviso aceptado por el proveedor.

La recepción debe responder rápidamente después de persistir lo necesario. Eventos duplicados, reintentos y entrega desordenada son escenarios que deben soportarse; la firma se verifica con el cuerpo sin transformar. [Documentación oficial de webhooks de Stripe](https://docs.stripe.com/webhooks).

### Fallos y conciliación propuestos

- Una clave única por evento evita reprocesarlo; otra protección por cobro/pedido evita duplicar efectos cuando distintos eventos describen el mismo pago.
- Un timeout al crear Checkout recupera el intento original con su clave y referencias; no crea inmediatamente un segundo intento. Si el resultado sigue desconocido, se conserva para conciliación, incluso si ya no fuera seguro repetir la solicitud remota.
- Como máximo un intento potencialmente cobrable por pedido. Cambiar carrito/contacto/cupón requiere resolver o confirmar la expiración del anterior antes de crear otro pedido con su nuevo intento; no sobrescribir las líneas ni el contacto históricos del pedido anterior. Un nuevo intento del mismo pedido solo puede reutilizar su cotización sin cambios y debe volver a validar las condiciones vigentes antes de emitirse; si estas cambian el resumen, requiere nuevo pedido y aceptación del comprador.
- Rechazos de tarjeta se registran en el intento sin convertir automáticamente todo el pedido en fallo definitivo. No rebajar un pedido pagado por un evento atrasado de error o expiración.
- Conciliar sesiones inciertas y eventos persistidos pendientes con consultas autenticadas a Stripe. La conciliación usa las mismas invariantes idempotentes; la URL de éxito nunca sustituye este proceso.
- Si se detectan dos cobros reales, registrar ambos y abrir incidencia administrativa. No duplicar pedido, uso de cupón ni correo de compra; resolver el excedente mediante revisión y devolución.
- Si aparece un pago incompatible con una liberación previa, registrar el cobro y la anomalía del cupón sin ocultar dinero ni asignar otro uso automáticamente. Revisar la causa y conciliar.

**Propuesto:** tarjetas y pago único inicialmente. No habilitar métodos diferidos hasta diseñar su ciclo de reservas. Las devoluciones se ejecutan inicialmente en Dashboard de Stripe; registrar los resultados e importes parciales y definitivos comunicados por sus webhooks, sin alterar las líneas históricas.

**Acordado tras el critic: recuperación financiera limitada en la primera versión.** La conciliación periódica recupera pagos pendientes y trabajo de eventos ya recibidos; no se añade un recorrido periódico de cobros pagados para descubrir reembolsos o disputas cuyos webhooks nunca llegaron. Estos asuntos se resuelven externamente y Stripe es la referencia financiera. Laravel registra sus cambios cuando recibe los eventos correspondientes; si falta un evento, el panel puede quedar desactualizado y el administrador consulta Stripe. Esta limitación no elimina la conciliación de pagos pendientes, necesaria para recuperar compras no confirmadas localmente.

Modelo financiero propuesto: pedido pendiente o pagado, con cierre sin pago cuando todos sus intentos son terminales y no hay cobros; intentos creando, abiertos, desconocidos, pagados, fallidos o expirados; reembolsos con su propio resultado e importe. Presentar reembolso parcial/total como resumen de devoluciones confirmadas, y disputa como indicador independiente. Una devolución fallida no equivale a dinero devuelto. Ninguno de estos datos representa preparación o entrega.

### Transiciones y recuperación definidas tras la revisión

Un intento terminal sin pago requiere evidencia remota suficiente. Webhook, cron y consulta administrativa usan una misma operación transaccional de aplicación del resultado, con bloqueos en orden estable y restricciones únicas por identificador remoto. El evento recibido y su trabajo pendiente se persisten antes de responder; si falla esa persistencia, no reconocer recepción exitosa. Marcar el evento procesado dentro de la transacción que registra sus efectos.

| Resultado verificado | Transición y efectos |
|---|---|
| Intento local creado | Pedido pendiente, intento creando y reserva opcional, todos en una transacción. Guardar clave idempotente y parámetros exactos de creación antes de contactar Stripe. |
| Stripe crea o recupera la sesión | Asociar sesión única al intento y guardar vencimiento; pasar a abierto. Si un webhook ya confirmó pago, completar referencias sin rebajar el estado pagado. |
| Timeout o interrupción antes de guardar la respuesta | Intento desconocido; conservar reserva. Reintentar creación solo con la misma clave y parámetros, dentro de la ventana segura de idempotencia; nunca sustituir automáticamente la clave. Si la ventana no puede garantizarse, consultar referencias disponibles y mantener incidencia hasta obtener evidencia concluyente. |
| Fallo definitivo de creación sin sesión cobrable | Intento fallido y reserva liberada una vez. Cerrar pedido sin pago si no quedan intentos cobrables ni cobros. Un error genérico de red o servidor remoto no demuestra este caso. |
| Sesión abierta o pago sin confirmar | Mantener pedido pendiente y reserva activa. Un rechazo de tarjeta no termina una sesión que admite reintentos. |
| Pago confirmado con identidad, moneda e importe correctos | Intento pagado, cobro registrado, pedido pagado, reserva consumida y aviso de compra pendiente en una transacción. Repetir no produce efectos adicionales. |
| Sesión remota expirada y sin pago | Intento expirado, reserva liberada una vez y cierre sin pago si corresponde. Un resultado que contradiga un cobro registrado abre incidencia y no lo borra. |
| Evento tardío, duplicado o con datos incompatibles | Duplicado: ningún efecto nuevo. Evento tardío: no rebajar pago confirmado. Identidad, moneda o importe incompatibles: no confirmar la compra ni liberar cupón; conservar evidencia mínima e incidencia para verificar. |
| Segundo cobro real o pago después de liberación | Registrar el dinero y la incidencia, sin duplicar consumo ni avisos. La empresa resuelve cualquier devolución externamente. |
| Reembolso o disputa comunicado por Stripe | Registrar resultado e importes, recalcular resumen financiero y conservar compra original. No ejecutar una devolución ni restituir cupón automáticamente. |

Las referencias propias de pedido/intento se envían a Stripe para vincular resultados, pero no conceden acceso público. Desactivar enlaces de recuperación que creen otra sesión fuera de este flujo. Verificar los límites vigentes de idempotencia antes de programar los reintentos. [Idempotencia de Stripe](https://docs.stripe.com/api/idempotent_requests).

Pruebas obligatorias de recuperación: caída después de reservar y antes de contactar Stripe; después de crear la sesión y antes de guardar su ID; después de persistir un evento y antes de aplicarlo; después de confirmar pago y antes de enviar correo. En cada caso deben conservarse los importes y existir como máximo un consumo y un aviso automático. Cubrir también webhook anterior a la respuesta de creación, carreras entre webhook y cron, expiración frente a pago y timeout con ventana de idempotencia agotada.

## 10. Recogida y notificaciones

**Acordado:** al confirmar el pago se envía correo de compra. El administrador pulsa **“Notificar listo para recoger”** para enviar el segundo correo. Registrar cuándo se envían para evitar duplicados accidentales. No existen estados logísticos.

**Aclaración acordada:** la entrega física, comprobación de identidad y autorización de terceros se gestionan externamente por la empresa. El aviso de recogida no constituye un registro de entrega.

**Envío acordado:** intentar el correo de compra inmediatamente después de registrar el pago, y el aviso de recogida inmediatamente después de persistir la solicitud del administrador. Cron recupera avisos pendientes o fallidos; no introduce una espera deliberada en el envío normal. Esto no garantiza llegada en segundos: la aceptación y entrega dependen del proveedor. Aplicar reclamación exclusiva con vencimiento recuperable para que una interrupción no deje el aviso bloqueado permanentemente, conservando el tratamiento de resultados inciertos descrito abajo.

**Propuesto:** el correo de compra incluye referencia, productos, importes, IVA y la indicación de esperar el aviso de recogida. El segundo incluye referencia, local y horario configurado; no incluye costos de fábrica. Invitaciones y recuperación son correos de acceso adicionales, separados de estos dos avisos de compra.

El botón estará disponible para pedidos pagados y no reembolsados totalmente ni con incidencia financiera pendiente de revisión. Después de encolar el aviso quedará protegido contra doble clic. Mostrar por separado fecha de solicitud, aceptación por el proveedor de correo, error e intento de reenvío.

**Acordado tras el critic:** antes de cada envío inicial, reintento automático o reenvío manual del aviso de recogida, comprobar nuevamente esa elegibilidad usando la información financiera registrada en Laravel. Si el pedido ya tiene un reembolso total confirmado, cancelar el aviso pendiente y registrar el motivo; si tiene una incidencia financiera pendiente de revisión, mantener el aviso bloqueado hasta resolverla y volver a comprobar la elegibilidad. Esta comprobación no consulta Stripe ni añade conciliación de cobros pagados: si falta el webhook del reembolso, Laravel puede desconocerlo, conforme a la limitación acordada. No retira mensajes ya aceptados por el proveedor ni garantiza impedir un envío cuando el cambio financiero ocurre después de la comprobación.

Cada aviso automático tiene clave única por pedido/tipo. Un reenvío explícito crea un intento auditado, con confirmación en el panel. Si falla SMTP, el pago y el pedido permanecen válidos; reintentar con espera y mostrar la incidencia.

No se puede prometer entrega exactamente una vez mediante SMTP: un timeout después de que el proveedor aceptó el mensaje deja resultado incierto. Usar idempotencia del proveedor si existe y registrar la incertidumbre antes de reintentar. “Enviado” significa aceptación del proveedor, no lectura del destinatario. Los fallos o rebotes conocidos se muestran.

## 11. Modelo de datos propuesto

Los nombres son conceptuales; no son migraciones ni comprometen todavía nombres de archivos.

| Entidad | Datos y restricciones esenciales |
|---|---|
| Usuarios | Rol administrador/instalador, correo único, contraseña protegida, activación y suspensión |
| Invitaciones | Usuario, token protegido, vencimiento, consumo y emisor |
| Categorías | Nombre y presentación |
| Productos | Nombre, descripción, categoría, características, publicación/desactivación y costo privado sin IVA |
| Fotografías | Producto, ruta pública segura, orden y texto alternativo |
| Reglas de precio | Aumentos público/instalador, tasa configurable, moneda y versión de cambios |
| Excepciones de precio | Producto + tarifa únicos; importe manual sin IVA; ausencia significa automático |
| Cupones | Código único, tipo/valor, vigencia, mínimo, límite y banderas de elegibilidad |
| Cupón-producto | Participantes; selección explícita de todos o lista, sin interpretar accidentalmente una lista vacía como todos |
| Derecho de uso de cupón | Cupón + teléfono canónico únicos, estado libre/reservado/consumido, intento y pedido; historial de cambios separado |
| Pedidos | Referencia pública no secuencial, usuario nullable, contacto histórico, tarifa, moneda, totales, situación financiera y acceso protegido para invitado |
| Líneas de pedido | Referencia al producto y copia de nombre, cantidad, precio base, origen automático/manual, descuento, tasa, IVA y total |
| Intentos de pago | Pedido, clave idempotente única, estado, sesión Stripe única, vencimiento y resultado conocido/desconocido |
| Cobros y reembolsos | Identificadores Stripe únicos, pedido/intento, moneda, importes, resultado y fechas; admite detectar cobros adicionales |
| Eventos Stripe | ID único, tipo, referencia, recepción, procesamiento y error; datos mínimos necesarios |
| Notificaciones / intentos | Pedido o usuario, tipo, clave de deduplicación, destino, fechas, aceptación/error y reenvío auditado |
| Auditoría | Actor, acción, entidad, fecha y cambios sensibles mínimos |

Relaciones: producto pertenece a categoría y tiene fotos y hasta dos excepciones; pedido tiene líneas e intentos, y opcionalmente un instalador; cupón tiene participantes y derechos de uso por teléfono. El pedido conserva una copia del cupón y sus reglas aplicadas. Los cambios futuros no modifican esos valores.

Propuesta de integridad: claves foráneas, índices únicos, transacciones y bloqueos con orden estable. Desactivar productos/cupones en lugar de borrar referencias históricas. No copiar el costo de fábrica a representaciones públicas ni a recibos. No hay tablas de existencias, reservas de stock, direcciones de envío ni estados de entrega.

## 12. Seguridad

**Acordado:** costo solo para administrador; tarifa privada protegida en servidor; sin datos completos de tarjetas.

**Propuesto:**

- Autorización por acción y por pedido, incluyendo panel, endpoints, búsquedas y descargas. Un invitado no accede a pedidos ajenos ni a precios privados conociendo sus identificadores.
- No serializar costo o tarifa instalador en HTML/JSON público, campos ocultos, metadatos, sitemap, logs públicos o cachés compartidas. Respuestas privadas sin caché compartida.
- Validar cantidades, precios y cupones en servidor; listas de campos permitidos para impedir cambios de rol o importes desde el cliente.
- Protección CSRF salvo la ruta webhook protegida por firma; cookies seguras, sesiones revocables, limitación de intentos de acceso, cupón y checkout.
- Contraseñas con mecanismos seguros del framework y segundo factor administrativo propuesto; invitaciones y enlaces de pedido temporales y resistentes a adivinación.
- Validar tipo, tamaño y contenido de imágenes; nombres generados; impedir ejecución de archivos subidos y escapar contenido al renderizar.
- Secretos fuera de repositorio y raíz pública; acceso mínimo a BD y registros; separar pruebas y producción.
- Minimizar datos personales, enmascarar teléfonos en diagnósticos y definir retención/acceso antes del lanzamiento. No registrar tokens, secretos ni cargas completas innecesarias de pagos.
- Respaldos externos protegidos y restauración ensayada; actualizaciones y revisión de incidencias.

## 13. HostGator: condiciones de despliegue

**Acordado:** validar el paquete concreto antes del despliegue. “Básico” no identifica capacidades suficientes. Este documento no certifica compatibilidad ni tarifas del proveedor.

**Propuesto:** verificar pronto la viabilidad para evitar trabajo incompatible, sin contratar ni modificar servicios durante esta etapa de diseño.

### Investigación pública de HostGator México, 29 de septiembre de 2026

| Plan compartido | Almacenamiento publicado | Promoción: equivalente mensual publicado | Total publicado por 3 años, pagados por adelantado |
|---|---|---|---|
| Personal | 5 GB | $20.50 MXN | $737.10 MXN |
| Emprendedor | 25 GB | $29.50 MXN | $1,058.40 MXN |
| Negocios | 50 GB | $43.50 MXN | $1,562.22 MXN |
| Turbo | 75 GB | $85.75 MXN | $3,081.60 MXN |

Son importes promocionales publicados, no cotizaciones ni mensualidades contratables a ese precio. Hay diferencias entre los equivalentes mensuales redondeados, totales y precios regulares que muestran las páginas. Confirmar en el carrito importe final, impuestos, ciclo y renovación. La tabla de renovación publica para Personal $1,158 MXN anual y $2,835 MXN trienal; no asumir que esas cifras prevalecen sobre la cotización del carrito. El dominio y correo promocionales requieren revisar sus costos posteriores. [Planes oficiales](https://www.hostgator.mx/web-hosting), [precios regulares y renovación](https://www.hostgator.mx/precios-hostgator).

**Recomendación técnica inicial:** evaluar primero Personal. Su ficha publica SSL, MySQL, SSH y cron, suficientes como punto de partida para este monolito sin workers permanentes. Esto es una inferencia de viabilidad, no certificación de la cuenta. Emprendedor se evalúa si almacenamiento o recursos medidos de Personal son insuficientes; Negocios/Turbo no son obligatorios solo por vender en línea. [Ficha Personal](https://soporte.hostgator.mx/hc/es-419/articles/28438471521171-Plan-Personal-Recursos-y-funcionalidades).

La aplicación instalada es Laravel 13.33.0, comprobada con `composer show --direct`; Laravel 13 exige PHP >= 8.3 y sus extensiones. HostGator documenta PHP 8.3 para compartidos, aunque sus artículos discrepan sobre versiones adicionales. Verificar PHP web y CLI y compatibilidad del archivo de dependencias real; no asumir que PHP 8.5 local estará disponible ni utilizar APIs exclusivas de esa versión sin validar. Su documentación de cron especifica intervalos de al menos 15 minutos para compartidos. [Laravel 13](https://laravel.com/docs/13.x/deployment), [PHP compartido](https://soporte.hostgator.mx/hc/es-419/articles/28439435267603-C%C3%B3mo-cambiar-la-versi%C3%B3n-de-PHP), [cron](https://soporte.hostgator.mx/hc/es-419/articles/28438327533843-C%C3%B3mo-funciona-un-cron-job).

| Comprobación en la cuenta destino | Evidencia exigida antes de abrir ventas |
|---|---|
| Versión Laravel/PHP | Elegir versión soportada y verificar PHP web, CLI y extensiones contra sus requisitos |
| Base de datos | Transacciones, bloqueo de filas, índices únicos y capacidad suficiente |
| Despliegue | SSH/CLI o mecanismo seguro autorizado para migraciones y mantenimiento; artefacto con dependencias compatibles |
| Raíz pública | Dominio apuntando a `public/` o disposición equivalente con código y secretos fuera de `public_html` |
| HTTPS y red | Certificado válido, acceso saliente a Stripe y entrada de webhooks sin bloqueo |
| Cron | Intervalo permitido, PHP CLI y duración/recursos máximos confirmados con el paquete |
| Correo | SMTP/proveedor accesible, autenticación de dominio y límites de envío comprobados |
| Archivos y recursos | Permisos de escritura, imágenes públicas seguras, espacio, inodos, memoria y procesos |
| Recuperación | Respaldo, restauración, reversión de despliegue y conservación de la clave de aplicación |

Laravel documenta los requisitos de servidor, el punto de entrada público y la configuración de producción; deben aplicarse a la versión que finalmente se seleccione. [Despliegue oficial de Laravel](https://laravel.com/docs/deployment).

La página comercial de hosting es solo referencia del proveedor, no evidencia del entorno contratado. [HostGator México](https://www.hostgator.mx/web-hosting).

**Envío de correos acordado:** intento inmediato tras persistir el hecho que origina el aviso, con tiempos de red acotados, sin workers permanentes y sin esperar deliberadamente al cron. **Propuesta de recuperación:** tareas cortas por cron cada 15 minutos para avisos cuyo primer intento falló, quedó incierto o no llegó a ejecutarse, además de conciliación de pagos, condicionado a comprobar la cuenta. Solo estos avisos pendientes pueden esperar hasta el siguiente ciclo, o más si hay fallos o acumulación. Medir aceptación y entrega antes del lanzamiento. El webhook registra el pago independientemente del correo.

Activar exclusión mutua y límites por lote; mostrar último cron exitoso y antigüedad de tareas pendientes. Si la latencia o confiabilidad no es aceptable, revisar proveedor de correo o alojamiento con el propietario, sin contratar automáticamente.

Producción con depuración desactivada, HTTPS, permisos mínimos y assets precompilados. No exponer rutas para ejecutar comandos ni servir con el servidor de desarrollo. Probar que `.env`, logs, respaldos y dependencias privadas no sean descargables. Una reversión debe considerar también el esquema de BD y conciliar cobros pendientes.

Validaciones comerciales previas al lanzamiento: país/moneda, tasa fiscal, datos reales del local, condiciones y proceso de devoluciones, activación de Stripe, costos de renovación y comisiones vigentes. No se requieren estos datos para los ejemplos ficticios del desarrollo.

## 14. Criterios de aceptación

Los criterios de las reglas acordadas son obligatorios; los que precisan políticas nuevas aplican si se aprueban sus propuestas.

| Caso verificable | Resultado esperado |
|---|---|
| Crear/editar/publicar/desactivar desde panel | Solo publicados visibles; sin edición de código ni campo stock |
| Compra pública | Se completa con nombre, correo y teléfono, sin cuenta ni dirección de envío |
| Invitación instalador | Solo administrador la crea; activación segura; sin registro público |
| Dos instaladores activos | Misma tarifa del mismo producto |
| Visitante inspecciona HTML, JSON y caché | No obtiene costo ni tarifa privada |
| Alterar rol, precio o descuento en navegador | Prevalece validación del servidor |
| Costo 1,000 y aumentos 30%/10% | Bases 1,300 y 1,100; no fórmula de margen sobre venta |
| Cambiar costo/aumento con una excepción | Cambia automático; excepción permanece; pedidos históricos intactos |
| Cotización cambia antes del checkout | Nuevo total visible y aceptado antes de pagar |
| Cambiar cotización después de iniciar Checkout | Confirmar que el intento anterior ya no puede cobrarse; crear otro pedido con el nuevo resumen aceptado, conservando el anterior y sus referencias sin sobrescribirlos |
| Historial predeterminado de pedidos | Mostrar compras pagadas, incluso con reembolsos o incidencias; pendientes y cerrados sin pago accesibles mediante filtros de diagnóstico |
| Cupón por porcentaje o fijo | Descuento solo en elegibles, mínimo/vigencia/límite respetados y total consistente |
| Cupón en instalador o precio manual | Excluido por defecto, permitido solo con habilitación correspondiente |
| Mismo teléfono escrito con distinto formato | Se normaliza a la misma clave; nombre distinto no elude la regla |
| Dos intentos simultáneos del mismo cupón/teléfono | Solo una reserva válida |
| Dos compradores compiten por el último uso | Consumos más reservas no exceden el límite |
| Cupón ya usado | Se ofrece continuar sin descuento y confirmar nuevo total |
| Abandono, rechazo de tarjeta o reloj vencido | No liberan por sí solos la reserva |
| Expiración remota confirmada sin cobro | Reserva liberada una vez; capacidad recuperada |
| Timeout creando Stripe / webhook atrasado | Se recupera o concilia el intento; no se inventa un fallo ni se duplica checkout |
| Interrupción en cada punto de persistencia del pago | Recuperación con mismos importes, un consumo y un aviso; sin rebajar un pago confirmado |
| Exceso de solicitudes o sesiones nuevas | Respuesta de espera; ninguna reserva adicional; reanudación autorizada disponible |
| Cron detenido o reserva sin resolver | Incidencia visible al alcanzar los umbrales; consulta remota antes de liberar |
| Descuento porcentual o fijo mayor al tope acordado | Porcentaje inválido rechazado; fijo aplicado limitado al 90% de base elegible y mostrado |
| Total inferior al mínimo aplicable de Stripe | Mensaje para ajustar carrito/cupón; sin pedido, reserva ni Checkout inválidos |
| URL de éxito falsa / firma inválida | No confirma pago |
| Evento repetido o desordenado | Un cobro efectivo, un consumo y un aviso automático; no se rebaja pago confirmado |
| Dos cobros reales detectados | Ambos registrados, incidencia visible y sin duplicar efectos de la compra |
| Fallo de SMTP / cron atrasado | Pago preservado, aviso pendiente visible y reintento controlado |
| Pago confirmado o aviso de recogida solicitado con correo disponible | Intento de envío inmediato después de persistir, sin esperar cron; reclamación exclusiva del aviso |
| Devolución parcial o total de una compra con cupón | Cupón permanece consumido para ese teléfono y sigue contando en el límite total |
| Checkout de 30 minutos vencido localmente | No liberar por reloj local; comprobar expiración y ausencia de pago en Stripe |
| Doble clic en “Notificar listo para recoger” | Un aviso inicial; fecha e intentos registrados; no crea estado logístico |
| Aviso de recogida pendiente y reembolso total confirmado en Laravel antes del envío | Cancelar el aviso y registrar el motivo; no enviar ni reintentar |
| Aviso de recogida pendiente e incidencia financiera registrada | Bloquear envío, reintento o reenvío hasta resolver la incidencia y volver a comprobar elegibilidad |
| Evento recibido de devolución parcial/total/fallida | Registrar el resultado e importes comunicados por Stripe, conservando originales intactos |
| Webhook de reembolso o disputa no recibido | El panel puede quedar desactualizado; consultar Stripe como referencia financiera. No se exige descubrimiento periódico de esos cambios en cobros pagados |
| Suspensión con checkout privado abierto | Sin nuevos accesos; expiración intentada y carrera de cobro registrada |
| Pedido ajeno o archivos privados por URL | Acceso denegado |
| Compra de cualquier cantidad válida | No consulta ni reserva existencias; no bloquea por agotados |
| Prueba de hosting y restauración | Requisitos, cron, webhook, correo y recuperación comprobados |

Pruebas locales y de integración con productos, contactos y precios ficticios; Stripe en modo de prueba. Las pruebas de concurrencia requieren una base de datos con el comportamiento transaccional de producción. Una eventual transacción real controlada requiere coordinación posterior, no forma parte de esta tarea.

## 15. Orden general de desarrollo propuesto

1. Revisar este diseño y aceptar o ajustar sus políticas propuestas; evaluar primero Personal y verificar los requisitos del hosting antes de construir piezas que dependan de él.
2. Redactar el plan detallado con Superpowers: writing-plans, usando los resultados de viabilidad y el objetivo de mínimo costo.
3. Preparar base Laravel, esquema, autenticación administrativa e invitaciones.
4. Construir catálogo/panel y motor de precios con datos ficticios y separación de tarifas.
5. Desarrollar carrito, compra invitada y pedidos con importes históricos, inicialmente sin cupones.
6. Integrar Stripe Checkout, webhooks, idempotencia, expiraciones, conciliación y correo de compra. Verificar primero una compra completa sin cupón: producto → precio → Checkout → webhook → pedido pagado → correo, incluyendo recuperación de interrupciones.
7. Incorporar reglas y reservas concurrentes de cupones sobre el flujo de pago ya verificado.
8. Completar botón de aviso de recogida, invitaciones, reintentos y diagnóstico administrativo.
9. Verificar criterios críticos, seguridad, accesibilidad y fallos de red/concurrencia.
10. Validar configuración real, despliegue, restauración y operación antes de abrir ventas.

No se asignan aún archivos, comandos, horas ni tareas de programación: corresponden al siguiente documento.

## 16. Revisión de contradicciones y propuestas a revisar

| Decisión antigua descartada | Regla vigente |
|---|---|
| Shopify como plataforma recomendada | Laravel + HostGator condicionado + Stripe Checkout |
| Público con cuenta obligatoria | Compra invitada |
| Inventario y bloqueo por agotados | Sin control de existencias |
| Reservas de unidades | Solo reservas de usos de cupón |
| Entrega a domicilio y zonas | Solo recogida local |
| Estados de preparación/envío/entrega | Estados financieros y registro de notificaciones |
| Solicitud/registro público de instaladores | Alta administrativa e invitación |
| Precios sin fórmula consolidada | Aumento sobre costo sin IVA, dos tarifas y excepciones |
| Compatibilidad o cron asumidos | Validación específica del paquete antes del despliegue |

Las principales decisiones nuevas sometidas a revisión son: monolito Blade; excepciones y descuento fijo expresados sin IVA; mínimo sobre base elegible; redondeo por línea; vigencia de la cotización durante Checkout; consulta protegida para invitados; tarjetas como método inicial; cobro agregado en Stripe y devoluciones desde su Dashboard; parámetros de conciliación y reintentos condicionados al hosting.

La revisión posterior confirma gestión comercial y recogida externas, selección del hosting compatible de menor costo y prueba del flujo de compra sin cupón antes de incorporar promociones. La delegación para resolver recuperación de pagos se concreta en las transiciones de la sección 9. Quedan también confirmados el descuento máximo del 90%, Checkout de 30 minutos, cupón consumido después de devoluciones e intento inmediato de correo con recuperación por cron. Siguen siendo propuestas las frecuencias, umbrales y límites de solicitudes de los controles operativos. Personal es la opción mínima por evaluar y Emprendedor el candidato preferido si se elige un paquete superior; ninguno está contratado o certificado por esta investigación.

La revisión del usuario puede concentrarse en esas propuestas sin reabrir las decisiones ya acordadas. Aprobar este documento permitirá preparar el plan de implementación; no implica iniciar programación automáticamente.

**Acuerdos posteriores al critic:** conservar una cotización inmutable por pedido y crear otro cuando cambie, tras resolver el intento anterior; mostrar por defecto solo compras pagadas y consultar los demás pedidos mediante filtros. Para contener complejidad y costo, conservar conciliación de pagos pendientes y procesamiento de eventos recibidos, sin añadir descubrimiento periódico de reembolsos o disputas en cobros pagados; Stripe es la referencia cuando falta un evento.

Comprobar también la elegibilidad del aviso de recogida antes de cada envío, reintento o reenvío con los datos locales: un reembolso total confirmado cancela el aviso pendiente con motivo registrado; una incidencia financiera pendiente bloquea su envío hasta revisión. No requiere servicios adicionales ni consultas remotas.
