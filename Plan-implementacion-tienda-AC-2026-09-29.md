# Tienda de aire acondicionado — Implementation Plan

> **Modalidad vigente 07-10-2026:** **T16.1, T16.2, T17.1 y T17.2 ACEPTADOS; T17.3 COMPLETO LOCALMENTE, HITO C ABIERTO.** Encargo vigente: T16.1 → T17.3. Suite nueva 620/4239, grupo MySQL real 19/278, Pint/build/rutas y navegador 360/1280 aprobados. Hitos A/B y T15.3 conservan su aceptación previa. Pendiente evidencia de compra privada Stripe sandbox y correos de acceso/recogida reales según el registro «Verificación T16.1 → T17.3». Base de tienda y .env intactos; cambios sin commit. No iniciar T18, live ni despliegue.


> **Para agentes:** seguir [Pasos-desarrollo-supervisado-tienda-AC.md](Pasos-desarrollo-supervisado-tienda-AC.md) para elegir el punto y su prompt completo; este plan define archivos, contratos y pruebas. Claude ejecuta directamente un punto por solicitud del usuario y se detiene al cerrarlo. La modalidad Codex–Gemini del 01-10-2026 pertenece al historial. No ejecutar las 18 tareas automáticamente.

**Goal:** construir la tienda administrable con tarifas pública e instalador, compra invitada, Stripe Checkout, recogida local y promociones, verificando primero una compra completa sin cupón.

**Architecture:** monolito Laravel con Blade y Tailwind; MySQL/MariaDB transaccional para producción y pruebas de concurrencia. Pedidos conservan cotizaciones inmutables; pagos aplican resultados verificados mediante una operación común a webhook, conciliación y consulta administrativa. Eventos y notificaciones durables permiten recuperación sin Redis ni workers permanentes.

**Tech Stack:** Laravel 13.33.0; PHP local 8.5.0 (restricción Composer ^8.3); PHPUnit 12.5.36; Pint 1.32.1; Blade; Tailwind 4.3.3; Vite 8.3.1; cliente HTTP Laravel; Stripe Checkout; HostGator empresarial.

**Spec:** [Diseno-consolidado-tienda-AC-2026-09-29.md](Diseno-consolidado-tienda-AC-2026-09-29.md), aprobado por instrucción del usuario en esta sesión. La elección posterior de **HostGator empresarial** sustituye la evaluación de Personal/Emprendedor y cualquier referencia a hosting básico. La configuración concreta se verifica antes del despliegue; no se certifica aquí.

## Global Constraints

- Conservar todas las políticas del diseño aprobado, incluidas las anteriormente marcadas como propuestas. No reabrirlas por su etiqueta antigua.
- «Sin inventario, existencias, reservas de stock, bloqueos por agotados», sin envío a domicilio, registro público de instaladores ni estados logísticos.
- Público invitado con nombre, correo y teléfono; administrador crea instaladores; todos los activos comparten tarifa.
- Aumento sobre costo sin IVA: ejemplos editables de 30% público y 10% instalador. Excepciones independientes por producto/tarifa, expresadas sin IVA. Costo exclusivamente administrativo.
- Dinero entero en unidades mínimas; porcentajes de precisión fija; redondeo decimal de mitad hacia arriba del precio unitario y del impuesto por línea. Catálogo muestra IVA incluido.
- Una tienda, moneda y local; ejemplos MXN e IVA 16% exclusivamente ficticios. País, moneda, tasa y textos reales son validaciones previas al lanzamiento.
- Un cupón por pedido; máximo 90%; mínimo y descuento fijo sobre base elegible sin IVA; un uso por teléfono canónico. Texto exacto: «Cupón válido una vez por número de contacto».
- Checkout de tarjetas de 30 minutos; total agregado calculado en Laravel, sin segundo cálculo de impuestos o descuentos en Stripe. Éxito de navegación no confirma pago.
- Máximo un intento potencialmente cobrable por pedido. Timeout significa desconocido. Liberar cupón solo con evidencia remota de expiración sin pago o fallo definitivo sin sesión cobrable.
- Reembolso parcial o total no devuelve el uso de cupón ni capacidad del límite. Acuerdos comerciales, entrega y reembolsos se resuelven externamente; Stripe Dashboard inicia devoluciones.
- Intentar correo inmediatamente tras persistir pago o solicitud de recogida, fuera de transacciones financieras; cron recupera fallos e incertidumbres. SMTP no garantiza entrega exactamente una vez.
- Una cotización inmutable por pedido: cambiar productos, cantidades, contacto, tarifa, cupón o importes requiere otro pedido y nueva aceptación. Por acuerdo del 06-10-2026, se permiten compras independientes aunque otro pedido tenga un pago pendiente; no expirar ni cerrar el anterior por iniciar otra compra. Vaciar el carrito una sola vez después de guardar el pedido correctamente; reintentos, consultas y confirmaciones tardías conservan el carrito nuevo. Reanudar Checkout abierto conserva pedido e intento. No sobrescribir ni borrar pedidos anteriores para limpiar el historial.
- El listado de pedidos muestra por defecto compras pagadas, incluidas las reembolsadas o con incidencias; filtros permiten consultar Pending y ClosedUnpaid para diagnóstico.
- Conciliar pagos pendientes y procesar eventos ya recibidos, sin recorrido periódico de cobros pagados para descubrir reembolsos o disputas. Si falta su webhook, Laravel puede quedar desactualizado; consultar Stripe como referencia financiera externa.
- Comprobar elegibilidad local antes de cada envío, reintento o reenvío del aviso de recogida: reembolso total confirmado cancela con motivo; incidencia pendiente bloquea hasta revisión. No consultar Stripe para esta comprobación.
- No cambiar dependencias sin autorización; la reorganización del 01-10-2026 no inicia programación, instalaciones ni despliegues. En las sesiones de ejecución por punto, usar Artisan con `--no-interaction` para archivos PHP generables y PHPUnit para pruebas.
- Leer AGENTS.md y reglas aplicables al comenzar ejecución. No crear documentación adicional sin solicitud. El plan se guarda en la raíz existente para respetar la regla de no crear nuevas carpetas base; no se crea `docs/`.

## Review Focus

1. Webhook llega antes de guardar sesión, o tras una caída entre persistencias: recuperar referencias sin rebajar pago ni duplicar efectos (T8, T10).
2. Dos procesos compiten por último uso o mismo teléfono: capacidad y exclusividad reales en MySQL/MariaDB, no una falsa garantía de SQLite (T13, T14).
3. SMTP aceptó y luego hubo timeout: conservar incertidumbre y reclamación recuperable, sin declarar entrega ni reenvío automático ciego (T9, T10).
4. Cambia tarifa, producto o precio mientras el cliente revisa: nueva aceptación antes de crear intento; conservar snapshot de Checkout ya emitido (T4, T6, T7).
5. Sesión de instalador suspendido sigue abierta o invitado conoce ID ajeno: bloquear acceso; registrar cualquier cobro que ganó la carrera sin revelar pedido (T2, T7, T15).

## 1. Inspección y situación inicial comprobada

Inspección realizada el 29-09-2026, sin leer secretos de `.env` ni ejecutar migraciones, instalaciones o pruebas mutadoras.

| Evidencia | Resultado |
|---|---|
| `AGENTS.md` | Leído; reglas Laravel/Boost/PHP/Pint/PHPUnit aplicables |
| `.ai/rules` | No existe; no hay índice ni reglas adicionales que leer/buscar |
| `composer show --direct` | Laravel 13.33.0, Tinker 3.0.2, Boost 2.10.0, Pail 1.2.7, Pao 1.1.5, Pint 1.32.1, PHPUnit 12.5.36, Faker 1.24.1, Mockery 1.6.15, Collision 8.9.5 |
| `php --version`, `node --version`, `npm --version` | PHP 8.5.0 CLI; Node 23.9.0; npm 10.9.2 |
| `package.json` y `npm ls --depth=0` | Tailwind y plugin 4.3.3; Vite 8.3.1; laravel-vite-plugin 3.2.0; concurrently 10.0.5. Multiplex ^0.4.1 opcional no instalado; no bloquea la tienda |
| `php artisan route:list --except-vendor` | Una ruta `/` con vista `welcome`; `/up` configurada en bootstrap |
| `app/`, `database/`, `tests/` | User, Controller base, AppServiceProvider; migraciones users/cache/jobs, UserFactory, DatabaseSeeder y dos ExampleTest |
| `phpunit.xml`, `config/database.php` | Tests SQLite en memoria, correo array, sesión array; configuración MySQL/MariaDB disponible, servidor real no verificado |
| Frontend | Blade welcome; CSS Tailwind inicial; JS vacío. Assets comerciales inexistentes |
| Funcionalidad comercial | No hay catálogo, auth de negocio, tarifas, carrito, pedidos, Stripe, cupones ni correos de compra |
| Git inicial | Diseño consolidado sin seguimiento; conservarlo íntegro. Este plan no implica commit ni limpieza de archivos |

`User` usa atributos `Fillable`/`Hidden` y `casts(): array`; mantener esa convención. No crear otro proyecto Laravel ni sustituir el stack. Existe infraestructura del framework, pero ninguna prueba de comportamiento comercial ejecutada: comandos de verificación abajo son futuros.

El skill `testing-best-practices` solicitado por AGENTS.md no figura entre los disponibles ni se encontró en el proyecto. Antes de escribir pruebas durante ejecución, localizarlo en el catálogo/configuración; si sigue ausente, informar y acordar el sustituto. Esto no impide preparar el plan.

## 2. Convenciones de ejecución y contratos

### Ciclo verificable por tarea

**Actualización 01-10-2026:** cada T se divide en puntos Tn.m en la guía enlazada. Gemini implementa solo el punto seleccionado mediante ejecución aislada de Antigravity; Codex prepara el encargo, revisa el resultado y verifica antes de integrar. Los pasos siguientes se aplican a cada punto con las pruebas que le correspondan. Al terminar todos los puntos de una T, repetir sus pruebas completas y cerrar su aceptación. Una entrega de Gemini no implica aceptación automática. El usuario decide cuándo iniciar el siguiente punto.

Cada tarea incluye sus pasos de prueba, implementación y aceptación. Durante ejecución:

