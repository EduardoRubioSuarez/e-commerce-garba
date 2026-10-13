# Pasos de desarrollo supervisado de la tienda de aire acondicionado

> Modalidad vigente 07-10-2026: Codex continúa por encargo explícito desde T16.1 hasta T17.3. Los contratos y registros anteriores se conservan como historial. T18 fuera de alcance.

**Fecha:** 7 de octubre de 2026. **Estado:** **T16.1, T16.2, T17.1 y T17.2 ACEPTADOS; T17.3 COMPLETO LOCALMENTE, HITO C ABIERTO.** Encargo vigente: T16.1 → T17.3. Suite nueva 620/4239, grupo MySQL real 19/278, Pint/build/rutas y navegador 360/1280 aprobados. Hitos A/B y T15.3 conservan su aceptación previa. Pendiente evidencia de compra privada Stripe sandbox y correos de acceso/recogida reales según el registro «Verificación T16.1 → T17.3». Base de tienda y .env intactos; cambios sin commit. No iniciar T18, live ni despliegue.

**Objetivo:** construir la tienda paso a paso, con entregas pequeñas que puedan revisarse antes de continuar.

**Arquitectura:** monolito Laravel con Blade y Tailwind, precios calculados en servidor, pedidos con cotizaciones inmutables, Stripe Checkout y notificaciones durables. MySQL/MariaDB para verificar concurrencia y para producción.

**Stack:** conservar el del [plan técnico](Plan-implementacion-tienda-AC-2026-09-29.md); sus versiones son la inspección del 29-09-2026, no sustituyen comprobar las instaladas al ejecutar.

**Especificación:** [Diseño consolidado](Diseno-consolidado-tienda-AC-2026-09-29.md), con los acuerdos posteriores recogidos en el plan técnico, incluida la elección de HostGator empresarial.

## Cómo usar los dos documentos

Esta guía determina el punto a ejecutar, su alcance y su aceptación. Cada punto incluye un prompt completo para Claude. La sección T correspondiente del plan técnico aporta el contrato de archivos, firmas, casos, valores y restricciones; ambos documentos y el diseño deben leerse desde el repositorio. No basta con el título del punto. Si el resumen del prompt difiere del contrato técnico, contrastar código y acuerdos posteriores antes de implementar; no cambiar una decisión comercial por conveniencia.

Los puntos están ordenados. Cada punto depende del anterior y de las dependencias de su T indicadas en la sección 3 del plan técnico. No se crean contratos alternativos al subdividir. Una T solo se cierra cuando todos sus puntos y sus pruebas completas están aceptados. Los hitos A, B y C conservan su significado original.

## Cómo continuar con Claude

Mensaje inicial listo para copiar:

```text
Continúa este proyecto siguiendo Pasos-desarrollo-supervisado-tienda-AC.md.
Lee el estado vigente y ejecuta únicamente el siguiente punto pendiente cuyo
anterior y dependencias estén aceptados, utilizando su Prompt completo.
Encargo hasta T15.3 completado en el alcance documentado; T14.2 aceptado y Hito B cerrado. Compra PROMO10 sandbox Paid, consumo único, aviso original SMTP Accepted y recibido por el propietario, devoluciones parcial/total reales comprobadas. Conservar cambios sin commit y detenerse. No repetir compras, correos ni refunds, migrar la tienda, reintentar avisos antiguos ni iniciar T16 sin nueva solicitud.
Comprueba primero el código real y los archivos sin commit.
Lee también AGENTS.md, las reglas aplicables y los contratos del plan técnico.
Implementa y verifica directamente; no necesitas Codex, Gemini ni Antigravity.
Conserva el trabajo T1–T9 y T10.1. No reconstruyas los puntos marcados como aceptados.
No instales Boost: ya está instalado; su bootstrap en CLAUDE.md es antiguo.
No cambies dependencias, migres la BD de aplicación, hagas commits, despliegues
o uses Stripe live. Si falta sandbox u otra evidencia, completa lo local
independiente y registra el gate pendiente, sin inventar que pasó.
Actualiza esta guía y el plan con la evidencia comprobada y detente al terminar.
```

Para elegir un punto concreto: «Ejecuta únicamente T7.1 usando su Prompt completo de esta guía». Para un punto aceptado, solicitar explícitamente revisión o corrección; su prompt no autoriza recrear el código. Los bloques ya contienen instrucciones y verificaciones específicas, sin campos que el usuario deba completar. Los contratos del plan siguen siendo parte obligatoria del encargo para evitar divergencia entre copias.

### Contexto de traspaso comprobado al 02-10-2026

- T0.1 y T1–T6 aceptados localmente. Próximo T7.1; Stripe, webhook, correos de compra y promociones posteriores aún no implementados. Tokens privados y snapshot local sí existen.
- Última regresión registrada: 392 pruebas/1872 aserciones; Pint, build y navegador 360/1280 px aprobados. Son evidencia histórica, no sustituyen comprobar el estado al comenzar.
- Trabajo integrado mayormente sin commit, con archivos untracked. HEAD por sí solo no representa el avance. No hacer reset/clean/stash destructivo ni partir de un checkout vacío creyendo que es la base completa.
- Las migraciones comerciales están en archivos, sin aplicarse por estas entregas a la BD de aplicación. No usar migrate:fresh ni seed sobre esa BD para continuar; preparar entorno de pruebas y solicitar por separado cualquier cambio de BD de desarrollo.
- Concurrencia MySQL real de doble confirmación y pruebas financieras aún pendientes. SQLite no acredita locks. La inspección visual específica de la galería administrativa T3.2 seguía pendiente; incluirla en aceptación visual T17 si no se aporta antes.
- Hito A exige compra sin cupón real sandbox antes de T11–T14. Hitos B/C y lanzamiento tienen gates propios. No declarar completos por mocks ni por el número de pruebas.
- HostGator empresarial es el destino acordado; cuenta concreta/compatibilidad no certificadas. Sin nuevas dependencias, contratos de servicios, commits, cobros live o despliegues automáticos.

## Responsabilidades y ciclo por punto

1. El usuario selecciona un punto o solicita continuar el siguiente habilitado. Esto autoriza implementación local y correcciones de ese alcance, no las acciones externas indicadas como separadas.
2. Claude inspecciona reglas, versiones, código, git y registro; confirma la base real y dependencias antes de editar. Los prompts de puntos aceptados sirven de referencia, no de lista para rehacer.
3. Claude escribe pruebas relevantes, implementa el punto y verifica en entorno aislado. No depende de delegación ni de skills/conectores específicos de otro agente; si una herramienta no existe, aplica las convenciones y comandos disponibles sin inventar una invocación.
4. Claude revisa diff, contratos, privacidad, atomicidad y casos límite. Ejecuta pruebas focalizadas, regresiones afectadas, Pint y build cuando corresponde, además de las verificaciones externas exigidas para cerrar un hito.
5. Si usa copia aislada, integra solo archivos verificados después de comprobar que la base no cambió. Si trabaja en el workspace, preserva cambios ajenos y describe los archivos de su punto. No crear commits sin petición separada.
6. Claude marca solo lo verificado y actualiza guía/plan con comandos, resultados y límites. Ante bloqueos externos, mantiene la casilla o hito correspondiente pendientes y señala la condición concreta.
7. Claude informa resultado y siguiente punto, y se detiene hasta nueva instrucción. T18 requiere permisos externos explícitos; preparar un procedimiento no concede desplegar ni cobrar en live.

Los acuerdos técnicos y de seguridad previos se mantienen. Esta modalidad sustituye la exigencia histórica de Codex como supervisor y Gemini/Antigravity como implementador, también donde el plan conserva ese relato. No sustituye las reglas Laravel de AGENTS.md. Si el bootstrap antiguo de CLAUDE.md sugiere instalar Boost, confirmar el paquete ya existente en composer antes de cualquier instalación; las dependencias no se cambian sin autorización.

Si faltan sandbox, SMTP o MySQL/MariaDB, registrar la verificación pendiente. No cerrar un hito que exige esa evidencia. Las decisiones comerciales reales, configuración remota, restauraciones y lanzamiento conservan sus autorizaciones específicas.

## Reglas comunes de aceptación

- Conservar compra invitada, dos tarifas, recogida local y altas de instaladores exclusivamente administrativas. Sin inventario, envíos ni estados logísticos.
- Importes enteros en centavos, tasas de precisión fija y redondeo del plan. Nunca confiar en precios o roles enviados por el navegador.
- Mantener nombres y firmas compartidos del plan. En T4/T6, descuento cero y base descontada igual a la base original mientras no exista cupón; conservar esos campos en DTOs y snapshot.
- No editar migraciones ya aplicadas para incorporar módulos posteriores: añadir migraciones incrementales cuando corresponda. No anticipar claves foráneas a tablas inexistentes ni trasladar la identidad de cobros a una columna única del pedido: se admiten cobros adicionales con incidencia.
- T8 persiste `OrderNotification` y su esquema completo; T9 añade intentos y envío, sin volver a crear la tabla. T8 puede consultar Stripe para resolver evidencia, pero no depende de SMTP.
- Timeout de Stripe conserva incertidumbre; cancelación de navegador o reloj local no demuestra ausencia de pago. HTTP y SMTP siempre fuera de locks financieros.
- Ningún cupón antes del hito A. La concurrencia financiera exige procesos independientes sobre MySQL/MariaDB; SQLite no acredita esos puntos.
- Recogida: reembolso total local cancela aviso pendiente; incidencia pendiente bloquea envío y reenvío hasta revalidación. No se añade descubrimiento periódico de reembolsos en cobros pagados.
- Generar PHP con Artisan según AGENTS.md. No ampliar dependencias, carpetas base ni documentación fuera de este encargo.

## T0 — Preparar la ejecución

- [x] **T0.1 — Comprobar entorno y establecer la base.** Codex confirma `git status --short`, archivos actuales, AGENTS.md, `.ai/rules/index.md` si existe y reglas por ruta/palabra clave. Ejecuta `composer show --direct`, consulta `package.json`, `php --version`, `php artisan route:list --except-vendor` y las pruebas existentes con `php artisan test --compact` sobre entorno de pruebas. Verifica disponibilidad de MySQL/MariaDB dedicado, sin instalarlo ni modificar producción. Confirma estado Antigravity, copia aislada y protocolo de verificación. **Salida:** entorno apto o bloqueo concreto, archivos base identificados y encargo T1.1 listo. No implica que el sistema comercial exista. Evidencia y límites en el registro de avance.

### Prompt completo — T0.1

```text
Claude, atiende únicamente T0.1 — Comprobar entorno y establecer la base.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: Solo inspección de código, configuración sin secretos, dependencias, rutas, reglas y entorno de pruebas. No modificar código de aplicación.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Determina git HEAD y git status --short; considera también archivos untracked: buena parte de T1–T6 está integrada sin commit. Lee reglas y versiones, identifica BD exclusiva y acceso MySQL/MariaDB sin instalar servicios. Examina phpunit.xml antes de ejecutar pruebas. No exijas Antigravity: Claude puede trabajar directamente. Resume base preservada y condiciones pendientes.

Verificación específica:
php --version; composer show --direct; consulta package.json; php artisan route:list --except-vendor; php artisan test --compact únicamente tras comprobar aislamiento de BD. No certifiques MySQL por detectar solamente puerto o servicio.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T1 — Configuración comercial y auditoría

Archivos, interfaces y pruebas: T1 del plan técnico. Comando de cierre: `php artisan test --compact tests/Feature/StoreSettingsTest.php`.

- [x] **T1.1 — Persistir configuración y tarifas.** Crear `PriceTier`, `UserRole`, `StoreSetting`, factory, migración y `config/store.php`. Probar `StoreSetting::current()` y valores ficticios MXN/3000/1000/1600. **Aceptación:** configuración persistente con enteros y valores demo explícitos.

### Prompt completo — T1.1

```text
Claude, atiende únicamente T1.1 — Persistir configuración y tarifas.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: config/store.php; app/Enums/PriceTier.php y UserRole.php; app/Models/StoreSetting.php; database/factories/StoreSettingFactory.php; migración create_store_settings_table; tests/Feature/StoreSettingsTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Configuración singleton persistente de moneda, país, zona, local y tasas en enteros; StoreSetting::current(): StoreSetting. Fixtures explícitamente ficticios MXN, aumento público 3000, instalador 1000 e IVA 1600 basis points. Conservar convenciones y configuración existentes. No implementar todavía auditoría o pantallas posteriores.

Verificación específica:
php artisan test --compact tests/Feature/StoreSettingsTest.php. Verifica defaults, persistencia y representación entera. Este punto ya está aceptado: verifica/reutiliza lo existente, no recrees su esquema.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T1.2 — Auditar cambios y proteger semillas.** Crear `AuditEntry`, factory/migración; probar incremento monotónico de versión y auditoría transaccional, sin secretos ni teléfono completo. Ajustar `.env.example` y seeding para impedir credenciales demo en producción. **Aceptación:** todas las pruebas T1 pasan; cambio y auditoría se confirman juntos.

### Prompt completo — T1.2

```text
Claude, atiende únicamente T1.2 — Auditar cambios y proteger semillas.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: app/Models/AuditEntry.php; factory y migración create_audit_entries_table; StoreSetting.php; StoreSettingsTest.php; .env.example; database/seeders/DatabaseSeeder.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
StoreSetting::updateCurrent(array $changes, ?User $actor = null): StoreSetting incrementa versión monotónica y persiste cambio/auditoría en una transacción. Proteger id/version del cliente; inicialización/factories no generan auditorías. No registrar tokens, secretos ni teléfono completo. Variables Stripe/correo vacías en ejemplo y rechazo de datos/credenciales demo en producción.

Verificación específica:
php artisan test --compact tests/Feature/StoreSettingsTest.php. Cubrir versión, rollback cuando falla auditoría y ausencia de escrituras demo en producción. Reusar implementación aceptada.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T2 — Autenticación administrativa

Referencias: T2. Cierre: `php artisan test --compact tests/Feature/StoreAuthenticationTest.php`.

- [x] **T2.1 — Roles, activación y acceso al panel.** Migración de usuarios, estados de factory y middleware de cuenta activa/administrador. Probar invitado, instalador y suspendido, así como protección de rol frente al request. **Aceptación:** permisos comprobados en servidor; ninguna ruta de registro público.

### Prompt completo — T2.1

```text
Claude, atiende únicamente T2.1 — Roles, activación y acceso al panel.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: app/Models/User.php; database/factories/UserFactory.php; migración add_store_access_to_users_table; app/Http/Middleware/EnsureActiveAccount.php y EnsureAdministrator.php; bootstrap/app.php; tests/Feature/StoreAuthenticationTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Roles Administrator/Installer; User::isActive(): bool exige is_active, activated_at y suspended_at ausente; isAdministrator(): bool usa enum. Cuenta nueva Installer inactiva. Campos de rol/activación/suspensión fuera de asignación pública. Aliases active.account y administrator después de auth. Sin registro público ni invitaciones completas todavía.

Verificación específica:
php artisan test --compact tests/Feature/StoreAuthenticationTest.php. Invitado redirigido, instalador/suspendido denegados y rol enviado por cliente sin privilegios. No rehacer punto aceptado.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T2.2 — Login, logout y alta segura del administrador.** Implementar controlador/request, vistas/layout, rutas nombradas y comando `store:create-admin`. Probar regeneración/invalidez de sesión, throttling y CSRF; contraseña por mecanismo oculto, nunca argumento literal. **Aceptación:** panel utilizable y protegido; MFA queda expresamente para T15. Verificado e integrado el 01-10-2026: 12 pruebas nuevas/97 aserciones; suite completa 40/240, Pint y build aprobados. La delegación a Gemini falló por permisos; Codex implementó y verificó localmente en copia aislada.

### Prompt completo — T2.2

```text
Claude, atiende únicamente T2.2 — Login, logout y alta segura del administrador.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: AuthController.php; LoginRequest.php; app/Console/Commands/CreateAdministrator.php; resources/views/auth/login.blade.php; admin/dashboard.blade.php; components/layout.blade.php; routes/web.php; tests/Feature/StoreLoginTest.php, CreateAdministratorTest.php y StoreAuthenticationTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Rutas login/logout/admin.dashboard; regenerar sesión al autenticar, invalidar sesión/CSRF al salir, throttle y protección CSRF. store:create-admin --email obtiene contraseña oculta, nunca literal o seed conocido; asigna internamente administrador activo con activated_at y suspended_at null. Preservar carrito saneado al logout según T5, sin preservar permisos de pedidos. MFA pertenece a T15.

Verificación específica:
php artisan test --compact --filter='StoreAuthenticationTest|StoreLoginTest|CreateAdministratorTest'; npm run build si modificas vistas. Verifica ausencia /register y redirecciones seguras. Punto ya aceptado.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T3 — Catálogo administrable

Referencias: T3. Cierre: `php artisan test --compact tests/Feature/AdminCatalogTest.php`.

- [x] **T3.1 — Categorías y productos.** Modelos/factories/migraciones, CRUD autorizado, requests/policy y formularios. Probar creación/edición/publicación/desactivación, costo inválido y acceso no administrador. **Aceptación:** catálogo gestionable; desactivar conserva referencias. La validación de precio efectivo se completa en T4. Integrado 01-10-2026: Gemini implementó núcleo y Codex formularios/controladores/requests/tests; 24 pruebas/188 aserciones focalizadas y 64/428 completas aprobadas, revisión independiente sin hallazgos bloqueantes, Pint/build aprobados.

### Prompt completo — T3.1

```text
Claude, atiende únicamente T3.1 — Categorías y productos.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: Category.php y Product.php, factories y migraciones create_categories_table/create_products_table; ProductPolicy.php; Admin/ProductController.php y CategoryController.php; requests Store/UpdateProduct y Store/UpdateCategory; vistas admin/products/index y form, admin/categories/index; routes/web.php; dashboard; AdminCatalogTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Productos con categoría, nombre, slug único aun desactivado, descripción, specifications, equipment_contents, costo entero privado y flags publicado/activo. base_cost decimal de 0.01 a 9999999999.99 se convierte sin floats; no confiar en centavos request. Crear/editar/desactivar sin borrado físico ni stock; FK restrict y autorización admin. Escape de textos y no-store. Validación de precio efectivo se completa en T4.

Verificación específica:
php artisan test --compact tests/Feature/AdminCatalogTest.php; build si cambian vistas. Cubrir costo inválido, CRUD/publicación/desactivación y acceso ajeno. No recrear migraciones aceptadas.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T3.2 — Fotografías y datos ficticios.** `ProductPhoto`, upload, orden, alt y almacenamiento; seed de demostración protegido. JPEG/PNG/WebP hasta 5 MiB y 6000×6000, rechazo de falsos/ejecutables y limpieza ante fallo BD verificados. **Aceptación:** galería renderizada y ordenable; sin stock ni cargas ejecutables. Integrado 01-10-2026: Gemini núcleo y Codex supervisión/formularios/seeder/tests; focalizada 22 pruebas/121 aserciones, completa 86/549, Pint/build aprobados. Enlace público creado. Sin inspección de navegador ni ejecución del seeder en la BD de aplicación.

### Prompt completo — T3.2

```text
Claude, atiende únicamente T3.2 — Fotografías y datos ficticios.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: ProductPhoto.php, factory, migración create_product_photos_table; Admin/ProductPhotoController.php; Store/UpdateProductPhotoRequest.php; relación Product; admin/products/photos.blade.php; rutas; StoreDemoSeeder.php; ProductPhotosTest.php y StoreDemoSeederTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Fotos JPEG/PNG/WebP máximo inclusivo 5 MiB y 6000×6000, validación MIME/extensión/contenido y nombres UUID generados. Rechazar SVG/HTML/ejecutables y rutas del cliente. Orden y alt editables; conservar fotos existentes, limpiar archivo nuevo si BD/guardado falla. Seed idempotente solo local/testing, datos ficticios y sin cuentas; no sembrar BD de aplicación. Revisar galería/alt/orden en navegador; no dar por cerrada una inspección visual por tests HTML.

Verificación específica:
php artisan test --compact --filter='ProductPhotosTest|StoreDemoSeederTest|AdminCatalogTest'; npm run build si cambia UI. Storage fake, fallo BD y guardado cancelado. Inspección visual de fotos que seguía pendiente requiere evidencia específica, no capturas de productos sin fotos.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T4 — Precios y excepciones

Referencias: T4 y contratos de la sección 2. Cierre: `php artisan test --compact --filter=Pricing`.

- [x] **T4.1 — Cotización con dinero entero.** DTOs y `PricingService::quote` integrados. Bases 130000/110000, finales 150800/127600 y tres unidades públicas total 452400 verificados; mitad de centavo, IVA por línea y overflow cubiertos. **Aceptación:** cotización serializada explícitamente sin costo ni segunda tarifa; descuento inicial cero. Gemini implementó núcleo; Codex corrigió límites/moneda y verificó 39 pruebas/111 aserciones focalizadas, 125/660 completas, Pint y revisión independiente aprobados. Sin nueva pantalla; T4.2 incorpora excepciones/panel.

### Prompt completo — T4.1

```text
Claude, atiende únicamente T4.1 — Cotización con dinero entero.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: app/Data/CartLineData.php, QuoteLineData.php, QuoteData.php; app/Services/PricingService.php; tests/Unit/PricingServiceTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
quote(array $lines, PriceTier $tier): QuoteData, list<CartLineData>. Dinero entero y half-up precio unitario, IVA por línea; agrupar productos duplicados y ordenar IDs. Quote/fingerprint efectivos sin costo ni segunda tarifa; descuento cero y base descontada igual original. Costo fixture 100000: bases 130000/110000, finales 150800/127600; tres públicas total452400. Overflow real rechazado sin rechazar resultados representables por intermedios grandes.

