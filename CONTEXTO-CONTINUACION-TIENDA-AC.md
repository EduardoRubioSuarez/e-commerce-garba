# Contexto para continuar la tienda AC

## Estado vigente — 7 de octubre de 2026

**T10.2 aceptado y Hito A cerrado.** Pruebas de fallos, compras reales Stripe sandbox y aviso original SMTP comprobados. En el pedido 11 se observó Pending con pago remoto confirmado, antes del webhook demorado 90 segundos; después pasó a Paid con un cobro/un aviso. El propietario confirmó la vista pagada tras recargar y la recepción del correo. Evidencia y límites en «Cierre T10.2 y Hito A» al final.

**T16.1, T16.2, T17.1 y T17.2 ACEPTADOS; T17.3 COMPLETO LOCALMENTE, HITO C ABIERTO.** Encargo vigente: T16.1 → T17.3. Suite nueva 620/4239, grupo MySQL real 19/278, Pint/build/rutas y navegador 360/1280 aprobados. Hitos A/B y T15.3 conservan su aceptación previa. Pendiente evidencia de compra privada Stripe sandbox y correos de acceso/recogida reales según el registro «Verificación T16.1 → T17.3». Base de tienda y .env intactos; cambios sin commit. No iniciar T18, live ni despliegue.

Todo lo que sigue es historial salvo este estado vigente y el registro «Verificación T16.1 → T17.3». La autorización más reciente sustituye las paradas anteriores. Conservar cambios sin commit; Hito C sigue abierto y T18 no autorizado. Recepción SMTP de A/B permanece confirmada.

## Registro histórico del 5 de octubre y continuaciones

Fecha del estado registrado: 5 de octubre de 2026.

## En qué paso se detuvo

El desarrollo se detuvo en **T10.2 — Compra sin promoción y Hito A**.

T1–T9 y T10.1 están completados en su alcance local. La parte local de T10.2 también está implementada y comprobada, pero **T10.2 todavía no está aceptado y Hito A sigue abierto** porque falta una compra real en Stripe sandbox.

El encargo vigente es continuar en orden hasta **T12.2** y detenerse allí. **T11.1, T11.2, T12.1 y T12.2 no se han iniciado**.

## Por qué se detuvo

La guía del proyecto exige cerrar Hito A con evidencia sandbox antes de empezar T11–T14. T11.1 repite expresamente este requisito: las pruebas simuladas no lo sustituyen.

En la última inspección no estaban configuradas estas variables en `.env`:

- `STRIPE_SECRET_KEY`
- `STRIPE_WEBHOOK_SECRET`
- `STRIPE_ACCOUNT_ID`

Stripe CLI tampoco estaba disponible. No se instaló, no se hicieron cobros reales y no se utilizaron claves live.

El requisito pendiente es una compra de prueba desde el Checkout generado por **el mismo pedido de la aplicación**, con sus referencias y webhook correspondientes. Un evento genérico de prueba no acredita ese recorrido.

## Qué está comprobado

Se agregó `tests/Feature/PurchaseWithoutCouponTest.php`, que comprueba tres recorridos locales:

1. Compra normal de un invitado.
2. Timeout al crear Checkout, recuperando el mismo intento y su clave de idempotencia.
3. Fallo al consultar Stripe durante el webhook, recuperado por conciliación.

Los recorridos verifican contacto, revisión y aceptación del total, webhook firmado, pedido pagado, un cobro y un aviso; los duplicados no crean efectos adicionales. Se conserva el snapshot del pedido y sus líneas, sin cupones ni reservas.

Ejemplo de prueba: costo 100000, aumento público 30%, IVA 16%, total **150800 unidades mínimas MXN ($1508.00)**.

Última verificación registrada:

| Comprobación | Resultado |
|---|---|
| PurchaseWithoutCouponTest | 3 pruebas / 266 aserciones correctas |
| PaymentRecoveryTest | 10 pruebas / 32 aserciones correctas |
| Suite completa | 461 pruebas / 2430 aserciones correctas |
| Laravel Pint | Correcto |
| Revisión del diff | Sin errores de espacios |

Las pruebas usaron SQLite en memoria, HTTP simulado y `Mail::fake()`. **No acreditan Stripe sandbox, entrega SMTP ni concurrencia real MySQL/MariaDB.**

Se conservaron los archivos sin commit. No se migró ni sembró la base de datos de aplicación, se cambiaron dependencias, se hicieron commits/push ni se desplegó.

## Qué tienes que hacer ahora

1. Preparar el acceso a un entorno **sandbox/de prueba de Stripe**, nunca live.
2. Configurar de forma privada en `.env` la clave de prueba, el ID de cuenta y el secreto de firma del webhook que vaya a recibir esta aplicación. No pegar credenciales en el chat ni guardarlas en documentos o Git.
3. Preparar cómo llegarán los webhooks al entorno de prueba. Si hace falta instalar Stripe CLI u otra herramienta, autorizarlo antes; no se ha instalado ninguna automáticamente. El secreto debe corresponder al mecanismo de webhook que se utilice.
4. Pedir a Codex que prepare el recorrido en una base de datos dedicada de pruebas, compruebe la configuración y complete la compra sandbox desde el Checkout del propio pedido. Mantener intacta la base de datos de aplicación.
5. Verificar y registrar las referencias propias, modo de prueba, versión API, moneda, total, webhook recibido, pedido Paid, un cobro y un aviso. Comprobar también que el retorno sigue Pending mientras no llegue la confirmación de pago. Distinguir correo simulado de correo enviado mediante SMTP real.
6. Cuando esa evidencia permita aceptar T10.2 y cerrar Hito A, continuar **T11.1 → T11.2 → T12.1 → T12.2**, verificando y actualizando el avance después de cada punto. Detenerse al terminar T12.2.

SMTP real, concurrencia MySQL/MariaDB y cron/hosting tienen verificaciones pendientes propias; no declararlas aprobadas por haber cerrado el recorrido local.

## Mensaje para reanudar

Una vez preparada la configuración segura de prueba, puedes usar este mensaje:

```text
Lee CONTEXTO-CONTINUACION-TIENDA-AC.md y el estado vigente de
Pasos-desarrollo-supervisado-tienda-AC.md. Comprueba primero el código real
y los archivos sin commit. La configuración Stripe sandbox está preparada;
verifícala sin mostrar secretos.

Completa la evidencia real pendiente de T10.2 con el Checkout del mismo
pedido y su webhook, en un entorno y BD dedicados de pruebas. No uses
Stripe live ni migres o siembres la BD de aplicación. Si hace falta instalar
herramientas, solicita autorización para esa instalación.

Solo después de aceptar T10.2 y cerrar Hito A, continúa en orden con los
prompts completos de T11.1, T11.2, T12.1 y T12.2. Conserva el código
existente y los archivos sin commit; verifica cada punto y actualiza
la guía y el plan. Detente al terminar T12.2.
```

## Documentos de referencia

- [Guía y prompts completos](Pasos-desarrollo-supervisado-tienda-AC.md), especialmente T10.2, T11.1 y el último registro de avance.
- [Plan técnico y contratos](Plan-implementacion-tienda-AC-2026-09-29.md).
- [Diseño consolidado](Diseno-consolidado-tienda-AC-2026-09-29.md).

Este documento resume el estado registrado; al reanudar, comprobar si la configuración o el código han cambiado.

### Avance 07-10-2026 — Preparación de Resend SMTP (pendiente de acceso y destinatario)

Encargo vigente: configurar y verificar solamente Resend; detenerse al terminar esta tarea. T10.2 y Hito A siguen abiertos; no iniciar T11. Se conservó todo el trabajo sin commit. No existe .ai/rules. Versiones comprobadas: Laravel 13.33.0 y PHPUnit 12.5.36; dependencias sin cambios.

Inspección privada de .env: MAIL_MAILER=log, host local, puerto 2525, usuario/contraseña null y remitente hello@example.com. No hay RESEND_API_KEY en .env. No hay acceso mediante conector ni sesión de Resend disponible para confirmar cuenta, dominio verificado o permisos de una clave. La existencia de cuenta/dominio fuera del proyecto no está descartada: requiere información del propietario. No se modificó .env ni se activó SMTP mientras falta identificar al único destinatario autorizado. Resend documenta SMTP con smtp.resend.com, puerto 465, usuario resend y clave API como contraseña: https://resend.com/docs/send-with-smtp. Conservar MAIL_MAILER=log hasta preparar una prueba aislada del aviso específico para evitar que cron u otros procesos envíen avisos antiguos.

Consulta local de solo lectura: cinco avisos purchase Failed por mail_preparation_failed. Correspondencia verificada:

| Aviso | Pedido | Referencia | Total MXN |
|---|---|---|---|
| 1 | 5 | 2b63cc65-dbd8-434a-ae70-e3934273e8f5 | 6786.00 |
| 2 | 6 | dfe77109-a651-4309-9070-62426d08a74a | 6786.00 |
| 3 | 7 | d22d1074-426f-41b8-afc8-c049a4d34ae8 | 13572.00 |
| 4 | 8 | 42307b49-30b2-4755-934b-e4ffa4eed46c | 12818.00 |
| 5 | 9 | dc9a1a99-64bf-483c-a730-d0175848142e | 9802.00 |