1. Generar el test indicado: `php artisan make:test --phpunit NombreTest --no-interaction` (añadir `--unit` solo en los unitarios). Escribir los casos nombrados con las aserciones descritas.
2. Ejecutar el comando focalizado y comprobar fallo por comportamiento ausente, no por infraestructura rota.
3. Generar modelos con `make:model Nombre --factory --no-interaction`, migraciones con `make:migration nombre --no-interaction`, controladores/requests/policies/mail/commands con sus comandos `make:`. Confirmar opciones con `php artisan list` y `php artisan make:... --help`; clases genéricas con `make:class`.
4. Implementar únicamente los archivos y contrato de la tarea; ejecutar `vendor/bin/pint --dirty --format agent` si cambió PHP; repetir prueba focalizada y comprobar salida PASS, cero fallos.
5. Codex revisa el diff, integra únicamente los archivos verificados del punto y registra el avance en la guía. Si la sesión incluye commits, realizar un commit por tarea completa con `git add <rutas exactas de la tarea>` y el mensaje indicado; no confundir integración de archivos con merge o publicación remota. No usar `git add .`; no incluir secretos, registros Antigravity ni cambios ajenos. Si signing está configurado, comprobar que funciona antes del primer commit, sin desactivarlo.

No instalar paquetes por conveniencia. Stripe se integra con `Illuminate\Support\Facades\Http`, ya disponible. La validación de firma se implementa según el protocolo oficial con HMAC SHA-256, comparación constante, timestamp y múltiples firmas v1; pruebas con cuerpo original. No introducir Cashier, un panel externo ni starter kit.

### Tipos y nombres compartidos

- `App\Enums\UserRole`: `Administrator`, `Installer`; `PriceTier`: `Public`, `Installer`. `PaymentAttemptStatus`: `Creating`, `Open`, `Unknown`, `Paid`, `Failed`, `Expired`. `OrderStatus`: `Pending`, `Paid`, `ClosedUnpaid`. `CouponUseStatus`: `Free`, `Reserved`, `Consumed`. `NotificationStatus`: `Pending`, `Claimed`, `Accepted`, `Failed`, `Uncertain`, `Cancelled`. Incidencia financiera bloquea el aviso sin estado adicional: conservar estado e historial y registrar motivo de bloqueo; volver a evaluar antes de reclamar envío.
- Configuración implementada en T1: `StoreSetting::current(): StoreSetting` y `StoreSetting::updateCurrent(array $changes, ?User $actor = null): StoreSetting`. T4/T16 usarán updateCurrent para cambiar configuración comercial: versión y auditoría atómicas, sin permitir id/version del cliente; factories e inicialización no generan auditoría. Valores de tasas con sufijo `_basis_points`, escala 10000, conservan la precisión fijada.
- `CartLineData(int $productId, int $quantity)`; `ContactData(string $name, string $email, string $phoneOriginal, string $phoneCanonical)`.
- `QuoteLineData`: producto/nombre/cantidad, base unitaria, origen manual/automático, base de línea, descuento, base descontada, tasa e IVA, total. `QuoteData`: líneas, tarifa, moneda, subtotal, descuento, base, IVA, total, versión y fingerprint. Ninguno contiene costo privado.
- `PricingService::quote(array $lines, PriceTier $tier): QuoteData`; PHPDoc `list<CartLineData>`. T12 añade argumento opcional `?Coupon $coupon = null`; llamadas anteriores siguen válidas.
- `CheckoutService::start(array $lines, ContactData $contact, ?User $actor, string $acceptedFingerprint, string $submissionKey): PaymentAttempt`. T14 añade `?string $couponCode = null` al final. `submissionKey` de servidor identifica la confirmación y deduplica doble envío; no concede acceso a pedido.
- `StripeGateway::createCheckout(PaymentAttempt $attempt): CheckoutSessionData`; `retrieveCheckout(string $sessionId): CheckoutSessionData`; `expireCheckout(string $sessionId): CheckoutSessionData`; `findCheckout(PaymentAttempt $attempt): ?CheckoutSessionData`. Nunca concluir inexistencia solo porque una búsqueda parcial no encontró sesión.
- `PaymentResultApplier::apply(PaymentAttempt $attempt, VerifiedPaymentResult $result, ?StripeEvent $event = null): void`; DTO verificado lleva origen/evidencia, IDs remotos, entorno, moneda, importe, estado y fechas. `PaymentResultResolver::resolve(StripeEvent $event): VerifiedPaymentResult` usa consultas remotas si el evento no basta.
- `NotificationDispatcher::attempt(OrderNotification $notification): void`; `PaymentReconciler::run(int $limit): int`; `PhoneNormalizer::normalize(string $input, string $country): string` devuelve E.164 o error de validación, no infiere identidad.
- `OrderAccessService::issue(Order $order): string` entrega token una vez; `allows(Order $order, ?User $actor, ?string $token): bool`. Guardar hash/expiración; sesión vinculada al pedido permitido.

DTOs en `app/Data/`, operaciones en `app/Services/`, enums en `app/Enums/`: subdirectorios del árbol `app/` existente; sin nuevas carpetas base. Controllers públicos en `app/Http/Controllers/`, panel en `app/Http/Controllers/Admin/`. Vistas en `resources/views/`. No asumir que un modelo entero puede serializarse para la tienda.

### Persistencia y bloqueos

Migraciones indicadas por sufijo; Artisan decide el prefijo timestamp y ese nombre real se registra en el commit. Modelos nuevos tienen factory homónima en `database/factories/`; seed ficticio solo en `database/seeders/StoreDemoSeeder.php`, no ejecutar en producción. No sembrar un administrador con contraseña conocida.

Importes `BIGINT` en centavos; porcentajes en enteros de precisión fija de 1/10000 (30% = 3000, IVA 16% = 1600), evitando floats. Conservar snapshot completo y fingerprint de datos comerciales; los umbrales de importes/cantidades deben impedir overflow y exceder límites remotos. No inventar límite de stock.

Orden global de bloqueo cuando aplique: cupón → derecho cupón/teléfono → pedido → intento → cobros/reembolsos → evento/notificación. Operaciones sin cupón omiten esos locks. Revalidar estado dentro de la transacción; no mantener locks durante HTTP/SMTP. Reintento acotado de deadlock con los mismos identificadores. Todo dinero confirmado se registra, incluso anomalías.

## 3. Secuencia, dependencias e hitos

| Tarea | Depende de | Resultado revisable |
|---|---|---|
| T1 Configuración y esquema mínimo | — | Configuración comercial y auditoría |
| T2 Acceso administrativo | T1 | Panel protegido y bootstrap admin |
| T3 Catálogo administrable | T2 | Productos/fotos/categorías en BD |
| T4 Precios | T1,T3 | Dos tarifas exactas y aislamiento |
| T5 Tienda y carrito | T3,T4 | Navegación y compra móvil |
| T6 Contacto y pedidos | T2,T4,T5 | Snapshot y acceso invitado |
| T7 Checkout | T6 | Creación remota recuperable |
| T8 Webhook y finanzas | T7 | Pago durable e idempotente |
| T9 Correo y recogida | T8 | Dos avisos con outbox |
| T10 Recuperación y hito A | T7–T9 | Compra completa sin cupón probada |
| T11 Panel de cupones | Hito A | Configuración sin alterar checkout |
| T12 Descuento | T4,T11 | Cálculo y reparto exactos |
| T13 Derechos concurrentes | T8,T11,T12 | Reservas sin sobreconsumo |
| T14 Checkout con cupón | T10,T12,T13 | Promociones integradas y hito B |
| T15 Invitaciones e instaladores | T2,T4,T7,T9 | Acceso privado y suspensión |
| T16 Operación y reembolsos | T8–T15 | Diagnóstico y recuperación admin |
| T17 Seguridad y aceptación integral | T14–T16 | Hito C, release verificable |
| T18 Gate HostGator y lanzamiento futuro | T17 | Cuenta y recuperación comprobadas |

Las tareas son unidades de aceptación; sus puntos de implementación y revisión están en la guía de desarrollo supervisado. Ejecutarlos en orden, salvo cambio explícito de secuencia con dependencias verificadas por Codex. No comenzar T11–T14 hasta aceptar Hito A. Las invitaciones completas pueden esperar a T15; T2 crea roles y permite usuarios ficticios activos en tests, sin un alta pública provisional.

## 4. Tareas detalladas

### T1 — Configuración comercial y auditoría

**Archivos:** crear `config/store.php`, `app/Models/StoreSetting.php`, `app/Models/AuditEntry.php`, `app/Enums/PriceTier.php`, `app/Enums/UserRole.php`, migraciones `create_store_settings_table`, `create_audit_entries_table`, factories correspondientes y `tests/Feature/StoreSettingsTest.php`; modificar `.env.example` y `database/seeders/DatabaseSeeder.php` para impedir credenciales demo en producción.

**Interfaces:** configuración en BD de aumentos/tasa/moneda/país/zona/local, versión monotónica; `StoreSetting::current(): StoreSetting`. Settings contienen centavos/enteros, no secretos Stripe. Auditoría actor/acción/entidad/cambios mínimos, sin tokens ni teléfono completo.

- [x] Escribir `test_defaults_are_explicit_demo_values`: MXN, 3000/1000/1600; `test_settings_changes_increment_version`; `test_demo_seeding_is_refused_in_production`. Verificar fallo: `php artisan test --compact tests/Feature/StoreSettingsTest.php`.
- [x] Generar y crear esquema/configuración; transacción al cambiar configuración y escribir auditoría. `.env.example` documenta variables vacías para Stripe y correo; llaves reales fuera del repo.
- [x] Repetir comando, PASS. Aceptación: ejemplos inequívocamente ficticios, configuración persiste, no hay datos comerciales hardcodeados ni administrador demo desplegable. Verificado e integrado el 01-10-2026: focalizada 13 pruebas/99 aserciones; completa 15 pruebas/101 aserciones; Pint aprobado. Commit propuesto, todavía no realizado: `feat: add store settings and audit foundation`. Evidencia y restricciones en el registro T1.2 de la guía.

### T2 — Autenticación y autorización administrativa

**Archivos:** modificar `app/Models/User.php`, `routes/web.php`, `bootstrap/app.php`, `database/factories/UserFactory.php`; crear migración `add_store_access_to_users_table`, `app/Http/Controllers/AuthController.php`, `app/Http/Requests/LoginRequest.php`, `app/Http/Middleware/EnsureActiveAccount.php`, `app/Http/Middleware/EnsureAdministrator.php`, `app/Console/Commands/CreateAdministrator.php`, `resources/views/auth/login.blade.php`, `resources/views/admin/dashboard.blade.php`, `resources/views/components/layout.blade.php`, `tests/Feature/StoreAuthenticationTest.php`.