Verificación específica:
php artisan test --compact --filter=Pricing. Probar mitad de centavo, IVA por línea, agrupación, orden/fingerprint, overflow y serialización privada. Reutilizar motor aceptado.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T4.2 — Excepciones y panel de precios.** Modelo/factory/migración de override, panel/request y auditoría integrados. Independencia por tarifa, conservación al cambiar costo, vuelta a automático y bloqueo de publicación con precio efectivo inválido verificados. **Aceptación:** vista previa sin/con IVA coincide con el motor. Suite completa: 167 pruebas/866 aserciones; Pint y build aprobados. Evidencia en el registro de entrega.

### Prompt completo — T4.2

```text
Claude, atiende únicamente T4.2 — Excepciones y panel de precios.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: PriceOverride.php/factory/migración create_price_overrides_table; PricingService.php; Admin/PricingController.php; UpdatePricingRequest.php; admin/pricing/edit.blade.php; publicación Product y configuración StoreSetting; rutas; PricingAdministrationTest.php y PricingServiceTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Override único producto/tarifa sin IVA; ausencia usa automático. Actualizar costo/porcentajes no altera precios manuales; quitar un override no modifica el otro. Vista previa ambas tarifas sin/con IVA mediante motor común. Auditoría/versión y cambios atómicos; no publicar ni mantener productos publicados con precio efectivo no positivo. Rollback si eventos cancelan guardado, borrado o auditoría.

Verificación específica:
php artisan test --compact --filter=Pricing; npm run build. Cubrir independencia, retorno a automático, publicación inválida, autorización y rollback. No rehacer punto aceptado.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T5 — Tienda y carrito sin cupones

Referencias: T5. Cierre: `php artisan test --compact --filter='StorefrontTest|CartTest'` y `npm run build`.

- [x] **T5.1 — Catálogo público y ficha.** Controladores/vistas de catálogo/producto, búsqueda, categorías y paginación. Probar solo activos/publicados, precio público para invitado y privado para instalador activo, sin filtración en HTML/JSON/metadata y con `private, no-store`. **Aceptación:** navegación y tarifa derivadas del servidor.

### Prompt completo — T5.1

```text
Claude, atiende únicamente T5.1 — Catálogo público y ficha.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: CatalogController.php; ProductController.php público; StorefrontService.php existente; vistas store/catalog.blade.php y product.blade.php; rutas/layout; tests/Feature/StorefrontTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Catálogo / y /catalogo; ficha /productos/{slug}; solo activos/publicados, búsqueda literal nombre/descripción, categoría y paginación20. Tarifa desde actor fresco persistido: instalador activo recibe privada; demás público. Proyección HTML/JSON/metadata explícita sin costo ni otra tarifa; Cache-Control private,no-store también errores. Fotos ordenadas/textos escapados/precio IVA incluido. No anticipar carrito si ejecutas este punto desde cero.

Verificación específica:
php artisan test --compact tests/Feature/StorefrontTest.php; npm run build; navegador360px/escritorio/teclado. Punto aceptado; no eliminar funcionalidad posterior al revisarlo.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T5.2 — Carrito e información de recogida.** `CartService`, request, rutas/vistas y datos informativos configurables. Probar IDs/cantidades de sesión, recotización al logout y cantidades inválidas; revisar 360px, escritorio y teclado. **Aceptación:** carrito calculado en servidor, errores claros y build correcto; sin cupón provisional.

### Prompt completo — T5.2

```text
Claude, atiende únicamente T5.2 — Carrito e información de recogida.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: CartService.php; CartController.php; UpdateCartRequest.php; InformationController.php; store/cart.blade.php e information.blade.php; formulario producto/layout/rutas; config/store.php; bootstrap/app.php; AuthController.php logout; CartTest.php y StorefrontTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
lines(): array list<CartLineData>, put(productId,quantity): void reemplaza cantidad, remove(productId): void. Sesión únicamente IDs/cantidades; sanear retirados/corruptos con aviso; cotizar antes de mutar para no alterar sesión si se rechaza overflow. Cantidad entera positiva sin límite de stock. Logout conserva solo carrito saneado y recotiza público. Información configurable ficticia de local/políticas, recogida exclusiva y esperar segundo correo. Sin cupones provisionales.

Verificación específica:
php artisan test --compact --filter='StorefrontTest|CartTest'; npm run build; navegador360/1280px, Tab/Enter, agregar/actualizar/quitar y errores. Verificar no-store en éxitos y excepciones. Ya aceptado.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T6 — Contacto y pedido inmutable

Referencias: T6. Cierre: `php artisan test --compact --filter='OrderCheckoutTest|OrderAccessTest|PhoneNormalizerTest'`.

- [x] **T6.1 — Contacto y teléfono canónico.** `ContactData`, `PhoneNormalizer` y validación de contacto; probar equivalencia de formatos mexicanos del plan, entradas inválidas y país explícito. **Aceptación:** formato internacional válido; teléfono no autentica ni revela pedidos. Ampliar países solo según reglas comprobadas, sin nueva dependencia implícita.

### Prompt completo — T6.1

```text
Claude, atiende únicamente T6.1 — Contacto y teléfono canónico.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: ContactData.php; PhoneNormalizer.php; StartCheckoutRequest.php; tests/Unit/PhoneNormalizerTest.php; tests/Feature/ContactValidationTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
ContactData readonly(name,email,phoneOriginal,phoneCanonical). normalize(input,country): string E.164 o ValidationException; MX seleccionado explícitamente, original preservado y canónico servidor. Equivalencia +52 614 123 4567/+526141234567. Rechazar extensiones, prefijos antiguos +521/01/044/045, locales ambiguos, controles, UTF-8 inválido y tipos inesperados. No autenticar/buscar por teléfono. Ampliar países solo con reglas oficiales verificadas y aprobación si requiere dependencia.

Verificación específica:
php artisan test --compact --filter='PhoneNormalizerTest|ContactValidationTest'. Verificar old_input JSON sin corrupción ni500 por bytes inválidos. Ya aceptado.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T6.2 — Persistir cotización aceptada.** `Order`, `OrderLine`, `PaymentAttempt`, enums/factories/migraciones y `CheckoutService::start`. Probar fingerprint viejo sin persistencia, doble envío deduplicado, mínimo de prueba 1000 y snapshot resistente a cambios comerciales. **Aceptación:** una transacción crea snapshot/intento; request no controla precio/rol; no llamar aún a Stripe real. Integrado 02-10-2026: Codex implementó y verificó en copia aislada; 43 pruebas/131 aserciones focalizadas, suite completa en fuente 359/1675 y Pint aprobados. Gemini entregó después del timeout; propuesta no integrada por defectos de contrato. Evidencia en el registro de entrega.

### Prompt completo — T6.2

```text
Claude, atiende únicamente T6.2 — Persistir cotización aceptada.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: Order.php, OrderLine.php, PaymentAttempt.php y factories; enums OrderStatus/PaymentAttemptStatus; migraciones create_orders_table/create_order_lines_table/create_payment_attempts_table; CheckoutService.php; config/store.php; OrderCheckoutTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
start(lines,ContactData,?User,acceptedFingerprint,submissionKey): PaymentAttempt. Cotización/contacto snapshot inmutable sin costo, IDs UUID; submission servidor único no concede acceso. Actor fresco activo y cálculo desde settings/productos/overrides bloqueados usando quoteLine, no quote con snapshot viejo. Crear pedido Pending+intento Creating atómicamente; doble envío recupera original aun cambien catálogo/settings. Fingerprint viejo/mínimo 1000/máximo 99999999/overflow rechazan sin persistir. Nunca Stripe dentro de locks.

Verificación específica:
php artisan test --compact tests/Feature/OrderCheckoutTest.php. Cubrir rollback inserción cancelada/fallida, privacidad, cambio comercial y doble confirmación. Conserva el arreglo T6.3: lecturas actuales de recuperación y serialización por settings; SQLite no acredita carreras MySQL.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T6.3 — Revisión y acceso protegido.** `OrderAccessService`, policy/controladores, revisión/resultado y token hash de 7 días configurable. Probar pedido ajeno, token inválido/vencido y acceso propietario. **Aceptación:** invitado revisa contacto y total; URL de retorno muestra pendiente y nunca confirma pago por sí sola.

### Prompt completo — T6.3

```text
Claude, atiende únicamente T6.3 — Revisión y acceso protegido.
Este punto ya está aceptado. Su prompt documenta el contrato; no reconstruyas ni retrocedas funcionalidad posterior. Solo revisarlo o corregir un defecto si el usuario lo solicita.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: OrderAccessService.php; OrderPolicy.php; CheckoutController.php; OrderController.php; store/checkout-review.blade.php y order-result.blade.php; enlace store/cart; rutas; bootstrap/app.php; config/store.php; CheckoutService.php; OrderAccessTest.php y CheckoutReviewTest.php; ContactValidationTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Revisión invitada de contacto/total/condiciones; consentimiento ligado al nonce, contacto y fingerprint servidor. Recalcular y exigir nueva aceptación si cambia carrito/tarifa/precio. Propietario activo fresco, grant de sesión por pedido/hash o token de 256 bits; hash SHA-256 y TTL 7 días configurable, entrega raw una vez. Token válido redirige 303 a URL limpia; ajeno, expirado exacto, suspendido, malformado y logout denegados. UUID solo no autoriza. No-store/no-referrer también errores; resultado BD nunca paid por URL. Transacción externa no debe causar replay antiguo en MySQL; preservar lecturas actuales y serialización actuales.

Verificación específica:
php artisan test --compact --filter='OrderCheckoutTest|OrderAccessTest|PhoneNormalizerTest|CheckoutReviewTest|ContactValidationTest'; npm run build; navegador360/1280 y teclado. Ya aceptado; concurrencia real de doble POST MySQL sigue pendiente antes de gate financiero.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T7 — Stripe Checkout recuperable

Referencias: T7. Cierre: `php artisan test --compact tests/Feature/StripeCheckoutTest.php`.

- [x] **T7.1 — Gateway y creación idempotente.** Gateway/DTO/excepciones, configuración sin secretos y fixtures acordes con versión API comprobada. `Http::fake()` verifica agregado exacto, tarjetas, moneda, URLs nombradas, expiración 30 minutos persistida y parámetros/clave estables. **Aceptación:** una sesión lógica y ninguna red con locks abiertos.

### Prompt completo — T7.1

```text
Claude, atiende únicamente T7.1 — Gateway y creación idempotente.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: app/Services/StripeGateway.php; app/Data/CheckoutSessionData.php; app/Exceptions/UnknownStripeOutcome.php y DefinitiveStripeFailure.php; CheckoutService.php; PaymentAttempt.php; migración incremental si faltan campos; config/services.php; .env.example; rutas/resultados T6; tests/Feature/StripeCheckoutTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Continúa desde T6: crear Checkout remoto sin romper compra invitada ni acceso privado. StripeGateway::createCheckout(PaymentAttempt $attempt): CheckoutSessionData. Preparar y persistir parámetros exactos, clave idempotente estable y expiración de 30 minutos calculada desde el primer envío antes de llamar HTTP; repetir sin recalcularlos. Usar Http Laravel, POST form encoded, HTTPS, autenticación y timeouts. Total agregado del servidor, moneda MXN de prueba, mode=payment, solo card, referencias propias de pedido/intento; impuestos y promociones de Stripe desactivados; success/cancel con rutas nombradas.
Fijar Stripe-Version verificada con documentación oficial y sandbox; no inventar versión ni fixtures. Account ID y modo esperados configurables, sin secretos en repo. Si falta acceso autorizado, registrar la verificación externa pendiente. HTTP debe ocurrir fuera de TODAS las transacciones: inspeccionar también la transacción externa del controlador T6, no solo la interna del servicio. Distinguir rechazo definitivo de resultado desconocido, dejando la política completa de recuperación para T7.2.

Verificación específica:
php artisan test --compact tests/Feature/StripeCheckoutTest.php; regresión OrderCheckoutTest, CheckoutReviewTest y OrderAccessTest. Http::fake/preventStrayRequests comprueban cuerpo, headers, clave, importe, URLs, expiry estable y transactionLevel() cero al HTTP. Doble envío crea una sesión lógica y token una vez. Versión/sandbox no verificados quedan explícitamente pendientes.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T7.2 — Incertidumbre, reanudación y cambio de cotización.** Distinguir rechazo definitivo de timeout; retrieve/expire/find y ventana conservadora 23h. Probar reanudación mismos IDs, límite de solicitudes y cambio que requiere resolver intento cobrable antes de crear otro pedido. **Aceptación:** Unknown no dispara otro Checkout; pedido anterior permanece inmutable y vinculable a evento tardío.

### Prompt completo — T7.2

```text
Claude, atiende únicamente T7.2 — Incertidumbre, reanudación y cambio de cotización.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: StripeGateway.php; CheckoutSessionData.php; UnknownStripeOutcome.php/DefinitiveStripeFailure.php; CheckoutService.php; PaymentAttempt.php; controladores/rutas/resultados T6; configuración; StripeCheckoutTest.php; migraciones incrementales necesarias.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Implementar retrieveCheckout(string $sessionId), expireCheckout(string $sessionId) y findCheckout(PaymentAttempt $attempt) con el DTO compartido. Timeout conserva Unknown y parámetros/clave originales; retry solo dentro de 23 horas desde el primer envío. Después conciliar sin cambiar automáticamente la clave. Un listado parcial vacío no demuestra inexistencia de sesión.
Reanudar el mismo resumen conserva pedido/intento. Cambiar carrito, contacto o tarifa requiere resolver el intento previo y demostrar que ya no puede cobrarse; cerrar ClosedUnpaid cuando corresponda y crear otro pedido/submission con aceptación nueva. Si el pago gana, conservar compra pagada y vincular eventos tardíos al intento anterior. Nunca modificar snapshots anteriores ni permitir dos intentos potencialmente cobrables. Límites por sesión e IP: 10 solicitudes/minuto y 5 intentos nuevos/hora; reanudación no consume otro intento y abuso no persiste pedidos. No limitar webhook con estos contadores.

Verificación específica:
php artisan test --compact tests/Feature/StripeCheckoutTest.php; regresión checkout/acceso T6. Timeout/rechazo definitivo, recuperación/23h, pago vs expire, mismo resumen y contacto nuevo, abuso sin persistencia, red sin locks. Carrera real de doble POST MySQL pendiente debe acreditarse antes del gate financiero.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T8 — Webhook y registro financiero

Referencias: T8. Cierre: `php artisan test --compact --filter='StripeWebhookTest|PaymentTransitionsTest'`.

- [x] **T8.1 — Verificar firma y persistir eventos.** `StripeWebhookVerifier`, `StripeEvent` y endpoint. Probar cuerpo original, firmas múltiples, tolerancia 300s, duplicado y fallo BD que impide 2xx; eximir CSRF exclusivamente en webhook. **Aceptación:** evento durable y recuperable; firma/URL falsas no pagan.

### Prompt completo — T8.1

```text
Claude, atiende únicamente T8.1 — Verificar firma y persistir eventos.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: StripeWebhookVerifier.php; StripeEvent.php/factory; migración create_stripe_events_table; StripeWebhookController.php; bootstrap/app.php; routes/web.php; StripeWebhookTest.php; configuración de firma/versión compartida con T7.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
StripeWebhookVerifier::verify(string $body, string $signatureHeader): array con PHPDoc del evento validado. Verificar protocolo Stripe oficial: HMAC SHA-256 sobre cuerpo ORIGINAL, comparación constante, timestamp con tolerancia 300 segundos y múltiples firmas v1. Configuración compatible con versión/mode de T7.
Persistir evento firmado con ID remoto único antes de responder 2xx; fallo o cancelación de guardado devuelve no-2xx. Duplicados no duplican trabajo; pendientes recuperables y eventos no soportados registrados como ignorados. Exención CSRF exclusivamente stripe/webhook. No convertir completed ni URL de éxito en Paid, no aplicar todavía finanzas de T8.2 ni enviar SMTP.

Verificación específica:
php artisan test --compact tests/Feature/StripeWebhookTest.php. Cuerpo alterado, firmas inválidas/múltiples, tiempo, duplicados y fallo de persistencia. Otros POST conservan CSRF; ningún secreto o PII en errores.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T8.2 — Aplicar resultados financieros verificados.** Modelos restantes, resolver/DTO/applier y outbox completo `OrderNotification`. Probar identidad/modo/importe/moneda, evento temprano, eventos desordenados, un efecto por cobro y dos cobros conservados con incidencia. **Aceptación:** Payment, Order Paid y aviso Pending se registran atómicamente; Paid no retrocede; ningún envío SMTP en esta tarea.

### Prompt completo — T8.2

```text
Claude, atiende únicamente T8.2 — Aplicar resultados financieros verificados.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: Payment.php, Refund.php, FinancialIncident.php, OrderNotification.php y factories/migraciones; NotificationStatus.php; VerifiedPaymentResult.php; PaymentResultResolver.php; PaymentResultApplier.php; StripeEvent/controller/Gateway; PaymentTransitionsTest.php y StripeWebhookTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
PaymentResultResolver::resolve(StripeEvent $event): VerifiedPaymentResult; PaymentResultApplier::apply(PaymentAttempt $attempt, VerifiedPaymentResult $result, ?StripeEvent $event = null): void. Validar identidad/account/modo/moneda/importe y evidencia antes de cumplir compra; completed aún unpaid no paga. Evento temprano se vincula por metadata y consultas remotas necesarias, siempre fuera de locks.
Transacción común: Payment, intento Paid, Order Paid, aviso compra Pending y evento procesado juntos. Paid no retrocede. Payment tiene cobro remoto único, NO unique por pedido: conservar dos cobros distintos y abrir incidencia sin repetir efectos de compra. Outbox completo con destino/tipo/clave única/fechas/error/claim/lease para T9. Refund remoto único, fallido no reduce neto; disputa independiente. Conservar líneas originales y registrar dinero incluso ante anomalías. Ningún SMTP en este punto.

Verificación específica:
php artisan test --compact --filter='StripeWebhookTest|PaymentTransitionsTest'; regresión StripeCheckoutTest. Evento temprano/desordenado, identidad/importe incompatibles, URL falsa, pago doble y atomicidad. SQLite no acredita carreras reales.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T9 — Correo y aviso de recogida

Referencias: T9. Cierre: `php artisan test --compact tests/Feature/OrderNotificationsTest.php`.

- [x] **T9.1 — Despachar confirmación de compra.** `NotificationAttempt`, dispatcher, claim/lease, plantilla/mail de compra y envío después de commit. Probar duplicado, caída SMTP, aceptación incierta y recuperación de claim. **Aceptación:** pago no se pierde por fallo de correo; Accepted significa aceptación del proveedor; Uncertain no reenvía ciegamente.

### Prompt completo — T9.1