Cada pedido está Paid y tiene exactamente un Payment local con livemode=false, moneda MXN e importe igual al total. Esta consulta no acredita una nueva consulta remota a Stripe ni todos los criterios del recorrido sandbox. Render real de PurchaseConfirmed para los cinco: referencia en asunto/cuerpo, total correcto e instrucción de esperar segundo correo comprobados. No se eligió ni reintentó ninguno: falta cotejar su destino con el correo autorizado del propietario. No modificar destinos ni snapshots para adaptar un pedido a la prueba.

Verificación nueva: php artisan test --compact tests/Feature/OrderNotificationsTest.php tests/Feature/PaymentRecoveryTest.php tests/Feature/PurchaseWithoutCouponTest.php: 32 pruebas, 378 aserciones, exit 0. Pruebas aisladas SQLite en memoria y correo simulado; no equivalen a SMTP. La suite completa 467/2532 es evidencia previa aportada por el usuario, no una ejecución nueva. git diff --check correcto, con aviso preexistente de normalización CRLF en .env.example. Sin cambios PHP ni frontend.

Evidencias separadas: contenido/importe/referencia verificados localmente; aceptación SMTP de Resend NO comprobada; entrega al servidor del destinatario NO comprobada; recepción en buzón NO confirmada. No se enviaron mensajes, reintentaron avisos, borraron pedidos, hicieron commits o despliegues ni se usó Stripe live.

Pendientes antes del envío: propietario indica único destinatario autorizado, confirma cuenta/dominio/remitente y guarda clave directamente en .env sin chat; cotejar aviso específico; configurar SMTP con TLS y enviar solamente ese aviso; registrar estado y Message-ID sin secretos, consultar evidencia del proveedor si hay acceso y obtener confirmación del buzón. Revisar además criterios T10.2: recorrido sandbox del mismo pedido, metadata/referencias, modo/API/moneda/importe, retorno Pending con webhook demorado, webhook correspondiente, un cobro/un aviso y recuperación de fallos. No cerrar por pruebas simuladas ni por la sola aceptación SMTP.
### Avance 07-10-2026 — Resend aceptó copia SMTP al destinatario autorizado

El propietario autorizó expresamente sebasgarba007@gmail.com. Laravel reconoce la clave privada en MAIL_PASSWORD y SMTPS smtp.resend.com:465 con usuario resend, remitente onboarding@resend.dev. No hay dominio propio; remitente temporal de prueba. MAIL_MAILER=log permanece en .env; SMTP se activó únicamente en memoria del proceso que efectuó el envío, sin habilitar recuperación masiva.

Se cotejaron los cinco avisos originales: ninguno tiene como destino el correo autorizado. Por ello NO se reintentaron ni modificaron sus destinos/estados. Se envió una sola copia de PurchaseConfirmed del pedido 9 directamente al destinatario autorizado. Alcance: plantilla y transporte real; NO acredita la recuperación del aviso original mediante NotificationDispatcher ni la entrega a su destinatario original.

Precondiciones verificadas antes de enviar: pedido Paid, referencia dc9a1a99-64bf-483c-a730-d0175848142e, total 980200 centavos MXN (9802.00 MXN), un Payment local sandbox por ese importe. Render contiene referencia y total exactos. Resend aceptó el mensaje SMTP: Message-ID 2ff769ae699b17c71e99a6af9840af1b@resend.dev. Aviso original 5 permanece Failed, historial preservado. No se cambiaron pedidos ni datos de contacto.

Evidencia: contenido/importe/referencia verificados; aceptación SMTP real comprobada por SentMessage de Symfony tras envío sin excepción; entrega al servidor de Gmail no consultada independientemente; recepción en buzón pendiente de confirmación del propietario. No equiparar aceptación con entrega/lectura. Sin dependencias nuevas, Stripe live, commits, borrados ni despliegue. T10.2/Hito A abiertos, T11 no iniciado; criterios pendientes del registro anterior siguen sin cerrar. Detenerse en esta tarea.
Verificación posterior al envío: 32 pruebas / 378 aserciones, exit 0, de OrderNotificationsTest, PaymentRecoveryTest y PurchaseWithoutCouponTest; correo simulado y BD en memoria. git diff --check correcto (advertencia CRLF preexistente). Recepción sigue pendiente de confirmación.

### Confirmación del propietario — recepción de la prueba SMTP

El propietario confirmó en esta conversación que el correo de prueba llegó a spam en el buzón autorizado. Para el mensaje 2ff769ae699b17c71e99a6af9840af1b@resend.dev, referencia dc9a1a99-64bf-483c-a730-d0175848142e y total 9802.00 MXN: contenido/importe/referencia verificados localmente, aceptación SMTP comprobada y recepción en spam confirmada por el destinatario. No se inspeccionaron cabeceras ni se obtuvo evidencia independiente del evento de entrega de Resend; no se atribuye una causa específica al filtrado. Esta recepción no acredita llegada a bandeja principal ni entregabilidad desde el futuro dominio de Garba.

Tarea de prueba temporal de Resend SMTP terminada. MAIL_MAILER=log permanece para evitar envíos automáticos; credencial privada y configuración SMTP disponibles en .env para la prueba controlada. No hubo nuevos envíos tras esta confirmación. Avisos originales sin reintentar y trabajo previo sin commit conservado. Sin cambios PHP/dependencias, Stripe live, borrados, commits o despliegue. Verificación relevante vigente: 32 pruebas / 378 aserciones del envío anterior; no se repitió por este registro documental.

T10.2 y Hito A siguen abiertos. Pendientes: revisión completa de sus criterios sandbox y recuperación, envío del aviso original a destinatario autorizado mediante el dispatcher cuando corresponda, y configuración/verificación del dominio propio antes del envío a clientes. No iniciar T11. Detenido al terminar esta tarea conforme al encargo vigente.
### Continuación T10.2 — nueva compra sandbox preparada

El propietario autorizó continuar la revisión de T10.2, sin iniciar T11. Se revisaron instrucciones, contratos y evidencia local; .ai/rules no existe. Laravel 13.33.0 y Stripe CLI 1.53.0 disponibles, sin instalar dependencias. Configuración privada comprobada: Stripe habilitado, clave sk_test (valor oculto), livemode=false, secreto webhook y cuenta presentes, API 2026-08-26.dahlia. Los eventos locales procesados charge.succeeded, payment_intent.succeeded y checkout.session.completed del 06-10-2026 contienen livemode=false y esa versión API; no es una nueva compra ni una nueva validación remota de sus importes.

No hay pedidos cuyo contacto sea el correo autorizado. Para cubrir el aviso original y el recorrido de contacto/revisión/aceptación, se preparó la tienda local en http://127.0.0.1:8000 (GET 200) y un listener Stripe sandbox con filtro checkout.session.completed,payment_intent.succeeded,charge.succeeded, forward a /stripe/webhook. El secreto del listener coincide con .env, comprobado sin imprimirlo. Listener listo con API esperada. No se activó correo SMTP global ni ejecutó cron/reintento masivo.

Se solicitó al propietario completar desde la interfaz una compra nueva con sebasgarba007@gmail.com. Cotización vigente: una unidad Minisplit 12000 BTU (prueba), subtotal 585000 centavos, IVA 93600, total 678600 MXN (6786.00), descuento cero. No se cambió el catálogo para forzar el ejemplo local de 150800; ese ejemplo continúa probado por PurchaseWithoutCouponTest. No se crearon pedidos artificiales mediante script ni se simuló un pago real. Pendiente pago del propietario, recepción del webhook de ese pedido, verificación de Paid/un cobro/un outbox y envío dirigido del aviso original a su destinatario autorizado mediante NotificationDispatcher en proceso SMTP controlado. Mantener datos e historial anteriores intactos.

Verificación nueva focalizada: PurchaseWithoutCouponTest, PaymentRecoveryTest, StripeCheckoutTest, StripeWebhookTest, PaymentTransitionsTest y OrderNotificationsTest: 69 pruebas / 576 aserciones, exit 0. SQLite en memoria y correo/HTTP simulados; no sustituyen el recorrido externo pendiente. La evidencia de retorno Pending con webhook demorado y demás criterios de T10.2 se mantiene pendiente de revisión; no declarar el hito cerrado por estas pruebas.

T10.2/Hito A abiertos; T11 no iniciado. Sin Stripe live, commits, despliegues, borrados ni cambios de dependencias. La nueva compra requiere acción del propietario en Checkout, luego continuar solo con la verificación de este recorrido.
Regresión completa nueva de esta continuación: php artisan test --compact, 467 pruebas / 2532 aserciones, exit 0. Confirmada después de preparar servidor/listener. El último cotejo aún no encuentra pedido con el contacto autorizado; compra pendiente de acción del propietario. Servidor local y listener sandbox se mantienen disponibles para este recorrido.

### T10.2 — compra nueva del propietario y aviso original SMTP aceptado

El propietario confirmó haber completado la compra. Pedido 10, referencia f6a140e5-71e9-4345-9ab4-d9b114cff5a4, contacto igual al único destinatario autorizado. Compra real distinta de la propuesta de una unidad: snapshot contiene CUATRO unidades Minisplit 12000 BTU (prueba), total 2714400 centavos MXN (27144.00), descuento cero. No se alteró el pedido ni se presentó el importe propuesto como importe pagado.