**Interfaces:** rutas nombradas `login`, `logout`, `admin.dashboard`; rol/activación/suspensión protegidos de asignación pública. Comando `store:create-admin --email=...` obtiene contraseña oculta por mecanismo seguro, nunca literal en historial. Recuperación de contraseña en T15. Segundo factor administrativo en T15.

**Avance 01-10-2026:** T2.1 verificado e integrado: User::isActive(): bool exige is_active, activated_at y ausencia de suspended_at; User::isAdministrator(): bool comprueba UserRole. Aliases active.account y administrator para aplicar después de auth en rutas web. Cuenta nueva por defecto instalador inactivo; campos privilegiados fuera de Fillable. T2.2 debe activar explícitamente al administrador creado por su comando (is_active=true, activated_at presente, suspended_at=null), con asignación interna controlada, y conservar estos permisos. Pruebas de T2.1: focalizada 13/42, completa 28/143 y Pint aprobados en copia aislada. Los pasos de T2 permanecen pendientes de cierre hasta completar login/logout y pantallas en T2.2.

**Cierre T2.2 01-10-2026:** login/logout/panel y comando seguro verificados e integrados. Gemini no entregó operaciones porque su control de permisos rechazó listar archivos; Codex implementó en copia aislada. Se añadieron `StoreLoginTest.php` y `CreateAdministratorTest.php`: 12 pruebas/97 aserciones. Regresión completa: 40 pruebas/240 aserciones. Pint y build aislado/fuente aprobados. Detalle de ejecución, huellas y límites en la guía supervisada. Sin migrar BD de aplicación, crear usuarios reales ni commit. T2 aceptada; siguiente punto T3.1.

- [x] Casos de acceso invitado/instalador, regeneración e invalidación de sesión, suspensión y privilegios de request; se verificó fallo antes de implementar y PASS después. Tests reales de login/comando separados del test de middleware.
- [x] Login con herramientas del framework, throttling y CSRF; políticas de panel comprobadas en servidor. Configuración de sesiones database preservada para ejecución comercial; driver array exclusivamente en pruebas.
- [x] PASS focalizada T2.1 13/42 y nuevas T2.2 12/97. Aceptación: 403 para instalador en panel, redirección segura para invitado, sin `/register`, sin privilegios recibidos del cliente. Commit previsto (no realizado): `feat: secure administrator access`.

### T3 — Catálogo y fotografías administrables

**Archivos:** crear modelos `Category.php`, `Product.php`, `ProductPhoto.php` y factories; migraciones `create_categories_table`, `create_products_table`, `create_product_photos_table`; `app/Policies/ProductPolicy.php`, `app/Http/Controllers/Admin/ProductController.php`, `app/Http/Controllers/Admin/CategoryController.php`, `app/Http/Controllers/Admin/ProductPhotoController.php`, `app/Http/Requests/StoreProductRequest.php`, `UpdateProductRequest.php`, `StoreCategoryRequest.php`, `UpdateCategoryRequest.php`, `StoreProductPhotoRequest.php`, `UpdateProductPhotoRequest.php`; vistas `admin/products/index.blade.php`, `admin/products/form.blade.php`, `admin/products/photos.blade.php`, `admin/categories/index.blade.php`; `database/seeders/StoreDemoSeeder.php`, `tests/Feature/AdminCatalogTest.php`, `tests/Feature/ProductPhotosTest.php`, `tests/Feature/StoreDemoSeederTest.php`; modificar `routes/web.php` y `resources/views/admin/dashboard.blade.php`.

**Interfaces:** Product categoría, nombre, slug único, descripción escapada, características, contenido del equipo, costo base, publicación/activación. Fotos orden/ruta/alt; precio positivo antes de publicar tras T4. Desactivar conserva referencias. CRUD con rutas `admin.products.*`, `admin.categories.*`, `admin.product-photos.*`.

**Avance T3.1 01-10-2026 — aceptado:** Category con name/slug; Product con category_id, name/slug, description, specifications, equipment_contents, base_cost_cents (BIGINT entero privado), is_published e is_active. Formularios usan base_cost decimal de 0.01 a 9999999999.99 unidades monetarias, convertido sin floats; no confiar en centavos del request. Slugs únicos incluso desactivados, FK restringida y sin rutas DELETE. Middleware/policy/requests protegen el catálogo, vistas private/no-store y texto escapado. Gemini entregó siete archivos del núcleo tras reducir una respuesta que excedió su límite de salida; Codex implementó adaptadores/vistas/tests y verificó todo antes de integrar diecinueve archivos. Migraciones reales: 2026_10_01_202535_create_categories_table.php y 2026_10_01_202536_create_products_table.php. Focalizada AdminCatalogTest: 24 pruebas/188 aserciones; suite completa 64/428; Pint y build aprobados, revisión independiente sin bloqueantes. Sin migrar BD de aplicación ni commit. Evidencia completa en la guía. T3 sigue abierta hasta fotografías/seed T3.2; el bloqueo por precio efectivo inválido se integra en T4.

**Cierre T3.2 01-10-2026 — aceptado:** Gemini entregó seis archivos del núcleo y Codex supervisó/adaptó e implementó galería, relación, rutas, seed y pruebas. Fotografías con nombres UUID, MIME/extensión/contenido validados, límites inclusivos de 5 MiB y 6000×6000, alt y orden editables, sin borrado/reemplazo ni rutas del cliente. Limpieza del archivo nuevo ante fallos de escritura, BD o eventos que cancelan guardado; si el disco impide limpiar se reporta el fallo. Seeder explícito solo local/testing, idempotente y sin cuentas; no modifica DatabaseSeeder. Migración real 2026_10_01_210655_create_product_photos_table.php. Focalizada 22 pruebas/121 aserciones, suite completa 86/549; Pint y build aprobados. Revisión independiente: defecto de guardado cancelado reproducido y corregido. Catorce archivos integrados con hashes idénticos a copia probada. Enlace public/storage creado, sin migrar ni sembrar la BD de aplicación. T3 cerrada funcionalmente; siguiente T4.1. Inspección visual de navegador pendiente; render/alt/orden verificados por pruebas. Detalle en la guía supervisada.

- [x] Casos de crear/editar/publicar/desactivar, costo inválido, acceso no admin y upload falso/ejecutable/excesivo verificados primero en fallo y después en éxito. JPEG/PNG/WebP hasta 5 MiB y 6000×6000 con nombres generados; SVG/HTML rechazados. Tests AdminCatalogTest, ProductPhotosTest y StoreDemoSeederTest.
- [x] Esquema, requests, policies, formularios y almacenamiento público seguro implementados; sin stock. Limpieza ante fallo BD y conservación de fotos existentes verificadas.
- [x] PASS y verificación automatizada de render de imagen/alt/orden. Aceptación funcional: administrador gestiona catálogo y fotos, costo privado; filtro público publicado+activo se consume en T5. Commit previsto, no realizado: `feat: add managed product catalog`.
- [ ] Inspección visual manual de imagen/alt/orden en navegador; no realizada en esta entrega.

### T4 — Motor de precios y excepciones

**Archivos:** crear `app/Models/PriceOverride.php`, factory, migración `create_price_overrides_table`, `app/Data/CartLineData.php`, `QuoteLineData.php`, `QuoteData.php`, `app/Services/PricingService.php`, `app/Http/Controllers/Admin/PricingController.php`, `app/Http/Requests/UpdatePricingRequest.php`, `resources/views/admin/pricing/edit.blade.php`, `tests/Unit/PricingServiceTest.php`, `tests/Feature/PricingAdministrationTest.php`; modificar publicación de T3 y rutas.

**Interfaces:** contrato `PricingService::quote` arriba; excepción única producto/tarifa, ausencia automático. `PricingController::update(UpdatePricingRequest $request): RedirectResponse`. Fingerprint incluye cantidades, IDs, tarifa autorizada, configuración/valores efectivos; no expone costo.

**Avance T4.1 01-10-2026 — aceptado:** CartLineData/QuoteLineData/QuoteData y PricingService integrados; motor interno de dinero entero con half-up unitario e IVA por línea, descuento cero, serialización explícita sin costo ni segunda tarifa. Agrupa duplicados antes del IVA y ordena IDs; fingerprint incluye cotización efectiva y versión. Aritmética descompuesta acepta resultados representables aun con multiplicaciones intermedias grandes; overflow real se rechaza. Gemini entregó cuatro archivos; Codex añadió pruebas y corrigió moneda/límite de factor. Focalizada 39 pruebas/111 aserciones, suite completa 125/660; Pint y revisión independiente aprobados. Cinco archivos con hashes idénticos a copia probada. Sin nueva pantalla, rutas, dependencias ni BD de aplicación modificada. Callers futuros autorizan tarifa/disponibilidad; el motor permite borradores para vista previa. Excepciones, panel/auditoría y bloqueo de publicación siguen en T4.2; casillas compartidas de T4 aún pendientes de esos casos. Evidencia completa en la guía supervisada.

- [x] Pruebas: costo 100000, bases público 130000 e instalador 110000, finales con IVA 16% 150800/127600; cantidad 3 pública subtotal 390000, IVA 62400, total 452400. Mitad de centavo redondea arriba; caso IVA de línea distinto a sumar IVA unitario. Cambiar costo/porcentaje conserva override de una tarifa; quitarlo vuelve a automático; rechazar precio efectivo no positivo. Fallo: `php artisan test --compact --filter=Pricing`.
- [x] Implementar aritmética entera con overflow controlado, tasas de precisión fija y panel de vista previa sin/con IVA de ambas tarifas; registrar cambios auditados y versión.
- [x] PASS. Aceptación: fórmula de aumento, no margen; descuentos aún cero; representaciones públicas explícitas sin costo ni segunda tarifa. Commit previsto, no realizado: `feat: calculate tier prices and manual overrides`.

**Cierre T4.2 01-10-2026 — aceptado:** excepciones únicas producto/tarifa, panel administrativo con vista previa sin/con IVA, auditoría y versión transaccionales integrados. Quitar una excepción restaura el cálculo automático sin modificar la otra tarifa; cambiar costo o porcentajes conserva precios manuales. Publicación y cambios globales rechazan cotizaciones inválidas; eventos que cancelan guardado/eliminación/auditoría revierten la transacción. Productos publicados revalidados por lotes con overrides precargados. Revalidación en fuente: focalizada 42 pruebas/206 aserciones, suite 167/866, Pint y build aprobados; 14 archivos coinciden por SHA-256 con la copia aislada. Migración nueva 2026_10_01_215310_create_price_overrides_table.php pendiente de aplicar en BD de desarrollo. Sin commit ni despliegue. T4 cerrada; T5.1 no iniciada.

