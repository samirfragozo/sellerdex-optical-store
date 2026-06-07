# Sistema de gestión para óptica — Diseño

- **Fecha:** 2026-06-07
- **Estado:** Aprobado para planificación
- **Stack base:** Laravel 13, Filament v4 (back-office), Inertia + Vue 3 (POS), Fortify (auth), SQLite (dev)

## 1. Objetivo

Digitalizar el proceso completo de una óptica que hoy se lleva en cuaderno y facturas
físicas: desde la captura de datos del cliente y su prescripción, pasando por la venta
(incluido el plan separe con abonos), hasta el cuadre de caja automático y la entrega de
reportes. El acceso a cada acción depende del rol del usuario.

La interfaz se presenta en **español**; el **esquema de base de datos (tablas, columnas,
valores de enum) está en inglés** por convención estándar.

## 2. Arquitectura

Dos superficies sobre un único backend Laravel:

- **Filament `/admin`** — back-office para el Administrador (catálogo, costos/stock,
  gastos, métodos de pago, usuarios, cuadre de caja, reportes) y, hasta que exista el POS,
  también el ciclo de ventas / abonos / prescripciones.
- **POS Vue (Inertia)** — pantalla rápida para el Vendedor: crear cliente, armar venta,
  tomar abono, imprimir. Se construye en la última fase, sobre el backend ya probado, y
  consume los modelos/servicios vía Wayfinder.

Fortify ya provee la autenticación; Filament se monta sobre el mismo modelo `User`.

### Decisión de secuencia
Se elige **Filament primero, POS después**: construir y validar el ciclo completo
(incluida la lógica de dinero/cuadre, lo más delicado) en Filament, y luego añadir el POS
en Vue como una capa de UX sobre un backend probado.

## 3. Roles y control de acceso

Roles mediante enum `role` en `users` (`admin`, `seller`) + Policies de Laravel.
Se descarta `spatie/laravel-permission` por ahora (YAGNI para 2 roles); se podrá migrar a
permisos granulares + Filament Shield sin reescribir la lógica de negocio.

| Capacidad | Administrador | Vendedor |
|---|:--:|:--:|
| Clientes / Prescripciones (crear, editar) | ✅ | ✅ |
| Crear ventas y abonos | ✅ | ✅ |
| Eliminar / anular / editar pago ya registrado | ✅ | ❌ |
| Ver costos, márgenes, utilidad | ✅ | ❌ |
| Catálogo / precios / stock | ✅ | 👁️ solo lectura (para vender) |
| Gastos · Métodos de pago · Cuadre · Reportes de dinero · Usuarios | ✅ | ❌ |

### Reglas anti-fraude (transversales)
- El **Vendedor no puede eliminar** ningún registro. El borrado es exclusivo del Admin.
- **Anular o editar un pago/venta ya registrado** es exclusivo del Admin. El vendedor solo
  crea y corrige antes de confirmar.
- **Soft deletes** en customers, prescriptions, sales, payments, expenses.
- **Bitácora de auditoría** con `spatie/laravel-activitylog` sobre sales, payments y
  expenses (incluye creación, edición, anulación y borrado).

## 4. Modelo de datos

Convenciones: montos en **pesos enteros** (COP, sin decimales); nomenclatura óptica
estándar **OD** = ojo derecho, **OS** = ojo izquierdo; 🗑️ = soft deletes; todas con
`timestamps`. Las etiquetas de enum se traducen a español en la UI; las semillas visibles
(métodos de pago, categorías de gasto) se almacenan con su etiqueta en español en `name`.

### users *(ya existe)*
`+ role` enum(`admin`,`seller`), `+ is_active` (bool).

### customers 🗑️
`name`, `id_number` (única, nullable), `phone`, `address`, `city`, `age`, `email`
(nullable), `notes`.

### prescriptions 🗑️
`customer_id`, `sale_id` (nullable), `created_by`, `exam_date`,
OD: `od_sphere`, `od_cylinder`, `od_axis`, `od_add`, `od_va`, `od_pd`,
OS: `os_sphere`, `os_cylinder`, `os_axis`, `os_add`, `os_va`, `os_pd`,
`lens_type` (monofocal, bifocal, progresivo, rango extendido…), `filters` (json:
fotocromático, antirreflejo blue…), `usage`, `control_period`, `diagnosis` (el "R:"),
`drops`, `lensometry`.

### products 🗑️
`name`, `sku` (nullable), `category` enum(`lens`,`frame`,`filter`,`accessory`,`promo`,
`service`), `brand` (nullable), `price`, `cost`, `is_stockable` (bool), `stock` (nullable
cuando no aplica, p. ej. lentes a tallar bajo pedido), `is_active`, `attributes` (json:
material/color).

### payment_methods
`name`, `is_active`, `is_default` (bool — Efectivo sembrado y protegido: no se puede
desactivar ni eliminar), `sort_order`.

### sales 🗑️
`number` (consecutivo), `customer_id`, `seller_id`, `prescription_id` (nullable),
`document_type` enum(`quote`,`order`,`layaway`,`remission`,`billing`),
`status` enum(`draft`,`partial`,`paid`,`delivered`,`voided`),
`subtotal`, `discount`, `total`, `is_delivered`, `delivered_at`, `sold_at`, `notes`,
`created_by`. Atributo calculado `saldo = total − Σ payments.amount`.