Pedido Paid, intento 10 Paid (referencia 635f1c3b-6121-486e-801a-f2c91f055491), exactamente un Payment local por 2714400 MXN, livemode=false. Eventos locales 8 charge.succeeded, 9 payment_intent.succeeded y 10 checkout.session.completed procesados, API 2026-08-26.dahlia y livemode=false. Consulta remota nueva mediante PaymentResultResolver sobre evento 10: source stripe_api, cuenta coincide con configuración, referencias pedido/intento coinciden, importe/moneda correctos, state paid y problems vacío. Checkout cs_test_a1w4JHI3XBF7RsFdVbcA18Luk6QxqmjqqOk00Wy0nEpptXofZq5s30UVeS; PaymentIntent pi_3UO04VKAJa8N2np71CLyvKBl; Charge ch_3UO04VKAJa8N2np71kauor9E. Consulta inicial aislada no pudo confirmar; nueva ejecución autorizada fuera del aislamiento confirmó, sin mostrar secretos y sin crear otro cobro.

Aviso purchase 6 originalmente Failed por mail_preparation_failed (mailer log). Precondiciones locales: destinatario exacto autorizado, Paid, un Payment, sin bloqueo financiero. Render real verificó referencia y total 27144.00 MXN. Se adelantó exclusivamente next_attempt_at de este aviso a null para el reintento dirigido autorizado; no se ejecutó recuperación masiva, no se cambiaron destinos/snapshots ni se borró historial. SMTP solo en memoria de este proceso; guardia MessageSending limita To al destinatario autorizado y rechaza CC/BCC. NotificationDispatcher envió el AVISO ORIGINAL, ahora Accepted; último NotificationAttempt Accepted, Message-ID f1fc235fe48047c5ba70e448544046e4@resend.dev. Dos intentos conservados (fallido y aceptado), no dos avisos ni dos cobros. MAIL_MAILER=log permanece en .env para prevenir envíos automáticos de los otros avisos.

Evidencias separadas: referencia/importe/contenido verificados; pago Stripe sandbox y aceptación SMTP de Resend comprobados; recepción de ESTE mensaje pendiente de confirmación del propietario (la confirmación previa en spam corresponde a otra copia y no sirve como evidencia de este envío). No se obtuvo un evento de entrega de Resend independientemente.

Pruebas nuevas: OrderNotificationsTest, PaymentRecoveryTest, PurchaseWithoutCouponTest, 32 pruebas / 378 aserciones, exit 0. Regresión completa inmediatamente anterior 467/2532. Sin cambios PHP/dependencias, Stripe live, commits, borrados o despliegue. Trabajo previo conservado.

Revisión de cierre T10.2: compra propia real, contactos del destinatario, metadata/referencias, API, modo sandbox, moneda/importe, webhook, Paid, un cobro, un outbox y aceptación del aviso original comprobados. Ejemplo exacto 150800 y recuperación de fallos cubiertos por pruebas locales, no por esta compra de cuatro unidades. No hay evidencia observada de retorno Pending con webhook demorado en ESTE recorrido; mantener ese criterio pendiente y no sustituirlo por las pruebas simuladas. Concurrencia física MySQL/MariaDB y cron/hosting no acreditados. T10.2/Hito A siguen abiertos; T11 no iniciado. Esperar confirmación de recepción y revisar evidencia pendiente antes de cualquier cierre.
### Confirmación de recepción — aviso original del pedido 10

El propietario confirmó que el aviso original del pedido f6a140e5-71e9-4345-9ab4-d9b114cff5a4 llegó a spam en su buzón autorizado. Esta confirmación corresponde al envío mediante NotificationDispatcher, Message-ID f1fc235fe48047c5ba70e448544046e4@resend.dev, total 27144.00 MXN por cuatro unidades; sustituye el pendiente de recepción del registro anterior. Contenido/importe/referencia y aceptación SMTP verificados; recepción en spam confirmada por el destinatario. No se acredita llegada a bandeja principal ni se atribuye una causa al filtrado sin inspección de cabeceras. Remitente temporal onboarding@resend.dev; dominio de Garba pendiente de adquisición/configuración y de una nueva verificación antes del lanzamiento.

No hubo nuevos envíos ni reintentos tras esta confirmación. Historial de avisos y pedidos conservado; MAIL_MAILER=log permanece para evitar envíos automáticos. Actualización exclusivamente documental: no se repitieron las pruebas, cuya evidencia vigente es 32/378 focalizada y 467/2532 completa de esta continuación.

La prueba del aviso original por SMTP y su recepción queda comprobada. T10.2/Hito A siguen abiertos por la evidencia observada pendiente de retorno Pending al demorar el webhook, además de la revisión final de criterios antes de aceptación. T11 no iniciado. Sin cambios de dependencias, Stripe live, borrados, commits o despliegues.
### T10.2 — prueba de retorno con webhook demorado preparada

Continuación autorizada del propietario. Se detuvo el listener directo anterior para que no gane la carrera de esta prueba. Servidor local de tienda conservado. Proxy temporal solo en memoria en 127.0.0.1:8001 recibe únicamente eventos livemode=false y conserva cuerpo/firma sin imprimirlos; demora 90 segundos antes de reenviar sin modificaciones a /stripe/webhook en puerto 8000. Listener sandbox listo con filtro charge.succeeded,payment_intent.succeeded,checkout.session.completed y API 2026-08-26.dahlia. No se cambió código de aplicación ni .env, dependencias, pedidos anteriores o avisos. SMTP global sigue log; no se ejecutan comandos de recuperación masiva.

Se pidió al propietario una compra sandbox nueva con el destinatario autorizado, observar «Pago pendiente» al retornar, no volver a pagar y recargar tras la demora. Observador local de solo lectura preparado para registrar Pending/cantidad de cobros y avisos, y posterior Paid. No confundir estos preparativos con evidencia de compra completada. Aún pendiente la acción del propietario, captura de retorno, procesamiento de webhooks demorados, referencias/importe de la nueva compra y ausencia de duplicados, así como envío dirigido del aviso original cuando corresponda.

Pruebas nuevas: PurchaseWithoutCouponTest, PaymentRecoveryTest, OrderNotificationsTest y OrderAccessTest: 51 pruebas / 462 aserciones, exit 0. Pruebas con HTTP/correo simulados, SQLite en memoria. T10.2/Hito A abiertos hasta verificar los resultados y revisar criterios completos; T11 no iniciado. Sin live, commits, borrados ni despliegue.
### T10.2 — retorno Pending con pago remoto y webhook demorado comprobado

Compra sandbox nueva del propietario: pedido 11, referencia 12903d0b-b1c8-4704-824f-48ac094f6836, una unidad Minisplit 24000 BTU (prueba), total 1281800 centavos MXN (12818.00). Destinatario del pedido/aviso coincide con el único correo autorizado. El propietario reportó ver «pago pendiente» después de regresar de Stripe, durante la demora.

Observación local a 2026-10-07T19:15:43Z: Pending, cero Payments y cero avisos. Consulta remota nueva mientras el pedido seguía Pending: Checkout complete, payment_status paid, livemode=false, amount_total=1281800, MXN y metadata de pedido/intento coincidente; local_payments=0. Esta consulta solo leyó Stripe y no aplicó el pago. La primera consulta aislada no confirmó; la repetición autorizada fuera del aislamiento confirmó sin secretos. Esto acredita que el retorno no paga por sí solo aunque Stripe ya haya cobrado.

Proxy retuvo 90 segundos cada evento real sin alterar cuerpo ni firma. Eventos: evt_1UO0K2KAJa8N2np7OAHluMNS (checkout.session.completed), evt_3UO0K0KAJa8N2np70cXTiVNk (charge.succeeded), evt_3UO0K0KAJa8N2np70RO9FL8J (payment_intent.succeeded). Tras la demora los tres recibieron 200 de Laravel. Observador a 2026-10-07T19:17:46Z: Paid, exactamente un Payment y un aviso. No se usaron eventos genéricos stripe trigger ni firmas fabricadas. El 200 inicial del proxy solo acreditaba encolado; los 200 posteriores corresponden al endpoint Laravel.

Aviso original purchase 7 Failed por mailer log; reintento específico autorizado mediante NotificationDispatcher, adelantando solo next_attempt_at y conservando intento anterior. Guardia limita To al correo autorizado, sin CC/BCC; SMTP en memoria del proceso, .env sigue log. Aviso/último intento Accepted, Message-ID 29156bf5218d07b8582860d905409cd6@resend.dev. Render verificó referencia y total exactos. Después del envío siguen un cobro y un aviso. No se reintentaron avisos antiguos ni se alteraron snapshots.

Revisión T10.2: ejemplo local 150800 y recuperación de fallos cubiertos por PurchaseWithoutCouponTest/PaymentRecoveryTest; regresión checkout/webhook/avisos aprobada. Evidencia sandbox propia, metadata, modo/API/moneda/importes y aviso original acreditados por compras 10 y 11; pendiente ante webhook demorado ahora observado tanto por propietario como por consulta local/remota. No se atribuye una compra sandbox de 150800: importes reales 2714400 y 1281800, diferentes del ejemplo de PHPUnit. Correo del pedido 10 recibido en spam; pedido 11 aceptado por Resend, recepción y vista pagada tras recargar solicitadas al propietario y aún sin confirmar. No declarar recibido este último por la evidencia del anterior.

Últimas pruebas nuevas de esta prueba: 51 pruebas / 462 aserciones. Suite completa de la continuación previa: 467/2532; no se repitió por esta observación externa sin cambios de código. Concurrencia física MySQL/MariaDB, operación cron/hosting y entregabilidad con dominio propio siguen pendientes propios y no se acreditan aquí. T10.2/Hito A permanecen abiertos hasta concluir confirmación y revisión final; T11 no iniciado. Sin dependencias nuevas, Stripe live, borrados, commits o despliegue.