### T5 — Tienda, información y carrito sin cupones

**Archivos:** crear `app/Http/Controllers/CatalogController.php`, `ProductController.php`, `CartController.php`, `InformationController.php`, `app/Http/Requests/UpdateCartRequest.php`, `app/Services/CartService.php`, `resources/views/store/catalog.blade.php`, `product.blade.php`, `cart.blade.php`, `information.blade.php`, `tests/Feature/StorefrontTest.php`, `CartTest.php`; modificar `routes/web.php`, `resources/css/app.css`, `resources/js/app.js`, layout T2.

**Interfaces:** `CartService::lines(): array` con PHPDoc `list<CartLineData>`; `put(int $productId, int $quantity): void`; `remove(int $productId): void`. Sesión guarda IDs/cantidades, nunca precio confiable. Rutas `store.catalog`, `store.product`, `cart.show`, `cart.update`, `store.information`.

- [x] Casos: solo publicados, búsqueda/categoría/paginación; invitado precio público y sin filtración en HTML/JSON/metadata; activo precio instalador con `Cache-Control: private, no-store`; cantidad entera positiva, rechazar 0/negativo/fracción/overflow, sin límite de existencias; logout recotiza. Fallo: `php artisan test --compact --filter='StorefrontTest|CartTest'`.
- [x] Implementar Blade adaptable, etiquetas/errores/foco/teclado, catálogo muestra precio unitario con IVA y carrito total por línea. Describir recogida exclusiva y esperar segundo correo; sin promesa inmediata. Información: local/contacto/condiciones/privacidad/devoluciones configurables con contenido ficticio señalado.
- [x] PASS; `npm run build` termina sin errores. Revisión manual a 360px y escritorio/teclado. Aceptación: puede llenarse carrito sin cuenta, sin campo cupón provisional ni envío. Commit: `feat: add storefront and server priced cart`.

**Avance T5.1 02-10-2026 — aceptado:** catálogo público y ficha integrados por Codex desde copia aislada; Antigravity no entregó archivos. Filtros, paginación, tarifa vigente, proyección sin costos/segunda tarifa y private/no-store verificados: 31 pruebas/241 aserciones; regresión aislada 198/1107, Pint/build y revisión independiente aprobados. Nueve archivos integrados por hash. Inspección visual de navegador pendiente. T5.2 y cierre de T5 permanecen pendientes; evidencia en la guía supervisada.

**Cierre T5.2 y T5 02-10-2026 — aceptado:** CartService y carrito invitado, IDs/cantidades en sesión, cantidades enteras sin stock, cotización antes de mutar y conservación saneada al logout con tarifa pública. Información de local/contacto y políticas configurables marcadas demo, espera del segundo correo. Implementación de Codex tras fallo de entrega Gemini. Revisión independiente: atomicidad de rechazo y no-store en errores reproducidos/corregidos. Focalizada 58 pruebas/451 aserciones; suite fuente 225/1317; Pint/build aprobados. Chromium a 360/1280 px y teclado aprobados, capturas inspeccionadas; se resuelve inspección pendiente de T5.1. Trece archivos integrados por hash. Sin BD de aplicación modificada ni commit. Evidencia en guía supervisada. Siguiente punto T6.1.

### T6 — Contacto, revisión y snapshot de pedido

**Archivos:** crear `app/Models/Order.php`, `OrderLine.php`, `PaymentAttempt.php` y factories; migraciones `create_orders_table`, `create_order_lines_table`, `create_payment_attempts_table`; `app/Enums/OrderStatus.php`, `PaymentAttemptStatus.php`, `app/Data/ContactData.php`, `app/Services/PhoneNormalizer.php`, `CheckoutService.php`, `OrderAccessService.php`, `app/Policies/OrderPolicy.php`, `app/Http/Requests/StartCheckoutRequest.php`, `app/Http/Controllers/CheckoutController.php`, `OrderController.php`, `resources/views/store/checkout-review.blade.php`, `order-result.blade.php`, `tests/Feature/OrderCheckoutTest.php`, `OrderAccessTest.php`, `tests/Unit/PhoneNormalizerTest.php`; modificar rutas.

**Interfaces:** contratos compartidos arriba. Persistir token hash y caducidad configurable inicial 7 días para enlace, referencia UUID no secuencial; acceso sesión o usuario propietario o token, nunca ID solo. Presentar contacto original, guardar también clave canónica. Snapshot no copia costo de fábrica.

- [x] Casos: nombre/email/teléfono obligatorios sin domicilio/cuenta; +52 614 123 4567 y +526141234567 canónicos iguales, inválido rechazado; teléfono no revela ni autentica pedidos; precio/rol del request ignorado; fingerprint viejo devuelve revisión de nuevo total sin pedido/intento; snapshot inmutable tras editar productos; ID ajeno/token expirado denegado. Fallo: `php artisan test --compact --filter='OrderCheckoutTest|OrderAccessTest|PhoneNormalizerTest'`.
- [x] Implementar normalizador internacional: exigir selección de país y número nacional válido o E.164; mapear formato/localidad de acuerdo con país, no quitar dígitos ambiguos ni aceptar cualquier cadena de 7–15 dígitos. Cubrir reglas de los países comerciales confirmados antes de lanzamiento. Si se necesita biblioteca de metadatos para ampliar países, solicitar aprobación de dependencia antes de añadirla; no reducir silenciosamente esta regla.
- [x] Implementar transacción de snapshot+intento con parámetros/clave idempotente preparados; `submissionKey` único impide pedidos nuevos por doble clic. Límite técnico de cantidades/importes basado en overflow y capacidad Stripe, no inventario. Total inferior al mínimo configurable de cuenta: error antes de persistir pedido. Fixtures MXN mínimo 1000 centavos, pendiente confirmación real.
- [x] Conservar una cotización por pedido, incluyendo contacto; no añadir versiones ni registros provisionales separados. Reenvío de la misma confirmación recupera su pedido original. La resolución remota del intento antes de aceptar una cotización distinta se implementa y verifica en T7; no se acredita en este cierre local.
- [x] PASS. Aceptación: revisión y consentimiento se ligan a cotización/contacto actuales; retorno protegido muestra pendiente, no pago por URL. Commit: `feat: freeze checkout orders and protect guest access`.

**Avance T6.1 02-10-2026 — aceptado:** ContactData readonly con firma compartida, PhoneNormalizer::normalize y validación de contacto en StartCheckoutRequest integrados por Codex. MX explícito; teléfono original preservado, canónico +52 con diez dígitos calculado en servidor; rechazo de extensiones, prefijos antiguos, tipos/UTF-8 inválidos y países no soportados. Teléfono valida formato y no concede identidad. Gemini aportó segunda opinión de solo lectura, sin autoría de código. Revisión independiente detectó aceptación de UTF-8 inválido; fallo 500 y corrupción de old_input JSON reproducidos y corregidos. Focalizada 91 pruebas/227 aserciones; suite fuente 316/1544, Pint aprobados. Seis archivos integrados por hash; sin ruta/controlador checkout ni cambios de frontend/BD de aplicación. Registro completo en guía supervisada. T6 continúa abierta, siguiente T6.2.

**Avance T6.2 02-10-2026 — aceptado:** Order/OrderLine/PaymentAttempt, factories, enums, tres migraciones incrementales y CheckoutService::start integrados. Snapshot/contacto/dinero en transacción, tarifa de actor fresco, rechazo de fingerprint obsoleto sin persistencia, UUID submission único y replay original tras cambios comerciales. Cotización desde configuración/productos/overrides bloqueados con cálculo compartido quoteLine; guards Eloquent, datos privados ocultos y rollback ante insert cancelado/fallido. Mínimo fixture 1000 y máximo técnico 99999999; sin Stripe/SMTP. Codex implementó tras fallar y luego agotar tiempo la delegación; la segunda entrega Gemini llegó después y no se integró por exigir autenticación invitada y llamar quote estáticamente. Revisión independiente: riesgo de lecturas consistentes antiguas corregido con regresión inyectada, sin acreditar concurrencia MySQL real. Focalizada 43 pruebas/131 aserciones; suite completa aislada/fuente 359/1675 y Pint aprobados. Catorce archivos integrados por SHA-256 con base intacta. Sin migrar BD de aplicación, frontend, commit ni despliegue. Registro completo en guía supervisada. T6 sigue abierta, siguiente T6.3.


**Cierre T6.3 y T6 02-10-2026 — aceptado en alcance local:** revisión invitada y consentimiento ligados a contacto/fingerprint; cotización y tarifa en servidor. OrderAccessService/policy con propietario activo, sesión por pedido y token aleatorio hash de siete días configurable. UUID no basta; token válido redirige a URL limpia; errores no-store y no-referrer. Resultado desde BD, nunca pago por parámetros URL. Revisión independiente corrigió lecturas de replay MySQL antiguas y serialización de recuperación; concurrencia MySQL real pendiente. Regresión aislada/fuente 392 pruebas/1872 aserciones, Pint/build y Chromium móvil/escritorio/teclado aprobados. Catorce archivos integrados por hash. Codex implementó tras fallar la entrega Antigravity; registro completo en guía. Sin BD de aplicación modificada, dependencias nuevas, commit o despliegue. Siguiente T7.1; resolución de intentos remotos sigue pendiente en T7.

### T7 — Stripe Checkout recuperable

**Archivos:** crear `app/Services/StripeGateway.php`, `app/Data/CheckoutSessionData.php`, `app/Exceptions/UnknownStripeOutcome.php`, `DefinitiveStripeFailure.php`, `tests/Feature/StripeCheckoutTest.php`; modificar `CheckoutService.php`, `PaymentAttempt.php`, `config/services.php`, `.env.example`, rutas/resultados T6.

**Interfaces:** Gateway según contratos; `Stripe-Version` fijada explícitamente a versión estable verificada en sandbox antes de escribir fixtures, misma para webhook. Configurar account ID esperado, modo test/live, minimum charge, timeouts y ventana segura. Clave única por intento y parámetros exactos persistidos; sesión Stripe única; datos personales mínimos en metadata, solo referencias propias.