```text
Claude, atiende únicamente T9.1 — Despachar confirmación de compra.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: NotificationAttempt.php/factory/migración create_notification_attempts_table; NotificationDispatcher.php; PurchaseConfirmed.php; mail/purchase-confirmed.blade.php; OrderNotification.php/applier/configmail; OrderNotificationsTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Reutilizar outbox y NotificationStatus de T8, sin otra migración de creación. NotificationDispatcher::attempt(OrderNotification $notification): void reclama exclusivamente con lease e historial; envío inmediato después de commit y fuera de locks. Timeout inicial de red 5 segundos acotado al presupuesto del webhook. Accepted significa aceptación del proveedor, no lectura o entrega.
Fallo SMTP conserva pago y trabajo recuperable. Aceptación seguida de timeout implica Uncertain, sin reenvío automático ciego. Resolver mediante ID/idempotencia del proveedor si existe; SMTP puro requiere decisión deliberada auditada. Plantilla con referencia, productos, desglose, local y espera del segundo correo, sin costo. Preparar historial de intentos reutilizable por usuario/pedido nullable para T15, sin implementar todavía invitaciones.

Verificación específica:
php artisan test --compact tests/Feature/OrderNotificationsTest.php; regresión PaymentTransitionsTest. Mail::fake demuestra envío tras commit, duplicados, fallos/timeouts y recuperación de claim; no acredita SMTP real.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T9.2 — Solicitar y enviar aviso de recogida.** Endpoint admin, plantilla y botón «Notificar listo para recoger». Probar doble clic, reenvío auditado y elegibilidad local común antes de envío/reintento/reenvío: reembolso total cancela; incidencia bloquea; resolución exige revalidar. **Aceptación:** historial preservado y dos tipos de aviso; ninguna entrega logística implícita.

### Prompt completo — T9.2

```text
Claude, atiende únicamente T9.2 — Solicitar y enviar aviso de recogida.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: ReadyForPickup.php; mail/ready-for-pickup.blade.php; Admin/OrderNotificationController.php; vistas/botón admin/rutas; NotificationDispatcher.php/OrderNotification.php; migración incremental de campos si falta; OrderNotificationsTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
OrderNotificationController::store(Order $order): RedirectResponse exclusivamente admin; botón exacto «Notificar listo para recoger». Un aviso inicial por pedido/tipo; doble clic deduplicado. Reenvío es intención explícita confirmada y auditada, sin borrar historial.
Elegibilidad LOCAL común antes de cada envío inicial, retry, claim cron y reenvío manual: Paid, sin reembolso total confirmado ni incidencia pendiente. Reembolso total cancela aviso pendiente/fallido con motivo y fecha, sin SMTP. Incidencia bloquea claim y conserva historial; resolverla exige nueva validación. Accepted previo y aviso de compra se conservan. No consultar Stripe para este control ni introducir estados entregado/enviado. No prometer impedir cambios que ocurran después de comprobar elegibilidad.

Verificación específica:
php artisan test --compact tests/Feature/OrderNotificationsTest.php. Autorización, doble clic, cancelación por reembolso sin retry, incidencia en todos los caminos, resolución y conservación de Accepted. Build y navegador si cambia UI.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T10 — Recuperación y primera compra completa

Referencias: T10. Cierre local: `php artisan test --compact tests/Feature/PaymentRecoveryTest.php` y `php artisan test --compact tests/Feature/PurchaseWithoutCouponTest.php`.

- [x] **T10.1 — Conciliar pagos y recuperar avisos.** `PaymentReconciler`, dos comandos, schedule cada 15 minutos, lotes y métricas. Probar las cuatro interrupciones del plan, idempotencia agotada y claim abandonado. **Aceptación:** usa resolver/applier/dispatcher existentes; alertas no liberan reservas; solo concilia pagos pendientes y eventos recibidos.

### Prompt completo — T10.1

```text
Claude, atiende únicamente T10.1 — Conciliar pagos y recuperar avisos.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: PaymentReconciler.php; app/Console/Commands/ReconcilePayments.php y RetryOrderNotifications.php; routes/console.php; config/store.php; Gateway/resolver/applier/dispatcher existentes; PaymentRecoveryTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
PaymentReconciler::run(int $limit): int. Comandos store:reconcile-payments --limit=25 y store:retry-notifications --limit=25, handle(): int. Schedule cada 15 minutos con exclusión mutua y cache DB compartida; presupuesto inicial 40 segundos, lotes/cursor sin monopolizar por un primer error.
Recuperar interrupciones antes de HTTP, sesión creada sin ID local, evento persistido sin aplicar y Paid sin SMTP. Reutilizar resolver/applier/dispatcher, no duplicar lógica. Solo pagos pendientes y eventos recibidos; no barrer pagos ya pagados buscando refunds/disputas. Avisos respetan elegibilidad T9 y excluyen Cancelled/Uncertain sin resolución. Unknown 30 minutos y cron sin éxito 45 minutos generan alertas, nunca liberación por reloj. El umbral de reservas futuro se integra después de T13. Ventana idempotente agotada no permite otro POST; recuperar claim abandonado. Métricas sin secretos.

Verificación específica:
php artisan test --compact tests/Feature/PaymentRecoveryTest.php; php artisan schedule:list; regresión webhook/avisos/Checkout. Interrupciones, límites y trabajo recuperado. Carreras se verifican con procesos MySQL cuando corresponda.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T10.2 — Compra sin promoción y hito A.** Aceptado el 07-10-2026; véase «Cierre T10.2 y Hito A» al final. Ejemplo local 150800 y recuperación de fallos probados; compras reales sandbox propias, retorno Pending con webhook demorado, un cobro/un aviso y SMTP aceptado/recibido comprobados. Conservar los límites de evidencia documentados; T11 no iniciado.

### Prompt completo — T10.2

```text
Claude, atiende únicamente T10.2 — Compra sin promoción y hito A.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: tests/Feature/PurchaseWithoutCouponTest.php; PaymentRecoveryTest.php; correcciones demostradas del flujo T5–T10. Sin modelos/reservas/cupones nuevos.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Invitado con producto publicado, costo 100000, aumento 30%, IVA 16%, total 150800: contacto → revisión → Checkout → webhook firmado → Order Paid, un Payment, un aviso y cero cupones. Repetir recuperación de fallos.
Recorrido real sandbox desde Checkout del MISMO pedido, con metadata/referencias propias; retorno pendiente si se retrasa webhook. Stripe CLI solo si ya está disponible y autorizada; no instalar automáticamente ni usar trigger genérico como prueba de esa compra. Diferenciar Mail::fake, proveedor de prueba y SMTP real. Hito A exige pruebas de fallos y evidencia sandbox completa; si falta acceso, mantener hito abierto y NO iniciar T11–T14.

Verificación específica:
php artisan test --compact tests/Feature/PurchaseWithoutCouponTest.php; php artisan test --compact tests/Feature/PaymentRecoveryTest.php; regresión checkout/webhook/avisos y recorrido Stripe test con referencias propias. Registrar modo/versión/total/efectos sin secretos.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T11 — Administración de cupones

Referencias: T11. Cierre: `php artisan test --compact tests/Feature/AdminCouponsTest.php`.

- [x] **T11.1 — Persistir reglas y validar cupones.** Modelo/factory/migraciones/requests; probar porcentaje >90%, fijo inválido, moneda, vigencias, código normalizado, flags y Selected vacío. **Aceptación:** reglas inequívocas; desactivar conserva historial.

### Prompt completo — T11.1

```text
Claude, atiende únicamente T11.1 — Persistir reglas y validar cupones.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: Coupon.php/factory; migraciones create_coupons_table y create_coupon_product_table; StoreCouponRequest.php y UpdateCouponRequest.php; enums existentes o clases locales necesarias del contrato T11; AdminCouponsTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Requiere Hito A aceptado con sandbox, no solo mocks. Cupón con código normalizado único case-insensitive, kind percentage/fixed, valor entero, inicio incluido/fin excluido UTC, mínimo sobre base sin IVA, max_uses, flags installer/manual false y scope All/Selected explícito.
Selected vacío inválido; rechazar porcentaje >90%, fijo no positivo o moneda distinta y vigencia invertida. Desactivar conserva historial. No activar descuento ni promociones en Stripe/Checkout. La edición de max_uses frente a Reserved+Consumed se integra en T13 cuando existen derechos; no crear reservas prematuramente.

Verificación específica:
php artisan test --compact tests/Feature/AdminCouponsTest.php. Reglas, normalización, límites y autorización de requests; ningún stock o reserva anticipada.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T11.2 — Panel autorizado y ejemplo PROMO10.** CRUD/vistas/rutas, auditoría, zona horaria y seed ficticio. **Aceptación:** administrador configura promociones, pero este punto no activa su uso en Checkout.

### Prompt completo — T11.2

```text
Claude, atiende únicamente T11.2 — Panel autorizado y ejemplo PROMO10.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: Admin/CouponController.php; resources/views/admin/coupons/index.blade.php y form.blade.php; rutas/dashboard; StoreDemoSeeder.php; auditoría/configuración horaria existentes; AdminCouponsTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
CRUD exclusivamente admin sin borrar historial. Etiquetas de importes/mínimos sin IVA, condiciones visibles; UTC en BD y zona del negocio en formularios. Cambios auditados transaccionalmente.
Seed ficticio PROMO10, porcentaje 10%, público y máximo 50 usos, solo local/testing; no ejecutarlo sobre la BD de aplicación. Este punto permite configurar promociones, todavía no usarlas en Checkout. Depende de T11.1 y Hito A.

Verificación específica:
php artisan test --compact tests/Feature/AdminCouponsTest.php; npm run build. Navegador móvil/escritorio/teclado/errores. No admin denegado, zona/vigencias correctas y rollback de auditoría.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T12 — Cálculo del descuento

Referencias: T12. Cierre: `php artisan test --compact tests/Unit/CouponPricingTest.php`.

- [x] **T12.1 — Elegibilidad y límites.** `CouponEvaluator`, extensión opcional de quote y pruebas de mínimo 130000/130001, flags instalador/manual y tope 90%. **Aceptación:** solo la base elegible determina descuento/mínimo; cotización sin cupón sigue compatible.

### Prompt completo — T12.1

```text
Claude, atiende únicamente T12.1 — Elegibilidad y límites.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: CouponEvaluator.php; PricingService.php; QuoteData/QuoteLineData y snapshot de reglas; tests/Unit/CouponPricingTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
CouponEvaluator::eligibleLines(QuoteData $quote, Coupon $coupon): array de índices elegibles. PricingService::quote añade ?Coupon $coupon = null al final sin romper llamadas anteriores. Revalidar vigencia, moneda, scope y flags instalador/manual.
Solo base elegible sin IVA determina mínimo/descuento: base 130000 cumple mínimo 130000 y falla mínimo 130001; cero elegibles no descuenta. Tope 90%, fijo <=floor(base×9000/10000), porcentaje 9001 rechazado. Congelar reglas efectivas sin costo. Coordinar distribución con T12.2; no reservar ni activar todavía Checkout con cupón.

Verificación específica:
php artisan test --compact tests/Unit/CouponPricingTest.php; regresión --filter=Pricing. Scope/flags/vigencia/límites y quote sin cupón compatible.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T12.2 — Distribución exacta y desglose.** `DiscountAllocator` por mayor resto, desempate estable, IVA posterior y presentación. Probar bases 101/202 con 30 → 10/20, bases 1/1/1 con 1 → 1/0/0 y PROMO10 total 135720. **Aceptación:** suma exacta y bases no negativas; cambio de total exige aceptación; todavía sin reservas.

### Prompt completo — T12.2

```text
Claude, atiende únicamente T12.2 — Distribución exacta y desglose.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: DiscountAllocator.php; PricingService.php; DTOs/snapshot de reglas; vistas store/cart.blade.php y checkout-review.blade.php donde corresponda al cálculo; CouponPricingTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
DiscountAllocator::allocate(array $bases, int $discountMinor): array del mismo orden. Mayor resto con aritmética entera: cuota piso proporcional, residuos descendentes y desempate estable por producto/orden. Evitar overflow; suma exacta del descuento y ninguna base negativa.
Bases 101/202, descuento 30 → 10/20; bases 1/1/1, descuento 1 → 1/0/0. IVA half-up por línea DESPUÉS del descuento. PROMO10: base 130000, descuento 13000, IVA 18720, total 135720. Mostrar fijo recortado y exigir aceptación del nuevo total; mínimo Stripe sobre total final. Snapshot congela reglas, sin reservas y sin flujo productivo hasta T14.

Verificación específica:
php artisan test --compact tests/Unit/CouponPricingTest.php; regresión PricingServiceTest/CheckoutReviewTest; npm run build si cambian vistas. Orden/desempate/overflow y fórmula manual.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T13 — Reservas de cupón y concurrencia

Referencias: T13. Cierre: `php artisan test --compact tests/Feature/CouponReservationTest.php` y `vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/CouponConcurrencyTest.php`.

- [x] **T13.1 — Derecho permanente y ciclo de reserva.** Modelos/factories/migraciones/enum, `reserve/consume/release`, historial y ajuste al editor de T11. Probar único cupón/teléfono, consumo idempotente, liberación con evidencia y devolución sin restitución. **Aceptación:** Reserved + Consumed respeta capacidad; registro único no se borra. **Estado: aceptado localmente; evidencia y límites en el registro del 07-10-2026.**

### Prompt completo — T13.1

```text
Claude, atiende únicamente T13.1 — Derecho permanente y ciclo de reserva.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: CouponUse.php y CouponUseHistory.php/factories; CouponUseStatus.php; migraciones create_coupon_uses_table/create_coupon_use_histories_table y vínculos incrementales Order si faltan; CouponReservationService.php; applier/editor T11; CouponReservationTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
reserve(Coupon $coupon, ContactData $contact, Order $order, PaymentAttempt $attempt): CouponUse; consume(CouponUse $use, Payment $payment): void; release(CouponUse $use, VerifiedPaymentResult $evidence): void.
Par coupon_id/phone_canonical único PERMANENTE con Free/Reserved/Consumed e historial; nunca borrar al consumir/liberar. Reserved+Consumed <=max_uses; impedir edición por debajo de ese conteo. Locks y transacción común según plan; consumo junto a Payment, no separado. Liberar solo con expiración remota sin pago o fallo definitivo sin sesión cobrable, nunca por abandono/timeout/reloj. Refund no devuelve uso/capacidad. Un intento no se apropia de reserva ajena por saber el teléfono. Snapshot de cupón en pedido y vínculos incrementales.

Verificación específica:
php artisan test --compact tests/Feature/CouponReservationTest.php; AdminCouponsTest y PaymentTransitionsTest. Idempotencia, mismo teléfono, liberación con/sin evidencia y refunds. Concurrencia real sigue en T13.2.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T13.2 — Carreras reales en MySQL/MariaDB.** Configuración `phpunit.mysql.xml`, BD exclusiva y procesos con barrera; probar mismo teléfono, último uso, consumo/expiración y recuperación de deadlock. **Aceptación:** pruebas ejecutadas con locks reales; backend ausente produce bloqueo explícito, sin PASS por SQLite o skip. Evidencia vigente en «Verificación final local T14 y T15.3»; no implica cierre del Hito B.

### Prompt completo — T13.2

```text
Claude, atiende únicamente T13.2 — Carreras reales en MySQL/MariaDB.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: phpunit.mysql.xml; tests/Feature/CouponConcurrencyTest.php y helpers dentro tests; ajustes demostrados CouponReservationService/applier/editor. Configuración BD exclusiva por entorno, sin secretos en repo.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
MySQL/MariaDB real con InnoDB y BD exclusiva de pruebas comprobada antes de migrate/refresh; secretos por entorno. Procesos independientes y barrera determinista dentro de tests, sin transacción envolvente compartida.
Mismo teléfono en dos formatos → una reserva; dos teléfonos compiten por último uso → una admisión; consumo vs expiración; deadlock recuperable con IDs estables. Assert Reserved+Consumed<=max_uses. Backend ausente significa bloqueo explícito, no skip/PASS SQLite. Si sigue pendiente, incluir doble POST de checkout T6 y visibilidad de recuperación bajo transacción externa/REPEATABLE READ. No ejecutar tests destructivos contra BD de aplicación.

Verificación específica:
php vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/CouponConcurrencyTest.php; php artisan test --compact tests/Feature/CouponReservationTest.php. Registrar motor/versión/aislamiento y procesos ejecutados; no tocar BD de aplicación.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T14 — Checkout con promociones

Referencias: T14. Cierre: `php artisan test --compact --filter='PurchaseWithCouponTest|CouponCheckoutRecoveryTest'`, concurrencia T13 y regresión de compra sin cupón.

- [x] **T14.1 — Validar y reservar al confirmar.** Endpoint/request de validación y extensión opcional de `CheckoutService::start`; reserva/pedido/intento en misma transacción. Probar precio/flags/capacidad vigentes, rechazo de abuso y consumo al cobrar. **Aceptación:** validar no reserva; texto «Cupón válido una vez por número de contacto»; nuevo total sin cupón exige aceptación. Evidencia vigente en «Verificación final local T14 y T15.3»; no implica cierre del Hito B.

### Prompt completo — T14.1

```text
Claude, atiende únicamente T14.1 — Validar y reservar al confirmar.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: CouponValidationController.php; ValidateCouponRequest.php; CheckoutService.php; Order/PaymentAttempt/DTOs y migraciones incrementales requeridas; cart/review/rutas; applier; PurchaseWithCouponTest.php y CouponCheckoutRecoveryTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Requiere Hito A, T12 y T13 con concurrencia real. CheckoutService::start añade ?string $couponCode = null al FINAL. Validar código no reserva. Al aceptar resumen, revalidar bajo locks cupón/precios/flags/vigencia/capacidad y crear snapshot/pedido/intento/reserva en una transacción; HTTP después de commit.
Validación 10/minuto por sesión/IP separada de webhook; abuso no persiste. Texto exacto «Cupón válido una vez por número de contacto». Otro nombre no evade teléfono; Reserved permite reanudar solo a autorizado. Usado/fallido ofrece total sin cupón con nueva aceptación, nunca cambia total silenciosamente. Validar mínimo Stripe final y consumir por applier común, sin duplicar lógica.

Verificación específica:
php artisan test --compact --filter='PurchaseWithCouponTest|CouponCheckoutRecoveryTest'; regresión PurchaseWithoutCouponTest y T13 secuencial/real. Fingerprint/tarifa/flags/capacidad/abuso y rollback conjunto. Build y navegador si cambia UI.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T14.2 — Recuperación con promoción y hito B.** Integrar liberación/consumo con applier y reconciliador, sin duplicación; probar timeout, pago tras liberación y devolución sin recuperar cupón. Recorrido sandbox PROMO10 total 135720. **Aceptación:** un uso/correo, ninguna regresión del hito A y concurrencia T13 verificada. **Aceptado; Hito B cerrado.** Evidencia y límites en «Cierre T14.2, Hito B y encargo hasta T15.3»: cobro real, correo original aceptado/recibido, devoluciones reales, conciliación dirigida y excepción operativa autorizada en la fixture.

### Prompt completo — T14.2

```text
Claude, atiende únicamente T14.2 — Recuperación con promoción y hito B.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: Applier/reconciliador/Gateway/CheckoutService y servicios de cupón existentes; PurchaseWithCouponTest.php; CouponCheckoutRecoveryTest.php; CouponConcurrencyTest.php; correcciones demostradas.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Recuperación conserva descuento, snapshot, parámetros y clave. Unknown no crea otro Checkout ni libera por tiempo; expiración remota sin pago libera una vez. Pago contradictorio después de liberar se registra con incidencia sin consumir/reutilizar derecho ajeno automáticamente.
Refund parcial o total mantiene Consumed y capacidad ocupada. Sandbox PROMO10 total 135720, un uso y un aviso, sin regresión de Hito A. Hito B requiere sandbox con referencias propias y concurrencia T13 ejecutada; falta de evidencia mantiene el hito pendiente.

Verificación específica:
php artisan test --compact --filter='PurchaseWithCouponTest|CouponCheckoutRecoveryTest|PurchaseWithoutCouponTest'; php vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/CouponConcurrencyTest.php; compra/refund sandbox, nunca live.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T15 — Acceso de instaladores y segundo factor

Referencias: T15. Cierre: `php artisan test --compact --filter='InstallerAccessTest|AdminSecondFactorTest'`.

- [x] **T15.1 — Alta administrativa e invitación.** Modelo/factory/migración, servicio/controladores/mail/vistas y reutilización de intentos de notificación. Probar token hash de un uso, 48h configurables, vencido/usado/reenvío y dos instaladores con la misma tarifa. **Aceptación:** invitación activa cuenta sin enviar contraseñas; no registro público. **Estado: aceptado localmente; evidencia y límites en el registro del 07-10-2026.**

### Prompt completo — T15.1