### Cierre T10.2 y Hito A — 07-10-2026

El propietario confirmó que al recargar el retorno del pedido 11 aparece confirmado y que recibió su correo. No indicó carpeta para este último mensaje: registrar RECEPCIÓN CONFIRMADA, ubicación no especificada; los mensajes anteriores fueron confirmados en spam. Último cotejo local: pedido 11 Paid, descuento cero, total 1281800 MXN, intento 23aa7f3a-8515-46e4-b822-0440d8fb187f, un Payment sandbox por el mismo importe, aviso original Accepted al destinatario autorizado y dos intentos históricos (fallido y aceptado). Tres eventos propios processed, API 2026-08-26.dahlia, livemode=false. Message-ID 29156bf5218d07b8582860d905409cd6@resend.dev.

Revisión completa de criterios de T10.2:

| Criterio | Evidencia y alcance |
|---|---|
| Compra invitada sin promoción, ejemplo 150800 | PurchaseWithoutCouponTest: normal, timeout Checkout y fallo de consulta webhook; snapshot inmutable, contacto/revisión/aceptación, un cobro/un aviso, ausencia de tablas de cupones/reservas. Importe 150800 solo en pruebas locales; no atribuirlo a las compras sandbox. |
| Recuperación de fallos | PaymentRecoveryTest cubre caída previa a HTTP, sesión remota antes de ID local con parámetros/idempotencia originales, evento persistido, pago antes de SMTP, ventana agotada y claims abandonados sin reenvío ciego. Pruebas locales simuladas; no afirmar inyección de esos fallos en Stripe remoto. |
| Recorrido real del mismo pedido | Compras del propietario 10 y 11 desde la tienda/Checkout sandbox, referencias metadata propias, moneda/importes concordantes, modo sandbox y API esperada; no stripe trigger genérico ni pago fabricado. |
| Retorno pendiente con webhook demorado | Propietario observó Pending; consulta remota registró Stripe complete/paid y local Pending/0 cobros. Reenvío de cuerpos/firmas originales tras 90 segundos produjo 200 en Laravel; observador registró Paid/1 cobro/1 aviso. Propietario confirmó la pantalla pagada al recargar. |
| Un cobro y un aviso | Cotejos locales anteriores y posteriores conservan un Payment y un outbox por pedido; tres eventos del mismo pago no duplican efectos. |
| Correo real | NotificationDispatcher envió solo avisos originales de pedidos 10 y 11 al único destinatario autorizado; contenido/referencia/importe comprobados, aceptación SMTP y recepción confirmadas separadamente. Fallo inicial log e historial conservados. |
| Regresión | Verificación final nueva: PurchaseWithoutCouponTest, PaymentRecoveryTest, StripeCheckoutTest, StripeWebhookTest, PaymentTransitionsTest, OrderNotificationsTest y OrderAccessTest: 88 pruebas / 660 aserciones, exit 0. Suite completa previa de esta continuación 467/2532; no es una ejecución nueva del cierre. |

**Resultado: T10.2 ACEPTADO; Hito A CERRADO en el alcance de compra sin cupón y evidencia sandbox.** No implica preparación para producción. Concurrencia física MySQL/MariaDB, operación cron/hosting, dominio propio/autenticación y entregabilidad del remitente definitivo siguen pendientes de sus verificaciones. No se afirmó llegada a bandeja principal ni se consultó evento de entrega independiente de Resend.

T11 no iniciado; próximo punto disponible T11.1 solo ante nueva solicitud. Encargo actual terminado. MAIL_MAILER=log permanece en .env para impedir envíos automáticos de los avisos antiguos; no activar SMTP global sin una estrategia que respete destinatarios autorizados. Listener de esta prueba detenido; servidor local conservado para consultar el resultado. Sin dependencias nuevas, Stripe live, borrados de pedidos, commits ni despliegues. Trabajo previo sin commit conservado. Cambios de este cierre exclusivamente documentales; Pint/build no requeridos.
### Ejecución autorizada T11.1 → T12.2 — T11 completado

El usuario solicitó continuar hasta terminar T12.2 y detenerse. Plan de ejecución: T11.1 definiciones/requests/migraciones; T11.2 panel/auditoría/seed y navegador; T12.1 elegibilidad/mínimo/tope/snapshot; T12.2 distribución entera/IVA/desglose, regresión y revisión final. Se aplica el diseño/plan ya aprobado; no se solicitan aprobaciones repetidas, no se inicia T13/T14. Sin dependencias nuevas ni commits. Trabajo directo con ediciones limitadas; pruebas PHPUnit SQLite :memory: y navegador con BD exclusiva .antigravity-mcp/t11-t12-verification/browser.sqlite. No migrar ni sembrar BD de aplicación. .ai/rules no existe. Skill testing-best-practices no disponible en catálogo; se siguen PHPUnit existente y TDD.

T11.1: Coupon con código ASCII normalizado uppercase, kind Percentage/Fixed y scope All/Selected, valor entero (basis points o centavos), moneda, UTC, mínimo y max_uses, flags instalador/manual false, desactivación sin borrado. Migraciones incrementales 2026_10_07_192646_create_coupons_table y 2026_10_07_192647_create_coupon_product_table. Requests de admin activo validan definición y Selected vacío. Pruebas primero fallaron por funcionalidad ausente; 21 pruebas/74 aserciones correctas.

T11.2: rutas/vistas autorizadas, conversión horaria del negocio, importes sin IVA y condiciones visibles, auditoría y pivot atómicos con rollback si auditoría/save cancelados. Seed explícito PROMO10 10%, 50 usos, público, local/testing, idempotente y sin sobrescribir edición; no ejecutado sobre BD de aplicación. AdminCouponsTest + StoreDemoSeederTest después de Pint: 29/134, exit 0. npm run build correcto (fontaine opcional preexistente). Chromium aislado 360/1280: sin overflow, validación y guardado Enter correctos; captura móvil inspeccionada. Primer intento bloqueado WinError5, reejecución autorizada; fallo de selector amplio corregido en comprobación, sin cambio del producto. Artefactos en directorio ignorado. T11.1/T11.2 aceptados localmente; capacidad Reserved+Consumed y su edición se integran en T13, sin reservas anticipadas. Cupón configurado todavía no aplica a Checkout.

Decisión T12: reutilizar brick/math 1.0.0 YA instalado por Laravel para multiplicación/división exactas de mayor resto si el producto intermedio excede PHP_INT_MAX; no añadir ni actualizar paquetes. Contrato sin cupón conserva payload/fingerprint anteriores; snapshot se incluye solo al evaluar un cupón. Valores y reglas deben congelarse en DTO, no referencias mutables al modelo.

### Cierre T11.1–T12.2 — 07-10-2026

T12.1: CouponEvaluator revalida moneda, vigencia UTC (inicio incluido/fin excluido), All/Selected, instalador y precio manual. Mínimo solo sobre base elegible: 130000 cumple y 130001 no; cero elegibles no descuenta. PricingService::quote mantiene argumento opcional final y payload/fingerprint anterior sin cupón. Snapshot readonly congela definición/productos y descuento solicitado/aplicado, sin costo privado; cambios de reglas invalidan fingerprint.

T12.2: DiscountAllocator usa mayor resto entero, orden estable y BigInteger ya instalado para evitar overflow intermedio. Casos 101/202 con 30 → 10/20 y 1/1/1 con 1 → 1/0/0; entradas inválidas y sumas no representables rechazadas, casos límite PHP_INT_MAX y 50 distribuciones deterministas con suma exacta/bases no negativas comprobados. Quote ordena y combina productos, incluidos duplicados. IVA half-up por línea después del descuento. PROMO10: base130000, descuento13000, IVA18720, total135720 centavos MXN. Fijo solicitado200000 recortado117000, total15080; tope floor90%, porcentaje9001 rechazado y mínimo Stripe revisado sobre total final.

Carrito/revisión muestran descuento sin IVA y aviso de recorte con importe solicitado/aplicado; revisión avisa total actualizado y conserva consentimiento obligatorio. Render probado con cotización de cupón inyectada exclusivamente en pruebas; no existe todavía validación/aplicación productiva del cupón en Checkout (T14). No hay reservas ni consumo/capacidad efectivos (T13).

Verificación final nueva: CouponPricingTest 26 pruebas/715 aserciones; PurchaseWithoutCouponTest 3/281; suite completa 520/3370, exit 0. Primera regresión completa detectó 3 aserciones históricas que exigían ausencia de tabla coupons: actualizadas para comprobar compra sin descuento aun con PROMO10 activo configurado; siguen sin reservas y con un cobro/un aviso. Pint --dirty --format agent aprobado; npm run build aprobado; git diff --check exit 0 (aviso CRLF preexistente de .env.example). Cinco rutas admin de cupones, sin DELETE. Chromium móvil360/escritorio1280 verificó panel sin overflow, errores y guardado mediante teclado en BD exclusiva; servidor exclusivo detenido. Revisión final independiente de solo lectura sin hallazgos concretos; se contrastaron sus conclusiones con código/pruebas.