- [ ] Con `Http::fake()` verificar total agregado, MXN, `mode=payment`, `payment_method_types[]=card`, referencias pedido/intento, impuestos/promos Stripe desactivados, success/cancel a rutas nombradas, expiración 30 minutos calculada al primer envío y guardada sin cambios al repetir. Doble envío crea una sesión lógica; rechazo HTTP definitivo y timeout se distinguen; no red con locks abiertos. Fallo: `php artisan test --compact tests/Feature/StripeCheckoutTest.php`.
- [ ] Implementar POST form encoded a API, autenticación/HTTPS/timeouts, métodos retrieve/expire/list para recuperación. Capturar `expires_at` remoto. Si timeout, conservar intento Unknown; reintentar mismos parámetros/clave solo dentro de 23 horas desde primer envío como margen conservador, no nueva clave al día siguiente. Las claves pueden eliminarse en Stripe después de al menos 24 horas: ver referencia R1.
- [ ] Tratar cambio carrito/contacto/cupón: reanudar autorizado sin cambios conserva pedido e intento; una compra independiente requiere otro pedido con el nuevo resumen aceptado y no exige resolver pagos anteriores (acuerdo 06-10-2026). Nunca mutar líneas, contacto o referencias del anterior ni expirar su Checkout al iniciar otra compra. Un nuevo intento del mismo pedido solo reutiliza cotización sin cambios y revalida condiciones vigentes; si el resumen cambia, crear otro pedido con nueva aceptación. Carrito vacío después de guardar el pedido, sin volver a vaciarlo en reintentos o pagos tardíos; serializar solicitudes de la misma sesión. Límite inicial 10 solicitudes/min sesión e IP y 5 nuevos intentos/hora sesión e IP; reanudación no consume otro intento. Abuso no persiste pedidos nuevos. No limitar webhook con estos contadores.
- [ ] Añadir en `StripeCheckoutTest` casos: reanudación conserva IDs; cambio con sesión aún cobrable no crea otro pedido; cambio tras expiración confirmada crea nuevo pedido con cotización aceptada y anterior ClosedUnpaid inmutable; evento tardío se vincula al intento anterior. Ejecutar de nuevo el comando focalizado de T7, PASS.
- [ ] PASS. Aceptación: URL cancel no cierra ni libera; intento desconocido visible/reconciliable; sin segundo Checkout automático; accesos Stripe solo en sandbox en futura ejecución. Commit: `feat: create recoverable Stripe Checkout sessions`.

### T8 — Webhook durable y aplicación de pago

**Archivos:** crear modelos/factories `Payment.php`, `Refund.php`, `StripeEvent.php`, `FinancialIncident.php`, `OrderNotification.php`; migraciones `create_payments_table`, `create_refunds_table`, `create_stripe_events_table`, `create_financial_incidents_table`, `create_order_notifications_table`; `app/Enums/NotificationStatus.php`, `app/Data/VerifiedPaymentResult.php`, `app/Services/StripeWebhookVerifier.php`, `PaymentResultResolver.php`, `PaymentResultApplier.php`, `app/Http/Controllers/StripeWebhookController.php`, `tests/Feature/StripeWebhookTest.php`, `PaymentTransitionsTest.php`; modificar bootstrap/rutas/Gateway.

**Interfaces:** `StripeWebhookVerifier::verify(string $body, string $signatureHeader): array` (PHPDoc shape evento validado); contratos resolver/applier compartidos. Índices únicos evento ID, checkout ID, PaymentIntent/cobro ID; Payment admite cobros adicionales del mismo pedido, no unique por pedido. Refund único remote refund ID, resultado independiente. Recibir `checkout.session.completed`, `checkout.session.expired`, eventos de cobro/PaymentIntent necesarios para resolver, actualizaciones de reembolso y disputas.

- [ ] Casos firma inválida/cuerpo alterado/timestamp fuera de tolerancia 300s/múltiples v1; URL éxito falsa; evento repetido/desordenado; completed aún unpaid no paga; identidad/account/modo/importe/moneda incompatibles abren incidencia y no cumplen compra. Evento antes de guardar sesión: resolver metadata y consultar Stripe, sin rebajar Paid. Fallo: `php artisan test --compact --filter='StripeWebhookTest|PaymentTransitionsTest'`.
- [ ] Persistir evento firmado antes de responder 2xx; fallo BD devuelve no-2xx. Procesar resultado validado mediante transacción común con locks/unique; marcar evento procesado junto a efectos. Evento no soportado se registra como ignorado sin convertirlo en pago. Eximir CSRF exclusivamente `stripe/webhook`.
- [ ] Aplicar pago: intento Paid, Payment registrado, pedido Paid y notificación durable de compra una vez. Crear outbox completo en esta tarea: clave única, destino, tipo, estado Pending, fechas, claim/lease y error; T9 añade historial de intentos y envío. Nunca modificar líneas originales; registrar segundo cobro/incidencia sin repetir efectos. Reembolso fallido no reduce neto; disputa independiente.
- [ ] PASS. Aceptación: eventos pendientes recuperables y dinero visible ante anomalía; no reconocimiento positivo si falta persistencia. Commit: `feat: persist and apply verified Stripe events`.

### T9 — Outbox, correo inmediato y aviso de recogida

**Archivos:** crear modelo/factory `NotificationAttempt.php`, migración `create_notification_attempts_table`, `app/Services/NotificationDispatcher.php`, `app/Mail/PurchaseConfirmed.php`, `ReadyForPickup.php`, `resources/views/mail/purchase-confirmed.blade.php`, `ready-for-pickup.blade.php`, `app/Http/Controllers/Admin/OrderNotificationController.php`, `tests/Feature/OrderNotificationsTest.php`; modificar `OrderNotification.php`, applier/rutas/config mail. Reutilizar tabla y enum de T8, sin segunda migración de creación.

**Interfaces:** dispatcher arriba; `OrderNotificationController::store(Order $order): RedirectResponse`; notificación lógica única pedido/tipo; reenvíos auditados separados por intención explícita, no anulan historial. Columnas solicitud/aceptación/error/reclamación/lease/next_attempt_at; claim atómico y vencimiento recuperable. Correo de acceso reutiliza infraestructura de intentos con usuario nullable/order nullable en T15.

Para recogida, añadir motivo/fecha de cancelación y motivo de bloqueo a la notificación. Cancelled es terminal para el aviso pendiente por reembolso total registrado; una incidencia no borra intentos ni aceptación previa. El dispatcher consulta elegibilidad local antes de cada reclamación de envío y también en reenvíos manuales; no modifica la notificación de compra ni mensajes ya aceptados por el proveedor.

- [ ] Casos: paid crea un aviso; `Mail::fake` recibe envío inmediato después de commit; duplicate webhook no envía otra vez; SMTP falla conserva pago y pendiente; timeout aceptado-antes-error queda Uncertain; doble clic recogida crea un aviso inicial; totalmente reembolsado/incidencia bloquean botón; reenvío requiere confirmación y auditoría. Fallo: `php artisan test --compact tests/Feature/OrderNotificationsTest.php`.
- [ ] Implementar claim con lease, envío fuera de BD y tiempo de red acotado inicial 5s, dejando margen al presupuesto webhook. Aceptación significa proveedor aceptó, no lectura/entrega. Intento normal inmediato; si petición se interrumpe, cron recupera. Uncertain no reenvía ciegamente: consulta proveedor con ID/idempotencia si disponible; SMTP puro exige decisión deliberada auditada con advertencia de duplicado posible.
- [ ] Plantillas incluyen referencia, productos, desglose, espera del segundo correo/local/horario; nunca costo. Botón exacto «Notificar listo para recoger», registra comunicación, no estado de entrega.
- [ ] Implementar elegibilidad local común al envío inicial, cron y reenvío manual: pedido pagado, sin reembolso total confirmado ni incidencia pendiente. Reembolso total cancela aviso pendiente con motivo/fecha y sin SMTP; incidencia impide reclamar envío y conserva historial hasta resolverla y revalidar. No hacer consultas Stripe ni prometer impedir cambios ocurridos después de comprobar elegibilidad.
- [ ] Añadir a `OrderNotificationsTest`: aviso Pending o Failed seguido de reembolso total → Cancelled con motivo y ningún envío/reintento; incidencia → ningún envío inicial/reintento/reenvío; resolución de incidencia → envío permitido solo tras revalidar; correo ya Accepted conserva historial. Ejecutar otra vez el comando focalizado de T9, PASS.
- [ ] PASS. Aceptación: dinero y correo desacoplados, reclamación exclusiva frente a cron, reenvíos visibles. Commit: `feat: deliver durable purchase and pickup notifications`.

### T10 — Conciliación y hito A: compra completa SIN cupón

**Archivos:** crear `app/Services/PaymentReconciler.php`, `app/Console/Commands/ReconcilePayments.php`, `RetryOrderNotifications.php`, `tests/Feature/PaymentRecoveryTest.php`, `PurchaseWithoutCouponTest.php`; modificar `routes/console.php`, config/store y Gateway/applier/dispatcher.

**Interfaces:** comandos `store:reconcile-payments --limit=25`, `store:retry-notifications --limit=25`; ambos `handle(): int`. `PaymentReconciler::run` usa resolver/applier de T8. `Schedule` cada 15 minutos con exclusión mutua, DB cache compartida; presupuesto configurable inicial 40s por comando, no promesa sobre hosting. Continuación por cursor/fechas, sin monopolizar lote por primer error.

- [ ] Pruebas de caída antes de contactar Stripe, después de crear sesión antes de guardar ID, después de evento antes de aplicar, después de Paid antes de SMTP; webhook anterior a respuesta y carreras cron/webhook/pago/expiración; ventana idempotencia agotada mantiene Unknown sin nuevo POST; restaurar claim abandonado. Fallo: `php artisan test --compact tests/Feature/PaymentRecoveryTest.php`.
- [ ] Implementar recuperación bounded y alertas iniciales: creación Unknown 30min, reserva (más tarde T13) 30min tras expires_at, cron sin éxito 45min. Registrar inicio/fin/último éxito/error, sin liberar por alerta. No tomar silencio/listado incompleto como prueba de ausencia de Checkout.
- [ ] Limitar conciliación a intentos de pago pendientes y trabajo de eventos recibidos. No recorrer cobros pagados para descubrir reembolsos/disputas ausentes; eventos de ese tipo ya persistidos sí se recuperan mediante resolver/applier. El comando de reintento de avisos reutiliza la elegibilidad de T9 y excluye Cancelled.
- [ ] Escribir `test_guest_completes_purchase_without_coupon`: producto publicado 100000 costo, 30%, IVA16%, total150800; revisión contacto → POST checkout → webhook firmado → Order Paid, un Payment, una notificación aceptada, ningún uso/reserva de cupón. Ejecutar `php artisan test --compact tests/Feature/PurchaseWithoutCouponTest.php` y prueba de recuperación anterior, PASS.
- [ ] Verificación futura sandbox: `php artisan serve` y, si Stripe CLI ya está disponible, `stripe listen --forward-to http://127.0.0.1:8000/stripe/webhook`; comprar con tarjeta de prueba desde Checkout real de ese pedido, sin asumir que `stripe trigger` genérico tiene sus metadata. Verificar referencias/total/correo de prueba y retorno pendiente si se retrasa webhook. No instalar CLI sin aprobación.
- [x] **Hito A:** aceptado el 07-10-2026 con recorrido real sandbox, retorno Pending con webhook demorado, aviso original SMTP aceptado/recibido y pruebas de fallos. Véase «Cierre T10.2 y Hito A» al final. T11 no iniciado; requiere nueva solicitud. No se hizo commit; se conserva el mensaje previsto como referencia: `feat: reconcile payments and verify coupon free purchase`.