```text
Claude, atiende únicamente T15.1 — Alta administrativa e invitación.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: InstallerInvitation.php/factory/migración create_installer_invitations_table; Admin/InstallerController.php; InstallerActivationController.php; InstallerInvitationService.php; InstallerInvitationMail.php; admin/installers/index.blade.php; auth/activate.blade.php y plantilla mail; dispatcher/notificationattempts existentes; InstallerAccessTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
InstallerInvitationService::invite(User $installer, User $administrator): void; activate(string $token, string $password): User. Solo admin crea instalador; token hash de un uso, TTL configurable 48 horas, resend invalida anterior. Activación elige contraseña, nunca enviarla por correo.
Dos instaladores activos comparten tarifa privada, sin precios individuales. Reusar historial y recuperación de avisos T9 con usuario/pedido nullable y migración incremental si falta; no outbox paralelo. Auditoría sin secretos. Sin register público ni identidad por formato del teléfono.

Verificación específica:
php artisan test --compact tests/Feature/InstallerAccessTest.php; regresión StoreAuthenticationTest/StorefrontTest/OrderNotificationsTest. Token inválido/vencido/usado, resend, permisos y tarifa fresca. Build/recorrido invitación/login; fake no certifica entrega.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T15.2 — Recuperar contraseña y verificar administrador.** Password Broker nativo y MFA por correo, hash, 5 minutos y máximo 5 intentos; invalidar código anterior al reenviar. **Aceptación:** respuestas sin enumeración y panel inaccesible con MFA pendiente; pruebas de expiración, reutilización y abuso. **Estado: aceptado localmente; evidencia y límites en el registro del 07-10-2026.**

### Prompt completo — T15.2

```text
Claude, atiende únicamente T15.2 — Recuperar contraseña y verificar administrador.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: PasswordResetController.php; AdminSecondFactorController.php; AdminSecondFactorService.php; AdministratorLoginCode.php; auth/password-reset.blade.php y second-factor.blade.php; plantilla mail; auth/middleware/User/rutas/config; InstallerAccessTest.php y AdminSecondFactorTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Password Broker nativo con respuestas sin enumerar email. Después del password de administrador exigir código por correo: hash, un uso, TTL 5 minutos y máximo 5 intentos; resend invalida previo.
MFA pendiente jamás abre panel o acciones admin aunque el rol sea Administrator. Regenerar sesión, limitar abuso y reutilizar infraestructura T9. Preservar login de instalador activado y autorización por estado fresco. No imprimir password/códigos. MFA por correo no prueba identidad fiscal; habilitado antes del lanzamiento.

Verificación específica:
php artisan test --compact --filter='InstallerAccessTest|AdminSecondFactorTest|StoreAuthenticationTest|StoreLoginTest|CreateAdministratorTest'; expiración/reutilización/resend/abuso/no enumeración/recuperación. Build y navegador de formularios.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T15.3 — Suspender acceso y resolver carrera de pago.** Revocar sesiones/remember token y permisos, intentar expiración fuera de transacción. **Aceptación:** endpoints privados denegados; pago que ganó carrera conservado con incidencia; historial financiero intacto. Repetir también pruebas de autenticación T2. Evidencia vigente en «Verificación final local T14 y T15.3»; no implica cierre del Hito B.

### Prompt completo — T15.3

```text
Claude, atiende únicamente T15.3 — Suspender acceso y resolver carrera de pago.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: InstallerInvitationService.php; User/auth/middleware/sesiones; Admin/InstallerController.php; CheckoutService/Gateway/applier existentes; InstallerAccessTest.php y pruebas checkout/transiciones afectadas.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
InstallerInvitationService::suspend(User $installer, User $administrator): void. Marcar primero revocación de permiso y auditar, invalidar sesiones/remember token. Endpoints privados consultan cuenta fresca y deniegan sesiones viejas.
Expirar Checkout fuera de transacción con lógica común T7/T8. No borrar historial ni inferir ausencia de pago por silencio. Si pago gana carrera, conservar íntegro el dinero con incidencia; suspensión no descarta webhook ni cobro. Revalidar acceso a pedido/token de actor suspendido. Sin registro público ni estados logísticos.

Verificación específica:
php artisan test --compact --filter='InstallerAccessTest|AdminSecondFactorTest|StoreAuthenticationTest|StoreLoginTest|StripeCheckoutTest|PaymentTransitionsTest|OrderAccessTest'. Sesiones viejas, remember y endpoints; dinero/expire sin HTTP en locks; carrera real en suite MySQL.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T16 — Operación administrativa

Referencias: T16. Cierre: `php artisan test --compact --filter='AdminOperationsTest|RefundAndDisputeTest'`.

- [x] **T16.1 — Pedidos y configuración del local.** Listado/detalle/controladores/vistas de pedidos y settings. Probar filtro Paid por defecto con reembolsos/incidencias incluidos y filtros Pending/ClosedUnpaid/todos. **Aceptación:** información financiera/contacto accesible solo al administrador; no borrar pedidos para limpiar historial.

### Prompt completo — T16.1

```text
Claude, atiende únicamente T16.1 — Pedidos y configuración del local.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: Admin/OrderController.php; SettingsController.php; admin/orders/index.blade.php y show.blade.php; admin/settings/edit.blade.php; dashboard/rutas/configuración local existente; AdminOperationsTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Admin con MFA completo; listado OrderStatus::Paid por defecto incluye compras reembolsadas/con incidencias, excluye Pending/ClosedUnpaid sin borrarlas. Filtros explícitos para esos estados y todos. Detalle de contacto autorizado, snapshot original, tarifa/referencias, cobros adicionales, reservas/eventos y avisos.
Editar local/horario/condiciones/privacidad/retención usando updateCurrent auditado y versión. No secretos ni resets. Diagnóstico mascara PII, contacto autorizado puede mostrarla. No limpiar historial ni introducir estados enviados/entregados.

Verificación específica:
php artisan test --compact tests/Feature/AdminOperationsTest.php; regresión settings/precios/auth. Filtros Paid con refund/incidencia, autorización/MFA/auditoría. Build y navegador.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T16.2 — Diagnóstico y recuperación autorizada.** Recuperación de pago pendiente, eventos de devolución/disputa, estado cron y avisos. Probar refund exitoso/fallido/parcial/total/desordenado y deduplicación. **Aceptación:** originales intactos, neto solo de reembolsos confirmados; Stripe referencia ante evento ausente; sin barrido periódico de pagos ya pagados ni botón de reembolso.

### Prompt completo — T16.2

```text
Claude, atiende únicamente T16.2 — Diagnóstico y recuperación autorizada.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: Admin/PaymentRecoveryController.php; admin/notifications/index.blade.php; resolver/applier/Gateway; dashboard/rutas/config; AdminOperationsTest.php y RefundAndDisputeTest.php.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
PaymentRecoveryController::store(PaymentAttempt $attempt): RedirectResponse autoriza admin y recupera pagos pendientes mediante Gateway y applier común. Refund/dispute recibidos actualizan Refund remoto único y neto solo de succeeded sin mutar Payment/snapshot; pending/fallido no descuentan y desorden no degrada.
Disputa/incidencia bloquea recogida según T9; dos cobros quedan visibles. Diagnóstico de cron/último éxito/umbrales/avisos fallidos/inciertos/cancelados, recuperación y reenvío auditado. NO RefundReconciler, barrido periódico de pagados ni botón que ejecute refund. Stripe Dashboard es referencia externa cuando faltó evento. No liberar Unknown por reloj.

Verificación específica:
php artisan test --compact --filter='AdminOperationsTest|RefundAndDisputeTest'; regresión PaymentRecoveryTest/OrderNotificationsTest/PaymentTransitionsTest. Refund parcial/total/fallido/desordenado/deduplicado y ausencia de barrido con webhook ausente. Build/navegador si cambia UI.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T17 — Seguridad y aceptación integral

Referencias: T17. Cierre: comandos completos de T17 del plan técnico.

- [x] **T17.1 — Fronteras de seguridad.** `StoreSecurityTest`: fugas HTML/JSON/cache/errores, IDOR, mass assignment, XSS, CSRF, cookies de producción, uploads y suspensión. **Aceptación:** pruebas focalizadas pasan; corregir solo defectos demostrados.

### Prompt completo — T17.1

```text
Claude, atiende únicamente T17.1 — Fronteras de seguridad.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: tests/Feature/StoreSecurityTest.php; solo archivos de aplicación/configuración/vistas con defecto concreto demostrado; reglas y pruebas existentes.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Auditar HTML/JSON/metadata/cache/errores por costo, tarifa privada y PII; cada audiencia ve exclusivamente datos autorizados. IDOR de pedidos/fotos privadas, mass assignment de rol/importe, XSS, CSRF local y firma webhook, cookies de producción Secure/HttpOnly/SameSite, uploads no ejecutables, sesiones suspendidas y redacción de secretos/logs.
Corregir solo defectos demostrados con regresión, sin nuevas funcionalidades/dependencias. Retención no borra evidencia financiera necesaria; valores reales acordados antes de ventas. Reportar evidencia concreta, no garantía global por un test count.

Verificación específica:
php artisan test --compact tests/Feature/StoreSecurityTest.php; regresión áreas corregidas y Pint/build si corresponde. Reportar ruta, reproducción y evidencia de cada hallazgo.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [x] **T17.2 — Concurrencia financiera.** `PaymentConcurrencyTest` con procesos MySQL/MariaDB: webhook/cron, pago/expiración, doble recogida, claim cron/petición y dos cobros distintos. **Aceptación:** un efecto por cobro y ambos cobros anómalos visibles; ninguna carrera acreditada solo con SQLite.

### Prompt completo — T17.2

```text
Claude, atiende únicamente T17.2 — Concurrencia financiera.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: tests/Feature/PaymentConcurrencyTest.php; phpunit.mysql.xml y helpers existentes; ajustes mínimos de applier/CheckoutService/outbox/reservas con regresión.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
MySQL/MariaDB y BD dedicada reales, procesos independientes y barreras. Mismo cobro con diferentes eventos → un efecto; webhook vs cron, pago vs expiración, doble recogida, claim cron vs petición y dos cobros distintos → ambos registrados con incidencia sin doble aviso.
Preservar orden de locks e IDs en retry de deadlock. Incluir doble POST checkout con transacción externa y snapshot REPEATABLE READ pendiente de T6, además de reanudaciones T7. HTTP/SMTP fuera de locks. Backend ausente bloquea: no sustituir con SQLite ni skip.

Verificación específica:
php vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/PaymentConcurrencyTest.php; php vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/CouponConcurrencyTest.php. Motor/aislamiento y ejecución reales; regresión secuencial afectada.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [ ] **T17.3 — Aceptación completa y hito C.** `StoreAcceptanceTest`, suite completa, grupo concurrency, Pint, build y rutas; revisión móvil/escritorio/teclado y flujos sandbox. **Aceptación:** trazabilidad del plan satisfecha, fallos materiales corregidos y evidencias externas identificadas; correo fake no demuestra entrega real.

### Prompt completo — T17.3

```text
Claude, atiende únicamente T17.3 — Aceptación completa y hito C.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: tests/Feature/StoreAcceptanceTest.php; ajustes demostrados por aceptación; documentos existentes guía/plan para trazabilidad y evidencia.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Trazar criterios de secciones 2–14 del diseño y matriz del plan. Compra invitada/privada sandbox, cupón admitido/excluido, refunds test en Dashboard, avisos/recuperación/suspensión/MFA. Navegador 360px/escritorio/teclado/errores/precios con IVA; incluir galería de fotos pendiente si no está evidenciada.
Suite completa y concurrency MySQL ejecutadas, Pint, build y rutas. Correo fake no demuestra SMTP; evidencia externa faltante mantiene Hito C abierto aunque lo local pase. Sin despliegue desde este punto.

Verificación específica:
php artisan test --compact; php vendor/bin/phpunit -c phpunit.mysql.xml --group concurrency; php vendor/bin/pint --dirty --format agent; npm run build; php artisan route:list --except-vendor. Cero fallos; concurrencia ejecutada sin skips que oculten gates, sin register y evidencia visual/sandbox/SMTP diferenciada.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## T18 — Hosting y lanzamiento futuro

Referencias: T18. Estos puntos requieren los accesos y autorizaciones externas descritos allí. No forman parte de la implementación local automática.

- [ ] **T18.1 — Compatibilidad de la cuenta concreta.** Comprobar PHP web/CLI/extensiones contra lock, InnoDB y locks, raíz pública, HTTPS, permisos, Stripe/webhook, cron y SMTP/SPF/DKIM/DMARC. **Aceptación:** evidencia de cuenta HostGator empresarial real; incompatibilidad se comunica antes de cambiar stack o contratar.

### Prompt completo — T18.1

```text
Claude, atiende únicamente T18.1 — Compatibilidad de la cuenta concreta.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: Inspección de cuenta HostGator empresarial concreta y composer.lock/package-lock/config DB/mail/session/logging/rutas console/.env.example; correcciones locales solo cuando incompatibilidad demostrada con regresión.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Requiere acceso autorizado a cuenta exacta HostGator, dominio/DNS, BD staging, Stripe test y SMTP. Verificar PHP web/CLI y extensiones contra lock, especialmente Pdo\Mysql frente a PHP 8.5 local; InnoDB/locks/índices con tests staging exclusivos.
Raíz public, secretos fuera de web, HTTPS, entrada webhook/salida Stripe, permisos storage/cache, uploads no ejecutables, recursos y SSH/vía segura. Cron real cada 15 minutos con rutas comprobadas, presupuesto y último éxito; SMTP/timeouts/límites/SPF/DKIM/DMARC/entrega de prueba. «Empresarial» no certifica SKU o compatibilidad. Sin instalar, contratar, cambiar dependencias o producción por inspeccionar; incompatibilidad se comunica antes.

Verificación específica:
composer check-platform-reqs --no-dev en target/artefacto; php artisan schedule:list; pruebas staging autorizadas. Requests .env/logs/backups/vendor privado deben 403/404 sin contenido. Falta de acceso significa pendiente, no compatibilidad ficticia.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [ ] **T18.2 — Preparar lanzamiento y restauración.** Validar valores comerciales/textos/MFA, artefacto reproducible, respaldo/APP_KEY y ensayo de restauración en entorno protegido. **Aceptación:** restauración comprobada y procedimiento de despliegue revisable; no exponer secretos ni hacer rollback que ignore cobros Stripe.

### Prompt completo — T18.2

```text
Claude, atiende únicamente T18.2 — Preparar lanzamiento y restauración.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: Artefacto reproducible desde lock, backups protegidos de BD/archivos y configuración existente; documentación de procedimiento/evidencia dentro de guía/plan ya autorizados, sin secretos.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Requiere gates T17/T18.1 y permisos de build/restauración en entorno protegido. Confirmar con responsable país/moneda/IVA/mínimo Stripe/local/horario/textos legales/retención/MFA/account/modo/API y costos actuales; fixtures no son valores reales.
Artefacto reproducible desde locks: composer install --no-dev --prefer-dist --optimize-autoloader, npm ci/build únicamente autorizados y sin modificar locks por sorpresa. Backups BD/archivos y APP_KEY por canal seguro. Ensayar restauración realmente en BD/archivos aislados; rollback debe considerar cobros externos Stripe, no borrarlos conceptualmente al restaurar. Identificar revisión del artefacto aun si hay untracked, sin crear commits no solicitados. Preparar procedimiento revisable; no ejecutar deployment.

Verificación específica:
Identificar revisión/lock/build y ejecutar restauración autorizada en entorno aislado. Comparar datos, snapshots, acceso y conciliación de hechos externos antes de reabrir. Preparar parte independiente si falta acceso/valor legal; no afirmar ensayo por redactar un plan.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

- [ ] **T18.3 — Desplegar cuando se autorice y verificar operación.** Ejecutar el despliegue autorizado, migraciones/configuración y comprobar revisión servida, assets, webhook/cron/SMTP y flujo correcto. **Aceptación:** todos los gates T18 satisfechos antes de abrir ventas; transacción real solo con coordinación y autorización expresa posteriores.

### Prompt completo — T18.3

```text
Claude, atiende únicamente T18.3 — Desplegar cuando se autorice y verificar operación.
Punto pendiente. Comprueba que el anterior y las dependencias/hitos de la sección 3 estén aceptados antes de implementar.

Trabaja en este repositorio Laravel existente. Lee AGENTS.md y CLAUDE.md, .ai/rules/index.md y reglas aplicables si existen; lee el diseño consolidado, secciones 2 y 3 y la T correspondiente de Plan-implementacion-tienda-AC-2026-09-29.md, más este punto y el registro actualizado de la guía. Conserva firmas, stack y decisiones aprobadas; confirma versiones con composer show --direct y package.json. Boost ya está instalado: no reinstales por el bootstrap antiguo de CLAUDE.md.
Inspecciona git status y código real, incluidos archivos sin commit. No descartar ni sobrescribir trabajo existente. Claude implementa y verifica directamente; no necesita Codex, Gemini, Antigravity ni skills ausentes. Aísla las pruebas en una BD dedicada; no migres ni siembres la BD de aplicación durante desarrollo local. Las acciones externas de T18 requieren su autorización específica. Genera PHP con Artisan --no-interaction, tests PHPUnit y factories. Migraciones nuevas incrementales, no reescribir las aplicadas.
Sin inventario/envíos/registro público/estados logísticos; dinero entero, costo privado y HTTP/SMTP fuera de locks. No cambiar dependencias ni contratar servicios externos; no hacer commits, push, cobros live o despliegue sin autorización específica. No expongas secretos de .env, credenciales, tokens o claves en chat, documentos, comandos literales o logs; usa la configuración segura existente cuando la integración autorizada los requiera.

Alcance de archivos: Únicamente artefacto/revisión y entorno destino previamente aprobados, migraciones incrementales/configuración operativa existente; ajustes demostrados con pruebas.
Los nombres PHP abreviados se resuelven en las carpetas definidas por la T del plan y los archivos vecinos; usa la ruta existente si ya existe. No modificar archivos de puntos posteriores salvo regresión demostrada dentro de este contrato.

Instrucciones específicas:
Seleccionar este punto NO concede automáticamente deploy o cobro live. Preparar resultado revisable con revisión, destino, gates, backup/rollback; pedir autorización expresa concreta si aún no fue concedida. Después, solo despliegue autorizado del artefacto revisado, migraciones incrementales y comandos migrate --force --no-interaction, storage:link --no-interaction y optimize --no-interaction conforme al destino.
Secretos por canal seguro y APP_KEY conservada. Verificar revisión SERVIDA, assets, rutas, /up, producto, Checkout staging, webhook, cron, SMTP y logs redactados. Exit 0 de comando no acredita release. Gates faltantes bloquean ventas; cobro real requiere autorización coordinada separada.

Verificación específica:
Evidencia externa de revisión servida/assets/route:list//up, modos Stripe y webhook propios, último éxito cron y entrega a buzón, restauración T18.2 y T17. No declarar operación correcta sin comprobar estado servido.

Primero prueba el comportamiento ausente cuando añadas funcionalidad; implementa solo este alcance. Ejecuta los comandos indicados y regresiones afectadas; Ejecuta php vendor/bin/pint --dirty --format agent si cambias PHP y npm run build si cambias frontend. Comprueba el diff y resultados reales. Si falta acceso externo, termina lo local independiente y registra qué evidencia falta; no inventes PASS ni cierres el hito dependiente. Actualiza casilla/estado/evidencia en esta guía y avance correspondiente del plan solo tras verificar. Entrega archivos, comandos/resultados, límites y siguiente punto. Detente: no iniciar otro punto automáticamente.
```

## Uso de los prompts y cierre

Cada uno de los 41 puntos tiene su bloque «Prompt completo». Claude debe leer el bloque del punto seleccionado junto con los contratos compartidos y la T del plan. El alcance del punto, no la lista entera de archivos de la T, determina lo que se implementa en esa sesión. Los comandos son instrucciones de ejecución futura, no afirmaciones de pruebas ya corridas.

Al terminar, registrar: punto/estado, rutas cambiadas, comandos y resultados reales, revisión y correcciones, verificación visual/externa si aplica, base/integración y límites. Marcar aceptado solo con sus criterios satisfechos. Si una parte local terminó pero falta un gate externo, describir ambas situaciones sin marcar el hito completo. No empezar el siguiente punto automáticamente.

## Registro de avance