**T11.1, T11.2, T12.1 y T12.2 ACEPTADOS LOCALMENTE. Encargo terminado; detenerse aquí.** Siguiente punto disponible T13.1 solo ante nueva solicitud. Hito A permanece cerrado; Hito B no cerrado. Migraciones nuevas y seed no ejecutados en BD de aplicación: panel requiere esas migraciones cuando se autorice su aplicación. SQLite no acredita concurrencia física MySQL/MariaDB. Sin dependencias nuevas, Stripe live, nuevos correos, borrados de pedidos, commits ni despliegue; trabajo existente conservado. MAIL_MAILER=log permanece.

### Encargo autorizado hasta T15.3 — ejecución en curso

**T16.1, T16.2, T17.1 y T17.2 ACEPTADOS; T17.3 COMPLETO LOCALMENTE, HITO C ABIERTO.** Encargo vigente: T16.1 → T17.3. Suite nueva 620/4239, grupo MySQL real 19/278, Pint/build/rutas y navegador 360/1280 aprobados. Hitos A/B y T15.3 conservan su aceptación previa. Pendiente evidencia de compra privada Stripe sandbox y correos de acceso/recogida reales según el registro «Verificación T16.1 → T17.3». Base de tienda y .env intactos; cambios sin commit. No iniciar T18, live ni despliegue.

T13.2 preparado con phpunit.mysql.xml, guardia MYSQL_TEST_* y base exclusiva garba_coupon_tests antes de toda migración destructiva, workers independientes y barreras: mismo teléfono, último uso, pago/expiración, deadlock y snapshot REPEATABLE READ. MySQL80 está en ejecución, pero acceso existente falló con 1045; variables de prueba aún ausentes. Ejecución real de 5 casos se detuvo antes de migrar; no acreditar PASS ni concurrencia. Se solicitó al usuario guardar las credenciales directamente en .env, sin secretos en chat.

Ruling: avanzar con trabajo independiente T15 mientras se resuelve T13.2 — dependencias de T15 en sección 3 son T2/T4/T7/T9, todas aceptadas; T14 requiere concurrencia T13 comprobada y permanece sin iniciar — si el cambio de secuencia causa una incompatibilidad, deberá corregirse antes de la regresión final; no se cierra Hito B ni se inventa evidencia sandbox.

### Avance del encargo hasta T15.3 — 07-10-2026

**T16.1, T16.2, T17.1 y T17.2 ACEPTADOS; T17.3 COMPLETO LOCALMENTE, HITO C ABIERTO.** Encargo vigente: T16.1 → T17.3. Suite nueva 620/4239, grupo MySQL real 19/278, Pint/build/rutas y navegador 360/1280 aprobados. Hitos A/B y T15.3 conservan su aceptación previa. Pendiente evidencia de compra privada Stripe sandbox y correos de acceso/recogida reales según el registro «Verificación T16.1 → T17.3». Base de tienda y .env intactos; cambios sin commit. No iniciar T18, live ni despliegue.

T13.1: par cupón/teléfono canónico único permanente, Free/Reserved/Consumed, capacidad Reserved+Consumed, historial inmutable y consumo en la misma transacción que Payment. Snapshot y enlaces coupon_id/coupon_use_id del pedido se adjuntan una sola vez antes de Stripe y no cambian al editar cupón. Liberación por expiración remota concordante o fallo definitivo local de un intento Failed jamás enviado, sin sesión/intención/cobro; un intento enviado no admite evidencia local. Refund no restituye cupón. Pago antiguo conserva dinero e incidencia sin consumir derecho reutilizado; expiración antigua no libera derecho ajeno. Editor impide reducir capacidad por debajo del uso ocupado. CouponReservationTest: 27 pruebas/71 aserciones, exit 0. Migración adicional incremental 2026_10_07_211103_add_coupon_snapshot_links_to_orders_table.

T13.2: phpunit.mysql.xml y MySqlTestEnvironment comprueban testing, variables, nombre exacto garba_coupon_tests y acceso ANTES de migrate:fresh; base de aplicación excluida. Workers con PIDs distintos, barreras y tablas InnoDB: teléfonos equivalentes, último uso, pago/expiración, deadlock con reintento, capacidad bajo snapshot anterior, pago durante suspensión y consumo de reserva confirmada después del snapshot. Los siete requieren ejecución real; ninguno acreditado. Grupo concurrency excluido de SQLite habitual; configuración dedicada siempre falla claramente si falta backend.

T15.1: altas solo administrativas, invitación 48h configurable, hash de un uso, invalidación al reenviar y elección de contraseña sin enviarla. Dos cuentas activadas consultan el mismo producto con tarifa privada 127600 en pruebas; sin precios individuales ni registro público. Auditoría y outbox atómicos, rollback ante auditoría fallida. Reutiliza recuperación e historial T9; payload cifrado oculto, borrado al aceptar/cancelar, aviso vencido cancelado antes del reintento.

T15.2: Password Broker nativo y respuesta genérica para cuentas desconocidas/inactivas/suspendidas; token de un uso, cambio de contraseña revoca remember/sesiones y aumenta auth_version. Segundo factor administrativo: código hash de seis dígitos, cinco minutos, cinco intentos, reenvío invalida anterior y límites por sesión/IP/usuario. Panel/mutaciones denegados mientras está pendiente; desafío ligado a sesión y versión. Contraseñas/códigos/tokens no se guardan como old input. Correos de acceso usan el mismo dispatcher; fakes no certifican entrega.

T15.3 local: suspensión auditada revoca permiso primero, remember, sesiones, invitaciones y enlaces de pedido, preservando contacto/líneas/dinero. Cuenta y auth_version revalidadas en middleware, tarifa, pedido, controlador y transacción Checkout. Recuperación no crea nuevo Checkout de cuenta revocada. Expiración HTTP fuera de transacciones por gateway/resolver/applier comunes; silencio conserva Pending. Cobro que gana carrera conserva Paid, un Payment/un aviso e incidencia. InstallerAccessTest: 16 pruebas/149 aserciones; regresiones T2/T7/T8/T9 incluidas en suite completa. Carrera física preparada en MySQL pero PENDIENTE: T15.3 no aceptado todavía.

Revisión final de solo lectura exigida por executing-plans señaló dos riesgos importantes: actor con auth_version obsoleta y búsqueda de reserva bajo snapshot anterior. Primer caso corregido con RED→GREEN en OrderCheckoutTest. Segundo corregido con locator inmutable y lectura actual en orden cupón→derecho; sin locator suministrado se bloquean cupones por ID antes de buscar derecho, evitando snapshot obsoleto a costa de posible serialización de pagos. Medir/revisar en MySQL; NO afirmar RED→GREEN físico hasta tener backend. Nuevos tests detectaron además faltantes snapshot/liberación definitiva y expiración antigua contra derecho reutilizado: corregidos con RED→GREEN local. Revisión estática y SQLite no cierran T13.2.

Verificación final NUEVA: php artisan test --compact, 569 pruebas/3672 aserciones, exit 0. Pint --dirty --format agent aprobado. npm run build exit 0; aviso preexistente fontaine opcional, sin instalar paquetes. git diff --check correcto (aviso CRLF preexistente .env.example). Chromium en BD exclusiva .antigravity-mcp/t13-t15-verification/browser.sqlite completó invitación/activación/login, recuperación genérica, bloqueo de panel antes de MFA y panel tras verificar; recuperación/MFA/listado sin overflow en 360/1280. Mailer inválido del fixture impidió todo correo externo. Script inicial pulsaba logout en vez de verificar: selector corregido y recorrido completo repetido con invitación nueva; no fue fallo del producto. Último fallo de regresión fue nombre de ruta incorrecto añadido al test de tarifa, corregido a store.product; suite completa repetida después y aprobada.

Estado: T13.1 y T15.1/T15.2 ACEPTADOS LOCALMENTE; T13.2 BLOQUEADO; T14/Hito B SIN INICIAR; T15.3 IMPLEMENTADO LOCALMENTE, pendiente aceptación física. Nuevas migraciones solo en bases aisladas; panel/autenticación requieren aplicarlas cuando corresponda, no se aplicaron a la tienda. MAIL_MAILER=log permanece. Ningún correo real nuevo, dependencia, Stripe live, borrado de pedidos, commit ni despliegue. Conservar trabajo sin commit. Servidor exclusivo de verificación 8015 detenido; servidor previo de la tienda conservado. Detenerse: NO iniciar T16; encargo completo aún no cerrado.

Para desbloquear: guardar directamente en .env MYSQL_TEST_HOST, MYSQL_TEST_PORT, MYSQL_TEST_DATABASE=garba_coupon_tests, MYSQL_TEST_USERNAME y MYSQL_TEST_PASSWORD con acceso exclusivo a esa base de pruebas creada; nunca pegar claves en chat. Ejecutar siete casos, resolver fallos reales y solo después iniciar T14. Para T14/Hito B faltará recorrido propio Stripe sandbox PROMO10 total135720, un consumo/un aviso y devoluciones parcial/total sin restituir uso; fakes no sustituyen evidencia externa.


### Desbloqueo MySQL y arranque T14 — 07-10-2026