### T11 — Configuración administrativa de cupones

**Archivos:** crear modelos/factories `Coupon.php`, migraciones `create_coupons_table`, `create_coupon_product_table`; `app/Http/Controllers/Admin/CouponController.php`, `app/Http/Requests/StoreCouponRequest.php`, `UpdateCouponRequest.php`, `resources/views/admin/coupons/index.blade.php`, `form.blade.php`, `tests/Feature/AdminCouponsTest.php`; modificar rutas/seed demo.

**Interfaces:** code normalizado único, kind percentage/fixed, value entero (porcentaje precisión T1 o dinero), inicio incluido/fin excluido UTC, minimum_base_minor, max_uses, flags installer/manual, scope explícito All/Selected; selected vacío inválido, no equivale All. Desactivar conserva historial. Datos demo PROMO10 10%, público, 50 usos.

- [x] Casos T11: rechazar >90%, vigencia invertida, fijo no positivo/moneda distinta, scope vacío Selected; flags por defecto false; código case insensitive. La restricción de editar max_uses frente a consumos+reservas queda pendiente de T13. Fallo inicial y posterior PASS: `php artisan test --compact tests/Feature/AdminCouponsTest.php`.
- [x] Implementar CRUD autorizado, etiquetas sin IVA y condiciones visibles; almacenar UTC y presentar zona del negocio, auditoría.
- [x] PASS. Aceptación: cupón configurado no activa aún descuento en flujo de pago; sin cupones en Stripe. Commit: `feat: manage local coupon definitions`.

### T12 — Elegibilidad, tope y distribución del descuento

**Archivos:** crear `app/Services/CouponEvaluator.php`, `DiscountAllocator.php`, `tests/Unit/CouponPricingTest.php`; modificar `PricingService.php`, DTOs y resumen carrito/revisión.

**Interfaces:** `CouponEvaluator::eligibleLines(QuoteData $quote, Coupon $coupon): array` índices elegibles; `DiscountAllocator::allocate(array $bases, int $discountMinor): array` listas de enteros mismo orden. Quote acepta cupón opcional, retorna misma estructura con descuento real y snapshot reglas.

- [x] Pruebas: PROMO10 costo100000 público base130000 descuento13000 IVA18720 total135720; mínimo130000 cumple, 130001 falla para esa base; instalador/manual excluidos salvo flag. Fijo aplicado <=floor(base×9000/10000), porcentaje 9001 rechazado; elegibles cero no descuenta. Bases101/202 descuento30 reparten10/20; bases1/1/1 descuento1 asigna1/0/0 con desempate estable product ID/orden de línea. Suma descuento igual aplicado, ninguna base negativa. Fallo: `php artisan test --compact tests/Unit/CouponPricingTest.php`.
- [x] Algoritmo entero de mayor resto: cuota piso `discount*b_i/sumBases`, ordenar residuo descendente y clave estable ascendente, distribuir centavos faltantes; aplicar IVA después al valor descontado de cada línea. Revisar overflow. Mostrar fijo recortado y consentimiento, no modificar total sin informar.
- [x] PASS. Aceptación: cálculo idéntico catálogo/revisión/pedido cuando corresponda, snapshot congela reglas; mínimo Stripe validado tras descuento, aún sin reservas. Commit: `feat: calculate eligible coupon discounts exactly`.

### T13 — Derechos de cupón, reservas y concurrencia real

**Archivos:** crear modelos/factories `CouponUse.php`, `CouponUseHistory.php`, enums `CouponUseStatus.php`, migraciones `create_coupon_uses_table`, `create_coupon_use_histories_table`; `app/Services/CouponReservationService.php`, `tests/Feature/CouponReservationTest.php`, `CouponConcurrencyTest.php`, `phpunit.mysql.xml`; modificar applier y edición T11.

**Interfaces:** `reserve(Coupon $coupon, ContactData $contact, Order $order, PaymentAttempt $attempt): CouponUse`; `consume(CouponUse $use, Payment $payment): void`; `release(CouponUse $use, VerifiedPaymentResult $evidence): void`. Par coupon_id/phone_canonical único permanente; liberar vuelve Free y mantiene historial. Pedido almacena snapshot cupón y enlace. Operaciones requieren transacción/orden locks global, consumo junto a pago, no transacción independiente.

- [x] Casos secuenciales de reserva, consumo único, devolución sin restitución, expiración remota libera una vez; rechazo/abandono/tiempo local no liberan; reserva de otro intento no se apropia con teléfono. Fallo: `php artisan test --compact tests/Feature/CouponReservationTest.php`. Estado local implementado; los criterios físicos de concurrencia permanecen pendientes en el registro final.
- [x] Implementar locking de cupón y par único, conteo `Consumed + Reserved`, historial y coherencia ante deadlock. Nunca borrar el registro único al consumir. Índices/constraints y estados garantizan recuperación de par Free. Estado local implementado; los criterios físicos de concurrencia permanecen pendientes en el registro final.
- [x] Crear config tests MySQL/MariaDB dedicada, secretos por entorno y nombre DB de pruebas; no apuntar producción. `phpunit.mysql.xml` no fuerza SQLite/DB :memory:, MAIL array, sesiones de test; grupos concurrency requieren backend real y fallan claramente si falta. Tests lanzan procesos independientes sincronizados en barrera dentro del test, no meras llamadas secuenciales ni transacción envolvente compartida. Estado local implementado; los criterios físicos de concurrencia permanecen pendientes en el registro final.
- [x] Ejecutar `vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/CouponConcurrencyTest.php`: mismo cupón/teléfono con dos formatos → una reserva; dos teléfonos último uso → uno admitido; consumo vs expiración y deadlock recuperable; `Consumed+Reserved<=max_uses`. Ejecutar también caso secuencial, PASS. Aceptación: evidencia de locks reales, no sustituible por PASS SQLite. Commit: `feat: reserve coupon uses with transactional limits`. Evidencia vigente en «Verificación final local T14 y T15.3»; no implica cierre del Hito B.

### T14 — Checkout con cupón y hito B

**Archivos:** crear `app/Http/Controllers/CouponValidationController.php`, `app/Http/Requests/ValidateCouponRequest.php`, `tests/Feature/PurchaseWithCouponTest.php`, `CouponCheckoutRecoveryTest.php`; modificar CheckoutService, applier, conciliador, cart/review, DTOs/Order/PaymentAttempt, rutas.

**Interfaces:** argumento couponCode opcional al final de `CheckoutService::start`; validación sin reserva, reserva exclusivamente al aceptar el resumen e iniciar Checkout; consumo al confirmar cobro. Transaction crea pedido/líneas/intento/reserva juntos, llama red después. Applier consume/libera con T13 sin duplicar operación. Validación 10/min sesión e IP, separada de webhook.

- [x] Casos código usado ofrece nuevo total sin cupón y requiere confirmar, nombre distinto no evade teléfono; reservado informa espera/reanudar solo propietario; nuevo login/flags/precios invalidan fingerprint; fija/vigencia/capacidad se revalidan al reservar; edits no alteran cotización reservada; demasiadas solicitudes no crean reserva. Fallo: `php artisan test --compact --filter='PurchaseWithCouponTest|CouponCheckoutRecoveryTest'`. Evidencia vigente en «Verificación final local T14 y T15.3»; no implica cierre del Hito B.
- [x] Integrar política completa sobre flujo T10, incluir texto exacto y tope efectivo, mínimo Stripe antes de transacción. Recuperación conserva descuento/clave; no crea otro intento mientras previo cobrable. Pago contradictorio tras liberación registra Payment+incidencia sin reutilizar/consumir derecho ajeno automáticamente. Evidencia vigente en «Verificación final local T14 y T15.3»; no implica cierre del Hito B.
- [ ] Repetir comandos anteriores y `vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/CouponConcurrencyTest.php`, PASS; sandbox PROMO10 total135720 con un uso/correo; devolución parcial/total deja Consumed y límite ocupado.
- [ ] **Hito B:** promociones funcionan sin regresión de Hito A. Commit: `feat: integrate coupons into recoverable checkout`.

### T15 — Invitaciones, recuperación, MFA y suspensión

**Archivos:** crear `app/Models/InstallerInvitation.php`, factory, migración `create_installer_invitations_table`; `app/Http/Controllers/Admin/InstallerController.php`, `InstallerActivationController.php`, `PasswordResetController.php`, `AdminSecondFactorController.php`, `app/Services/InstallerInvitationService.php`, `AdminSecondFactorService.php`, `app/Mail/InstallerInvitationMail.php`, `AdministratorLoginCode.php`, `resources/views/admin/installers/index.blade.php`, `resources/views/auth/activate.blade.php`, `password-reset.blade.php`, `second-factor.blade.php`, plantillas mail homónimas; `tests/Feature/InstallerAccessTest.php`, `AdminSecondFactorTest.php`; modificar auth/middleware/User/dispatcher.

**Interfaces:** `InstallerInvitationService::invite(User $installer, User $administrator): void`, `activate(string $token, string $password): User`, `suspend(User $installer, User $administrator): void`. Token hash de un uso, plazo inicial configurable 48h, resend invalida previo. Password Broker nativo, respuestas sin enumerar email. MFA admin mediante código de un uso a correo tras password: hash, vence5min, máximo5 intentos, resend invalida; sesión no adquiere admin hasta segundo factor. No usar email MFA como prueba de identidad fiscal.