Estado vigente: T0.1 y T1–T6 aceptados localmente; siguiente T7.1. Las filas/entregas antiguas documentan la evolución, no sustituyen este estado. Sin commits, despliegue ni migraciones comerciales en la BD de aplicación. Modalidad actual: ejecución directa por Claude según los prompts.

| Punto | Estado | Evidencia revisada por Codex | Siguiente |
|---|---|---|---|
| Preparación de esta guía | Preparada | Plan técnico contrastado; consulta conceptual Gemini; ningún código implementado | T0.1 |
| T0.1 | Aceptado — preparación, 01-10-2026 | PHP/dependencias/rutas comprobados; `php artisan test --compact`: 2 pruebas y 2 aserciones, cero fallos; MySQL servicio/puerto detectados, BD dedicada pendiente; Antigravity habilitado; revisión Gemini contrastada; solo registro documental integrado, sin commit | T1.1 |
| T1.1 | Aceptado — 01-10-2026 | Gemini implementó; Codex revisó y verificó en copia aislada: fallo sin implementación, 6 pruebas/46 aserciones focalizadas y 8 pruebas/48 aserciones completas aprobadas. Pint aprobado. Siete archivos integrados con igualdad SHA-256 tras formato; sin commit ni migración en BD de aplicación | T1.2 |
| T1.2 | Aceptado — 01-10-2026 | Gemini implementó auditoría y guardia de seeding; Codex revisó/corrigió ejemplos y prueba de no escrituras, preparó Artisan y placeholders de .env.example. 13 pruebas/99 aserciones focalizadas y 15 pruebas/101 aserciones completas aprobadas en SQLite aislado; Pint y hashes de siete archivos coincidentes | T2.1 |
| T2.1 | Aceptado — 01-10-2026 | Gemini implementó modelo/roles/factories/middleware; Codex corrigió registro de rutas de test y reforzó sesión/activación. Focalizada 13 pruebas/42 aserciones; completa 28 pruebas/143 aserciones; Pint aprobado y siete archivos integrados con hashes coincidentes. Sin pantallas nuevas | T2.2 |
| T2.2 | Aceptado — 01-10-2026 | Intento Gemini rechazado por permisos, sin cambios. Codex implementó en aislado login/logout/panel y alta segura. Nuevas 12 pruebas/97 aserciones, suite 40/240, CSRF real, Pint y build aprobados. Once archivos integrados; sin migrar BD ni commit | T3.1 |
| T3.1 | Aceptado — 01-10-2026 | Gemini núcleo y Codex adaptadores/tests, revisión independiente sin bloqueantes. Categorías/productos administrables, costo entero privado, sin borrado físico. Focalizada 24/188, suite 64/428, Pint/build aprobados; diecinueve archivos con SHA-256 coincidente. Sin migrar BD ni commit | T3.2 |
| T3.2 | Aceptado — 01-10-2026 | Gemini núcleo y Codex integración/supervisión; subida validada, alt/orden y seed protegido. Revisión detectó cancelación silenciosa de guardado, reproducida y corregida. Focalizada 22/121, suite 86/549, Pint/build aprobados; catorce archivos con SHA-256 coincidente. Enlace público creado; sin migrar ni sembrar BD | T4.1 |
| T4.1 | Aceptado — 01-10-2026 | Gemini cuatro archivos núcleo; Codex pruebas/correcciones/supervisión. Cálculo entero, IVA por línea, overflow, DTOs privados y fingerprint canónico. Focalizada 39/111, suite 125/660, Pint y revisión independiente aprobados. Cinco archivos integrados con SHA-256 coincidente; sin modificar BD de aplicación | T4.2 |

| T4.2 | Aceptado — 01-10-2026 | Excepciones independientes, panel de ambas tarifas, auditoría/versión y validación de publicación integrados. Suite 167/866, Pint/build aprobados; catorce archivos iguales a copia aislada por SHA-256. Sin commit ni migración en BD de aplicación | T5.1 |

Al ejecutar, añadir una fila por punto con archivos, pruebas/salida, revisión, integración y commit si existe. Estados: pendiente, en implementación, en revisión, bloqueado o aceptado. Un bloqueo conserva la casilla vacía e indica la condición que falta.

### Evidencia T0.1

- Base: commit `702e7d2` (`Crear proyecto Laravel E-commerce Garba`), árbol de código sin diferencias respecto al commit. Documentos sin seguimiento: diseño consolidado, plan técnico y esta guía. No se hicieron commits ni se cambió código, dependencias o configuración.
- Reglas: AGENTS.md leído; `.ai/rules`, `.agents` y `.codex` locales ausentes. La habilidad `testing-best-practices` no se encontró en las ubicaciones disponibles de habilidades ni plugins; no se crearon pruebas nuevas en este punto. Antes de crear tests en T1.1, volver a comprobar su disponibilidad y aplicar PHPUnit/factories/convenios existentes sin atribuir instrucciones a una habilidad no leída.
- PHP CLI `8.5.0`; extensiones `pdo_mysql`, `pdo_sqlite`, `mysqli`, `curl`, `openssl`, `mbstring`, `fileinfo`, `dom` y `bcmath` disponibles, entre otras. `composer show --direct` confirma Laravel `13.33.0`, PHPUnit `12.5.36`, Pint `1.32.1`, Boost `2.10.0`, Pao `1.1.5`, Tinker `3.0.2` y Faker `1.24.1`.
- Node `23.9.0`, npm `10.9.2`; `npm ls --depth=0` confirma Vite `8.3.1`, Tailwind y plugin Tailwind/Vite `4.3.3`, Laravel Vite plugin `3.2.0`, concurrently `10.0.5`. `@laravel/multiplex` es opcional y está ausente; no se instaló. No se ejecutó build porque T0.1 no modifica frontend.
- `php artisan route:list --except-vendor --no-interaction`: salida correcta, una ruta de aplicación `GET|HEAD /` hacia welcome. No existe aún catálogo, panel o checkout de tienda.
- `phpunit.xml` configura `APP_ENV=testing`, SQLite `:memory:`, DB_URL vacío, correo/cache/sesión array y cola sync. No hay `.env.testing`, `phpunit.mysql.xml` ni cache de configuración `bootstrap/cache/config.php`; esta última ausencia evita interferencia con configuración de testing, no es un defecto.
- `php artisan test --compact`: exit 0, resultado `passed`, 2 pruebas, 2 aserciones. Son las dos pruebas Example existentes; no prueban ninguna función comercial. El intento previo con `--no-interaction` fue rechazado por PHPUnit porque Artisan lo trasladó al runner. Usar el comando de tests sin ese argumento; los generadores y demás comandos Artisan conservan `--no-interaction`.
- MySQL: servicio `MySQL80` Running y TCP `127.0.0.1:3306` accesible. No se verificaron login, versión SQL, InnoDB, base dedicada ni permisos; no se leyeron secretos o datos de negocio. Sin cliente mysql/mariadb/docker en PATH. La consulta opcional a detalles del servicio por CIM fue denegada; el estado y puerto sí se comprobaron, por lo que no se amplió acceso para esa consulta.
- La base exclusiva de pruebas MySQL/MariaDB debe acreditarse antes de validar integración financiera T8/T10; es obligatoria para carreras T13/T17. Servicio activo y extensión PHP no bastan. No se creó una BD ni se instalaron herramientas. La falta de esta evidencia no impide T1.1 con SQLite, pero impide afirmar garantías financieras reales.
- Antigravity: proyecto inicializado/habilitado, trustedWorkspace y readPermission activos. Lista de ejecuciones aisladas vacía. El aislamiento está exigido por el contrato de `antigravity_execute`, pero ninguna copia real fue creada o ensayada en T0.1; no se afirma lo contrario. Antes de aceptar cambios de T1.1, Codex debe verificar ruta aislada, base y contenido, inclusión de documentos sin commit, exclusión de secretos y disponibilidad del entorno de pruebas en esa copia. Si la copia no es verificable, no integrar.
- Protocolo: análisis actual de solo lectura; implementación futura exclusivamente `antigravity_execute`, `verification=none` porque no existe perfil PHPUnit en el conector. Codex verifica PHP por separado en entorno aislado, bajo la autorización de ejecución que corresponda; no se acepta automáticamente riesgo de código delegado por preparar el encargo.
- Delegación: Gemini recibió el diagnóstico de Codex en modo conceptual, sin herramientas ni cambios. `project_root=C:\Users\rubie\Desktop\e-commerce-garba`; `conversation_id=db7260f5-67a3-43a3-b731-b3782d35bc0f`; `run_id=no generado` (análisis, no ejecución). Respondió SUCCESS. Codex adoptó preparar MySQL antes del flujo financiero y verificar base/copia antes de integrar. No adoptó la necesidad de commit para transportar documentos: el requisito es comprobar que la copia incluya el estado actual, sea por snapshot o mecanismo explícito, sin asumirlo. Su observación sobre extensión MySQL ya estaba cubierta por `php -m`.

Huellas SHA-256 de la base revisada (la guía cambia al registrar avance):

| Archivo | SHA-256 |
|---|---|
| AGENTS.md | `3216309B6A1A4EB7F90EB1AEBEE3BAEDCBDD998CF051529019138451EE88C0BF` |
| composer.lock | `1FE2FF25399EA0CFEDDC6718A54A6AAC2207D4935DC983DAD3EAC3825EFC5649` |
| package-lock.json | `69A445F68327160836BF55131AA0ABCD5ADFF6B83641AE298548DAAB37AB8265` |
| phpunit.xml | `493369CFB7FEEA104DA4BEC214C2CC6526CA2F1B9D937C50A2961FC59CB61AAF` |
| Diseno-consolidado-tienda-AC-2026-09-29.md | `7A93DC15548EC4AB3577D5951F2F41B51AB0FC75DE0EBF52463CF73FDE245E33` |
| Plan-implementacion-tienda-AC-2026-09-29.md | `4B0B37D20E3E4760043FBFCF8D874FD3E80811F3F5DEC8EC93CB9F1F1FB0AE30` |

### Encargo T1.1 — ejecutado y verificado

```text
Implementa únicamente T1.1 — Persistir configuración y tarifas, en copia aislada.
Base: HEAD 702e7d2 más diseño/plan/guía actuales sin commit. Verifica que estén
incluidos; compara las huellas del registro T0.1 y el estado fuente actualizado.
Lee AGENTS.md, el diseño consolidado, Global Constraints y contratos de la
sección 2 del plan técnico, su T1 y el punto T1.1 de esta guía.
Antes de escribir, revisa User.php, UserFactory.php y migraciones existentes
para seguir los convenios reales de Laravel 13 del proyecto.
Archivos permitidos:
- config/store.php
- app/Enums/PriceTier.php
- app/Enums/UserRole.php
- app/Models/StoreSetting.php
- database/factories/StoreSettingFactory.php
- database/migrations/<timestamp>_create_store_settings_table.php
- tests/Feature/StoreSettingsTest.php
Artisan decide el timestamp real. Genera modelos/factory/migración y test
con sus comandos make:, --no-interaction; crea enums/clases según comandos
disponibles y no copies APIs de otra versión de Laravel.
Contrato público: StoreSetting::current(): StoreSetting.
PriceTier: Public, Installer; UserRole: Administrator, Installer (TitleCase).
Persistir aumentos público/instalador, tasa, moneda, país, zona, datos del local
y versión, de acuerdo con el plan; valores demo explícitos, configurables.
Tasas enteras en precisión 1/10000: público 3000, instalador 1000, IVA 1600;
moneda demo MXN. No introducir secretos ni credenciales en config/modelo.
Declara nombres de columnas/casts coherentes para conservarlos en T1.2/T4.
Escribe test_defaults_are_explicit_demo_values y pruebas de persistencia y
recuperación de current() con RefreshDatabase y factory, SQLite :memory:.
No basta comprobar config si el contrato requiere configuración en BD.
Prueba focalizada: php artisan test --compact tests/Feature/StoreSettingsTest.php.
Regresión: php artisan test --compact (incluye los Example existentes).
Pint si cambia PHP: vendor/bin/pint --dirty --format agent.
No implementar AuditEntry, incremento/auditoría de cambios, edición admin
o protección de semillas/.env.example: corresponden a T1.2.
No modificar User, rutas, dependencias ni documentos; no instalar ni desplegar.
No hacer commits ni integrar. Entrega archivos, explicación y salida real
de pruebas si hubo ejecución autorizada. Si no puedes generar con Artisan
o ejecutar pruebas en la copia, informa el bloqueo; no inventes comandos/PASS.
Codex verificará aislamiento, contratos, diff y pruebas antes de integrar.
Detente al completar el punto; no iniciar T1.2.
```

## Segunda opinión y decisiones de Codex

Consulta Gemini/Antigravity del 01-10-2026, solo análisis conceptual del resumen suministrado, sin inspección propia de archivos: `project_root=C:\Users\rubie\Desktop\e-commerce-garba`; `conversation_id=db7260f5-67a3-43a3-b731-b3782d35bc0f`; `run_id=no generado` (consulta de análisis, no ejecución aislada).

Codex contrastó sus recomendaciones con los contratos del plan y adoptó: preservar campos de descuento desde T4/T6, separar SMTP de persistencia T8, probar concurrencia real y fijar base/alcance por entrega. No adoptó anticipar claves foráneas a tablas futuras, poner un único payment_intent_id en pedido ni renombrar la tabla de avisos: contradicen la implementación incremental o los contratos existentes. Tampoco puso base descontada en cero sin cupón: corresponde a la base original. La supervisión usa contenido y diff, no solo `git diff --stat`.

### Entrega aislada T1.1 — aceptada e integrada

Solicitud del usuario: «Realiza T1.1 con Gemini y supervisa el resultado». Se delegó mediante `antigravity_execute`, `verification=none`; no se activó `allow_untrusted_verification`. El usuario respondió «sí» a la autorización explícita de ejecutar código delegado en copia aislada con SQLite en memoria. Codex realizó allí las pruebas PHP por separado, pues el conector no ofrece un perfil PHPUnit.

- `project_root=C:\Users\rubie\Desktop\e-commerce-garba`.
- Primer intento: `run_id=20261001T185441Z-fe8df5b5`, `conversation_id=e8aee64b-bbe4-4942-96f0-3875f8d6cacf`; falló por timeout y ausencia de operaciones estructuradas. Ningún archivo aplicado.
- Entrega válida: `run_id=20261001T185916Z-4bcb3e16`, `conversation_id=41e144c1-0b70-4adf-a8b7-fd87d545b044`; estado completed, AGY SUCCESS, siete operaciones aplicadas exclusivamente en `.antigravity-mcp/runs/20261001T185916Z-4bcb3e16/workspace`.
- Archivos: `config/store.php`, `app/Enums/PriceTier.php`, `app/Enums/UserRole.php`, `app/Models/StoreSetting.php`, `database/factories/StoreSettingFactory.php`, `database/migrations/2026_10_01_000001_create_store_settings_table.php`, `tests/Feature/StoreSettingsTest.php`.
- Revisión Codex: contratos enum y `current(): self` compatibles; enteros/booleanos, valores ficticios y campos locales persistidos; PK1 y `createOrFirst` recuperan fila existente sin sobrescribirla. API contrastada con Builder de Laravel instalado. Factory y tests mantienen alcance T1.1. Ningún secreto, cambio de dependencia, auditoría o ruta añadidos.
- Copia inicial del primer intento: huellas de AGENTS.md, composer.lock, phpunit.xml y guía coincidían con fuente, vendor disponible y `.env` ausente. Antes de probar la entrega válida, repetir estas comprobaciones en su copia; no asumir que comprobar una ejecución acredita todas las demás.
- Codex repitió en la copia válida las huellas de AGENTS.md, composer.lock, package-lock.json y phpunit.xml; todas coinciden. Vendor es directorio normal, no enlace; `.env` y cache de configuración ausentes. No se copió ningún secreto.
- Scaffolding: se preservó la entrega de Gemini en el directorio hermano `gemini-delivery`, se generó primero el test con Artisan y se aplicó su contenido. Las seis pruebas fallaron sin modelo/enums, demostrando comportamiento ausente. Se generaron modelo/factory, enums y config con Artisan, y la migración real `2026_10_01_190708_create_store_settings_table.php`. Se aplicaron después los contenidos de Gemini a esos archivos. Se corrigió únicamente el nombre del test de defaults a `test_defaults_are_explicit_demo_values` y el nombre generado de la migración. La invocación inicial de enums con prefijo Enums duplicó esa carpeta; se retiraron esos dos esqueletos propios aislados y se generaron correctamente con nombres PriceTier/UserRole; ninguna copia extra se integró.
- Focalizada: `php artisan test --compact tests/Feature/StoreSettingsTest.php` → exit 0, 6 pruebas y 46 aserciones aprobadas. Casos: defaults/tipos, current sin duplicados, configuración alternativa por factory, defaults configurables antes de crear, conservación tras cambio de config y valores de enums.
- Suite completa: primer intento aislado detectó falta de APP_KEY en el Example HTTP existente, porque `.env` está excluido. Se usó exclusivamente en el proceso una clave ficticia de pruebas (32 bytes nulos codificados base64), restaurando el entorno anterior al terminar. `php artisan test --compact` → exit 0, 8 pruebas y 48 aserciones aprobadas. SQLite :memory:, sin migrar una BD de aplicación ni demostrar concurrencia MySQL.
- Formato: `php vendor/bin/pint --dirty --format agent` y Pint con los siete archivos explícitos en la copia → aprobado; la lista explícita garantiza revisión aunque Git ignore la carpeta de ejecuciones. Pint también aprobado en fuente después de copiar.
- Integración: Codex comprobó que la base y los siete destinos no habían cambiado, copió solo archivos revisados y comparó SHA-256 de cada uno con la versión probada/formateada aislada: igualdad en los siete. `git diff --check` sin errores. No se ejecutó build (sin frontend), no se hizo commit, no se ejecutó migrate en fuente ni se inició T1.2. La auditoría, la actualización monotónica de versión y la protección de semillas siguen pendientes en T1.2.

### Entrega T1.2 — aceptada e integrada

- Encargo autorizado por «sigue con el siguiente paso». Se conserva la autorización explícita previa para ejecutar código delegado en copia aislada con SQLite en memoria; no se amplió a producción ni a otros perfiles de verificación.
- `project_root=C:\Users\rubie\Desktop\e-commerce-garba`; `conversation_id=cc063cd8-11f7-4918-a783-fa5eba38619b`; `run_id=20261001T191122Z-977d0c50`. Gemini devolvió operaciones estructuradas; el conector marcó el run failed al rechazar `.env.example` por su filtro de rutas, tras aplicar cinco operaciones exclusivamente aisladas. Codex no importó esa operación rechazada: editó directamente solo el archivo de ejemplo, preservando su contenido, vaciando credenciales SMTP ficticias y añadiendo cuatro variables Stripe vacías. Nunca se leyó o editó `.env`.
- El test permitido `tests/Feature/StoreSettingsTest.php`, que aparecía después de la operación rechazada, se recuperó de la respuesta estructurada del registro local usando únicamente esa ruta exacta. El filtro había sustituido correos ficticios por `[REDACTED_EMAIL]`; Codex restauró el ejemplo original del test T1.1 y usó `private-contact@example.test` para el caso de privacidad nuevo. No se recuperaron operaciones sobre archivos de secretos.
- Modelo `AuditEntry`/factory generados con Artisan y después contenidos de Gemini aplicados; migración real generada `2026_10_01_191837_create_audit_entries_table.php`, sin modificar la migración T1.1 existente. Modelo, tests y seed siguen los convenios del proyecto.
- Contrato nuevo: `StoreSetting::updateCurrent(array $changes, ?User $actor = null): self`. Valida campos permitidos antes de mutar, rechaza id/version/desconocidos, obtiene configuración bajo lock y transacción con hasta tres intentos, usa versión actual en BD, no incrementa en cambios sin efecto y persiste auditoría en la misma transacción. Registrar valores comerciales antes/después; contactos/local solo indicador changed, sin valores privados. Es la operación que deberán consumir T4/T16 para cambios comerciales; no se exige a factories generar auditoría.
- `DatabaseSeeder` ahora rechaza producción antes de escribir y solo inicializa configuración de forma idempotente en pruebas/local; no crea el usuario demo del scaffold. No se añadió alta de administradores, panel ni roles operativos T2.
- Red: con test nuevo y código T1.1 de base, seis pruebas previas pasan y siete nuevas fallan por operación/modelo ausente o seed sin protección. Green: código aplicado, 13 pruebas/96 aserciones aprobadas; Codex reforzó la prueba de producción para comprobar cero filas en users/store_settings/audit_entries, y repitió focalizada → 13 pruebas/99 aserciones aprobadas.
- Pint: ejecutado `php vendor/bin/pint --dirty --format agent` y lista explícita de PHP de la entrega en copia aislada; aplicó únicamente estilos a StoreSetting. Suite posterior `php artisan test --compact` con APP_KEY ficticia temporal del proceso → 15 pruebas y 101 aserciones aprobadas. SQLite :memory:, sin secretos o BD comercial. Esto no acredita carreras ni locks reales en MySQL; se mantiene pendiente su preparación antes de integración financiera.
- Integrados siete archivos: StoreSetting.php, AuditEntry.php, AuditEntryFactory.php, migración audit_entries, DatabaseSeeder.php, StoreSettingsTest.php y .env.example. Fuente contrastada con nueve hashes antes de copiar; destinos nuevos comprobados ausentes. Pint en fuente aprobado, siete hashes iguales a copia probada/formateada y `git diff --check` sin errores.
- T1 completa aceptada en comportamiento/configuración/auditoría/protección de semillas. No se creó commit, instaló dependencias, ejecutó build, desplegó o migró la BD de aplicación. Se detuvo antes de T2.1.