El propietario guardó MYSQL_TEST_* directamente en .env. Conexión/nombre exacto/InnoDB comprobados antes de migrar únicamente garba_coupon_tests. Primer intento llegó a la base pero falló bootstrap del worker: imports declarados después de usarlos. Corregido con error saneado/fallo temprano de barrera; no era contraseña ni fallo de aplicación. Prueba específica RED→GREEN; suite real ampliada con doble Checkout y recuperación bajo snapshot externo: 9/101, exit0, MySQL8.0.41 REPEATABLE-READ. Reversión temporal controlada de conteo/búsqueda anteriores reprodujo 2 fallos físicos (sobre-reserva y Paid sin consumo); correcciones restauradas, 9/101 aprobado de nuevo. T13.2 y carrera T15.3 aceptados; no se modificó la base de la tienda ni hubo HTTP Stripe/SMTP real. T14 iniciado con contrato aprobado y TDD; validación sin reserva, PROMO10 atómico, consentimiento renovado y recuperación son pruebas locales en desarrollo. Hito B sigue abierto. Retomar T14, no T16.


### Verificación final local T14 y T15.3 — 07-10-2026

T14.1: validación 10/min por sesión e IP no reserva ni crea pedidos. Confirmación bloquea datos vigentes y guarda pedido/líneas/intento/snapshot/reserva en una transacción; red fuera de locks. Carrito/revisión muestran PROMO10, descuento sin IVA y texto de uso único por contacto. Cambiar/quitar cupón o perder capacidad exige nueva aceptación; flags/precios vigentes se revalidan sin cobrar un total distinto silenciosamente. Compra con nombre diferente y teléfono equivalente no obtiene otro uso; errores no revelan pedido/correo ajeno.

T14.2 local: timeout conserva Reserved y parámetros/clave remotos originales; cambios posteriores en catálogo/cupón no modifican la compra reservada. Rechazo definitivo del primer envío se aplica y libera atómicamente; rechazo de una solicitud previamente enviada queda Unknown. Pago consume una vez; pago tardío contra derecho reutilizado conserva dinero y registra incidencia. Refund parcial/completo duplicado conserva Consumed/capacidad y un aviso original. Revisión independiente detectó expiración recordada fuera del applier: corregido con RED→GREEN; remember conserva elegibilidad para conciliación hasta resolución remota completa. Reemplazo de intento con cupón usa resolver/applier comunes; sin evidencia no cierra ni libera. Prueba física adicional detectó revalidación errónea del código renombrado al recuperar bajo snapshot antiguo: RED→GREEN, lectura actual de confirmación anterior a reglas nuevas.

Verificación final: php artisan test --compact 587/3799 PASS; php vendor/bin/phpunit -c phpunit.mysql.xml 11/127 PASS, MySQL8.0.41 REPEATABLE-READ, InnoDB y PIDs separados. Incluye último cupón con rollback de compra perdedora y recuperación tras renombrar/desactivar cupón bajo snapshot externo, además de carrera de suspensión/pago. Pint --dirty --format agent PASS; npm run build PASS (aviso opcional fontaine preexistente). Chromium en base exclusiva: 360/1280 sin overflow, aplicar PROMO10 135720, quitar 150800, consentimiento desmarcado; login y compra instalador con tarifa127600, Stripe desactivado. Invitación/activación/MFA ya comprobados en recorrido aislado previo. T15.3 aceptado en alcance de revocación y carrera real; no implica acreditación de entregabilidad SMTP de todos los correos de acceso.

Hito B pendiente externo: fixture nueva sandbox.sqlite aislada, producto costo100000 y PROMO10 10%, total135720; endpoint8014 listo y responde200. Credenciales existentes leídas sin mostrarlas; solo sk_test y livemode=false, correo de contacto restringido a sebasgarba007@gmail.com, mail log. Se solicitó al propietario una compra sandbox; inspección final todavía cero pedidos/derechos/avisos. Tras esa compra: conciliar únicamente su intento, cotejar metadata/importe/moneda/modo y un Payment/un Consumed/un aviso; SMTP dirigido únicamente al aviso original autorizado, verificar contenido/aceptación/recepción separadamente; devolución parcial y resto solo de ese cobro sandbox, comprobar Consumed y ausencia de duplicados. No reintentar avisos antiguos. La tienda y su servidor previo permanecen intactos; no se aplicaron nuevas migraciones allí. No cerrar T14.2/Hito B por mocks; no iniciar T16.
### Cierre T14.2, Hito B y encargo hasta T15.3 — 07-10-2026

El propietario confirmó «ya llegó»: RECEPCIÓN CONFIRMADA del aviso original PROMO10 enviado exclusivamente a sebasgarba007@gmail.com, Message-ID fa276f8def39258f9ffcbe6a027c8644@resend.dev, referencia48eaa13f-217c-4658-8358-5cc27ecdf140 y total1357.20MXN. No especificó carpeta; no atribuir bandeja principal ni spam. Contenido/referencia/importes, aceptación SMTP del proveedor y recepción confirmada por el destinatario son evidencias distintas, todas ahora comprobadas. No se volvió a enviar ningún correo para registrar esta confirmación.

Comprobación final NUEVA de solo lectura: final-state exit0, pedidoPaid, unPayment sandbox por135720, dos Refunds Succeeded por10000 y125720 (total135720), derechoConsumed, UNA transición aConsumed, capacidad ocupada1, un avisoAccepted con el Message-ID anterior, cero incidencias y mailer globallog. Parámetros/clave del intento original rechazado preservados y auditoría de sustitución autorizada conservada. Render de resultado exit0/HTTP200: «Pago confirmado», total1357.20MXN y ausencia de botón para otro pago. No se volvió a cobrar, reembolsar o enviar SMTP durante este cierre.

T14.2 ACEPTADO Y HITO B CERRADO por evidencia sandbox/SMTP real, recepción confirmada y concurrencia MySQL previamente ejecutada; no por mocks. Encargo hastaT15.3 COMPLETADO en sus alcances documentados. Se mantiene explícito el límite: pago conciliado individualmente y eventos refund.created originales importados mediante API autenticada; esta fixture no acredita entrega automática de webhook firmado ni recuperación productiva automática del caso de expires_at. La sustitución de intento fue una excepción operativa de fixture autorizada por el propietario, auditada y sin duplicar pedido. Las suites locales y físicas ya aceptadas conservan sus cifras587/3799 y11/127 como evidencia anterior, sin presentar una nueva ejecución inexistente.

Los tres documentos se actualizan al estado cerrado; no quedan operaciones externas de esta prueba por ejecutar. Se conserva fixture e historial completos y todos los cambios sin commit. MAIL_MAILER=log permanece; base de la tienda intacta. Sin dependencias nuevas, migraciones sobre la tienda, live, borrados, commits ni despliegue. DETENERSE AQUÍ: T16 NO INICIADO.

### Compra PROMO10, aviso SMTP y devoluciones reales comprobadas — 07-10-2026

El propietario completó Checkout pero la vista permanecía Pending al recargar. Consulta remota y conciliación dirigidas SOLO al intento5f6219d8-e547-4209-a47f-09063ef91131 confirmaron sesiónComplete/Paid, PaymentIntent pi_3UO3hyKAJa8N2np70caPVm23 y Charge ch_3UO3hyKAJa8N2np70Ipz1Csy por135720 MXN, livemode=false, cuenta/metadata/moneda/importe concordantes, API2026-08-26.dahlia y problems vacío. Se aplicó el resultado dos veces mediante PaymentResultApplier: pedidoPaid, exactamente unPayment, derechoConsumed, una transición aConsumed y un aviso purchase, cero incidencias. La fixture aislada no tenía recepción automática acreditada para ese pago; recargar no consulta Stripe. Es conciliación remota real, NO webhook firmado ni mock.

Contenido PurchaseConfirmed verificado por separado: referencia48eaa13f-217c-4658-8358-5cc27ecdf140, subtotal1300.00MXN, descuento130.00MXN, IVA187.20MXN y total1357.20MXN. NotificationDispatcher envió SOLO el aviso original1 a sebasgarba007@gmail.com; guardia MessageSending exige To único autorizado y ningúnCC/BCC. Configuración SMTP únicamente en memoria, MAIL_MAILER=log intacto. Aviso y últimoNotificationAttempt Accepted; Message-ID fa276f8def39258f9ffcbe6a027c8644@resend.dev. Dos intentos de aviso conservados (preparación fallida porlog y SMTP aceptado), UN aviso y UN cobro. Ningún aviso antiguo reintentado. Aceptación del proveedor comprobada; RECEPCIÓN PENDIENTE de confirmación del propietario, solicitada expresamente.

Devolución parcial sandbox real10000centavos: re_3UO3hyKAJa8N2np70ELB1f2T, estadoSucceeded; Stripe y Laravel acumulado10000, charge.refunded=false. Devolución del resto125720: re_3UO3hyKAJa8N2np70YB4wAUD, Succeeded; Stripe/Laravel acumulado135720 y charge.refunded=true. Ambas exclusivamente sobre el mismo Charge, con claves de idempotencia por etapa y guardias de fixture/modo/cuenta/importe/referencias. Eventos ORIGINALES refund.created consultados por API Stripe: evt_3UO3hyKAJa8N2np70SzCMNiR y evt_3UO3hyKAJa8N2np7086lRQ2r; persistidos con auditoría de importación dirigida y aplicados dos veces cada uno por resolver/applier comunes. No se fabricaron refunds/eventos ni firmas; no se atribuye esta importación a entrega webhook. Cada resultado remoto se volvió a consultar para confirmar identidad/estado/importe.