- [x] Casos admin exclusivo, token inválido/vencido/usado, reenvío invalida, no password por correo, dos instaladores misma tarifa, recuperar contraseña sin enumeración, MFA pendiente no abre panel. Suspensión invalida sesiones y remember token, bloquea endpoints privados y busca expirar checkout privado; si pago gana carrera registra dinero e incidencia. Fallo: `php artisan test --compact --filter='InstallerAccessTest|AdminSecondFactorTest'`. Estado local implementado; los criterios físicos de concurrencia permanecen pendientes en el registro final.
- [x] Implementar vistas/correos/acciones auditadas y recuperación password; suspensión marca primero permiso, expira fuera transacción y aplica resultado por lógica común. No ocultar historial financiero al suspender. Estado local implementado; los criterios físicos de concurrencia permanecen pendientes en el registro final.
- [x] PASS y prueba manual invitación/login/compra; ninguna ruta register. Aceptación: tarifa deriva exclusivamente usuario activo, MFA admin habilitado antes lanzamiento y correo de acceso recuperable; no altas públicas. Commit: `feat: activate and revoke installer access securely`. Evidencia vigente en «Verificación final local T14 y T15.3»; no implica cierre del Hito B.

### T16 — Panel operativo, reembolsos y recuperación autorizada

**Archivos:** crear `app/Http/Controllers/Admin/OrderController.php`, `PaymentRecoveryController.php`, `SettingsController.php`, `resources/views/admin/orders/index.blade.php`, `show.blade.php`, `admin/settings/edit.blade.php`, `admin/notifications/index.blade.php`, `tests/Feature/AdminOperationsTest.php`, `RefundAndDisputeTest.php`; modificar dashboard, resolver/applier/Gateway, rutas/config.

**Interfaces:** `PaymentRecoveryController::store(PaymentAttempt $attempt): RedirectResponse` recupera pagos pendientes consultando Stripe con applier común, no libera por tiempo. Resolver/applier de T8 procesan eventos recibidos de refund/dispute, actualizan Refund únicos y resumen neto por resultados confirmados sin modificar originales. No crear RefundReconciler ni un recorrido periódico de cobros pagados. Configuración de local/horario/condiciones/privacidad/retención por admin.

- [x] Casos refunded parcial/total/fallido/pending con evento desordenado, consulta remota resuelve sin degradación; neto refleja solo refunds succeeded. Disputa abre indicador, no estado logístico; segundo cobro visible; admin consulta, instalador 403; acción conciliar no libera Unknown sin evidencia. Fallo: `php artisan test --compact --filter='AdminOperationsTest|RefundAndDisputeTest'`.
- [x] Implementar listado con filtro predeterminado OrderStatus::Paid, incluyendo compras con reembolsos o incidencias. Filtros explícitos Pending, ClosedUnpaid y todos permiten diagnóstico; no borrar ni esconder definitivamente esos registros. Detalle de contacto/líneas financieras originales/tarifa/referencia y pagos adicionales, reservas/eventos pendientes, avisos aceptados/fallidos/inciertos/cancelados y motivos de bloqueo; máscara PII en diagnóstico, no en contacto autorizado. Resumen cron/umbrales y acciones de recuperación de pagos pendientes/reenvío auditadas. Mostrar que el resumen financiero refleja eventos registrados y remitir a Stripe como referencia si falta un evento.
- [x] Añadir a `AdminOperationsTest` casos: listado por defecto incluye Paid con reembolso/incidencia y excluye Pending/ClosedUnpaid; filtros permiten consultarlos sin modificar historial. En `RefundAndDisputeTest`, webhook recibido actualiza resumen idempotentemente; webhook ausente no activa descubrimiento periódico sobre cobros pagados. Repetir comando focalizado de T16, PASS.
- [x] PASS. Aceptación: no botón de ejecutar reembolso, no pedidos enviados/entregados, notificado recogida solo historial; operación reconoce fallos reales y no los oculta. Commit: `feat: expose financial recovery and operational diagnostics`.

### T17 — Seguridad, concurrencia financiera y aceptación integral

**Archivos:** crear `tests/Feature/StoreSecurityTest.php`, `PaymentConcurrencyTest.php`, `StoreAcceptanceTest.php`; modificar solo archivos cuyo fallo concreto requiera corrección, incluyendo configuración cookies/logs y vistas.

**Interfaces:** no ampliar features; auditoría de límites existentes. Privados `no-store`, salida Blade escapada, subida no ejecutable, CSRF en POST local, webhook firma, redacción secretos/PII. Políticas de retención configurables sin borrar evidencias financieras necesarias; valores reales acordados antes ventas.

- [x] Escribir tests para HTML/JSON/cache/errores sin costo ni tarifa privada, IDOR pedido/fotos privadas, mass assignment rol/importe, XSS, CSRF, cookie segura en perfil producción, endpoints suspendidos. Fallo: `php artisan test --compact tests/Feature/StoreSecurityTest.php`.
- [x] Escribir procesos concurrentes MySQL: mismo cobro con distintos eventos → un efecto; webhook+cron, pago+expiración, doble solicitud de recogida, claim cron+petición; dos cobros distintos se guardan ambos con incidencia. `vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/PaymentConcurrencyTest.php` debe PASS, no skip silencioso.
- [x] `php artisan test --compact` (suite completa), `vendor/bin/phpunit -c phpunit.mysql.xml --group concurrency`, `vendor/bin/pint --dirty --format agent`, `npm run build`, `php artisan route:list --except-vendor`. Esperado: cero fallos; concurrencia ejecutada; assets compilados; rutas públicas/admin previstas sin register ni ejecución de comandos.
- [ ] Revisión manual móvil/escritorio/teclado/error forms/precios con IVA; compra invitada y privada sandbox, cupón excluido/habilitado, devoluciones Dashboard sandbox, correo y recuperación. No confundir correo fake con entrega SMTP real.
- [ ] **Hito C:** criterios de secciones 2–14 trazados abajo satisfechos, defectos materiales corregidos. Commit: `test: verify store acceptance and failure boundaries`.

### T18 — Verificación de cuenta HostGator empresarial y lanzamiento FUTURO

**Archivos:** revisar `composer.lock`, `package-lock.json`, `config/database.php`, `config/mail.php`, `config/session.php`, `config/logging.php`, `routes/console.php`, `.env.example`, artefacto `public/build/manifest.json`. Configuración `.env` remota por mecanismo seguro; no añadir secretos a repositorio. Solo corregir compatibilidad/config comprobada, con tests de regresión; ningún script web de Artisan ni nuevo documento obligatorio.

**Dependencias externas:** acceso a cuenta exacta, dominio/DNS, Stripe sandbox y luego live, SMTP elegido, BD staging de la misma familia, autorizaciones futuras de despliegue/prueba real. «Empresarial» no certifica recursos ni equivale automáticamente a un SKU comercial determinado.

- [ ] Antes del despliegue, comprobar PHP web y CLI y extensiones contra `composer.lock` con `composer check-platform-reqs --no-dev` en entorno objetivo/artefacto; inspeccionar versión de BD/InnoDB, transacciones/locks/índices con tests de integración staging. Revisar particularmente `Pdo\Mysql` de configuración inicial contra PHP destino; no asumir que 8.5 local existe en hosting. Si requiere cambio de versión/dependencias, consultar antes de cambiar.
- [ ] Verificar raíz `public/`, secretos fuera de public_html, HTTPS, acceso saliente Stripe, entrada webhook, permisos de storage/bootstrap cache, uploads no ejecutables, espacio/inodos/memoria, SSH o vía segura de migración. Solicitudes a `.env`, logs, respaldos y vendor privados deben 403/404 sin contenido.
- [ ] Verificar cron real y presupuesto; propuesta `*/15 * * * * /ruta/php /ruta/app/artisan schedule:run` con rutas reales comprobadas, tasks everyFifteenMinutes, sin workers permanentes. `php artisan schedule:list` muestra ambos comandos; medir último éxito y retrasos. Reducir lotes según duración real, nunca liberar cupón por atraso.
- [ ] Verificar proveedor SMTP/acceso/timeouts/límites, SPF/DKIM/DMARC y entrega a buzones de prueba; sender y rebotes. Sin contratación automática; si entrega o hosting incumple, comunicar incompatibilidad concreta y opciones al usuario antes de ampliar alcance.
- [ ] Validar país/moneda/IVA/mínimo Stripe/local/horario/textos legales/retención/MFA, Stripe activado, versión API estable matching eventos, comisiones/renovación reales sin reutilizar tabla histórica del diseño como cotización vigente.
- [ ] Construir fuera de producción con runtime compatible: `composer install --no-dev --prefer-dist --optimize-autoloader` y `npm ci`/`npm run build` solo en sesión autorizada y sin alterar lock por sorpresa. Respaldar BD/archivos y conservar APP_KEY; artefacto reproducible e identificador commit servido verificable.
- [ ] Despliegue futuro autorizado: migraciones `php artisan migrate --force --no-interaction`, `php artisan storage:link --no-interaction`, `php artisan optimize --no-interaction`; validar `route:list`, `/up`, producto, pago sandbox en staging, webhook/cron/SMTP. Ninguna de estas acciones se ejecuta al redactar este plan.
- [ ] Ensayar restauración protegida y reversión considerando BD y cobros nuevos: restaurar no elimina hechos Stripe, conciliar antes de reabrir. Verificar served revision y assets, no solo exit code de comando.
- [ ] **Gate lanzamiento:** evidencia de cuenta, restauración, cron, correo y criterios T17; sin secretos expuestos; modos Stripe correctos. Transacción real controlada únicamente con coordinación y autorización posteriores. Si falta condición, bloquear apertura de ventas, no declarar compatibilidad ficticia. Commit de ajustes solo si hubo cambios: `chore: align store configuration with verified hosting`.

## 5. Trazabilidad de aceptación