### Entrega T2.1 — aceptada e integrada

- Solicitud de continuar con el siguiente punto; Gemini implementó exclusivamente T2.1 y Codex conservó la autorización de pruebas aisladas con SQLite. `project_root=C:\Users\rubie\Desktop\e-commerce-garba`; `conversation_id=06b98489-6791-4573-bb14-079e1b2a4613`; `run_id=20261001T194055Z-50d2abcb`. Conector completed/SUCCESS, verification none; pruebas PHP separadas por Codex.
- Entrega: User.php, UserFactory.php, bootstrap/app.php, EnsureActiveAccount.php, EnsureAdministrator.php, migración de acceso y StoreAuthenticationTest.php. No cambios a routes/web.php, dependencias, secretos o vistas. Copia verificada con reglas/lockfiles/phpunit/rutas coincidentes, sin .env ni config cache; base fuente preservada durante la revisión.
- Migración incremental generada por Artisan: `2026_10_01_194703_add_store_access_to_users_table.php`; agrega role por defecto installer, is_active falso, activated_at/suspended_at nullable. No concede acceso a usuarios preexistentes; no modifica migraciones anteriores.
- User conserva únicamente name/email/password fillable y sus campos ocultos; añade cast UserRole, boolean y fechas. `isActive(): bool` requiere bandera activa, fecha de activación y ausencia de suspensión. `isAdministrator(): bool` comprueba rol. Factory por defecto instalador inactivo; estados active/administrator/installer/inactive/suspended preparan usuarios ficticios de tests sin credenciales desplegadas.
- Aliases de middleware: `active.account` y `administrator`. Para futuras rutas web protegidas usar, en ese orden, `web`, `auth`, `active.account`, `administrator` (web ya viene en routes/web.php). EnsureActiveAccount consulta estado actual de BD, actualiza resolver/guard y rechaza cuenta inactiva/no activada/suspendida/eliminada con logout, invalidación de sesión y regeneración de token. EnsureAdministrator exige cuenta activa y rol administrador. T2.2 creará rutas/login/panel reales; T15 revocará todas las sesiones de cuentas suspendidas.
- Red: sin implementación T2.1, las 13 pruebas nuevas fallan por aliases/estados/casts ausentes. Modelos de T1 y datos de pruebas siguen aislados. Scaffolds de middleware y test/migración generados con Artisan antes de aplicar contenidos finales de Gemini.
- Primer intento con implementación: 12 pruebas correctas y un fallo al resolver nombre login en ruta definida dinámicamente. Codex comprobó RouteCollection del Laravel instalado y añadió `refreshNameLookups()` únicamente dentro del test; no añadió login a producción ni alteró el comportamiento de auth. Corrigió nombres de casos al plan y agregó aserciones de invalidación de datos de sesión y conservación exacta de fecha de activación frente a mass assignment.
- Focalizada: `php artisan test --compact tests/Feature/StoreAuthenticationTest.php` → 13 pruebas y 42 aserciones aprobadas. Cubre invitado HTML/JSON, instalador, administrador, inactivo, no activado, suspendido, privilegios enviados por request, rol cambiado, desactivación/suspensión/eliminación con sesión antigua y valores seguros por defecto/casts.
- Pint dirty y lista explícita de siete archivos en copia aislada aprobados (solo import/type formatting del test). Suite completa después de formato con APP_KEY ficticia temporal → 28 pruebas y 143 aserciones aprobadas; pruebas T1 incluidas. route:list en copia sigue mostrando una ruta de aplicación GET|HEAD /, sin register ni rutas `_test`.
- Integración: ocho hashes fuente comprobados antes de copiar; destinos nuevos ausentes; siete archivos copiados, Pint fuente aprobado y siete hashes coinciden con copia probada/formateada. git diff --check sin errores. No se ejecutó build, migración en fuente, commit o despliegue. SQLite no acredita concurrencia MySQL.
- T2 no está completa todavía: login/logout, comando de alta segura del administrador y pantallas corresponden a T2.2; segundo factor a T15. Se detuvo antes de T2.2.

### Entrega T2.2 — aceptada e integrada por Codex

- Encargo autorizado: continuar con el siguiente punto. Intento Gemini/Antigravity: `project_root=C:\Users\rubie\Desktop\e-commerce-garba`; `conversation_id=1f1b502f-4139-4d63-9689-e2527cb88f47`; `run_id=20261001T195454Z-19dcd3b6`; verification none. Estado failed, sin operaciones ni cambios de Gemini: su control de permisos rechazó Get-ChildItem. No se repitió la acción rechazada mediante Gemini. Codex implementó en la copia aislada ya disponible; esta entrega no se atribuye a Gemini.
- Base fuente: doce huellas SHA-256 comprobadas antes de integrar, sin divergencias. Copia aislada: reglas, lockfiles, phpunit, User y UserFactory coinciden; vendor y node_modules son directorios normales, sin .env ni config cache.
- Scaffolds de controller/request/command y dos tests generados con Artisan. Red: las 12 pruebas nuevas fallaron por rutas/comando inexistentes. Green: implementación local, 12 pruebas/97 aserciones aprobadas con SQLite :memory:. APP_KEY ficticia únicamente en proceso para la suite completa.
- Login normaliza correo, valida credenciales mediante Auth::attemptWhen y rechaza cuentas inactivas/no activadas/suspendidas con el mismo mensaje genérico. Límite de cinco fallos por correo+IP y límite adicional de veinte peticiones por IP/minuto. Éxito limpia contador y regenera sesión; destinos fijos panel o home, sin aceptar privilegios/redirecciones del request.
- Logout es POST autenticado, invalida sesión y renueva CSRF. Panel usa auth, active.account y administrator, con Cache-Control private/no-store. Sin registro público ni GET logout. Prueba CSRF desactiva expresamente la exención de testing y comprueba 419 sin token y cierre válido con token.
- Comando store:create-admin con email y nombre: contraseña y confirmación ocultas, sin fallback visible, mínimo doce caracteres, hash por cast; activa explícitamente rol Administrator. Correo duplicado/incorrecto, contraseña corta/distinta y ejecución no interactiva fallan sin escribir ni promover cuentas existentes. No se ejecutó contra la BD de aplicación.
- Vistas Blade/Tailwind en español: layout, login y panel básico con saludo real; etiquetas, errores asociados y foco de teclado, anchos fluidos. Sin cifras ficticias, dependencias ni funcionalidades anticipadas. Revisión visual del código y render mediante feature tests; no se realizó inspección de navegador.
- Regresión detectó conflicto del login simulado de T2.1 con la ruta real. Se retiró únicamente la ruta login ficticia del test y se comprobó la redirección con route('login'). Focalizada T2.1: 13/42 aprobadas. Suite final tras Pint: 40 pruebas/240 aserciones aprobadas.
- Pint dirty y lista explícita de PHP aprobados en aislado; Pint fuente aprobado. Build aislado inicialmente bloqueado por sandbox spawn EPERM; repetido mediante ejecución autorizada fuera del sandbox, aprobado. Build fuente aprobado, sin instalar paquetes. Aviso no bloqueante existente sobre fontaine opcional; no se modificaron dependencias.
- Integración: once archivos PHP/Blade/rutas/bootstrap/tests revisados, copiados y contrastados con la versión aislada mediante SHA-256. git diff --check aprobado. Sin commit, despliegue ni migraciones en BD de aplicación. T2 cerrada; T3.1 no iniciada; MFA y recuperación siguen en T15.

Para ver estas pantallas localmente, con la BD de desarrollo configurada, ejecutar `php artisan migrate --no-interaction`, crear el administrador con `php artisan store:create-admin --email=tu-correo@example.com` y arrancar `php artisan serve`. El comando de administrador debe ser interactivo para pedir la contraseña oculta; no usar --no-interaction en ese comando. Los estilos ya se compilaron con npm run build. Abrir http://127.0.0.1:8000/login. Estos comandos de preparación de BD/usuario/servidor no fueron ejecutados por Codex.

### Entrega T3.1 — aceptada e integrada

- Solicitud: continuar con el siguiente punto. Se conserva la autorización previa de ejecución aislada con SQLite en memoria. `project_root=C:\Users\rubie\Desktop\e-commerce-garba`.
- Primer intento completo Gemini: `run_id=20261001T200900Z-537cd0ed`, `conversation_id=77d49796-ea8d-4cdc-a1b2-0ff6c07e3fe5`, verification none. Falló por límite de tokens de salida; cero operaciones aplicadas. La llamada inicialmente no devolvió structuredContent y el guardado local de ese valor undefined falló; Codex comprobó luego el estado terminal mediante get_run, sin atribuirle una entrega.
- Segunda opinión conceptual Gemini: `conversation_id=360ec450-d11d-422f-8897-10162067545c`, sin run de implementación ni lectura propia de archivos. Adoptadas pruebas de dinero con cero/uno/dos decimales, privacidad y colisiones con slugs inactivos. No se adoptó renombrar slugs al desactivar, añadir desactivación de categorías ni editor enriquecido: el contrato mantiene identificadores únicos y texto plano escapado.
- Entrega reducida de núcleo Gemini: `run_id=20261001T201818Z-8be2a2ae`, `conversation_id=6c6f02e5-169a-424a-9a4f-840eb4b4402b`; completed, siete operaciones aisladas, verification none. Archivos: Category/Product, factories, dos migraciones y ProductPolicy. No ejecutó comandos de listado, lectura ni pruebas; usó los contratos suministrados. Codex implementó requests/controladores/vistas/rutas y las pruebas.
- Copia de supervisión separada: `.antigravity-mcp/local-t31-20261001`, con vendor normal, sin .env ni config cache. Reglas/lockfiles/phpunit/User contrastados con fuente tanto en esta copia como en la entrega reducida. No se leyeron secretos. Scaffolds de test, modelos/factories/migraciones, policy, requests y controllers generados con Artisan antes de aplicar contenidos.
- Migraciones reales: `2026_10_01_202535_create_categories_table.php` y `2026_10_01_202536_create_products_table.php`, conservando anteriores. Categoría name/slug; Product category_id, name/slug, description/specifications/equipment_contents, base_cost_cents BIGINT, is_published falso, is_active verdadero. FK restringe eliminar categorías referenciadas; no existen rutas DELETE. Desactivar conserva fila/relación. Codex retiró duplicación de fillable/hidden por propiedades y atributos en la entrega Gemini para seguir la convención de User; se mantienen los atributos Laravel 13.
- Costo de fábrica privado con Hidden; panel accede explícitamente. Formulario base_cost es texto decimal, conversión entera a centavos sin floats, de 0.01 a 9999999999.99 unidades monetarias; esta cota previene entradas monetarias desmedidas, no limita existencias. No confía en base_cost_cents/price/id del request. Slugs minúsculos únicos incluso en productos desactivados; actualización ignora únicamente el modelo vinculado a la ruta.
- Rutas admin.products index/create/store/edit/update y admin.categories index/store/update, todas bajo auth/active.account/administrator. ProductPolicy y requests refuerzan autorización. Todas las vistas del catálogo private/no-store. Blade escapa nombre/textos; formularios CSRF, etiquetas/errores/foco, checkbox con hidden 0 y old input. Dashboard ahora enlaza categorías/productos. No fotos, semillas, stock, tienda pública ni tarifas adelantadas.
- Red inicial: 20 pruebas fallaron por rutas/modelos ausentes. Green inicial: 20/164. Revisión independiente de solo lectura, siguiendo requesting-code-review: sin defectos bloqueantes; recomendó comprobar directamente FK, aislamiento de errores y flags omitidos. Se añadieron esos casos y CSRF real.
- Una prueba de seguimiento de errores falló porque el cliente de tests no conservaba la cookie de sesión y el driver JSON no recuperaba el error como un navegador. Codex contrastó StartSession/Store/ShareErrorsFromSession instalados; al conservar explícitamente la cookie entre PUT y GET, los errores aparecen solo en la categoría editada. Se reforzó también el seguimiento de error/checkbox de producto con cookie real. No fue necesario alterar el código de sesión de la aplicación.
- Focalizada final `php artisan test --compact tests/Feature/AdminCatalogTest.php`: 24 pruebas/188 aserciones, cero fallos. Casos: invitados/instaladores/suspendidos, creación/edición/publicación/desactivación, defaults, costo exacto y límites, centavos forjados, categoría inválida/slugs repetidos, escape HTML, paginación, privacidad, checkbox tras error, policy directa, restricción FK y CSRF sin exención de testing.
- Pint dirty y lista explícita de PHP aislado aprobados; suite final con APP_KEY ficticia solo en proceso: 64 pruebas/428 aserciones aprobadas, SQLite :memory:. Build aislado y fuente aprobados usando el permiso existente de npm run build fuera del sandbox. Aviso no bloqueante de fontaine opcional, sin instalar dependencias. Revisión de markup/render/tests y estilos compilados; no inspección de navegador ni prueba de concurrencia MySQL.
- Integración: diez hashes base fuente sin divergencias, diecisiete destinos nuevos ausentes; diecinueve archivos revisados copiados, Pint fuente aprobado y diecinueve SHA-256 iguales a la versión aislada probada. git diff --check aprobado. Se actualizan únicamente esta guía y el plan técnico como registro. No commit, despliegue, migraciones en BD de aplicación ni usuarios reales creados.

Para ver el catálogo, si T2.2 ya está preparado localmente, aplicar las dos migraciones nuevas con `php artisan migrate --no-interaction` y entrar como administrador en `/admin`; allí están los enlaces a categorías y productos. Los estilos ya están compilados. Si aún falta preparar el acceso, seguir las instrucciones de T2.2 anteriores. Codex no ejecutó estas migraciones en la BD de aplicación.

Al cerrar T3.1 quedaron fotografías y seed demo para T3.2. La validación del precio efectivo antes de publicar se completará en T4.

### Entrega T3.2 — aceptada e integrada

- Encargo: continuar con el siguiente punto, conservando la autorización de pruebas aisladas. `project_root=C:\Users\rubie\Desktop\e-commerce-garba`; `run_id=20261001T205914Z-a8b87a35`; `conversation_id=6bac8373-d428-445b-8011-0d0d65a144e2`; verification none. La llamada inicial agotó el tiempo de espera del conector; get_run recuperó el estado completed y seis operaciones aplicadas exclusivamente en su workspace aislado. No fue una entrega fallida ni se repitió la ejecución.
- Gemini entregó ProductPhoto, factory, migración, StoreProductPhotoRequest, UpdateProductPhotoRequest y ProductPhotoController. Codex generó scaffolds con Artisan, adaptó Fillable a la convención instalada, añadió relación, rutas, formulario/galería, seeder y pruebas, y corrigió los casos de fallo. Migración real: `2026_10_01_210655_create_product_photos_table.php`.
- Copia independiente `.antigravity-mcp/local-t32-20261001`: vendor normal, sin .env ni config cache; reglas/lockfiles/phpunit contrastados con fuente y copia Gemini. PHP GD ausente: fixtures PNG/JPEG/WebP reales mediante createWithContent, sin instalar dependencias ni usar fake()->image.
- Fotos: FK restringida, ruta única y controlada, nombre UUID según MIME real, alt requerido hasta 255 caracteres y orden entero entre 0 y 1000000. JPEG/PNG/WebP hasta 5120 KiB y dimensiones máximas inclusivas 6000×6000; extensiones, MIME y contenido validados. No SVG/HTML, nombres ejecutables, rutas recibidas del cliente ni borrado/reemplazo de fotos. PUT con scoped bindings impide editar una foto de otro producto.
- Ruta nueva conocida antes de escribir; ante escritura parcial fallida, excepción de BD o guardado cancelado por eventos, se elimina solo ese archivo nuevo y se conserva el existente. Si el almacenamiento impide eliminarlo, se reporta el fallo de limpieza y se propaga el error original; no se garantiza limpieza durante una caída del disco.
- Galería en edición del producto: URL pública, alt escapado, orden estable por sort_order/id, formularios independientes con CSRF y errores asociados, carga multipart. Seeder StoreDemoSeeder solo local/testing y explícito, idempotente, sin sobrescribir cambios: categoría y dos productos DEMOSTRACIÓN en borrador. No usuarios, credenciales ni fotos ficticias; no se incorporó al DatabaseSeeder.
- Red inicial: 14 pruebas fallaron por comportamiento ausente. Revisión independiente de solo lectura detectó que HasMany::create puede devolver un modelo no persistido cuando un evento cancela save; Codex reprodujo el defecto y comprobó exists antes de aceptar la operación. Revisión posterior confirmó la corrección sin otros defectos bloqueantes.
- Focalizada final: `php artisan test --compact tests/Feature/ProductPhotosTest.php tests/Feature/StoreDemoSeederTest.php` → 22 pruebas/121 aserciones aprobadas. Incluye límites exactos de tamaño/dimensiones, formatos reales y falsos, acceso/CSRF, aislamiento entre productos, metadatos forjados, render/escape/orden, fallos de disco/BD/eventos y protección/idempotencia del seed.
- Suite completa final en SQLite :memory: con APP_KEY ficticia solo en proceso: 86 pruebas/549 aserciones aprobadas. Pint dirty y lista explícita de archivos aprobados en aislado, Pint fuente aprobado. Build aislado y fuente aprobados sin instalar paquetes. Inspección de markup y render por pruebas; queda pendiente inspección visual en navegador. No se acredita concurrencia MySQL.
- Integración: once hashes base sin divergencias, destinos nuevos ausentes; catorce archivos seleccionados y sus SHA-256 idénticos a la copia probada/formateada. `git diff --check` aprobado. `php artisan storage:link --no-interaction` ejecutado en fuente: public/storage es Junction a storage/app/public dentro del proyecto, ignorado por Git. Sin migraciones, seeder, cuentas reales, commit ni despliegue en fuente.

Para probar las fotos con el acceso local ya preparado, ejecutar `php artisan migrate --no-interaction` y abrir la edición de un producto desde `/admin/products`. El almacenamiento público y los estilos están preparados. Opcionalmente, solo en desarrollo local, `php artisan db:seed --class=StoreDemoSeeder --no-interaction` crea los dos productos ficticios en borrador; estos comandos de BD no fueron ejecutados por Codex.

T3 cerrada funcionalmente. Siguiente punto T4.1, cálculo de precios; se detuvo al terminar T3.2.

### Entrega T4.1 — aceptada e integrada