Después de cada etapa: cupónConsumed, una transiciónConsumed, capacidad ocupada1, unPayment, unavisoAccepted, cero incidencias. Al finalizar hay DOS Refunds locales por total135720; pedido permanecePaid conservando el dinero histórico. Render final de OrderController autorizado: HTTP200, «Pago confirmado», total1357.20 y sin botón de otro pago. Pint del harness y sintaxis aprobados; git diff --check sin errores salvo aviso CRLF preexistente. Sin cambios productivos, migraciones, dependencias, live, borrados, commits ni despliegue. Suites587/3799 y MySQL11/127 corresponden a la evidencia anterior y no se repitieron por estas operaciones externas. Actualizar aceptación T14.2/Hito B solo cuando el propietario confirme recepción del aviso; T16 no iniciado.

### Nuevo intento autorizado dentro del mismo pedido PROMO10 — 07-10-2026

El propietario autorizó conservar el intento anterior como rechazado y crear otro en EL MISMO pedido, con clave nueva y reserva trasladada. Operación exclusiva de sandbox.sqlite: nueva consulta Stripe comprobó cuenta configurada, ausencia de Checkout para el intento original y ausencia de PaymentIntents con su referencia o la del pedido mediante listado completo; reenvío idempotente de los parámetros originales volvió a devolver HTTP400 por expires_at. No se interpretó un simple timeout como evidencia suficiente.

En una transacción se conservaron pedido48eaa13f-217c-4658-8358-5cc27ecdf140 y snapshot/contacto/total135720, intento anterior e907c399-a464-4462-8683-871bd67771d9 con parámetros/clave/first_sent_at originales y estadoFailed por excepción operativa autorizada; se creó intento5f6219d8-e547-4209-a47f-09063ef91131 y se trasladó el MISMO derechoReserved sin pasar aFree ni devolver capacidad. AuditEntry sandbox.authorized_checkout_attempt_replacement conserva evidencia y referencias; CouponUseHistory añade Reserved→Reserved con evidencia de auditoría. Esta intervención manual en fixture NO es recuperación automática productiva ni una nueva prueba de concurrencia.

Stripe sandbox creó Checkout cs_test_a1QmhkJHjjoHfdc9NuKlcGu8qV4wEoaW0NgAceTXB8C4YPJsxC1evKncbo, statusOpen/payment_statusUnpaid, total135720 MXN, livemode=false, expires_at1791415240. El nuevo intento usa vencimiento inicial de31min como margen para satisfacer el mínimo remoto30min; excepción exclusiva de esta prueba, sin cambiar StripeGateway productivo. Cuenta y metadata validadas; HTTP fuera de transacción. Estado local posterior: un pedidoPending, dos intentos históricos (Failed/Open), ceroPayments, un derechoReserved y ceroavisos. No se creó otro pedido ni se modificó la base de la tienda.

Render de OrderController con autorización de sesión aislada: HTTP200, botón «Continuar al pago seguro», enlace al mismo Checkout e importe1357.20MXN comprobados; GET del producto8014 responde200. El primer render directo carecía de $errors del middleware: el harness añadió ViewErrorBag y repitió correctamente; no fue fallo de la vista productiva. Pint del script y sintaxis correctos; Pint --dirty aprobado y git diff --check sin errores (aviso CRLF preexistente). Se preparó modo dirigido send-original, todavía NO ejecutado: solo el aviso purchase de este pedido y el destinatario autorizado, contenido/importes comprobados antes de SMTP; mailer global siguelog.

Se pidió al propietario recargar el MISMO pedido, pulsar «Continuar al pago seguro» y completar una sola compra sandbox. Pago real y evidencia Laravel/consumo/correo/recepción/refunds siguen pendientes; Hito B abierto y T16 no iniciado. Cambios únicamente en fixture/harness ignorado y documentos; sin dependencias, migraciones, live, borrados, commits ni despliegue.

### Excepción de vencimiento autorizada y rechazada por Stripe — 07-10-2026

El propietario autorizó renovar únicamente expires_at del intento e907c399-a464-4462-8683-871bd67771d9, conservando pedido y clave de idempotencia. En sandbox.sqlite se guardaron los parámetros originales, fecha anterior, first_sent_at y hash de la clave en AuditEntry sandbox.authorized_checkout_expiration_repair antes de renovar expires_at a now+31min. Guardia verificó base aislada, pedido/intento exactos, total135720, derechoReserved, ceroPayments/avisos, ausencia de sesión remota y rechazo400 de los parámetros originales por vencimiento.

Stripe rechazó la solicitud renovada: HTTP400 idempotency_error, exige parámetros idénticos para la clave ya utilizada. No se creó Checkout ni cobro. Se RESTAURARON los parámetros y vencimiento originales; clave, first_sent_at y referencias no cambiaron. Auditoría adicional sandbox.expiration_repair_rejected_restored conserva el rechazo y la restauración. Inspección posterior: un pedidoPending, un intentoUnknown sin sesión, un derechoReserved, ceroPayments/avisos. Pint del script aprobado; trabajo sin commit y sin cambios productivos/dependencias/migraciones.

La autorización puntual no cubre sustituir la clave inmutable. Próxima propuesta: excepción operativa exclusiva de la fixture, con nuevo contraste remoto y auditoría, conservar el intento anterior como rechazado y crear otro intento en EL MISMO pedido, con clave nueva y vencimiento válido, transfiriendo la reserva sin liberar capacidad ni cambiar dinero/contacto/snapshot. No ejecutado; requiere autorización específica del propietario. Hito B continúa abierto; aún no hay correo ni refunds y T16 no iniciado.

### Aclaración del propietario — Checkout no llegó a mostrarse

El propietario confirmó que NO apareció Stripe ni se realizó pago. La pantalla mostró «No hay un pago confirmado para este pedido» y referencia48eaa13f-217c-4658-8358-5cc27ecdf140, coincidente con la fixture. No considerar su mensaje previo como compra completada. Nueva inspección conserva un pedidoPending, intentoUnknown, derechoReserved, ceroPayments y ceroavisos; servidor8014 responde200. Recuperar este intento requiere resolver su expires_at original rechazado; no modificarlo silenciosamente porque el contrato aprobado exige conservar parámetros y clave originales. No se ejecutaron envíos, refunds ni cambios financieros. Hito B sigue abierto y T16 fuera de alcance.

### Comprobación tras aviso de pago PROMO10 — 07-10-2026, 15:39 hora local

El propietario indicó haber pagado con cupón. La fixture contiene exactamente un pedido: 48eaa13f-217c-4658-8358-5cc27ecdf140, total135720 MXN, descuento13000, estado Pending. Intento e907c399-a464-4462-8683-871bd67771d9: Unknown, sin stripe_session_id; first_sent_at2026-10-07T22:35:09Z. Hay un derecho Reserved, cero Payments y cero avisos. No se puede acreditar pago por la confirmación verbal sin concordancia remota.

Consulta inicial dentro del aislamiento falló; consultas autorizadas fuera de él sí llegaron a Stripe sandbox. Búsqueda por referencia no encontró sesión. Listado remoto completo disponible: once sesiones, has_more=false, ninguna con este pedido/intento ni total135720. No se aplicaron pagos ajenos. Recuperación específica con los parámetros y clave de idempotencia originales no creó Checkout: respuesta Stripe HTTP400 invalid_request_error, «The `expires_at` timestamp must be at least 30 minutes from Checkout Session creation.» La expiración original es1791414309; el reintento observado se hizo en1791412745. No se modificaron parámetros, snapshots, referencias ni derecho Reserved; un rechazo de un intento previamente enviado no libera automáticamente el cupón.

Servidor exclusivo8014 reiniciado como proceso oculto fuera del aislamiento para permitir red, conservando sandbox.sqlite; GET del producto200. No se ejecutaron migraciones ni seeds. Se solicitó al propietario confirmar si Stripe mostró pago completado y compartir únicamente la referencia visible del pedido. No se pidió repetir la compra ni se creó otro pedido. Cobro remoto, Paid, consumo, aviso original, SMTP/recepción y refunds continúan sin acreditar; Hito B abierto. MAIL_MAILER=log y restricción al destinatario autorizado conservados; ningún correo ni devolución ejecutados. Diagnóstico seguro en verify-promo-payment.php del directorio de pruebas ignorado. Pint --dirty --format agent PASS; Pint del script aplicado. Suite587/3799 y MySQL11/127 siguen siendo evidencia previa, no repetida en esta comprobación. Sin cambios de dependencias, live, borrados, commits, despliegue ni inicioT16.

### Reanudación PROMO10 — 07-10-2026, 15:33 hora local

Comprobación directa con inspect-sandbox.php: sandbox.sqlite conserva cero pedidos, cero derechos de cupón y cero avisos. No existe compra local de esta prueba que conciliar; no se creó otro pedido ni se solicitó otro cobro sobre un pedido existente. El servidor del puerto 8014 estaba detenido; se reinició con el router-sandbox.php existente, sin ejecutar setup, migraciones ni semillas. La URL http://127.0.0.1:8014/productos/promo10-prueba-sandbox responde HTTP 200. Bootstrap conserva la base aislada, exige clave de prueba Stripe y livemode=false, y mantiene mail.default=log; el router limita el contacto al destinatario autorizado.