| Requisito del diseño | Tareas y prueba/evidencia |
|---|---|
| §§2–5 catálogo/pantallas/panel y exclusiones | T2,T3,T5,T16; AdminCatalog/Storefront/StoreAcceptance; sin stock, envío o logística |
| §6 fórmula, manual independiente, privacidad, redondeo | T4,T5,T6,T12; Pricing/CouponPricing/StoreSecurity |
| §7 invitado, instalador, token/invitación, suspensión | T2,T6,T15; OrderAccess/InstallerAccess/AdminSecondFactor |
| §8 tipo/vigencia/mínimo/elegibles/90%/teléfono/límite | T11–T14; AdminCoupons/CouponPricing/CouponReservation/CouponConcurrency |
| §8 abandono, timeout, liberación con evidencia, devolución no restituye | T10,T13,T14,T16; PaymentRecovery/CouponCheckoutRecovery/RefundAndDispute |
| §9 dinero/snapshot/Checkout agregado/webhook/idempotencia | T6–T8; OrderCheckout/StripeCheckout/StripeWebhook/PaymentTransitions |
| Una cotización por pedido; cambiarla crea otro tras resolver el intento anterior | T6,T7; StripeCheckout: IDs de reanudación, nuevo pedido, snapshot anterior y evento tardío |
| Historial predeterminado de compras pagadas y filtros de diagnóstico | T16; AdminOperations: Paid incluye reembolsos/incidencias, Pending/ClosedUnpaid por filtro |
| Sin descubrimiento periódico de reembolsos/disputas; Stripe referencia externa | T8,T10,T16; RefundAndDispute: eventos recibidos y límite explícito ante webhook ausente |
| §9 cuatro interrupciones, eventos tempranos, carreras, dos cobros | T8,T10,T14,T17; PaymentRecovery/PaymentConcurrency, MySQL obligatorio |
| §10 dos correos inmediatos, dedup, incierto y reenvío | T9,T10,T16; OrderNotifications/PaymentRecovery y SMTP real T18 |
| Recogida: elegibilidad antes de envío/reintento/reenvío con datos locales | T9,T10,T16; OrderNotifications: Cancelled por reembolso total, bloqueo por incidencia y revalidación |
| §11 modelo e integridad, §12 seguridad | T1–T17; constraints, autorización, StoreSecurity y tests de concurrencia |
| §13 HostGator cuenta concreta/cron/recuperación | T18; evidencia staging y restauración, no tablas de precios históricos |
| §14 todos los casos financieros y comerciales | Tests focalizados anteriores + StoreAcceptance T17; prueba externa sandbox/SMTP/cuenta T18 |
| §15 compra completa sin promoción antes de cupones | Hito A bloquea T11–T14; PurchaseWithoutCoupon y recorrido sandbox |
| §16 decisiones antiguas descartadas | Global Constraints y comprobación sin register/stock/domicilio/logística |

## 6. Referencias técnicas y comprobaciones futuras

Consultadas para evitar asumir APIs/garantías; los contratos del plan no sustituyen revisar documentación al ejecutar.

- **R1:** [Stripe — Idempotent requests](https://docs.stripe.com/api/idempotent_requests). Persistir claves y parámetros; keys pueden retirarse tras al menos24h; nunca repetir creación Unknown después de ventana segura suponiendo deduplicación eterna.
- **R2:** [Stripe — Webhooks](https://docs.stripe.com/webhooks). Firma sobre cuerpo original, webhook HTTPS y respuesta rápida; deduplicación, persistencia y reintentos independientes del correo.
- **R3:** [Stripe — Create Checkout Session](https://docs.stripe.com/api/checkout/sessions/create). Revisar parámetros, expiración y versión estable contra cuenta de prueba antes de fijar fixtures; deshabilitar recuperación que cree sesiones fuera del flujo.
- **R4:** [Laravel 13 — Deployment](https://laravel.com/docs/13.x/deployment). Comprobar plataforma real, raíz pública, permisos, cache y configuración; no asumir requisitos resueltos porque local arranca.

No se realizó investigación nueva de precios HostGator: el usuario ya eligió empresarial. SMTP, país/moneda/IVA y SKU de cuenta son datos de lanzamiento, no motivo para programar otro producto. Si alguna comprobación obliga a cambiar stack, costo contratado o alcance, consultar al propietario con evidencia antes de hacerlo.

## 7. Revisión del plan y entrega

- Cobertura contrastada con §§1–16 y criterios del diseño; cambios posteriores del usuario preservados. Nombres de contratos y dependencias revisados; no hay tarea que requiera que los cupones estén disponibles antes del Hito A.
- Valores operativos iniciales son configurables y sujetos a medición, nunca equivalen a evidencia remota ni mecanismo de liberación.
- Ajustes posteriores al critic incorporados: pedido inmutable por cotización y filtro Paid por defecto; conciliación de pagos pendientes sin recorrido de reembolsos/disputas de cobros pagados; elegibilidad del aviso de recogida en dispatcher, reintentos y reenvíos. Se conservan las 18 tareas, dependencias e hitos, sin servicios ni dependencias adicionales.
- No hay decisión comercial pendiente que obligue a ampliar alcance para redactar este plan. La aprobación de diseño no autoriza cambiar dependencias ni contratar servicios. Si normalización internacional exige metadatos externos, evaluar necesidad concreta y pedir aprobación de dependencia al ejecutar, manteniendo canonicalización como requisito.
- El segundo factor por correo usa stack existente y cumple el requisito aprobado de segundo factor sin añadir paquete; su activación requiere SMTP real probado antes ventas. Puede endurecerse posteriormente por decisión expresa, sin bloquear el plan con una selección de biblioteca.
- Autoría/decisión final: Codex. Consulta Gemini por Antigravity en modo análisis: `project_root=C:\Users\rubie\Desktop\e-commerce-garba`; `conversation_id=f9570412-5c42-488b-8f16-629b6f9ffe20`; `run_id=no generado` (lista de runs vacía). Primera consulta sin respuesta porque el conector denegó un comando de búsqueda; segunda consulta conceptual sobre resumen suministrado, sin herramientas, terminó por timeout de75s. No se recibió dictamen especializado ni se adoptaron recomendaciones inexistentes. No se amplían permisos para obtenerlo; revisión del plan realizada por Codex.
- En la planificación original este archivo fue el único entregable. El 01-10-2026 se añadió, por solicitud del usuario, la guía de pasos supervisados y se actualizó el protocolo de ejecución. Los registros locales del conector son metadatos de consulta, no código de la tienda. La actualización no implementa tareas, instala dependencias, despliega ni crea commits.

La modalidad ya está elegida: Gemini implementa y Codex supervisa. El próximo inicio es el punto T0.1 de la guía; después se trabaja un punto seleccionado por el usuario cada vez. Este documento no inicia la ejecución automáticamente.

Avance 05-10-2026 T7.1: implementación local comprobada, 81 pruebas/358 aserciones T7/T6. Stripe-Version 2026-08-26.dahlia según changelog oficial; fixtures de contrato sintéticos y validación sandbox pendientes. Sin migrar BD comercial, commits ni dependencias nuevas.

Avance T7.2 05-10-2026: 86 pruebas/380 aserciones T7/T6; recuperación conservadora y cambio tras expiración remota. T7 local implementada; concurrencia MySQL y sandbox pendientes.

Avance T8.1 05-10-2026: 9 pruebas/19 aserciones aprobadas; persistencia antes de 2xx, exención CSRF única.

Avance T8.2 05-10-2026: esquema financiero y aplicación atómica local, 30 pruebas/161 aserciones T7/T8. Outbox durable; SMTP separado. MySQL/sandbox pendientes.

Avance T9.1 05-10-2026: 37 pruebas/182 aserciones T7–T9, despacho tras commit y fallo financiero desacoplado. SMTP real pendiente.

Avance T9.2 05-10-2026: 12 pruebas/45 aserciones; aviso de recogida/reenvío auditado y elegibilidad financiera local. Visual final pendiente.

### Avance vigente 05-10-2026 — T7.1 a T10.1

Implementación local T7/T8/T9 y T10.1 completada, conservando cambios previos sin commit. T10.2 NO iniciado; T10/Hito A permanecen abiertos. Evidencia completa por punto y gates en Pasos-desarrollo-supervisado-tienda-AC.md, sección «Entrega T10.1 y cierre del encargo».

Verificación final: php artisan test --compact → 458 pruebas/2164 aserciones, exit 0; Pint y npm run build aprobados; schedule:list confirma los dos comandos cada 15 minutos con mutex database; diff --check aprobado. Chromium sobre HTML Laravel y fixtures SQLite :memory: a 360/1280 px, seis verificaciones aprobadas, capturas inspeccionadas. No equivale a Stripe sandbox ni SMTP real.

T7: parámetros/clave/primer envío persistidos, API fijada 2026-08-26.dahlia documentada en https://docs.stripe.com/changelog (Endive elimina payment_method_types); fixtures sintéticos de contrato, validación sandbox pendiente. HTTP fuera de transacciones externas, incertidumbre conservada, recuperación 23h, expiración remota para reemplazo, límites sesión/IP y reanudación privada.
T8: firma/cuerpo original/evento durable, resolver remoto fuera de locks, aplicación atómica de dinero/pedido/outbox/evento, anomalías/cobros extra/reembolsos/disputas conservados y Paid monotónico.
T9: envío tras commit y claims recuperables, SMTP 5s, aceptación/fracaso/incertidumbre diferenciados; recogida y reenvío auditado con elegibilidad financiera local e historial preservado.
T10.1: recuperación bounded con cola común por fecha de revisión, deadline/margen SMTP, métricas/alertas 30/45 minutos sin liberar por reloj. Solo pendientes y eventos recibidos; sin descubrir refunds/disputas ausentes en pagos Paid.

Revisión independiente de solo lectura y correcciones RED→GREEN: equidad entre colas, presupuesto SMTP y claim bloqueado por finanzas, más regresiones de concurrencia simulada, cancelación de mail y expiraciones contradictorias. Estas simulaciones no acreditan locks MySQL. No findings materiales diferidos. No cambios de dependencias, BD de aplicación, commits ni despliegues.

Siguiente: T10.2 por nueva solicitud. Hito A requiere ciclo real sandbox y evidencia de fallos; MySQL/MariaDB concurrente, SMTP real y cron/hosting siguen pendientes. Las casillas históricas de los contratos detallados no sustituyen este avance local ni acreditan los gates externos.

### Avance vigente 05-10-2026 — T10.2 local, objetivo ampliado T12.2

PurchaseWithoutCouponTest implementado: normal, timeout Checkout y fallo de consulta webhook, recuperados sin otro pedido/cobro/aviso, total150800, snapshots del pedido y líneas preservados. Prueba focalizada 3/266; PaymentRecoveryTest 10/32; regresión final 461 pruebas/2430 aserciones correctas; Pint correcto. HTTP y correo simulados, SQLite en memoria: no certifican Stripe sandbox, SMTP ni concurrencia MySQL.

T10.2 conserva aceptación pendiente y Hito A abierto. No hay configuración Stripe de prueba (clave, secreto webhook y cuenta ausentes) ni CLI disponible. No se inició T11–T12.2 por el gate explícito de la guía; continuar requiere compra sandbox del mismo pedido y evidencia correspondiente. Encargo vigente: después del gate, avanzar secuencialmente hasta T12.2 y detenerse. Evidencia detallada en la guía, sección «T10.2 local y gate hasta T12.2». Trabajo previo sin commit conservado, sin migrar/sembrar BD de aplicación ni cambiar dependencias.

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