- Encargo: continuar con el siguiente punto. Se mantiene la autorización de implementación Gemini aislada y pruebas PHP con SQLite :memory:. `project_root=C:\Users\rubie\Desktop\e-commerce-garba`; `run_id=20261001T213644Z-6a30c2b2`; `conversation_id=7a2160f8-9e30-439d-8153-46a886cdad41`; verification none. La llamada agotó el tiempo de espera del conector; get_run confirmó primero running y después completed, AGY SUCCESS, cuatro operaciones aisladas. No se repitió la ejecución.
- Copia de supervisión `.antigravity-mcp/local-t41-20261001`: sin .env ni config cache, vendor normal. Reglas/lockfiles/phpunit y modelos contrastados con fuente; base anterior 86 pruebas/549 aserciones aprobadas. Scaffolds de tres DTOs, servicio y test generados con Artisan. Antes de aplicar implementación se observaron fallos por quote/validación ausentes: 25 casos iniciales y casos adicionales focalizados en red.
- Gemini entregó CartLineData, QuoteLineData, QuoteData y PricingService. Codex implementó PricingServiceTest con Tests\TestCase y RefreshDatabase aunque su ubicación prevista sea tests/Unit: prueba la integración real de configuración/productos persistidos, sin mocks de cálculo ni DB comercial.
- Dinero en centavos y tasas en escala 10000; aumento sobre costo, no margen. Unitario con mitad hacia arriba antes de multiplicar cantidad; IVA redondeado por línea, total por sumas enteras. Multiplicación/división descompuesta evita overflow intermedio cuando el resultado cabe. Cantidades/precios no positivos, configuración inválida, entradas malformadas o productos ausentes se rechazan; overflow real lanza OverflowException antes de operar.
- Revisión independiente detectó una expectativa incorrecta de Codex: costo 1 y markup PHP_INT_MAX producen 922337203685479, representable. Se convirtió en prueba positiva. La entrega inicial aprobó 37 de 39 casos; fallaban ese límite y moneda minúscula. Codex sustituyó factor 10000+markup por costo+round(costo*markup/10000), equivalente sin suma intermedia innecesaria; exigió código de moneda mayúsculo, hizo quote método de instancia según contrato y retiró un null check imposible. Después pasaron los 39 casos.
- Servicio interno `quote(array $lines, PriceTier $tier): QuoteData`; fusiona cantidades del mismo producto antes del IVA, ordena por ID y consulta productos en lote. Carrito vacío devuelve cero con configuración validada. Permite cotizar borradores para futura vista previa: los callers públicos de T5/T6 deberán autorizar la tarifa y filtrar disponibilidad; no es un endpoint público. No consulta overrides inexistentes de T4.2.
- DTOs readonly con serialización explícita snake_case, descuento inicial cero y base descontada igual base. No contienen modelos completos, costo privado, markup ni otra tarifa. Fingerprint SHA-256 de IDs/nombres/cantidades/tarifa/moneda/valores efectivos/impuesto/versión; no es un token de acceso. Las cotizaciones ya devueltas permanecen inmutables tras cambios de BD.
- Focalizada `php artisan test --compact tests/Unit/PricingServiceTest.php`: 39 pruebas/111 aserciones aprobadas. Incluye ambos ejemplos de tarifa, tres unidades, umbrales de redondeo, IVA por línea frente a unitario/global, duplicados, privacidad, cambios comerciales/IDs en huella, snapshots inmutables, límites representables y overflow de unitario/cantidad/IVA/agregados.
- Pint dirty y lista explícita de cinco archivos aprobados en aislado; suite completa después de formato: 125 pruebas/660 aserciones aprobadas, SQLite :memory:, APP_KEY ficticia solo en proceso. Revisión independiente final de solo lectura: sin defectos críticos/importantes. No se ejecutó build porque no cambiaron frontend ni assets.
- Integración: ocho hashes fuente sin divergencias y cinco destinos nuevos ausentes; cinco archivos revisados copiados, Pint fuente aprobado y SHA-256 iguales a copia probada/formateada. git diff --check aprobado. Sin dependencias, rutas, migraciones, seeds, usuarios, commit ni despliegue; BD de aplicación intacta.

T4 permanece abierta hasta T4.2: excepciones manuales por tarifa, panel de precios y validación de publicación con precio efectivo. T4.1 añade el motor interno; no introduce una pantalla nueva. Se detuvo al terminar este punto.

### Entrega T4.2 — aceptada e integrada

- Retomada tras interrupción por límite de uso. Los catorce archivos ya estaban integrados; Codex confirmó igualdad SHA-256 con `.antigravity-mcp/local-t42-20261001` antes de registrar el cierre. No se repitió la implementación ni se atribuyó una nueva entrega a Gemini.
- Archivos: Product, StoreSetting, PriceOverride y factory; migración `2026_10_01_215310_create_price_overrides_table.php`; PricingService/PricingAdministrationService; PricingController/ProductController; UpdatePricingRequest; vista de precios/panel; rutas y PricingAdministrationTest.
- Excepción única por producto/tarifa, precio base entero positivo sin IVA y relación privada. Precios manuales independientes, conservados ante cambios de costo/aumentos; dejar vacío vuelve al cálculo automático de esa tarifa. Vista previa de ambas tarifas calculada por el mismo motor, con/sin IVA y origen manual/automático.
- Cambios auditados con actor y antes/después, versión monotónica y transacción; operaciones sin efecto no generan auditoría ni versión. Cancelación de eventos de persistencia/eliminación/auditoría y overflow revierten cambios. Publicación y cambios de porcentajes validan cotizaciones efectivas de ambas tarifas. Revalidación de publicados por lotes de 100 con overrides precargados.
- Verificación nueva en fuente: `php artisan test --compact tests/Feature/PricingAdministrationTest.php`: 42 pruebas/206 aserciones; `php artisan test --compact`: 167 pruebas/866 aserciones, ambas exit 0. PHPUnit configurado con SQLite en memoria y sin caché de configuración; esto no acredita concurrencia MySQL.
- `php vendor/bin/pint --dirty --format agent`, `npm run build` y `git diff --check` aprobados. Rutas administrativas: 14, incluidas GET/PUT de precios y PUT por producto. Build conserva el aviso no bloqueante de fontaine opcional; dependencias intactas. Inspección visual en navegador pendiente.
- Plan y guía actualizados. Sin commit, despliegue ni migración en BD de aplicación. Para abrir el panel de precios localmente, aplicar las migraciones pendientes con `php artisan migrate --no-interaction` sobre la BD de desarrollo configurada y visitar `/admin/pricing` con cuenta administrativa activa.

T4 cerrada. Siguiente punto T5.1; no iniciado.


### Entrega T5.1 — 02-10-2026

Implementación de Codex en la copia aislada .antigravity-mcp/local-t51-20261001, retomada de la sesión anterior. El registro local de Antigravity 20261002T055214Z-ff9119e5 permanecía en running y no contenía entrega; no se atribuye código a Gemini ni se modifica su historial.

Catálogo en / y /catalogo; ficha por slug en /productos/{slug}; búsqueda literal por nombre/descripción, categorías visibles y paginación de 20. Solo productos activos/publicados. Tarifa consultada nuevamente en BD: instalador vigente activo recibe la privada; las demás audiencias reciben la pública. Proyección explícita HTML/JSON sin costo ni segunda tarifa, IVA incluido y Cache-Control private, no-store. Fotografías ordenadas y textos escapados. Sin carrito ni cupones.

Verificación aislada: 31 pruebas/241 aserciones de StorefrontTest pasan tras comprobar fallo por métodos ausentes; suite completa 198/1107. ExampleTest actualizado para preparar BD, configuración ficticia y Vite al servir ahora catálogo en portada. Pint y build aprobados. Revisión independiente de solo lectura sin hallazgos bloqueantes. Nueve archivos integrados con SHA-256 idénticos y base fuente comprobada. Pendiente inspección visual en navegador; adaptación responsiva y foco revisados en código. No se ejecutan migraciones/seeds sobre la BD de aplicación, commits ni despliegue. T5 sigue abierta hasta T5.2.

Revalidación en fuente después de integrar: php artisan test --compact, 198 pruebas/1107 aserciones aprobadas; Pint --dirty y npm run build aprobados. Los nueve archivos mantienen SHA-256 idéntico a la copia probada tras formatear.

### Entrega T5.2 — 02-10-2026

Encargo autorizado por el usuario al continuar con el siguiente punto. Base fuente incluye T5.1 sin commit; huellas antes de editar en .antigravity-mcp/local-t52-20261002/base-hashes.json. No existe .ai/rules. PHP/Laravel y dependencias comprobados; se conserva Laravel 13.33.0, PHPUnit 12.5.36 y Tailwind 4/Vite 8, sin ampliar dependencias.

Delegación Gemini: project_root=C:\Users\rubie\Desktop\e-commerce-garba; conversation_id=a2ae996c-47fc-42e2-80d0-80b6d40528ab; run_id=20261002T154620Z-f375f770. antigravity_execute con verification=none permaneció aislado y agotó el timeout del conector. Registro final failed: Antigravity did not return structured file operations. Ningún archivo entregado o adoptado de Gemini. Implementación íntegra de Codex en .antigravity-mcp/local-t52-20261002.

Carrito invitado con interfaces CartService::lines/put/remove, rutas cart.show/cart.update, formulario de cantidad en ficha y navegación. Sesión cart.lines contiene exclusivamente IDs/cantidades; put reemplaza la cantidad, no la suma. Productos retirados, inexistentes y filas corruptas se sanean al leer, con aviso. Cotización completa del motor T4 antes de persistir una actualización; cantidades sin límite de inventario, enteros representables y rechazo de overflow unitario/acumulado. Revisión de tarifa vigente en BD, precios recotizados en cada consulta. Logout invalida sesión y CSRF, conservando solamente líneas saneadas; el carrito posterior muestra precio público. Proyección explícita sin costos, otra tarifa ni origen de overrides. Cache-Control private, no-store también en respuestas de excepción de las rutas de tienda/carrito mediante bootstrap/app.php.

Información de recogida usa StoreSetting para local/contacto y config/store.php para condiciones, privacidad y devoluciones. Textos ficticios señalados, escapados, recogida exclusiva y espera del segundo correo sin prometer disponibilidad inmediata. Decisión: textos de políticas configurables en configuración, sin migración ni panel adicional; datos del local ya administrables por T1/T4. Si se necesita edición de políticas desde el panel, requerirá otro encargo.

Verificación: 26 casos iniciales de CartTest fallaron por endpoints/métodos ausentes; luego pasaron. Revisión independiente de solo lectura detectó saneamiento que podía mutar sesión antes de rechazar overflow y ausencia de no-store en validación. Ambos reproducidos en 12 pruebas fallidas y corregidos: saneamiento puro para candidate antes de confirmar; callback de respuestas de excepción limitado a cart.*, store.* y home. Caso de regresión nuevo conserva incluso productos retirados ante rechazo y sanea después al consultar. CartTest final: 27 pruebas/210 aserciones; cierre StorefrontTest|CartTest: 58/451. Regresión aislada final: 225/1317, Pint y build aprobados. No quedan hallazgos de revisión diferidos. Las pruebas HTML conservan explícitamente la cookie al seguir un redirect porque la sesión JSON de Laravel se vuelve a iniciar entre peticiones del harness; navegador real confirma mensajes y aria-invalid.

Chromium/Playwright ya disponibles, sin instalar paquetes: revisión de catálogo, ficha, carrito e información a 360 y 1280 px, sin desbordamiento horizontal ni errores JavaScript. Alta por Tab/Enter, actualización/eliminación y validación HTML real aprobadas. Capturas inspeccionadas de carrito móvil y ficha de escritorio. Evidencia y capturas en la copia aislada, t52-browser-results.json y t52-*-*.png. Verificación inicial de Playwright bloqueada por sandbox (WinError 5); rerun autorizado con escalación, sobre BD SQLite nueva y ficticia dentro de la copia, servidor terminado al concluir. Se cierra la inspección visual pendiente de T5.1.

Trece archivos integrados por SHA-256 idéntico a la copia verificada y base fuente intacta; paths en integrated-paths.json. Regresión focalizada en fuente 58/451, Pint --dirty y build aprobados. Sin migrar/sembrar BD de aplicación, ejecutar cobros, desplegar o hacer commit. T6.1 no iniciado.

Cierre T5.2 y T5: revalidación en fuente php artisan test --compact aprobada, 225 pruebas/1317 aserciones; proceso exit 0. Focalizada 58/451, Pint/build y revisión de navegador aprobados. Punto aceptado; siguiente T6.1, a solicitud del usuario.

### Entrega T6.1 — 02-10-2026

Continuación autorizada del siguiente punto. Implementación de Codex en copia aislada .antigravity-mcp/local-t61-20261002; base T5 preservada por SHA-256 en base-hashes.json. No existe .ai/rules. Versiones verificadas con composer show --direct y package.json; sin dependencia nueva. PHP generado con Artisan. Skill testing-best-practices no disponible en el catálogo/directorios de habilidades; se aplicaron test-driven-development, convenciones de PHPUnit existentes y revisión independiente.

ContactData final readonly conserva las cuatro propiedades y firma acordadas: name, email, phoneOriginal, phoneCanonical. PhoneNormalizer::normalize(string input, string country): string devuelve +52 y 10 dígitos nacionales o ValidationException con error genérico por campo, sin revelar el teléfono en el mensaje. Se admiten diez dígitos nacionales, +52 y separadores ASCII de espacio/guion/paréntesis simples de área. Solo MX explícito, normalizado sin inferir país del número. Se rechazan prefijos antiguos +521/01/044/045, números locales de 7/8 dígitos, otros países, extensiones, controles, Unicode ambiguo y longitudes/tipos inválidos. Validación estructural; no verifica asignación, disponibilidad ni propiedad del número.

Fuentes técnicas primarias comprobadas: https://www.ift.org.mx/comunicacion-y-medios/marcacion-a-10-digitos y https://sns.ift.org.mx/sns-frontend/consulta-numeracion/numeracion-geografica.xhtml. La primera establece diez dígitos y +52 sin el antiguo 1; la segunda identifica zonas 2–9. No se inventa un directorio de números/áreas asignados.

StartCheckoutRequest valida name/email/phone/country obligatorios, límites 150/255/64 caracteres y país MX. Normaliza nombre/correo/país; preserva exactamente el teléfono válido original, incluidos espacios exteriores, con excepción phone en TrimStrings de bootstrap/app.php. after() comprueba el teléfono incluso cuando el controlador no construye el DTO. contactData() calcula el canónico desde datos validados; inputs phone_canonical, phone_original, user_id, price y role no controlan el resultado. Nombre Unicode permitido. No cambia autenticación ni consulta pedidos por teléfono. No exige cuenta/domicilio. El request prepara T6.2: no se crean rutas/controller/formulario de checkout, modelos/pedidos/tokens ni pagos en T6.1. Rutas de pruebas exclusivamente dentro de ContactValidationTest; route:list en fuente confirma ausencia de checkout y _tests.

Consulta Gemini de solo lectura, razonamiento sobre resumen sin herramientas/código: project_root=C:\Users\rubie\Desktop\e-commerce-garba; conversation_id=f30fbbc2-6622-4ee3-9d85-42145d06b592; run_id=no generado (consulta de análisis). Resultado SUCCESS. Consideraciones verificadas: validación previa al DTO mediante after(), espacios externos sin modificar el original, nombres Unicode y rechazo de CRLF. No se adoptó conversión silenciosa de +521, normalización de separadores Unicode no acordados ni helper para paqueterías (recogida local; sin envíos). Gemini no escribió archivos; implementación atribuida a Codex.

Decisiones: soporte inicial MX conforme al alcance y fuentes; ampliar países requiere reglas verificadas. Se aceptan solo separadores ASCII y se rechazan prefijos antiguos con instrucción +52 y diez dígitos; costo si el usuario pega otro formato: deberá corregirlo. Datos inválidos UTF-8 se sustituyen por null para rechazar el campo, sin transformar silenciosamente un contacto inválido en válido; teléfonos válidos conservan íntegra la entrada. El HTML de error guarda únicamente cadenas de los cuatro campos de contacto seguras para JSON, no campos privilegiados/arrays ajenos; futuros metadatos de checkout se resolverán desde servidor en T6.2.

Pruebas iniciales: 85 casos, 31 fallos/53 errores por métodos ausentes/request sin implementar y una comprobación de ausencia de rutas ya válida; luego 85/206 aprobadas. Revisión independiente estática: P2 por not_regex con /u, que aceptaba error PCRE sobre nombre UTF-8 inválido. Reproducción con POST de formulario, no JSON de entrada: nombre inválido producía 500 en vez de 422; entrada inválida de teléfono hacía no serializable el old_input de la sesión JSON. Cinco regresiones de revisión, dos fallaron. Corrección: regex afirmativa anclada, comprobación explícita UTF-8 y failedValidation que limita/sanea el input que se conserva; requiere hacerlo en el FormRequest porque Handler usa el request HTTP original, no la copia preparada. Todas esas regresiones pasan. Prueba adicional confirma rechazo desde validación sin invocar contactData(). Sin hallazgos diferidos.

Resultado aislado final: PhoneNormalizerTest|ContactValidationTest, 91 pruebas/227 aserciones; suite completa 316/1544. Pint --dirty y formato explícito de archivos nuevos aprobados. Sin cambios frontend, build no requerido. Seis archivos integrados con SHA-256 idéntico a copia verificada y base fuente intacta; rutas en integrated-paths.json. No se modifica BD de aplicación, seeding, dependencias, commits ni despliegue. T6.2/T6.3 pendientes; T6 aún abierta.

Cierre T6.1 en fuente: php artisan test --compact --filter='PhoneNormalizerTest|ContactValidationTest' aprobado 91/227; suite completa php artisan test --compact aprobada 316/1544, exit 0. Pint --dirty aprobado y seis archivos idénticos por SHA-256 tras verificación. T6.1 aceptado; siguiente T6.2 a solicitud del usuario.

### Entrega T6.2 — aceptada e integrada, 02-10-2026