Se solicitó al propietario completar una sola compra PROMO10 por 135720 centavos MXN usando sebasgarba007@gmail.com. Cobro remoto, estado pagado Laravel, consumo único, contenido/referencia/importe del aviso original, aceptación SMTP, recepción confirmada y devoluciones parcial/total del mismo cobro siguen PENDIENTES de esa compra. No se enviaron correos ni se reintentaron avisos antiguos. Hito B sigue abierto; T16 no iniciado. Se conservaron todos los cambios sin commit y la base de la tienda intacta. Las cifras 587/3799 y MySQL 11/127 corresponden a la ejecución anterior documentada; no se repitieron suites en esta reanudación documental.

### Verificación T16.1 → T17.3 — 07-10-2026 (hora local)

Encargo explícito del propietario: continuar desde T16.1 hasta T17.3. T16.1, T16.2, T17.1 y T17.2 ACEPTADOS; T17.3 COMPLETO LOCALMENTE, pendiente de evidencia externa. Hitos A y B conservan su aceptación previa. Hito C ABIERTO. T18 no iniciado.

**Implementación:** listado administrativo Paid por defecto con reembolsos/incidencias incluidos; filtros Pending/ClosedUnpaid/todos sin borrar historial; detalle de contacto autorizado, líneas y totales originales, tarifa, todos los cobros/refunds, neto solo de refunds succeeded, reserva, eventos pendientes asociados por referencias conocidas y avisos/motivos. Configuración local y textos de condiciones/privacidad/devoluciones/retención mediante updateCurrent, versión y auditoría redactada. Retención es una política editable, sin purga automática de evidencia financiera. Migración incremental 2026_10_08_003106_add_operational_information_to_store_settings_table.php, aplicada SOLO a bases de prueba.

Recuperación administrativa auditada de un intento pendiente mediante PaymentReconciler::recoverAttempt y resolver/applier/Gateway comunes a cron. Diagnóstico de último éxito/atraso, 15 minutos de frecuencia, alerta a 45 minutos, presupuesto/lote, eventos pendientes y avisos con destinatario enmascarado; recuperación de avisos Failed/Pending auditada, sin reintentar Uncertain a ciegas. Reenvío de recogida con intención y confirmación existentes, conservando elegibilidad local. No botón de devolución ni descubrimiento periódico de refunds/disputas en pedidos pagados.

**Defectos comprobados y corregidos:**
- Errores administrativos 403 devolvían no-cache en vez de no-store: redacción/headers también en respuesta de excepción, con regresión.
- Sin SESSION_SECURE_COOKIE, una petición HTTP bajo perfil producción emitía sesión sin Secure: valor predeterminado según APP_ENV; cookie Secure/HttpOnly/SameSite=Lax comprobada. La comprobación de HTTPS/servidor real sigue en T18.
- Evidencia de conciliación usaba STRIPE_LIVEMODE vacío como string: conversión explícita a booleano evita falsa incidencia mode_mismatch; aceptación integral RED→GREEN mediante consulta administrativa.
- Dos confirmaciones en transacciones externas REPEATABLE READ causaban deadlock por INSERT repetido de StoreSetting::current: lectura de fila existente antes de createOrFirst evita el upgrade de locks; prueba simultánea MySQL RED→GREEN.
- Revisión independiente detectó cancel_reason inexistente y eventos pendientes ausentes del detalle: corregidos con test de asociación, exclusión de evento ajeno, motivo y payload redactado. Segunda revisión confirmó ambos arreglos.
- Playwright detectó filtro de pedidos desbordando a 360 px: ancho del label/select corregido; misma comprobación aprobada después.

**Resultados reales de esta ejecución:**

| Comando/comprobación | Resultado |
|---|---|
| php vendor/bin/phpunit tests/Feature/AdminOperationsTest.php | 10 pruebas / 91 aserciones, PASS |
| php vendor/bin/phpunit tests/Feature/RefundAndDisputeTest.php | 8 / 104, PASS |
| php vendor/bin/phpunit tests/Feature/StoreSecurityTest.php | 11 / 72, PASS |
| php vendor/bin/phpunit tests/Feature/StoreAcceptanceTest.php | 4 / 173, PASS |
| php artisan test --compact --filter=AdminOperationsTest\|RefundAndDisputeTest\|StoreSecurityTest\|StoreAcceptanceTest | 33 / 440, PASS (filtro entre comillas en PowerShell) |
| php artisan test --compact | 620 / 4239, PASS, exit 0 |
| php vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/PaymentConcurrencyTest.php | 8 / 151, PASS |
| php vendor/bin/phpunit -c phpunit.mysql.xml --group concurrency | 19 / 278, PASS; cupones 11 y pagos 8, sin skips |
| Motor/aislamiento/base | MySQL 8.0.41 / REPEATABLE-READ / InnoDB / garba_coupon_tests exclusivamente |
| php vendor/bin/pint --dirty --format agent | PASS |
| npm run build | PASS; aviso opcional fontaine, sin instalar dependencias |
| php artisan route:list --except-vendor | 59 rutas; sin register ni ejecución de refunds |
| php artisan schedule:list | Ambos comandos cada 15 minutos, limit=25 |
| git diff --check y sintaxis worker/migración | PASS; aviso CRLF preexistente de .env.example |

Los dos runners MySQL comparten la BD exclusiva y deben ejecutarse SECUENCIALMENTE. Una ejecución intermedia superpuesta produjo errores de tablas existentes en dos casos de cupones; fue un error del harness de esta sesión, no se atribuye a producto. La ejecución final del grupo completo fue secuencial, sin esos errores. Nunca ejecutar ambos runners físicos en paralelo contra garba_coupon_tests.

Concurrencia con procesos distintos, barreras y locks físicos: mismo cobro con eventos distintos, resolver de webhook frente a cron, pago/expiración, doble recogida, claim cron/petición, dos cobros distintos preservados con incidencia, doble confirmación dentro de snapshots externos y reanudación con clave/parámetros originales. HTTP/SMTP simulados fuera de transacciones; ningún cobro o mensaje externo emitido por esta suite.

**Navegador:** Chromium/Playwright en base SQLite y sesiones/cache exclusivos en .antigravity-mcp/t16-t17-verification. A 360 y 1280 px: galería de DOS PNG ficticios cargados con alt/orden, precio público 1508.00 IVA incluido, navegación por teclado, login/MFA real local antes de panel, dashboard/pedidos/detalle/settings/diagnóstico sin overflow ni errores JS, filtros, neto 1408.00 tras refund parcial, PII visible solo en contacto autorizado y enmascarada en diagnóstico, guardado y validación de settings y texto público actualizado. Capturas guardadas en ese directorio ignorado. Este recorrido acredita interfaz y fixtures; no fotografía comercial final ni SMTP/Stripe real. El servidor exclusivo 8016 quedó detenido; servidores previos conservados.

**Trazabilidad de aceptación:**

| Criterio | Evidencia vigente / límite |
|---|---|
| §§2–5 catálogo/panel/exclusiones | Storefront/AdminCatalog + navegador T17; sin inventario/envío/logística/registro público |
| §6 precio, IVA, excepciones, aislamiento | Pricing/CouponPricing/StoreSecurity; navegador precio y pruebas de ambas tarifas |
| §7 invitado/instalador/MFA/token/suspensión | StoreAcceptance 4 recorridos, InstallerAccess/OrderAccess/AdminSecondFactor; evidencia sandbox privada y correo de acceso externos pendientes |
| §8 reglas y exclusividad del cupón | CouponPricing/CouponReservation/CouponConcurrency reales; Hito B conserva sandbox público y consumo tras refunds |
| §9 snapshots, dinero, interrupciones y carreras | PaymentTransitions/PaymentRecovery/PaymentConcurrency; consulta administrativa común, sin barrido de pagados |
| §10 avisos/elegibilidad/timeout/reenvío | OrderNotifications/AdminOperations/StoreAcceptance y claim físico; correo simulado no acredita entrega. SMTP original público de A/B es evidencia histórica |
| §§11–12 integridad/seguridad/retención | StoreSecurity + constraints y concurrencia; retención editable conserva evidencia, valores comerciales reales antes de ventas |
| §§13–14 infraestructura y pruebas externas | Hosting/cron real/HTTPS/SMTP de acceso/recogida y restauración en T18; no acreditados por pruebas locales |

**Pendiente T17.3 / Hito C:** recorrido de compra privada REAL en Stripe sandbox con su propio pedido/intento y concordancia remota, así como evidencia externa de los correos de acceso/MFA/recuperación y recogida que aún no figure en el historial, distinguiendo aceptación SMTP y recepción. Las compras públicas, SMTP original y refunds sandbox ya aceptados en A/B se conservan como evidencia previa, sin presentar una nueva ejecución. No se solicitaron nuevos pagos, envíos ni devoluciones externas en esta continuación. Completar lo pendiente requiere coordinar fixture/cuenta de prueba y destinatario autorizado con el propietario; no reintentar avisos antiguos.

Decisión de ejecución: el encargo explícito autoriza los cinco puntos en orden y sustituye las paradas antiguas en T15/T16. Conservar los prompts y registros históricos; trabajar en el árbol actual con sus cambios sin commit porque contiene la implementación previa. No crear otro plan ni descartar archivos. Se reutilizó TDD/diagnóstico/verificación y revisión independiente de solo lectura; testing-best-practices sigue sin estar disponible. Sin Gemini/Antigravity en esta ejecución.

Base de aplicación intacta, .env y MAIL_MAILER=log conservados, sin dependencias nuevas, claves expuestas, Stripe live, borrado de historial, commits/push ni despliegue. Detenerse en T17.3 local; NO iniciar T18 ni declarar Hito C cerrado.