### sale_items
`sale_id`, `product_id` (nullable para texto libre), `description` (snapshot/texto libre),
`quantity`, `unit_price`, `unit_cost` (snapshot para margen), `line_total`.

### payments *(abonos)* 🗑️
`sale_id`, `payment_method_id`, `amount`, `paid_at`, `received_by`, `reference`
(p. ej. "Nau Lina"/cuenta Nequi), `notes`.

### expense_categories
`name`, `is_active`. Semillas: Arriendo, Salario, Lentes terminados, Exámenes, Digitales,
Otros.

### expenses 🗑️
`expense_category_id`, `description`, `amount`, `payment_method_id` (nullable), `spent_at`,
`created_by`, `notes`.

### cash_closes *(cuadre)*
`type` enum(`daily`,`monthly`), `period_start`, `period_end`, `opening_cash` (caja
inicial; por defecto el `counted_cash` del cierre diario anterior, o 0), `total_sales`,
`total_collected`, `collected_by_method` (json), `total_expenses`, `total_receivable`,
`expected_cash`, `counted_cash`, `difference`, `status` enum(`open`,`closed`,`approved`),
`closed_by`, `closed_at`, `notes`.

### business_settings *(singleton)*
`name`, `tax_id`, `address`, `phones`, `logo` — encabezado de facturas y fórmulas.

### activity_log
Provisto por `spatie/laravel-activitylog`.

### Relaciones
- `customer` 1—N `sales`, `prescriptions`
- `sale` 1—N `sale_items`, `payments`; N—1 `customer`, `seller (user)`, `prescription`
- `payment` N—1 `payment_method`, `sale`
- `expense` N—1 `expense_category`, `payment_method`

## 5. Flujos

### Venta
1. Vendedor crea/busca cliente → opcional registra prescripción.
2. Arma la venta: ítems del catálogo (descuenta stock si `is_stockable`) o texto libre;
   elige `document_type`.
3. Registra abono(s) → `payment` con su método. `saldo = total − Σ pagos`. El `status`
   transiciona automáticamente: `draft → partial → paid`, y `delivered` al entregar.
4. Imprime factura/fórmula con encabezado de `business_settings`.

### Cuadre automático

**Diario (fecha D):**
- `total_sales` = Σ `sales.total` con `sold_at = D` (lo facturado).
- `total_collected` = Σ `payments.amount` con `paid_at = D` (recaudo real, incluye abonos
  de ventas previas), desglosado en `collected_by_method`.
- `total_expenses` = Σ `expenses.amount` con `spent_at = D`.
- `expected_cash` = `opening_cash` + pagos en efectivo − gastos pagados en efectivo.
- Admin ingresa `counted_cash`; `difference = counted_cash − expected_cash` (descuadre).
- `total_receivable` = Σ saldos de ventas con saldo > 0 (cuentas por cobrar, informativo).

Reproduce el cuaderno: **Venta** (`total_sales`) vs **Tengo** (`total_collected`/efectivo)
vs **Pte Recaudar** (`total_receivable`), cuadrado automáticamente.

**Mensual:** agrega el mes → `total_sales`, `total_collected`, `total_expenses` (incl.
arriendo), `total_receivable`, **resultado de caja = recaudado − gastos**. Métrica extra:
**utilidad por margen = ventas − costo (COGS) − gastos**, usando `cost`.

## 6. Reportes
(Los de dinero/utilidad solo para el Administrador.)

- **Ventas por período** — día/mes, por vendedor, por método de pago.
- **Cuentas por cobrar** — saldos de plan separe con antigüedad (aging) por cliente.
- **Gastos y utilidad** — por categoría + utilidad mensual.
- **Inventario** — stock, stock bajo, más vendidos, margen por producto.
- **Histórico de cuadres** — diarios y mensuales con descuadres.
- **Desempeño por vendedor** — ventas y recaudo por vendedor (base para comisión).

## 7. Fases de construcción

- **Fase 0 — Setup:** instalar Filament v4 y `spatie/laravel-activitylog`; enum `role` +
  Policies; `business_settings`; seeders base.
- **Fase 1 — Maestros:** customers, prescriptions, products (catálogo + costo + stock),
  payment_methods CRUD (Efectivo protegido), expense_categories + expenses.
- **Fase 2 — Ventas:** sales + sale_items + payments (abonos / plan separe), lógica de
  saldo y estados, impresión de documentos.
- **Fase 3 — Cuadre + Reportes:** cash_closes (diario/mensual) automático y los reportes.
- **Fase 4 — POS Vue:** captura rápida de ventas en Inertia/Vue sobre el backend probado.

Cada fase se prueba con **Pest** (feature tests de Policies, cálculo de saldos y cuadre).

## 8. Decisiones y supuestos
- Un solo negocio/sede por ahora; multi-sede queda como fase futura.
- Los documentos físicos (factura/fórmula) pueden adjuntarse como foto opcional a la venta.
- Las promos se modelan como un `product` de categoría `promo` con precio propio; el
  desglose tipo "bundle" queda como mejora futura.
- Filament v4 y `spatie/laravel-activitylog` son dependencias aprobadas para este trabajo.

## 9. Fuera de alcance (por ahora)
- Multi-sede / multi-empresa.
- Facturación electrónica DIAN.
- Comisiones calculadas/liquidadas automáticamente (solo se reporta el desempeño).
- Integración con pasarelas de pago o bancos.