- Encargo del usuario: continuar el siguiente punto, T6.2 exclusivamente. Inspección de AGENTS.md, contratos T6, modelos/servicios/factories vecinos y versiones instaladas: Laravel 13.33.0, PHP 8.5, PHPUnit 12.5.36, Pint 1.32.1. `.ai/rules` ausente. La skill `testing-best-practices` no está disponible en el catálogo ni en las carpetas de skills consultadas; se aplicaron las convenciones PHPUnit existentes y las guías de pruebas/verificación disponibles. Base: 316 pruebas/1544 aserciones aprobadas y hashes de app/bootstrap/config/database/resources/routes/tests guardados antes de modificar.
- Delegación Antigravity aislada con verification none, sin ampliar permisos: project_root `C:\Users\rubie\Desktop\e-commerce-garba`; primera ejecución `20261002T172034Z-61861c25`, conversación `122e5a20-59bd-4571-81cd-e46bd5e4bc35`, failed por comando de listado denegado, sin operaciones. Segunda ejecución `20261002T172620Z-6e102f46`, conversación `a2cc7b6c-8107-41d2-a9e2-63966720979f`, recibió contexto completo y prohibición de herramientas; la llamada excedió 300 s, pero get_run posterior confirmó completed y 13 archivos aislados. Al llegar, Codex ya había implementado y probado su alternativa. Se leyó la propuesta como contraste, sin ejecutarla ni integrarla: rechazaba invitados pese al contrato nullable y llamaba estáticamente al método de instancia PricingService::quote. No se atribuye a Gemini el código integrado.
- Copia de supervisión `.antigravity-mcp/local-t62-20261002`, con código/factories/migraciones/enums/test generados mediante Artisan; sin .env, BD comercial ni secretos. PHPUnit usa SQLite :memory:. Fallo inicial por CheckoutService ausente y luego start ausente: 35 casos preparados, 34 errores por comportamiento ausente y un caso de mass assignment ya satisfecho por el modelo base.
- Order/OrderLine/PaymentAttempt con factories, dos enums y tres migraciones incrementales: `2026_10_02_172308_create_orders_table.php`, `2026_10_02_172309_create_order_lines_table.php`, `2026_10_02_172310_create_payment_attempts_table.php`. Importes BIGINT en centavos y estados Pending/Creating al iniciar. Snapshot incluye contacto original/canónico, tarifa, moneda, versión/fingerprint, nombre/cantidad/origen de precio, bases/descuento/IVA/totales; no incluye costo ni otra tarifa. UUIDs para referencias y clave remota idempotente estable. Campos nullable de acceso y Stripe preparados; T6.3/T7 implementarán su uso.
- CheckoutService conserva la firma compartida. Revalida actor persistido/activo y deriva tarifa de servidor: invitado/administrador pública, instalador activo privada. Canonicaliza lista de CartLineData, suma duplicados sin overflow y ordena IDs. Configuración, productos y overrides se leen con bloqueos actuales; cálculo por PricingService::quoteLine y fingerprint por QuoteData. Cotización obsoleta/producto no disponible/cantidad inválida/mínimo o máximo incompatible se rechazan antes de crear el pedido. Mínimo configurable inicial 1000; límite técnico 99999999 verificado contra [Stripe PaymentIntent amount](https://docs.stripe.com/api/payment_intents/create). La configuración de la cuenta real sigue pendiente en T7/lanzamiento.
- Pedido, líneas e intento se confirman en una transacción con tres intentos acotados de deadlock y mismos UUIDs. Se comprueban cancelaciones de save además de excepciones. Submission UUID único en BD y hash de carrito/contacto/propietario/aceptación impiden reutilizar la confirmación para otros datos. Replay legítimo devuelve el intento original sin recotizar, incluso tras cambiar precio/nombre/IVA o desactivar el producto; actor suspendido no reanuda. Solo un conflicto en el índice/columna submission_key admite recuperación; otras violaciones únicas se propagan. No hay HTTP/SMTP ni acceso a pedidos basado en esta clave.
- Modelos completamente protegidos contra mass assignment; contacto, hashes, claves internas y parámetros ocultos en serialización. Guards impiden modificaciones/eliminaciones de snapshots mediante operaciones Eloquent ordinarias, permitiendo estado financiero y futuros campos de acceso/Stripe. Referencias históricas tienen FK restrict; más de un intento por pedido permitido. Las actualizaciones directas SQL no son un API público ni sustituyen estos guards.
- Revisión independiente de solo lectura detectó riesgo P2 bajo MySQL REPEATABLE READ si un caller abre antes una transacción/snapshot: reconsultar PricingService::quote podía ignorar los datos actuales bloqueados. Codex corrigió usando las instancias bloqueadas de configuración/productos/overrides y el cálculo compartido quoteLine. Regresión con lector de cotización consistente obsoleto inyectado pasó de aceptación incorrecta a rechazo sin persistencia. Otra regresión reprodujo llamada errónea a RuntimeException en configuración inválida; constructor corregido y rollback verificado. No hay hallazgos diferidos. Esta simulación no acredita concurrencia física MySQL de dos conexiones; preparación de BD dedicada y pruebas de carreras permanecen pendientes según T8/T13/T17.
- Verificación focalizada final: `php artisan test --compact tests/Feature/OrderCheckoutTest.php`, 43 pruebas/131 aserciones, exit 0. Cubre ejemplos 150800/127600/452400, tarifas frescas, stale producto/IVA, replay y conflictos, rollback/cancelación, privacidad/mass assignment, dinero inmutable, cantidad con overflow, override e IVA por línea, límites 999/1000 y 99999999/100000000, múltiples intentos y ausencia de red/correo. Suite completa final en aislado y en fuente: 359 pruebas/1675 aserciones, exit 0. Pint de archivos explícitos y `php vendor/bin/pint --dirty --format agent` en fuente aprobados.
- Integración: 14 archivos de código/configuración/pruebas copiados después de verificar todos los hashes base y ausencia de destinos nuevos. SHA-256 iguales a copia probada después de Pint; archivos de otras entregas intactos. Rutas sin cambios; checkout/revisión/acceso siguen en T6.3. No cambia frontend, por lo que no se requiere build. Sin migrar/sembrar la BD de aplicación, instalar dependencias, hacer commit ni desplegar.

T6.2 aceptado. T6 permanece abierta; siguiente punto T6.3 — revisión y acceso protegido. No se inicia automáticamente.

### Entrega T6.3 — 02-10-2026

Continuación autorizada del siguiente punto. Base fuente intacta comprobada por SHA-256; implementación de Codex en `.antigravity-mcp/local-t63-20261002`. Delegación Antigravity `20261002T175154Z-a6fe58d2`, conversación `dbc9817d-87ff-4755-be54-c11219053a65`, falló por comando denegado y ausencia de operaciones estructuradas. No se integró código de Gemini. No existe `.ai/rules`; se respetan versiones, convenciones y dependencias existentes.

Formulario invitado de contacto y selección explícita MX; revisión de contacto original, artículos, total con IVA y condiciones. Consentimiento vinculado a nonce de revisión, contacto y fingerprint guardados en sesión. Cotización/tarifa recalculadas en servidor; cambios exigen nueva aceptación y un doble envío recupera el pedido original. Identidades, contacto, importes y clave submission enviados para sobrescribir datos se ignoran. Rutas nombradas `/checkout`, `/checkout/revision` y `/pedidos/{order:reference}`; sin búsqueda por teléfono ni identificación numérica.

Acceso por propietario activo consultado nuevamente, sesión ligada al hash vigente o token aleatorio de 256 bits. Solo SHA-256 y caducidad configurable de siete días en BD; enlace completo entregado una vez al creador. Token válido concede acceso únicamente a ese pedido y redirige a URL limpia. Caducidad exacta, suspensión, logout, token inválido y pedido ajeno denegados. HTML/JSON proyectan exclusivamente snapshot público y contacto autorizado, sin costos, claves internas ni teléfono canónico. Respuestas y errores privados/no-store con Referrer-Policy no-referrer. Estado procede de BD; parámetros de retorno nunca confirman pago.

33 casos nuevos comprobados primero en rojo por rutas/servicios ausentes. OrderAccessTest, CheckoutReviewTest y ContactValidationTest: 72 pruebas/328 aserciones. Tras corrección de recuperación, OrderCheckoutTest, CheckoutReviewTest y OrderAccessTest: 76/329. Revisión independiente de solo lectura detectó lecturas antiguas de replay dentro de la nueva transacción externa bajo MySQL REPEATABLE READ: lookups de recuperación y pedido ahora usan lecturas bloqueadas actuales; todos los caminos de replay se serializan con el mismo lock de configuración antes de pedido/intento. Revisor confirmó corrección sin otros hallazgos materiales. No se acredita ejecución concurrente de dos conexiones MySQL; esa verificación sigue pendiente antes del hito que la exige.

Regresión aislada final: 392 pruebas/1872 aserciones, Pint y build aprobados. Chromium a 360/1280 px: contacto obligatorio, revisión, confirmación Tab/Space/Enter, resultado pendiente y enlace privado con redirección limpia aprobados; sin desbordamiento ni errores JavaScript. Capturas móvil de revisión y escritorio de resultado inspeccionadas. Evidencia `t63-browser-results.json` y `t63-*-*.png` en copia aislada. Primer Playwright bloqueado por sandbox WinError 5; repetido con escalación autorizada sobre SQLite exclusiva ficticia; servidor terminado.

Catorce archivos integrados, incluidos ajuste de CheckoutService y prueba previa de contacto actualizada; rutas en `integrated-paths.json`. Hashes iguales a copia probada después de Pint. No se migró/sembró la BD de aplicación, añadieron dependencias, ejecutaron cobros, hicieron commits ni desplegó. T6 cerrada en su alcance local; resolución de intentos remotos y cambio de cotización después de Stripe pertenecen a T7. Siguiente T7.1, no iniciado automáticamente.

Revalidación final en fuente: `php artisan test --compact` exit 0, 392 pruebas/1872 aserciones; `php vendor/bin/pint --dirty --format agent` y `npm run build` aprobados. SHA-256 de los catorce archivos coincide con copia probada; `git diff --check` aprobado. Rutas Artisan confirman cuatro endpoints checkout y uno de consulta UUID. Build conserva el aviso existente de fontaine opcional, sin cambio de dependencias.


### Traspaso documental a Claude — 02-10-2026

Por solicitud del usuario se añadieron prompts completos a todos los puntos, incluidos los aceptados como referencia. La guía adapta la ejecución a Claude directo, conserva historial y contratos del plan, identifica T7.1 como siguiente y aclara límites de verificación pendientes. Esta entrega modifica documentación; no implementa T7 ni instala herramientas/dependencias. La evidencia de 392 pruebas del cierre T6 es histórica y no una nueva ejecución de esta actualización documental.

### Entrega T7.1 — 05-10-2026

Gateway Http con parámetros/clave/primer envío persistidos, expiración 30 minutos, DTO y errores sin secretos; HTTP fuera de transacción externa del controlador. Resultado privado ofrece Checkout. Cinco pruebas nuevas y regresión T6: 81 pruebas/358 aserciones aprobadas. Versión 2026-08-26.dahlia documentada en https://docs.stripe.com/changelog; la versión Endive elimina payment_method_types. Fixtures sintéticos de contrato, NO capturados de sandbox. Cuenta, modo y versión en sandbox pendientes; integración deshabilitada por defecto. No se migró BD de aplicación ni se hicieron commits.

### Entrega T7.2 — 05-10-2026

Retrieve/expire/find paginado acotado; ventana conservadora 23h sin otro POST al agotarse. Listado vacío nunca autoriza otro intento. Cambio de contacto/carrito conserva snapshot anterior y exige expiración remota unpaid; pago ganador bloquea reemplazo. Límites sesión/IP 10 solicitudes/min y 5 nuevos/h; replay no consume intento nuevo. POST serializados por bloqueo de sesión. Regresión T7/T6: 86 pruebas/380 aserciones. Pint T7.1 y build aprobados; build requirió escalación por spawn EPERM y conservó advertencia fontaine existente. Carreras MySQL y Stripe sandbox pendientes.

### Entrega T8.1 — 05-10-2026

Webhook HMAC SHA-256 sobre cuerpo original, hash_equals, múltiples v1 y tolerancia 300s; evento único durable con payload/cuerpo ocultos, estado pending/ignored. BD fallida/cancelación impide 2xx. CSRF except exclusivamente stripe/webhook. Nueve pruebas/19 aserciones aprobadas. Protocolo contrastado con documentación oficial https://docs.stripe.com/webhooks. Sin SMTP ni confirmación financiera en este punto.

### Entrega T8.2 — 05-10-2026

Payment/Refund/FinancialIncident y outbox completo OrderNotification con factories/migraciones incrementales. Resolver consulta cuenta/sesión/PaymentIntent/cobro fuera de locks y applier transaccional registra dinero incluso ante anomalías. Referencias, versión, account/modo/importe/moneda verificados; completed unpaid no paga, Paid no retrocede; dos cobros preservados con incidencia, compra única. Evento procesado junto a dinero/pedido/outbox; fallo outbox revierte todo. Refund remoto único con éxito monotónico; disputa independiente. Webhook intenta procesar sin perder trabajo pendiente. 30 pruebas/161 aserciones T7/T8 aprobadas. Carreras MySQL, eventos y esquema real sandbox pendientes.

### Entrega T9.1 — 05-10-2026

NotificationAttempt con pedido/usuario nullable, claim UUID y lease 60s; SMTP fuera de locks/tras commit con timeout 5s. Accepted significa aceptación simulada del proveedor; rechazo SMTP explícito es Failed con retry, timeout es Uncertain sin reenvío ciego. Lease sending abandonado pasa Uncertain; prepared recuperable. Plantilla proyecta snapshot/local/espera de segundo correo sin costo. Mail::fake y transporte Symfony de prueba; NO SMTP real. 37 pruebas/182 aserciones T7–T9 aprobadas. Outbox conserva pago ante fallo de correo.

### Entrega T9.2 — 05-10-2026

Aviso pickup inicial único y endpoint administrador activo; botón Notificar listo para recoger en panel. Reenvío exige dos confirmaciones/intención UUID y auditoría independiente, sin borrar historial. Elegibilidad local común: Paid, sin reembolso total confirmado ni incidencia abierta; cancelación pendiente/fallida con motivo/fecha. Resolución revalida y Accepted previo se conserva. 12 pruebas/45 aserciones de avisos aprobadas. No introduce estados logísticos. Build e inspección visual final pendientes de la verificación de cierre.

### Entrega T10.1 y cierre del encargo T7.1–T10.1 — 05-10-2026

Encargo vigente del usuario: continuar secuencialmente desde T7.1 hasta T10.1, conservando T1–T6 y los archivos sin commit. Codex implementó en el workspace y verificó cada punto antes de pasar al siguiente. No se inició T10.2.

**Resultado local:** T7.1, T7.2, T8.1, T8.2, T9.1, T9.2 y T10.1 implementados y comprobados. Las casillas de esta entrega acreditan el alcance local, no Stripe sandbox, SMTP real ni locks concurrentes MySQL/MariaDB. Stripe permanece deshabilitado por defecto. T10 sigue abierta y Hito A NO aceptado.

T10.1 añade PaymentReconciler::run(int $limit): int y recuperación de avisos reutilizando resolver/applier/dispatcher. Comandos store:reconcile-payments --limit=25 y store:retry-notifications --limit=25; límites 1–100, presupuesto configurable inicial 40s. Schedule cada 15 minutos, withoutOverlapping(5) y cache database compartida. Trabajo ordenado por última revisión en una cola común de eventos e intentos para que un error no monopolice el lote, incluso con limit=1. HTTP recibe deadline; el dispatcher conserva outbox cuando no queda margen de 6s para SMTP y acota el timeout del transporte. Este presupuesto no certifica tiempos del hosting ni un SLA de entrega.

Recuperación comprobada: caída antes de HTTP, sesión remota sin ID local con parámetros/clave originales, evento durable sin aplicar y Paid sin SMTP. Ventana de 23h agotada mantiene Unknown, también tras caída en Creating, sin otro POST ni otra clave. Lease abandonado prepared recuperable; sending pasa Uncertain sin reenvío ciego, incluso si hay reembolso o incidencia. Avisos Cancelled/Uncertain se excluyen del retry automático. Solo intentos Creating/Open/Unknown y eventos ya recibidos: no barrido de pagos Paid buscando refunds/disputas ausentes.

Métricas en cache database: inicio/fin/último éxito/error, visitados/completados/errores; alertas de Unknown de más de 30 minutos y ausencia de éxito de más de 45 minutos, sin PII ni liberación por reloj. El aviso de cron obsoleto se evalúa al volver a ejecutar el comando; detectar que cron nunca vuelve a correr exige monitor externo y no queda acreditado por estas pruebas.

**Revisión y correcciones verificadas:** revisor independiente de solo lectura, sin modificación ni ejecución de tests, encontró falta de equidad entre colas, margen SMTP y bloqueo de recuperación de claims de recogida. Todos se reprodujeron en rojo y corrigieron. Regresiones adicionales cubren: Unknown concurrente no sobrescrito por rechazo obsoleto; correo cancelado por listener no se considera Accepted; revalidación local antes de SMTP; límite IP protegido con lock; sexto intento nuevo/h bloqueado; reanudación protegida del mismo intento; expiración no cierra un pedido con otro intento cobrable y contradicción con dinero registrado abre incidencia sin rebajar Paid. No hay findings materiales diferidos.

Decisiones de revisión: los cambios financieros posteriores a la última comprobación local no tienen garantía de bloqueo retroactivo del SMTP; fixtures de contrato sintéticos y API 2026-08-26.dahlia no equivalen a validación sandbox; MySQL/SMTP/sandbox siguen gates externos; recorrido completo sin cupón pertenece a T10.2; barrido de pagos Paid y pantallas T16 permanecen fuera del encargo. No se cambiaron políticas comerciales.

**Verificación final en fuente:**
- php artisan test --compact: **458 pruebas/2164 aserciones, exit 0**. Incluye 66 casos nuevos T7–T10.1 y regresión T1–T6.
- Pruebas focalizadas de StripeCheckoutTest, StripeWebhookTest, PaymentTransitionsTest, OrderNotificationsTest y PaymentRecoveryTest ejecutadas en los puntos, con fallos iniciales por funcionalidad ausente y resultados verdes tras implementar/corregir. Último grupo checkout/transiciones/recuperación: 38/205; avisos: 19/68.
- php vendor/bin/pint --dirty --format agent aprobado; npm run build aprobado, sin cambios de dependencias y con el aviso preexistente de fontaine opcional.
- php artisan schedule:list confirma ambos comandos cada 15 minutos. Rutas nombradas para webhook, reanudación privada y avisos administrativos comprobadas; git diff --check aprobado (solo advertencia de normalización CRLF de .env.example).
- Chromium: seis verificaciones a 360/1280 px de resultado abierto, resultado incierto y panel de avisos; sin overflow/errores JS; foco por teclado, confirmaciones de reenvío y botones bloqueados comprobados. Capturas móvil del panel y escritorio del resultado incierto inspeccionadas. HTML generado por Laravel/fixtures en SQLite :memory: con assets reales del build, sin servidor comercial ni Stripe. Artefactos locales ignorados en .antigravity-mcp/t7-t101-verification, browser-results.json y seis PNG. Navegador requirió escalación por WinError 5; build por spawn EPERM, ambos autorizados. No acredita compra remota ni SMTP.
- El primer control de base registró fallos ProductPhotosTest::test_size_and_dimension_limits_are_enforced y ::test_database_failure_cleans_only_new_file cuando dos suites se solaparon por error de orquestación. Las regresiones posteriores secuenciales completas pasaron; no se modificó catálogo/fotos para ocultarlos.
- .ai/rules no existe. Versiones Composer/package.json comprobadas; testing-best-practices no disponible en catálogo/directorios consultados. Se aplicaron convenciones PHPUnit existentes, test-driven-development y verification-before-completion.

**Persistencia nueva:** siete migraciones incrementales 2026_10_05: 074309_add_remote_checkout_tracking_to_payment_attempts_table, 075421_create_stripe_events_table, 075719_create_payments_table, 075720_create_refunds_table, 075721_create_financial_incidents_table, 075722_create_order_notifications_table y 080438_create_notification_attempts_table. Solo se aplicaron en SQLite de pruebas en memoria. Se conservó el trabajo previo; no se migró/sembró la BD de aplicación, instalaron paquetes, hicieron commits/push, cobros live ni despliegues.

**Pendiente externo:** cuenta/modo/API y ciclo real Stripe sandbox del pedido, SMTP real, concurrencia de procesos independientes MySQL/MariaDB y operación de cron/hosting. Siguiente punto **T10.2**, pendiente por decisión del encargo; detenido al completar **T10.1**.

### Avance vigente 05-10-2026 — T10.2 local y gate hasta T12.2

El usuario amplió el encargo hasta T12.2. Se revisaron los prompts completos y se completó el trabajo local independiente de T10.2. La casilla T10.2 continúa pendiente: su aceptación incluye la compra real sandbox del mismo pedido. T11.1, T11.2, T12.1 y T12.2 no se iniciaron porque T10.2 y T11.1 exigen explícitamente Hito A aceptado con sandbox.

Archivo nuevo: tests/Feature/PurchaseWithoutCouponTest.php, generado con Artisan. Tres recorridos integrados: compra normal, timeout de creación de Checkout recuperado con idénticos parámetros/clave, y fallo de consulta durante el webhook recuperado por conciliación. Invitado, contacto/revisión/aceptación, producto publicado de costo 100000, aumento 30%, IVA 16%, total 150800 MXN en unidades mínimas. Retorno Pending antes del webhook; firma sobre cuerpo original; una compra/intento/cobro/notificación aceptada; duplicados de confirmación/webhook/cron sin efectos adicionales. Snapshot del pedido y sus líneas intactos; sin tablas de cupones/reservas. HTTP exclusivamente simulado, rutas/métodos acotados, fuera de transacciones; Mail::fake no acredita SMTP ni entrega real.

Verificación final: PurchaseWithoutCouponTest 3 pruebas / 266 aserciones; PaymentRecoveryTest 10 / 32; regresión completa php artisan test --compact 461 / 2430, todas correctas. php vendor/bin/pint --dirty --format agent correcto; aplicó únicamente formato a cuatro archivos existentes de webhook/resolver/transiciones. Sin cambios de comportamiento productivo, frontend, dependencias, commits ni BD de aplicación. Revisión adicional de solo lectura incorporó comparación de líneas y rechazo de llamadas HTTP inesperadas.

Bloqueo comprobado sin exponer valores: .env no tiene configurados STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET ni STRIPE_ACCOUNT_ID; Stripe CLI no está disponible. Se solicitó configuración segura de prueba, sin credenciales en chat. No se instaló CLI ni se hicieron cobros externos. API de fixtures 2026-08-26.dahlia, livemode=false; esto no es evidencia del contrato remoto.

Para continuar: configurar acceso sandbox y webhook del proyecto, completar compra desde Checkout del mismo pedido con referencias propias y registrar su evidencia. Mantener Hito A abierto hasta entonces. Después ejecutar en orden T11.1 → T11.2 → T12.1 → T12.2 y detenerse en T12.2, conforme al encargo ampliado.
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

### Avance del encargo hasta T15.3 — 07-10-2026

El usuario autorizó continuar hasta T15.3. T13.1 se completó localmente. T13.2 necesita acceso MySQL real: la configuración existente devolvió 1045 y faltan MYSQL_TEST_* para garba_coupon_tests. La ejecución explícita final de siete casos falló en la guardia, cero aserciones y ANTES de migrar; no es PASS ni skip. T14.1/T14.2 permanecen sin iniciar y Hito B abierto. Decisión de secuencia: adelantar solo T15, cuyas dependencias T2/T4/T7/T9 ya están aceptadas; no sustituir la concurrencia T13 exigida antes de T14.

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
